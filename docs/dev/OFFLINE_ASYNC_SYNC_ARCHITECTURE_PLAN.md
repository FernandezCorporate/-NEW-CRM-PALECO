# PALECO OTRS-CRM: Offline-Asynchronous API Architecture & Sync Plan

> **Scope Restriction:**  
> This offline-asynchronous architecture applies **exclusively** to the Field Personnel operational lifecycle endpoints:
> 1. `PATCH /api/tickets/{ticket}/start` (Work Commencement)
> 2. `POST /api/tickets/{ticket}/accomplish` (Work Accomplishment / Resolution with Evidence)
> 
> Administrative, CWD intake, and supervisory endpoints operate in connected environments and remain synchronous.

---

## 1. Executive Summary & Problem Statement

Field personnel (linemen and technical repair teams) frequently operate in remote barangays and mountainous coverage corridors across Palawan where cellular coverage (3G/4G/5G) is intermittent or non-existent.

### Current System Bottlenecks in Low-Connectivity Zones
1. **Failed State Transitions:** When a lineman clicks "Start" or "Submit Accomplishment" offline, the mobile app receives an immediate socket timeout (`SocketException` / `TimeoutException`), blocking work recording.
2. **Duplicate Execution on Network Retry:** If an HTTP request reaches the server but the connection drops before the mobile client receives the HTTP `200/201` response, the mobile app retries. The server then throws a `422 Unprocessable Content` error (*"This ticket is already in progress"* or *"This ticket already has a pending accomplishment"*), leaving the client out of sync.
3. **Distorted SLA & Timestamps:** Under the current schema, `started_at` and `accomplished_at` are hardcoded to `now()` on the backend. If work is done at 1:00 PM offline but synced at 5:30 PM when the team returns to town, SLA compliance metrics and response-time auditing are severely falsified.
4. **Heavy Multipart Upload Failures:** Accomplishment reports require a touch signature and between 1 and 10 photographic evidence files. Uploading multiple megabytes in fluctuating network environments frequently breaks midway.

---

## 2. Core Architectural Patterns

```
┌────────────────────────────────────────────────────────────────────────┐
│                        FLUTTER MOBILE CLIENT                           │
│                                                                        │
│  [Lineman Action] ──> [Local Database: Isar/Hive] ──> [Optimistic UI]  │
│                                │                                       │
│                       (Network Restored)                               │
│                                │                                       │
│                                ▼                                       │
│                  [FIFO Sync Queue Processor]                           │
└────────────────────────────────┬───────────────────────────────────────┘
                                 │ HTTP POST/PATCH
                                 │ Headers:
                                 │  - X-Idempotency-Key: <UUIDv4>
                                 │  - X-Client-Timestamp: <ISO-8601>
                                 ▼
┌────────────────────────────────────────────────────────────────────────┐
│                     LARAVEL REST BACKEND                               │
│                                                                        │
│   [Idempotency Middleware] ──(Key Exists & Completed?)──> [Cached 200] │
│              │                                                         │
│        (New Request)                                                   │
│              ▼                                                         │
│   [Transactional Handler]                                              │
│      ├── Verify Ticket Assignment & Lock Row                           │
│      ├── Backdate started_at / accomplished_at from Client-Timestamp   │
│      ├── Mark is_offline_synced = true, synced_at = now()              │
│      ├── Store Evidence Files & Generate Model Records                 │
│      └── Store Response in idempotency_records Table                   │
└────────────────────────────────────────────────────────────────────────┘
```

The system employs three established enterprise patterns:

### 1. Client-Driven Idempotency Pattern (IETF Specification)
* The mobile client generates a globally unique identifier (UUID v4) for each operation at the exact instant the lineman takes action:
  * `start_action_uuid`
  * `accomplish_action_uuid`
* This key is transmitted via the HTTP header `X-Idempotency-Key`.
* If a network timeout occurs and the mobile app replays the request, the server recognizes the key, suppresses duplicate execution, and returns the cached successful response.

### 2. Client-Origin Timestamp Preservation
* The mobile client captures the device's clock timestamp at the moment of action and sends it via `X-Client-Timestamp` (e.g., `2026-10-09T13:15:00+08:00`).
* The server uses this timestamp for `started_at` and `accomplished_at`, while recording `synced_at = now()` and `is_offline_synced = true`.
* **Guardrail:** The backend validates that the client timestamp does not exceed `now() + 5 minutes` (to avoid future-dating from incorrect device clocks) and is not older than the ticket creation timestamp.

