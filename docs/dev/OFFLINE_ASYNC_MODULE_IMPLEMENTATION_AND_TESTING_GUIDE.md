# Offline-Asynchronous API Module: Implementation & Testing Guide

**Document Date:** October 10, 2026  
**System:** PALECO Consumer Relations Management & Field Dispatching System (OTRS-CRM)  
**Scope:** Field Personnel Offline Sync Engine (`/start` and `/accomplish`)  
**Status:** Implemented & Verified (Unmigrated / Uncommitted per instruction)

---

## Executive Summary

Field personnel (linemen and emergency technical repair crews) frequently perform line repairs, transformer fuse replacements, and outage restorations in remote barangays and mountainous coverage corridors across Palawan where cellular coverage (3G/4G/5G) is intermittent or non-existent.

To address network drops and low-connectivity environments without risking data corruption, duplicate database transactions, or falsified SLA records, we have implemented an enterprise-grade **Offline-Asynchronous Synchronization Module**.

### Core Guarantees
1. **Scope Restriction:** Applies **exclusively** to the Field Personnel operational lifecycle endpoints:
   - `PATCH /api/tickets/{ticket}/start` (Work Commencement)
   - `POST /api/tickets/{ticket}/accomplish` (Work Accomplishment / Resolution with Photo Evidence)
2. **Dual-Layer Idempotency:** Implements the IETF client-driven idempotency specification via middleware caching and service-level database row protection. Replayed network requests return `200/201` from cache without throwing `422 Unprocessable Content` errors (*"This ticket is already in progress"* or *"This ticket already has a pending accomplishment"*).
3. **True Timestamp & SLA Preservation:** Field workers capture actions offline. The server records the physical work timestamp from `X-Client-Timestamp` (`started_at`, `accomplished_at`), while recording `synced_at = now()` and `is_offline_synced = true` for management auditing.
4. **Clock-Drift Guardrails:** Prevents future-dating ($> 5$ minutes ahead of server clock) and back-dating earlier than ticket creation time.
5. **100% Backward Compatibility:** Normal online requests without idempotency or timestamp headers continue to function synchronously without behavioral changes.

---

## 1. Technical Changes Made

### 1.1 Database Migration
**File:** `database/migrations/2026_10_10_133000_create_offline_sync_and_idempotency_tables.php` *(Created, unmigrated)*

```php
// 1. New Table: idempotency_records
Schema::create('idempotency_records', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
    $table->string('idempotency_key', 64);
    $table->string('endpoint_path', 255);
    $table->string('request_hash', 64);
    $table->string('status', 30)->default('in_progress'); // in_progress, completed, failed
    $table->unsignedInteger('response_code')->nullable();
    $table->json('response_body')->nullable();
    $table->timestamp('expires_at')->index();
    $table->timestamps();

    $table->unique(['user_id', 'idempotency_key'], 'uniq_user_idempotency_key');
});

// 2. Add offline sync columns to tickets table
Schema::table('tickets', function (Blueprint $table) {
    $table->boolean('is_offline_synced')->default(false)->after('status');
    $table->timestamp('synced_at')->nullable()->after('is_offline_synced');
    $table->timestamp('client_started_at')->nullable()->after('started_at');
});

// 3. Add offline sync columns to ticket_accomplishments table
Schema::table('ticket_accomplishments', function (Blueprint $table) {
    $table->boolean('is_offline_synced')->default(false)->after('status');
    $table->timestamp('synced_at')->nullable()->after('is_offline_synced');
    $table->timestamp('client_accomplished_at')->nullable()->after('accomplished_at');
    $table->string('idempotency_key', 64)->nullable()->after('synced_at');

    $table->index('idempotency_key', 'idx_accomplishment_idempotency_key');
});
```

---

