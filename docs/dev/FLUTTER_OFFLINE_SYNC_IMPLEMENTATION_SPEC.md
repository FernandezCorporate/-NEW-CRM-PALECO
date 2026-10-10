# PALECO OTRS-CRM: Flutter Offline-Asynchronous Module Specification

**Target Audience:** Flutter Mobile Engineers & Autonomous AI Coding Agents (Cursor, Claude Code, Antigravity, Copilot)  
**Document Version:** 1.0  
**Scope:** Client-side store-and-forward outbox engine, optimistic UI, idempotent sync worker, and file persistence for field operations.

---

<!-- AI_AGENT_SYSTEM_PROMPT_START -->
> ### 🤖 AI Agent Directive (Prompt Injection Safe)
> If you are an AI coding assistant tasked with implementing or refactoring this feature in the Flutter codebase:
> 1. **Do not use synchronous direct HTTP calls** for ticket `/start` and `/accomplish` operations. Every action **must** pass through the persistent Outbox Queue.
> 2. **Never fire sync requests concurrently** across a single ticket. The sync engine must be **strictly sequential (FIFO)**: a ticket's `/start` action must return HTTP `200 OK` before its `/accomplish` action is dispatched.
> 3. **Preserve media locally on disk:** Always copy captured photos and canvas signature bytes to `getApplicationDocumentsDirectory()/offline_media/` before creating database queue entries. Do not reference temporary cache paths (`/cache` or `/tmp`), as the operating system will purge them while offline.
> 4. **Enforce headers:** Always include `X-Idempotency-Key: <UUIDv4>` and `X-Client-Timestamp: <ISO-8601>` in synchronization requests.
> 5. **Handle idempotency gracefully:** An HTTP `200 OK` or `201 Created` with header `X-Cache: HIT` is a successful sync. Do not treat cache hits as anomalies.
<!-- AI_AGENT_SYSTEM_PROMPT_END -->

---