### 3. Client-Side Outbox Pattern (FIFO Queue)
* On the mobile device, actions are stored in an encrypted local queue (`Isar` or `Hive`).
* Actions for a specific ticket are strictly sequential: `/start` must always be sent and confirmed before `/accomplish` is transmitted.

---

## 3. Database Schema Requirements

### 3.1 New Table: `idempotency_records`
Stores client idempotency keys, request hashes, and cached responses to prevent duplicate operations.

```sql
CREATE TABLE `idempotency_records` (
    `id` CHAR(26) NOT NULL PRIMARY KEY,                  -- ULID
    `user_id` CHAR(26) NOT NULL,                         -- Foreign key to users
    `idempotency_key` VARCHAR(64) NOT NULL,              -- Client-generated UUIDv4
    `endpoint_path` VARCHAR(255) NOT NULL,               -- e.g. /api/tickets/01HXYZ/accomplish
    `request_hash` VARCHAR(64) NOT NULL,                 -- SHA-256 of request parameters
    `status` ENUM('in_progress', 'completed', 'failed') NOT NULL DEFAULT 'in_progress',
    `response_code` INT UNSIGNED NULL,                   -- e.g. 200, 201
    `response_body` JSON NULL,                           -- Cached JSON response
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NOT NULL,                     -- Automatically purges after 7 days
    
    INDEX `idx_user_key` (`user_id`, `idempotency_key`),
    INDEX `idx_expires` (`expires_at`),
    CONSTRAINT `fk_idempotency_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.2 Schema Additions to `tickets`
Enables SLA auditing and distinction between live desk events vs offline field syncs.

```sql
ALTER TABLE `tickets`
    ADD COLUMN `is_offline_synced` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`,
    ADD COLUMN `synced_at` TIMESTAMP NULL DEFAULT NULL AFTER `is_offline_synced`,
    ADD COLUMN `client_started_at` TIMESTAMP NULL DEFAULT NULL AFTER `started_at`;
```

### 3.3 Schema Additions to `ticket_accomplishments`
Records when work was physically accomplished in remote terrain versus when the packet reached the cooperative's cloud servers.

```sql
ALTER TABLE `ticket_accomplishments`
    ADD COLUMN `is_offline_synced` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`,
    ADD COLUMN `synced_at` TIMESTAMP NULL DEFAULT NULL AFTER `is_offline_synced`,
    ADD COLUMN `client_accomplished_at` TIMESTAMP NULL DEFAULT NULL AFTER `accomplished_at`,
    ADD COLUMN `idempotency_key` VARCHAR(64) NULL AFTER `synced_at`;
```

---

## 4. Backend Implementation Architecture

### 4.1 Idempotency Middleware (`HandleIdempotency.php`)
Intercepts incoming requests containing the `X-Idempotency-Key` header.

* **Step 1:** Verify the header exists. If absent, continue normally (backward compatibility).
* **Step 2:** Check `idempotency_records` for the authenticated user and key.
  * If found and `status === 'completed'`, immediately return `response()->json($record->response_body, $record->response_code)`.
  * If found and `status === 'in_progress'`, return `409 Conflict` (*"Request currently processing, please retry shortly"*).
* **Step 3:** Insert a new row with `status = 'in_progress'`.
* **Step 4:** Execute the controller action.
* **Step 5:** Save the generated JSON response, status code, and mark `status = 'completed'`.