### 1.2 Eloquent Models
1. **`app/Models/IdempotencyRecord.php` (New):**
   - Handles persistence and lookup of client UUID keys, request hashes, HTTP status codes, and cached JSON payloads.
   - Automatically casts `response_body` to array and `expires_at` to datetime.
   - Linked to `User` via `belongsTo(User::class, 'user_id')`.
2. **`app/Models/Ticket.php`:**
   - Added `'is_offline_synced'`, `'synced_at'`, and `'client_started_at'` to `$fillable`.
   - Added `'is_offline_synced' => 'boolean'`, `'synced_at' => 'datetime'`, `'client_started_at' => 'datetime'` to `casts()`.
3. **`app/Models/TicketAccomplishment.php`:**
   - Added `'is_offline_synced'`, `'synced_at'`, `'client_accomplished_at'`, and `'idempotency_key'` to `$fillable`.
   - Added `'is_offline_synced' => 'boolean'`, `'synced_at' => 'datetime'`, `'client_accomplished_at' => 'datetime'` to `casts()`.

---

### 1.3 Idempotency Middleware (`app/Http/Middleware/HandleIdempotency.php`)
- **Key Interception:** Checks for `X-Idempotency-Key` or `Idempotency-Key` header.
- **Cache Hit:** If a completed record exists for the authenticated user and key, immediately terminates the lifecycle and returns the cached JSON response with headers:
  - `X-Cache: HIT`
  - `X-Idempotent-Replayed: true`
- **Concurrency Collision Guard:** If an identical key is already `in_progress`, returns `409 Conflict` to prevent parallel race conditions.
- **Failure Recovery:** If the application request throws a validation error (4xx) or server error (5xx), the temporary `in_progress` record is purged so the client can fix deficiencies and re-submit cleanly with the same key.
- **Registration:** Aliased as `'idempotent'` in `bootstrap/app.php` and bound strictly to the Field Personnel route group in `routes/api.php`.

---

### 1.4 Client Timestamp Guardrail Trait (`app/Http/Controllers/Api/Concerns/ResolvesClientTimestamp.php`)
Provides centralized timestamp resolution and validation:
1. Resolves `X-Client-Timestamp` header (with fallback to `client_timestamp` in request body).
2. Validates ISO-8601 format.
3. **Future Drift Guardrail:** Throws `422 Unprocessable Content` if the timestamp exceeds `now()->addMinutes(5)`.
4. **Past Bound Guardrail:** Throws `422 Unprocessable Content` if the timestamp is earlier than the ticket intake/creation timestamp (`reported_at ?? created_at`).

---

### 1.5 Service & Controller Synchronization
1. **`app/Services/Tickets/TicketService.php::startTicket`:**
   - Accepts `?Carbon $clientTimestamp = null`.
   - **Idempotency Row Safety:** If `$lockedTicket->status === TicketStatus::IN_PROGRESS`, returns `$lockedTicket->fresh(['category'])` safely without throwing an exception.
   - Calculates `$isOffline = $clientTimestamp !== null && $clientTimestamp->diffInMinutes(now()) > 5`.
   - Sets `started_at = $actualStartTime`, `client_started_at = $clientTimestamp`, `is_offline_synced = $isOffline`, `synced_at = $isOffline ? now() : null`.
   - Records `TicketStatusLog` with `created_at = $actualStartTime`.
2. **`app/Services/Tickets/TicketAccomplishmentService.php::accomplishTicket`:**
   - Accepts `?Carbon $clientTimestamp = null, ?string $idempotencyKey = null`.
   - **Idempotency Row Safety:** If `$idempotencyKey` matches an existing report, or if the ticket is already `RESOLVED` by the current worker, returns the existing report.
   - Calculates `$isOffline` and persists `client_accomplished_at`, `is_offline_synced`, `synced_at`, and `idempotency_key`.
   - Sets ticket `status = RESOLVED`, `resolved_at = $actualAccomplishedTime`, `is_offline_synced = true`, `synced_at = now()`.
   - Photos and signatures are saved only once.