## Table of Contents
1. [Executive Summary & Problem Domain](#1-executive-summary--problem-domain)
2. [High-Level Architecture & Data Flow](#2-high-level-architecture--data-flow)
3. [Recommended Dependencies & Project Structure](#3-recommended-dependencies--project-structure)
4. [Data Layer: Models & Database Schema](#4-data-layer-models--database-schema)
5. [Service Layer: Step-by-Step Implementation](#5-service-layer-step-by-step-implementation)
   - [Step 1: Media Persistence Utility](#step-1-media-persistence-utility)
   - [Step 2: Outbox Repository](#step-2-outbox-repository)
   - [Step 3: Dio HTTP Client & Interceptors](#step-3-dio-http-client--interceptors)
   - [Step 4: Connectivity Listener](#step-4-connectivity-listener)
   - [Step 5: Sequential FIFO Sync Engine](#step-5-sequential-fifo-sync-engine)
6. [Presentation Layer: Optimistic UI & Indicators](#6-presentation-layer-optimistic-ui--indicators)
7. [Edge Cases & Error Handling Matrix](#7-edge-cases--error-handling-matrix)
8. [AI Agent Prompt Library (Ready-to-Run Prompts)](#8-ai-agent-prompt-library-ready-to-run-prompts)
9. [Mobile QA & Verification Scenarios](#9-mobile-qa--verification-scenarios)

---

## 1. Executive Summary & Problem Domain

PALECO field linemen operate in remote barangays and mountainous sitios across Palawan with zero cellular reception (dead zones). 

When field personnel perform critical maintenance, two key operations occur:
1. **Start Work (`PATCH /api/tickets/{ticket}/start`):** Initiates emergency repair upon arrival.
2. **Accomplish Ticket (`POST /api/tickets/{ticket}/accomplish`):** Submits technical narrative, consumer e-signature, and 1–10 photographic evidences.

In an online-only app, these actions fail immediately in dead zones. With this **Offline-Asynchronous Module**, the mobile application writes actions and images to local device storage, optimistically renders the ticket as progressed, and automatically syncs the payload when cellular connectivity is restored.

---

## 2. High-Level Architecture & Data Flow

```mermaid
flowchart TD
    subgraph Mobile Device (Field - Dead Zone)
        UI[Field Personnel UI] -->|1. Tap Start / Accomplish| OptState[Optimistic UI State<br/>'In Progress' / 'Resolved']
        UI -->|2. Save Photos & Signature| LocalDisk[(Device App Storage<br/>/offline_media/)]
        OptState -->|3. Record Event Time & UUID| OutboxDB[(Local Database / Isar<br/>offline_actions table)]
    end

    subgraph Background Sync Worker
        NetCheck{Internet Available?}
        OutboxDB -->|Poll / Stream| NetCheck
        NetCheck -->|No| Wait[Sleep & Listen to Connectivity]
        NetCheck -->|Yes| FIFOLock[Lock Ticket & Order by Event Time]
    end

    subgraph PALECO Cloud API
        FIFOLock -->|4. Sync Start with X-Idempotency-Key| ApiStart[PATCH /api/tickets/:id/start]
        ApiStart -->|200 OK| OutboxMark1[Mark Action Synced]
        OutboxMark1 -->|5. Sync Accomplish with Multipart| ApiAcc[POST /api/tickets/:id/accomplish]
        ApiAcc -->|201 Created| OutboxMark2[Mark Action Synced & Clean Media]
    end
```

---

## 3. Recommended Dependencies & Project Structure

### 3.1 `pubspec.yaml`
```yaml
dependencies:
  flutter:
    sdk: flutter

  # Networking & HTTP
  dio: ^5.4.0

  # Local NoSQL Database (High speed, zero-boilerplate queries)
  isar: ^3.1.0+1
  isar_flutter_libs: ^3.1.0+1

  # Network Detection (Dual: radio state + true ICMP reachability)
  connectivity_plus: ^5.0.2
  internet_connection_checker_plus: ^2.1.0

  # Utilities
  uuid: ^4.3.3
  path_provider: ^2.1.2
  intl: ^0.19.0

dev_dependencies:
  build_runner: ^2.4.8
  isar_generator: ^3.1.0+1
```

> *Alternative Local Database:* If your project already uses `sqflite` or `drift`, the schema in Section 4 can be translated 1-to-1 to SQLite tables.

### 3.2 Target Directory Structure
```
lib/
├── core/
│   ├── network/
│   │   ├── api_client.dart           # Configured Dio singleton
│   │   ├── idempotency_interceptor.dart
│   │   └── network_info.dart         # Connectivity stream
│   └── utils/
│       └── file_storage_helper.dart  # Permanent offline image persistence
├── features/
│   └── tickets/
│       ├── data/
│       │   ├── models/
│       │   │   └── offline_action.dart # Isar / SQLite entity
│       │   └── repositories/
│       │       └── offline_outbox_repository.dart
│       ├── domain/
│       │   └── services/
│       │       └── ticket_sync_service.dart # FIFO Background Sync Engine
│       └── presentation/
│           ├── controllers/
│           │   └── ticket_detail_controller.dart
│           └── widgets/
│               └── sync_status_badge.dart
```

---

## 4. Data Layer: Models & Database Schema

### 4.1 `offline_action.dart` (Isar Database Model)

```dart
import 'package:isar/isar.dart';

part 'offline_action.g.dart';

enum OfflineActionType { start, accomplish }
enum SyncStatus { pending, inFlight, synced, failed }

@collection
class OfflineAction {
  Id id = Isar.autoIncrement;

  @Index(unique: true)
  late String idempotencyKey; // UUIDv4: e.g. "4f3c7a91-2a62-4b78-831d-b3ef9a82c401"

  @Index()
  late String ticketId; // Ticket ULID: e.g. "01m4jb68m7p0yrv867g9t9zswc"

  @Enumerated(EnumType.name)
  late OfflineActionType actionType; // start | accomplish

  late DateTime occurredAt; // Exact device event time when lineman clicked
  late DateTime queuedAt;   // Time inserted into local database

  @Enumerated(EnumType.name)
  @Index()
  SyncStatus status = SyncStatus.pending;

  int retryCount = 0;
  String? lastErrorMessage;

  // Fields specific to accomplish action
  String? remarks;
  String? consumerName;
  String? localSignaturePath; // Absolute path in getApplicationDocumentsDirectory()
  List<String>? localPhotoPaths;

  // Audit response data
  DateTime? syncedAt;
}
```

---

## 5. Service Layer: Step-by-Step Implementation

### Step 1: Media Persistence Utility
*Critical Rule:* Camera pickers and signature canvas controllers place bytes into temporary cache directories. On Android/iOS, if the device runs low on memory or the app terminates, cache files are deleted. **You must copy files to persistent application storage immediately.**

```dart
// lib/core/utils/file_storage_helper.dart
import 'dart:io';
import 'package:path_provider/path_provider.dart';

class FileStorageHelper {
  static Future<String> persistOfflineMedia(File sourceFile, String prefix) async {
    final appDir = await getApplicationDocumentsDirectory();
    final offlineDir = Directory('${appDir.path}/offline_media');
    
    if (!await offlineDir.exists()) {
      await offlineDir.create(recursive: true);
    }

    final ext = sourceFile.path.split('.').last;
    final timestamp = DateTime.now().millisecondsSinceEpoch;
    final destination = '${offlineDir.path}/${prefix}_$timestamp.$ext';

    final savedFile = await sourceFile.copy(destination);
    return savedFile.path;
  }

  static Future<void> cleanupMedia(List<String> paths) async {
    for (final path in paths) {
      try {
        final file = File(path);
        if (await file.exists()) {
          await file.delete();
        }
      } catch (_) {}
    }
  }
}
```

---

### Step 2: Outbox Repository
Provides high-level methods to enqueue operations and fetch pending queues.

```dart
// lib/features/tickets/data/repositories/offline_outbox_repository.dart
import 'package:isar/isar.dart';
import 'package:uuid/uuid.dart';
import '../models/offline_action.dart';

class OfflineOutboxRepository {
  final Isar isar;
  final _uuid = const Uuid();

  OfflineOutboxRepository(this.isar);

  /// Queue a ticket START action
  Future<OfflineAction> queueStartAction(String ticketId) async {
    final now = DateTime.now();
    final action = OfflineAction()
      ..idempotencyKey = _uuid.v4()
      ..ticketId = ticketId
      ..actionType = OfflineActionType.start
      ..occurredAt = now
      ..queuedAt = now
      ..status = SyncStatus.pending;

    await isar.writeTxn(() async {
      await isar.offlineActions.put(action);
    });

    return action;
  }

  /// Queue a ticket ACCOMPLISH action with cached media
  Future<OfflineAction> queueAccomplishAction({
    required String ticketId,
    required String remarks,
    String? consumerName,
    required String signaturePath,
    required List<String> photoPaths,
  }) async {
    final now = DateTime.now();
    final action = OfflineAction()
      ..idempotencyKey = _uuid.v4()
      ..ticketId = ticketId
      ..actionType = OfflineActionType.accomplish
      ..occurredAt = now
      ..queuedAt = now
      ..remarks = remarks
      ..consumerName = consumerName
      ..localSignaturePath = signaturePath
      ..localPhotoPaths = photoPaths
      ..status = SyncStatus.pending;

    await isar.writeTxn(() async {
      await isar.offlineActions.put(action);
    });

    return action;
  }

  /// Get pending actions ordered chronologically (FIFO)
  Future<List<OfflineAction>> getPendingActions() async {
    return isar.offlineActions
        .filter()
        .statusEqualTo(SyncStatus.pending)
        .sortByOccurredAt()
        .findAll();
  }
}
```

---

### Step 3: Dio HTTP Client & Interceptors

```dart
// lib/core/network/api_client.dart
import 'package:dio/dio.dart';

Dio createApiClient({required String baseUrl, required String Function() getToken}) {
  final dio = Dio(
    BaseOptions(
      baseUrl: baseUrl,
      connectTimeout: const Duration(seconds: 15),
      receiveTimeout: const Duration(seconds: 30),
      headers: {
        'Accept': 'application/json',
      },
    ),
  );

  dio.interceptors.add(
    InterceptorsWrapper(
      onRequest: (options, handler) {
        final token = getToken();
        if (token.isNotEmpty) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        return handler.next(options);
      },
    ),
  );

  return dio;
}
```

---

### Step 4: Connectivity Listener
Detects true internet availability beyond just local Wi-Fi association.

```dart
// lib/core/network/network_info.dart
import 'dart:async';
import 'package:internet_connection_checker_plus/internet_connection_checker_plus.dart';

class NetworkInfo {
  final InternetConnection _checker = InternetConnection();

  Future<bool> get isConnected async => await _checker.hasInternetAccess;

  Stream<InternetStatus> get onStatusChange => _checker.onStatusChange;
}
```

---

### Step 5: Sequential FIFO Sync Engine
**Core Business Requirement:** Operations for a ticket must execute in strict sequence:
1. `start` MUST complete with HTTP `200 OK` before `accomplish` is attempted.
2. If `start` fails due to network, `accomplish` must wait.
3. Requests attach `X-Idempotency-Key` and `X-Client-Timestamp`.

```dart
// lib/features/tickets/domain/services/ticket_sync_service.dart
import 'dart:io';
import 'package:dio/dio.dart';
import 'package:isar/isar.dart';
import '../../data/models/offline_action.dart';
import '../../data/repositories/offline_outbox_repository.dart';
import '../../../../core/network/network_info.dart';
import '../../../../core/utils/file_storage_helper.dart';

class TicketSyncService {
  final Isar isar;
  final Dio dio;
  final NetworkInfo networkInfo;
  final OfflineOutboxRepository outboxRepository;

  bool _isSyncing = false;

  TicketSyncService({
    required this.isar,
    required this.dio,
    required this.networkInfo,
    required this.outboxRepository,
  }) {
    // Automatically trigger outbox sync whenever connection restores
    networkInfo.onStatusChange.listen((status) {
      if (status == InternetStatus.connected) {
        syncPendingOutbox();
      }
    });
  }

  Future<void> syncPendingOutbox() async {
    if (_isSyncing) return;
    if (!await networkInfo.isConnected) return;

    _isSyncing = true;

    try {
      final pendingList = await outboxRepository.getPendingActions();
      
      // Group by ticketId to process each ticket's lifecycle sequentially
      final Map<String, List<OfflineAction>> ticketGroups = {};
      for (final action in pendingList) {
        ticketGroups.putIfAbsent(action.ticketId, () => []).add(action);
      }

      for (final entry in ticketGroups.entries) {
        final actions = entry.value;
        // Enforce FIFO order within ticket stream
        actions.sort((a, b) => a.occurredAt.compareTo(b.occurredAt));

        for (final action in actions) {
          final success = await _dispatchAction(action);
          if (!success) {
            // STOP processing subsequent actions for this ticket if earlier action failed!
            break;
          }
        }
      }
    } finally {
      _isSyncing = false;
    }
  }

  Future<bool> _dispatchAction(OfflineAction action) async {
    // 1. Mark in flight
    await isar.writeTxn(() async {
      action.status = SyncStatus.inFlight;
      await isar.offlineActions.put(action);
    });

    try {
      if (action.actionType == OfflineActionType.start) {
        await _executeStartRequest(action);
      } else if (action.actionType == OfflineActionType.accomplish) {
        await _executeAccomplishRequest(action);
      }

      // 2. Mark succeeded
      await isar.writeTxn(() async {
        action.status = SyncStatus.synced;
        action.syncedAt = DateTime.now();
        await isar.offlineActions.put(action);
      });

      // 3. Clean up cached images from device storage
      if (action.actionType == OfflineActionType.accomplish) {
        final toDelete = <String>[];
        if (action.localSignaturePath != null) toDelete.add(action.localSignaturePath!);
        if (action.localPhotoPaths != null) toDelete.addAll(action.localPhotoPaths!);
        await FileStorageHelper.cleanupMedia(toDelete);
      }

      return true;
    } on DioException catch (e) {
      await isar.writeTxn(() async {
        action.retryCount += 1;
        action.lastErrorMessage = e.message;

        // If server rejected with 422 (permanent business error), mark failed so it doesn't loop
        if (e.response?.statusCode == 422) {
          action.status = SyncStatus.failed;
        } else {
          action.status = SyncStatus.pending; // Re-queue for next attempt
        }
        await isar.offlineActions.put(action);
      });
      return false;
    } catch (e) {
      await isar.writeTxn(() async {
        action.status = SyncStatus.pending;
        action.retryCount += 1;
        action.lastErrorMessage = e.toString();
        await isar.offlineActions.put(action);
      });
      return false;
    }
  }

  Future<void> _executeStartRequest(OfflineAction action) async {
    final response = await dio.patch(
      '/api/tickets/${action.ticketId}/start',
      options: Options(
        headers: {
          'X-Idempotency-Key': action.idempotencyKey,
          'X-Client-Timestamp': action.occurredAt.toUtc().toIso8601String(),
        },
      ),
    );

    if (response.statusCode != 200) {
      throw DioException(
        requestOptions: response.requestOptions,
        response: response,
        message: 'Non-200 response: ${response.statusCode}',
      );
    }
  }

  Future<void> _executeAccomplishRequest(OfflineAction action) async {
    final formData = FormData.fromMap({
      'remarks': action.remarks,
      if (action.consumerName != null && action.consumerName!.isNotEmpty)
        'consumer_name': action.consumerName,
      'client_timestamp': action.occurredAt.toUtc().toIso8601String(),
      'signature': await MultipartFile.fromFile(
        action.localSignaturePath!,
        filename: 'signature_${action.idempotencyKey}.png',
      ),
    });

    if (action.localPhotoPaths != null) {
      for (int i = 0; i < action.localPhotoPaths!.length; i++) {
        final path = action.localPhotoPaths![i];
        formData.files.add(
          MapEntry(
            'photos[]',
            await MultipartFile.fromFile(path, filename: 'photo_${i}_${action.idempotencyKey}.jpg'),
          ),
        );
      }
    }

    final response = await dio.post(
      '/api/tickets/${action.ticketId}/accomplish',
      data: formData,
      options: Options(
        headers: {
          'X-Idempotency-Key': action.idempotencyKey,
          'X-Client-Timestamp': action.occurredAt.toUtc().toIso8601String(),
        },
      ),
    );

    if (response.statusCode != 201 && response.statusCode != 200) {
      throw DioException(
        requestOptions: response.requestOptions,
        response: response,
        message: 'Accomplishment upload failed with code: ${response.statusCode}',
      );
    }
  }
}
```

---

## 6. Presentation Layer: Optimistic UI & Indicators

### 6.1 Optimistic State Transition
When the user taps **"Start Work"**:
1. Save `OfflineAction` to local database.
2. Immediately change local ticket state to `status = 'in_progress'`.
3. Display a visual badge: `Sync Pending (Offline)`.
4. Allow user to navigate immediately to the **Accomplishment Form**.

When the user submits **"Accomplish Ticket"**:
1. Save `OfflineAction` to local database.
2. Immediately change local ticket state to `status = 'resolved'`.
3. Display a visual badge: `Upload Queued (Pending Signal)`.
4. Trigger background `syncPendingOutbox()` opportunistically (if signal is available, it syncs immediately; if not, it stays queued).

### 6.2 UI Sync Badge Widget
```dart
// lib/features/tickets/presentation/widgets/sync_status_badge.dart
import 'package:flutter/material.dart';
import '../../data/models/offline_action.dart';

class SyncStatusBadge extends StatelessWidget {
  final SyncStatus status;

  const SyncStatusBadge({super.key, required this.status});

  @override
  Widget build(BuildContext context) {
    switch (status) {
      case SyncStatus.pending:
        return const Chip(
          avatar: Icon(Icons.cloud_off, size: 16, color: Colors.orange),
          label: Text('Offline (Queued)', style: TextStyle(color: Colors.orange)),
          backgroundColor: Color(0xFFFFF3E0),
        );
      case SyncStatus.inFlight:
        return const Chip(
          avatar: SizedBox(
            width: 14,
            height: 14,
            child: CircularProgressIndicator(strokeWidth: 2),
          ),
          label: Text('Syncing...'),
        );
      case SyncStatus.synced:
        return const Chip(
          avatar: Icon(Icons.cloud_done, size: 16, color: Colors.green),
          label: Text('Synced', style: TextStyle(color: Colors.green)),
          backgroundColor: Color(0xFFE8F5E9),
        );
      case SyncStatus.failed:
        return const Chip(
          avatar: Icon(Icons.error_outline, size: 16, color: Colors.red),
          label: Text('Sync Error', style: TextStyle(color: Colors.red)),
          backgroundColor: Color(0xFFFFEBEE),
        );
    }
  }
}
```

---

## 7. Edge Cases & Error Handling Matrix

| Situation / Edge Case | What Occurs | Mobile Handling Strategy |
| :--- | :--- | :--- |
| **Spotty 3G / Connection Drops during upload** | Server might or might not have received request before tower dropped. | App retries when signal returns using the **exact same `X-Idempotency-Key`**. Server returns cached `200/201 OK` (`X-Cache: HIT`). App marks item as synced. |
| **Out-of-Order Execution Risk** | User taps Start at 9:30 AM and Accomplish at 11:15 AM while offline. | The sync engine groups actions by `ticketId` and **awaits** completion of `/start` before firing `/accomplish`. Out-of-order execution is mathematically impossible. |
| **Device Reboot / OS App Kill** | Device powers down while lineman is in remote mountains. | Because queue records are in Isar/SQLite and media files are in `getApplicationDocumentsDirectory()`, all data survives reboots. The worker resumes on next app launch. |
| **422 Unprocessable Content** | A validation rule fails on backend (e.g. invalid photo format). | The action status changes to `failed` and logs `lastErrorMessage`. Subsequent tickets continue syncing without stalling the queue. |
| **Clock Skew between Device and Server** | Lineman's phone clock is 2 minutes fast or slow. | Backend allows up to **+5 minutes in the future**. The sync engine sends UTC ISO-8601 strings (`.toUtc().toIso8601String()`). |

---

## 8. AI Agent Prompt Library (Ready-to-Run Prompts)

If your mobile developers are using AI coding assistants (Cursor, Claude Code, Antigravity, Copilot), copy and paste these modular prompts directly into the assistant:

### Prompt A: Generating Data Layer & Media Persistence
```markdown
Read the specification in `docs/dev/FLUTTER_OFFLINE_SYNC_IMPLEMENTATION_SPEC.md`.
Please implement the following files in the Flutter project:
1. `lib/features/tickets/data/models/offline_action.dart`:
   - An Isar collection entity with fields: id, idempotencyKey, ticketId, actionType, occurredAt, queuedAt, status, retryCount, lastErrorMessage, remarks, consumerName, localSignaturePath, localPhotoPaths, syncedAt.
2. `lib/core/utils/file_storage_helper.dart`:
   - Static methods `persistOfflineMedia(File, String)` to copy files into getApplicationDocumentsDirectory()/offline_media/.
   - Static method `cleanupMedia(List<String>)` to safely delete uploaded local cache files.
3. `lib/features/tickets/data/repositories/offline_outbox_repository.dart`:
   - Enqueue methods for start and accomplish actions with UUIDv4 generation.
   - Query method returning unsynced actions sorted chronologically by occurredAt.
Ensure all imports match modern Flutter 3.x standards and null-safety.
```

### Prompt B: Implementing the FIFO Sync Worker
```markdown
Read the specification in `docs/dev/FLUTTER_OFFLINE_SYNC_IMPLEMENTATION_SPEC.md` Section 5.
Create `lib/features/tickets/domain/services/ticket_sync_service.dart`.
Requirements:
1. Connects to `internet_connection_checker_plus` stream to trigger `syncPendingOutbox()` on reconnect.
2. Fetches pending actions from `OfflineOutboxRepository`.
3. Groups actions by `ticketId` and processes them in strict chronological FIFO order.
4. For ticket start: sends PATCH `/api/tickets/{ticketId}/start` with headers `X-Idempotency-Key` and `X-Client-Timestamp` (UTC ISO-8601).
5. For ticket accomplish: sends POST `/api/tickets/{ticketId}/accomplish` as multipart form-data including signature and photos.
6. Handles DioException: if 422, marks action as failed; if network error, leaves pending for retry.
7. Upon successful sync, calls `FileStorageHelper.cleanupMedia()` to free device disk space.
```

### Prompt C: Optimistic UI & Controller Integration
```markdown
Update the ticket detail screen and its state controller to integrate with `OfflineOutboxRepository`.
When the user taps "Start Ticket":
1. Check if device has internet. If offline, call `outboxRepository.queueStartAction(ticketId)` and optimistically update state to in_progress.
2. If online, invoke the API directly or let the outbox handle it with immediate drain.
When the user submits accomplishment:
1. Copy signature image and photos to permanent storage using `FileStorageHelper.persistOfflineMedia()`.
2. Enqueue via `outboxRepository.queueAccomplishAction()`.
3. Optimistically mark ticket as resolved and navigate back to list.
4. Render `SyncStatusBadge` on the ticket card showing whether it is Synced, Syncing, or Queued Offline.
```

---

## 9. Mobile QA & Verification Scenarios

### Test 1: Full Offline Journey (Flight Mode)
1. Turn on **Airplane Mode** on the mobile phone.
2. Open ticket in `assigned` status.
3. Tap **"Start Work"**. Verify:
   - UI instantly reflects **"In Progress"**.
   - Badge shows **"Offline (Queued)"**.
4. Open Accomplishment form. Enter remarks, draw signature on canvas, pick 2 photos from camera.
5. Tap **"Submit Accomplishment"**. Verify:
   - UI returns to ticket list.
   - Ticket shows **"Resolved (Pending Sync)"**.
6. Turn off **Airplane Mode** (re-enable Wi-Fi / LTE).
7. Observe logs:
   - Worker triggers `syncPendingOutbox()`.
   - Sends `PATCH /api/tickets/{id}/start` &rarr; Returns `200 OK`.
   - Sends `POST /api/tickets/{id}/accomplish` &rarr; Returns `201 Created`.
   - Badges update to **"Synced"**.
8. Inspect device storage: Verify cached photos in `/offline_media` are safely cleaned up.

### Test 2: Network Flapping & Replay Safety
1. Turn on Airplane Mode. Submit an offline start action.
2. Turn Wi-Fi on, and immediately turn it off during the HTTP request.
3. Observe: Request might time out or succeed before drop.
4. Re-enable Wi-Fi.
5. Verify: The app sends the request again with the **identical `X-Idempotency-Key`**.
6. Verify: Backend returns `200 OK` (either fresh or from idempotency cache). No `422 "Already in progress"` error occurs!

---

*This document is the canonical reference specification for the PALECO Mobile Division and should be committed directly to version control.*