### 4.2 Updated Ticket Start Logic (`TicketService::startTicket`)
```php
public function startTicket(Ticket $ticket, User $worker, ?Carbon $clientTimestamp = null, ?string $idempotencyKey = null): Ticket
{
    return DB::transaction(function () use ($ticket, $worker, $clientTimestamp) {
        $lockedTicket = Ticket::where('system_id', $ticket->system_id)->lockForUpdate()->firstOrFail();

        // Idempotency safety: If already IN_PROGRESS by the same worker, return ticket without throwing error
        if ($lockedTicket->status === TicketStatus::IN_PROGRESS) {
            return $lockedTicket->fresh(['category']);
        }

        if ($lockedTicket->status !== TicketStatus::ASSIGNED) {
            throw ValidationException::withMessages([
                'status' => 'Only assigned tickets can be started.',
            ]);
        }

        $isOffline = $clientTimestamp !== null && $clientTimestamp->diffInMinutes(now()) > 5;
        $actualStartTime = $clientTimestamp ?? now();

        $lockedTicket->statusLog()->create([
            'old_status' => $lockedTicket->status,
            'new_status' => TicketStatus::IN_PROGRESS,
            'changed_by' => $worker->id,
            'created_at' => $actualStartTime,
        ]);

        $lockedTicket->update([
            'status' => TicketStatus::IN_PROGRESS,
            'started_at' => $actualStartTime,
            'client_started_at' => $clientTimestamp,
            'is_offline_synced' => $isOffline,
            'synced_at' => $isOffline ? now() : null,
        ]);

        return $lockedTicket->fresh(['category']);
    });
}
```

### 4.3 Updated Accomplishment Logic (`TicketAccomplishmentService::accomplishTicket`)
```php
// Handles offline client timestamp and checks existing report for idempotency:
$isOffline = $clientTimestamp !== null && $clientTimestamp->diffInMinutes(now()) > 5;
$actualAccomplishedTime = $clientTimestamp ?? now();

// If idempotent replay occurs after commit:
if ($idempotencyKey && $existing = TicketAccomplishment::where('idempotency_key', $idempotencyKey)->first()) {
    return $existing->load('accomplishedBy', 'photos');
}
```

---

## 5. Postman Testing Manual (No Mobile App Needed)

You can verify the entire offline-async functionality using **Postman** or **cURL**.

### Setup Prerequisites
1. Base URL: `http://127.0.0.1:8000/api`
2. Obtain a Field Personnel token by logging in as `ralph`:
   * `POST http://127.0.0.1:8000/api/login`
   * Body: `{"username": "ralph", "password": "password"}`
   * Copy the returned token to your Postman environment variable: `{{field_token}}`.
3. Pick a ticket currently in `assigned` status: `{{ticket_ulid}}`.

---

### Dynamic Data Injection Criteria for Testing

Do not use hardcoded static timestamps. The backend enforces strict chronological guardrails:
1. **Lower Bound (Ticket Creation Barrier):** `X-Client-Timestamp` must be **$\ge$ `reported_at`** of the ticket. Work cannot start before the ticket was created.
2. **Upper Bound (Future Guardrail):** `X-Client-Timestamp` must be **$\le$ `Current Server Time + 5 minutes`**.
3. **Offline Classification Threshold:** If `X-Client-Timestamp` is **older than `now - 5 minutes`**, the server flags `is_offline_synced = true` and records `synced_at = now()`.

> **Quick Formula:** Look at your ticket's `reported_at` (e.g. `15:24`). Check current time (e.g. `15:35`). Inject a timestamp between `reported_at` and `now - 6 minutes` (e.g. `2026-10-10T15:26:00+08:00`).

---

### Test Scenario 1: Simulating Offline `/start`
*Simulates a lineman starting a repair in a dead zone and syncing upon network restoration.*

* **Method:** `PATCH`
* **URL:** `http://127.0.0.1:8000/api/tickets/{{ticket_ulid}}/start`
* **Headers:**
  ```http
  Authorization: Bearer {{field_token}}
  Accept: application/json
  X-Idempotency-Key: {{$guid}}
  X-Client-Timestamp: <Timestamp >= reported_at and <= now + 5m, e.g. 2026-10-10T15:26:00+08:00>
  ```
* **Expected Response (`200 OK`):**
  ```json
  {
    "success": true,
    "message": "Work has started on ticket TKT-261010-001.",
    "data": {
      "ticket_number": "TKT-261010-001",
      "status": "in_progress",
      "started_at": "Oct 10, 2026 03:26 PM",
      "is_offline_synced": true,
      "synced_at": "Oct 10, 2026 03:35 PM"
    }
  }
  ```

---

### Test Scenario 2: Network Interruption & Duplicate Replay Test
*Simulates the mobile client dropping connection immediately after the server processed Test 1. The phone retries with the identical idempotency key.*