3. **Controllers (`TicketController.php` & `TicketAccomplishmentController.php`):**
   - Consume `ResolvesClientTimestamp` trait and pass resolved values to services.
4. **Resources (`TicketResource.php` & `TicketAccomplishmentResource.php`):**
   - Expose `'is_offline_synced'` (`bool`) and `'synced_at'` (`string|null`).

---

## 2. Postman Testing Guide (No Mobile App Needed)

You can verify the entire offline-async functionality using **Postman** or **cURL**.

### Setup Prerequisites
1. **Base URL:** `http://127.0.0.1:8000/api`
2. **Obtain Field Personnel Bearer Token:**
   - `POST /api/login`
   - Body (`application/json`):
     ```json
     {
       "username": "ralph",
       "password": "password"
     }
     ```
   - Copy the returned token into your Postman header: `Authorization: Bearer <field_token>`.
3. **Select a Target Ticket:** Pick a ticket assigned to Ralph's team in `assigned` status (e.g. `01m4jb68m7p0yrv867g9t9zswc`).

---

### 2.0 Dynamic Data Injection Criteria (CRITICAL: Read Before Testing!)

Do not blindly copy-paste hardcoded static timestamps. The backend enforces strict relational and chronological guardrails. Use the following criteria to construct valid headers for your specific ticket:

#### Criteria for `X-Client-Timestamp`:
| Criterion | Rule & Constraint | Why This Is Enforced |
| :--- | :--- | :--- |
| **1. Lower Bound (Creation Barrier)** | Must be **greater than or equal to** the target ticket's `reported_at` (or `created_at`). | Work cannot physically start before the consumer even filed the complaint. |
| **2. Upper Bound (Future Guardrail)** | Must be **less than or equal to** `Current Server Time + 5 minutes`. | A mobile client cannot have started or completed field work in the future. (The 5-minute allowance tolerates minor device clock drift). |
| **3. Offline Flag Threshold** | If timestamp is **older than `Current Time - 5 minutes`**, the server marks `is_offline_synced = true` and `synced_at = now()`. | Distinguishes historical offline packet syncs from instant real-time requests. |
| **4. Format Standard** | Must follow ISO-8601 extended format: `YYYY-MM-DDTHH:mm:ss+08:00` or `YYYY-MM-DDTHH:mm:ssZ`. | Required for standard date parsing across systems. |

> [!TIP]
> **How to calculate a valid offline timestamp right now:**
> 1. Check your ticket's `reported_at` / `created_at` (e.g., `Oct 10, 2026 03:24 PM` = `15:24:00`).
> 2. Check the current local time (e.g., `03:35 PM` = `15:35:00`).
> 3. Pick a timestamp between `ticket reported time` and `now - 6 minutes`!  
>    *Example:* `2026-10-10T15:26:00+08:00` (which is after 3:24 PM, but 9 minutes before 3:35 PM).  
>    This cleanly satisfies all validation guardrails and will set `is_offline_synced: true`.

#### Criteria for `X-Idempotency-Key`:
- **Format:** Standard UUID v4 string (between 16 and 64 characters).
- **Postman Shortcut:** Use the built-in dynamic variable `{{$guid}}` in Postman to generate fresh UUIDs on each new action.
- **For Replay Testing:** Once a request succeeds, **DO NOT change the key**. Resend with the **exact same key** to verify that the cache returns `X-Cache: HIT` without re-executing logic.

---

### Scenario 1: Simulating Offline `/start`
*Simulates a lineman starting work in a remote dead zone and syncing when reconnected.*