* **Action:** Click **Send** in Postman again with the exact same headers and key (`9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d`).
* **Expected Behavior:**
  * **Status Code:** `200 OK` (Replayed cleanly from cache).
  * **Database Validation:** Open MySQL; verify there is **only one** entry in `ticket_status_logs`, not two!
  * **No 422 Error:** The user does not receive `"This ticket is already in progress"`.

---

### Test Scenario 3: Simulating Offline `/accomplish` (Multipart Upload)
*Simulates uploading remarks, touch signature, and photos recorded at 9:30 AM while offline.*

* **Method:** `POST`
* **URL:** `http://127.0.0.1:8000/api/tickets/{{ticket_ulid}}/accomplish`
* **Headers:**
  ```http
  Authorization: Bearer {{field_token}}
  Accept: application/json
  X-Idempotency-Key: {{$guid}}
  X-Client-Timestamp: <Timestamp >= started_at and <= now + 5m, e.g. 2026-10-10T15:28:00+08:00>
  ```
* **Body (form-data):**
  | Key | Type | Value |
  | :--- | :--- | :--- |
  | `remarks` | Text | `Replaced blown 25kVA transformer fuse link.` |
  | `consumer_name` | Text | `Edgardo Reyes` |
  | `signature` | File | `signature.png` (Select any sample PNG) |
  | `photos[]` | File | `evidence1.jpg` (Select any sample JPG) |
  | `photos[]` | File | `evidence2.jpg` (Select second sample JPG) |

* **Expected Response (`201 Created`):**
  ```json
  {
    "success": true,
    "message": "Accomplishment report for ticket 2026-10-0001 has been submitted.",
    "data": {
      "status": "pending",
      "remarks": "Replaced blown 25kVA transformer fuse link.",
      "accomplished_at": "2026-10-09T09:30:00.000000Z",
      "is_offline_synced": true,
      "photos": [
        { "file_name": "evidence1.jpg" },
        { "file_name": "evidence2.jpg" }
      ]
    }
  }
  ```

---

### Test Scenario 4: Replay Protection on Accomplishment
*Simulates a timeout on the multipart upload.*

* **Action:** Re-send the exact request from Scenario 3 without modifying the idempotency key.
* **Expected Behavior:**
  * Returns `201 Created` directly from `idempotency_records`.
  * **Storage Integrity:** No redundant duplicate photos are saved in `storage/app/public/accomplishments/photos`.

---

### Test Scenario 5: Future Clock Tampering Guardrail
*Simulates a phone with an incorrectly set future date/time (e.g., tomorrow).*

* **Headers:**
  ```http
  X-Client-Timestamp: 2026-10-15T00:00:00Z
  X-Idempotency-Key: e9999999-0000-0000-0000-000000000000
  ```
* **Expected Response (`422 Unprocessable Content`):**
  ```json
  {
    "success": false,
    "message": "The client timestamp cannot be in the future."
  }
  ```

---

## 6. Mobile Division Blueprint (Flutter Framework)

Provide this section directly to your Flutter developers.

### 6.1 Recommended Dependencies
Add to `pubspec.yaml`:
```yaml
dependencies:
  dio: ^5.4.0                          # HTTP client with interceptor support
  isar: ^3.1.0+1                       # High-speed offline NoSQL database
  isar_flutter_libs: ^3.1.0+1
  connectivity_plus: ^5.0.2            # Radio state detection
  internet_connection_checker_plus: ^2.1.0 # Actual internet reachability
  uuid: ^4.3.3                         # Client UUIDv4 generation
  path_provider: ^2.1.2                # Local disk image caching
```

---

### 6.2 Local Queue Entity Schema (`offline_action.dart`)
```dart
import 'package:isar/isar.dart';

part 'offline_action.g.dart';

@collection
class OfflineAction {
  Id id = Isar.autoIncrement;

  @Index(unique: true)
  late String idempotencyKey; // UUIDv4

  late String ticketId;       // Ticket ULID
  late String actionType;     // 'start' | 'accomplish'
  
  late DateTime occurredAt;   // Exact time lineman tapped button
  late DateTime queuedAt;     // Device time when stored in outbox

  String? remarks;
  String? consumerName;
  String? localSignaturePath; // Cached file path on device storage
  List<String>? localPhotoPaths;

  @Index()
  bool isSynced = false;
  int retryAttempts = 0;
  String? lastErrorMessage;
}
```

---