- **Method:** `PATCH`
- **URL:** `http://127.0.0.1:8000/api/tickets/{{ticket_ulid}}/start`
- **Headers:**
  | Key | Value Criteria / Example | Description |
  | :--- | :--- | :--- |
  | `Authorization` | `Bearer {{field_token}}` | Field personnel token (`ralph`) |
  | `Accept` | `application/json` | JSON response negotiation |
  | `X-Idempotency-Key` | `{{$guid}}` or e.g. `4f3c7a91-2a62-4b78-831d-b3ef9a82c401` | Fresh UUIDv4 |
  | `X-Client-Timestamp` | **Must be $\ge$ `reported_at` and $\le$ `now + 5m`**<br>*e.g. If ticket created at 3:24 PM and now is 3:35 PM: `2026-10-10T15:26:00+08:00`* | Offline start timestamp |

- **Expected Response (`200 OK`):**
  - **Status Code:** `200 OK`
  - **Response Headers:** `X-Cache: MISS`, `X-Idempotent-Key: <UUID>`
  - **Response Body:**
    ```json
    {
      "success": true,
      "message": "Work has started on ticket TKT-261010-001.",
      "data": {
        "id": "01m4jb68m7p0yrv867g9t9zswc",
        "ticket_number": "TKT-261010-001",
        "status": "in_progress",
        "started_at": "Oct 10, 2026 03:26 PM",
        "is_offline_synced": true,
        "synced_at": "Oct 10, 2026 03:35 PM"
      }
    }
    ```

---

### Scenario 2: Network Timeout Replay Test (`/start`)
*Simulates the mobile client dropping connection immediately after the server processed Scenario 1. The phone retries with the identical idempotency key.*

- **Action:** Click **Send** in Postman again with the exact same headers and the **exact same `X-Idempotency-Key`** used in Scenario 1.
- **Expected Response (`200 OK`):**
  - **Status Code:** `200 OK` (Replayed from cache)
  - **Response Headers:**
    - `X-Cache: HIT`
    - `X-Idempotent-Replayed: true`
  - **Database Verification:**
    - Open your database; verify there is **only one** entry in `ticket_status_logs`, not two!
    - The client **does not receive** `"This ticket is already in progress"` (422).

---

### Scenario 3: Simulating Offline `/accomplish` (Multipart Upload)
*Simulates uploading remarks, touch signature, and photos recorded while offline.*

- **Method:** `POST`
- **URL:** `http://127.0.0.1:8000/api/tickets/{{ticket_ulid}}/accomplish`
- **Headers:**
  | Key | Value Criteria / Example | Description |
  | :--- | :--- | :--- |
  | `Authorization` | `Bearer {{field_token}}` | Field personnel token (`ralph`) |
  | `Accept` | `application/json` | JSON response negotiation |
  | `X-Idempotency-Key` | `{{$guid}}` or fresh UUIDv4 | Unique UUID for this accomplishment |
  | `X-Client-Timestamp` | **Must be $\ge$ `started_at` and $\le$ `now + 5m`**<br>*e.g. `2026-10-10T15:28:00+08:00`* | Time work was completed offline |

- **Body (`form-data`):**
  | Key | Type | Value |
  | :--- | :--- | :--- |
  | `remarks` | Text | `Replaced blown 25kVA transformer fuse link.` |
  | `consumer_name` | Text | `Edgardo Reyes` |
  | `signature` | File | Select any sample `.png` file |
  | `photos[]` | File | Select first sample `.jpg` image |
  | `photos[]` | File | Select second sample `.jpg` image |

- **Expected Response (`201 Created`):**
  - **Status Code:** `201 Created`
  - **Response Headers:** `X-Cache: MISS`
  - **Response Body:**
    ```json
    {
      "success": true,
      "message": "Accomplishment report for ticket TKT-261010-001 has been submitted.",
      "data": {
        "status": "pending",
        "remarks": "Replaced blown 25kVA transformer fuse link.",
        "accomplished_at": "Oct 10, 2026 09:30 AM",
        "is_offline_synced": true,
        "synced_at": "Oct 10, 2026 11:30 AM",
        "photos": [
          { "id": 1, "url": "http://127.0.0.1:8000/storage/accomplishments/photos/..." },
          { "id": 2, "url": "http://127.0.0.1:8000/storage/accomplishments/photos/..." }
        ]
      }
    }
    ```

---

### Scenario 4: Replay Protection on Accomplishment
*Simulates a network timeout during multipart evidence upload.*

- **Action:** Re-send the exact request from Scenario 3 without modifying the idempotency key.
- **Expected Behavior:**
  - Returns `201 Created` directly from cache.
  - Headers: `X-Cache: HIT`, `X-Idempotent-Replayed: true`.
  - **Storage Integrity:** No redundant duplicate photos are saved in `storage/app/public/accomplishments/photos`.

---

### Scenario 5: Future Clock Tampering Guardrail
*Simulates a mobile device with an incorrectly set future clock (e.g. tomorrow).*

- **Headers:**
  - `X-Idempotency-Key: c1111111-2222-3333-4444-555555555555`
  - `X-Client-Timestamp: 2026-10-15T00:00:00+08:00`
- **Expected Response (`422 Unprocessable Content`):**
  ```json
  {
    "success": false,
    "message": "The client timestamp cannot be in the future.",
    "errors": {
      "client_timestamp": [
        "The client timestamp cannot be in the future."
      ]
    }
  }
  ```

---

## 3. Flutter Mobile Division Implementation Blueprint

Provide this section directly to your Flutter developers.

### 3.1 Recommended Dependencies
Add to `pubspec.yaml`:
```yaml
dependencies:
  dio: ^5.4.0                          # HTTP client with interceptor support
  isar: ^3.1.0+1                       # High-speed offline NoSQL database
  isar_flutter_libs: ^3.1.0+1
  connectivity_plus: ^5.0.2            # Radio state detection
  internet_connection_checker_plus: ^2.1.0 # Actual internet reachability
  uuid: ^4.3.3                         # Client UUIDv4 generation
  path_provider: ^2.1.2                # Local disk storage path resolution
```

---

### 3.2 Local Outbox Entity Schema (`offline_action.dart`)
```dart
import 'package:isar/isar.dart';

part 'offline_action.g.dart';

@collection
class OfflineAction {
  Id id = Isar.autoIncrement;

  @Index(unique: true)
  late String idempotencyKey; // UUIDv4 generated at tap instant

  late String ticketId;       // Target Ticket ULID
  late String actionType;     // 'start' | 'accomplish'
  
  late DateTime occurredAt;   // Exact time lineman tapped button
  late DateTime queuedAt;     // Device time when saved to local outbox

  String? remarks;
  String? consumerName;
  String? localSignaturePath; // Path in app document directory
  List<String>? localPhotoPaths;

  @Index()
  bool isSynced = false;
  int retryAttempts = 0;
  String? lastErrorMessage;
}
```

---

### 3.3 Critical: Local Media Persistence Before Queueing
When the lineman snaps photos or captures a signature in offline terrain, the files **must** be copied to the application's persistent documents directory immediately so the mobile OS does not purge temporary camera cache:

```dart
Future<String> persistOfflineMedia(File tempFile, String filename) async {
  final appDir = await getApplicationDocumentsDirectory();
  final offlineDir = Directory('${appDir.path}/offline_media');
  if (!await offlineDir.exists()) {
    await offlineDir.create(recursive: true);
  }
  final targetFile = await tempFile.copy('${offlineDir.path}/$filename');
  return targetFile.path;
}
```

---

### 3.4 FIFO Synchronization Service (`sync_service.dart`)
The sync worker processes outbox actions **sequentially** per ticket to guarantee causal ordering (`start` always finishes before `accomplish`):