### 6.3 Local File Caching Before Queueing
When the lineman takes photos or signs the screen in offline terrain, the files **must** be copied to the application's persistent document directory immediately so the operating system does not purge them:

```dart
Future<String> persistOfflineFile(File tempFile, String filename) async {
  final appDir = await getApplicationDocumentsDirectory();
  final offlineMediaDir = Directory('${appDir.path}/offline_media');
  if (!await offlineMediaDir.exists()) {
    await offlineMediaDir.create(recursive: true);
  }
  final savedFile = await tempFile.copy('${offlineMediaDir.path}/$filename');
  return savedFile.path;
}
```

---

### 6.4 FIFO Synchronization Service (`sync_service.dart`)
The sync worker processes actions sequentially per ticket to ensure causal order (`start` always finishes before `accomplish`):

```dart
class SyncService {
  final Isar isar;
  final Dio dio;

  SyncService(this.isar, this.dio);

  Future<void> processOutbox() async {
    // 1. Verify actual internet reachability (not just local Wi-Fi router)
    final hasInternet = await InternetConnection().hasInternetAccess;
    if (!hasInternet) return;

    // 2. Query pending actions ordered chronologically
    final pendingActions = await isar.offlineActions
        .filter()
        .isSyncedEqualTo(false)
        .sortByOccurredAt()
        .findAll();

    for (final action in pendingActions) {
      try {
        if (action.actionType == 'start') {
          await _syncStartAction(action);
        } else if (action.actionType == 'accomplish') {
          await _syncAccomplishAction(action);
        }

        // Mark completed and remove media from local device cache
        await isar.writeTxn(() async {
          action.isSynced = true;
          await isar.offlineActions.put(action);
        });

        _cleanupLocalFiles(action);
      } catch (e) {
        // Increment retry and back off; preserve queue order
        await isar.writeTxn(() async {
          action.retryAttempts += 1;
          action.lastErrorMessage = e.toString();
          await isar.offlineActions.put(action);
        });
        break; // Stop further processing until network stabilizes
      }
    }
  }

  Future<void> _syncStartAction(OfflineAction action) async {
    await dio.patch(
      '/tickets/${action.ticketId}/start',
      options: Options(headers: {
        'X-Idempotency-Key': action.idempotencyKey,
        'X-Client-Timestamp': action.occurredAt.toUtc().toIso8601String(),
      }),
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
        formData.files.add(MapEntry(
          'photos[]',
          await MultipartFile.fromFile(path),
        ));
      }
    }

    await dio.post(
      '/tickets/${action.ticketId}/accomplish',
      data: formData,
      options: Options(headers: {
        'X-Idempotency-Key': action.idempotencyKey,
        'X-Client-Timestamp': action.occurredAt.toUtc().toIso8601String(),
      }),
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

## 7. Edge Cases & Conflict Resolution Matrix

| Scenario | Conflict Condition | Resolution Strategy |
| :--- | :--- | :--- |
| **Lineman starts & finishes offline, but Supervisor cancelled ticket on Web.** | Supervisor cancelled ticket during outage. Ticket status in DB is `CANCELLED`. | Backend rejects `/accomplish` with `409 Conflict`. Mobile queue marks action `FAILED_CONFLICT` and logs an emergency notification for the lineman. The accomplishment data is kept in device history so photos are not lost. |
| **Out-of-Order Packet Delivery** | Cellular tower delivers `/accomplish` packet before `/start` packet. | Backend handles gracefully: If ticket is still in `ASSIGNED` when valid `/accomplish` packet arrives with valid evidence, backend auto-logs `started_at = client_timestamp` and immediately transitions to `RESOLVED`. |
| **Clock Drift / Device Clock Incorrect** | Lineman's phone battery drained; clock reset to Jan 1, 1970 or set 2 days into the future. | Backend inspects `X-Client-Timestamp`. If earlier than `ticket.created_at` or later than `now() + 5m`, backend rejects with `422 Unprocessable Content`. Mobile app prompts lineman to enable automatic network time sync. |
| **Partial Upload Timeout (Multi-photo)** | 5 of 8 photos uploaded before connection drops. | Web server terminates request without writing to database. When client retries with the same `X-Idempotency-Key`, transaction runs fresh. Once committed, no duplicate records are generated. |

---

*Document prepared for the PALECO Capstone Development Team.*