```dart
class SyncService {
  final Isar isar;
  final Dio dio;

  SyncService(this.isar, this.dio);

  Future<void> processOutbox() async {
    // 1. Verify actual internet reachability (not just local Wi-Fi router)
    final hasInternet = await InternetConnection().hasInternetAccess;
    if (!hasInternet) return;

    // 2. Fetch all unsynced actions in chronological order
    final pendingActions = await isar.offlineActions
        .filter()
        .isSyncedEqualTo(false)
        .sortByQueuedAt()
        .findAll();

    if (pendingActions.isEmpty) return;

    for (final action in pendingActions) {
      try {
        if (action.actionType == 'start') {
          await _syncStartAction(action);
        } else if (action.actionType == 'accomplish') {
          await _syncAccomplishAction(action);
        }

        // Mark as synced and delete cached local images
        await isar.writeTxn(() async {
          action.isSynced = true;
          await isar.offlineActions.put(action);
        });

        _cleanupLocalFiles(action);
      } on DioException catch (e) {
        // Handle 409 Conflict (retry shortly) vs 4xx validation errors
        if (e.response?.statusCode == 409) {
          break; // Wait for server to finish in-progress processing
        }
        await isar.writeTxn(() async {
          action.retryAttempts += 1;
          action.lastErrorMessage = e.message;
          await isar.offlineActions.put(action);
        });
      }
    }
  }

  Future<void> _syncStartAction(OfflineAction action) async {
    await dio.patch(
      '/tickets/${action.ticketId}/start',
      options: Options(
        headers: {
          'X-Idempotency-Key': action.idempotencyKey,
          'X-Client-Timestamp': action.occurredAt.toIso8601String(),
        },
      ),
    );
  }

  Future<void> _syncAccomplishAction(OfflineAction action) async {
    final formData = FormData.fromMap({
      'remarks': action.remarks,
      'consumer_name': action.consumerName,
      'signature': await MultipartFile.fromFile(action.localSignaturePath!),
    });

    if (action.localPhotoPaths != null) {
      for (final path in action.localPhotoPaths!) {
        formData.files.add(
          MapEntry('photos[]', await MultipartFile.fromFile(path)),
        );
      }
    }

    await dio.post(
      '/tickets/${action.ticketId}/accomplish',
      data: formData,
      options: Options(
        headers: {
          'X-Idempotency-Key': action.idempotencyKey,
          'X-Client-Timestamp': action.occurredAt.toIso8601String(),
        },
      ),
    );
  }

  void _cleanupLocalFiles(OfflineAction action) {
    if (action.localSignaturePath != null) {
      File(action.localSignaturePath!).delete().ignore();
    }
    if (action.localPhotoPaths != null) {
      for (final p in action.localPhotoPaths!) {
        File(p).delete().ignore();
      }
    }
  }
}
```

---

## 4. Verification & Automated Test Results

The module is covered by a dedicated feature test suite in `tests/Feature/OfflineAsyncSyncTest.php`:
1. `start endpoint rejects client timestamp in the future` $\rightarrow$ **PASSED**
2. `start endpoint rejects client timestamp earlier than ticket creation` $\rightarrow$ **PASSED**
3. `start endpoint rejects malformed or short idempotency key` $\rightarrow$ **PASSED**
4. `offline start transitions ticket with client timestamp and sets is_offline_synced` $\rightarrow$ **PASSED**
5. `idempotent replay on start returns cached response without duplicate status logs` $\rightarrow$ **PASSED**
6. `offline accomplish transitions ticket to resolved with evidence and sets is_offline_synced` $\rightarrow$ **PASSED**
7. `idempotent replay on accomplish returns cached response without duplicate records or photos` $\rightarrow$ **PASSED**

**Overall Test Suite Status:** `20 passed, 81 assertions, 0 failures`.  
**Frontend Production Build:** `built in 3.14s` (`npm run build`).

---

## 5. Next Steps for Database Deployment

When you are ready to update your local development database, run:
```bash
php artisan migrate
```
*(Or `php artisan migrate:fresh --seed` if performing a complete wipe)*.

