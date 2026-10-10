# PALECO OTRS-CRM Backend Comprehensive Audit & Architectural Improvement Plan

**Date:** October 7, 2026  
**Scope:** Backend Architecture (`app/` directory), Query Optimizations, Concurrency/State Integrity, DRY Activity Logging, and Directory Restructuring  
**Author:** Antigravity Engineering Review  
**Status:** Audit & Proposal (No Application Code Modified)

---

## 1. Executive Summary

A comprehensive architectural and algorithmic audit was performed across all classes, queries, conditional branches, and structural layers in the `PALECO_OTRS-CRM/app` directory. 

While the recent refactoring achieved high cosmetic uniformity and clean PSR-12 formatting, deep analysis reveals **critical algorithmic bugs, significant database query bottlenecks, artificial layer fragmentation, and pervasive DRY violations** that must be resolved prior to scaling or production deployment.

### Key Audit Findings at a Glance:
1. **Critical Activity Log Bug (`Ticket.php`):** The activity logging description callback checks `$this->isDirty('team_id')` and `$this->isDirty('status')` inside the `updated` event. Because Eloquent has already synced attributes upon saving, `$this->isDirty()` is *always* false, causing ticket audit descriptions to permanently fail and fall back to generic strings.
2. **Sequential Number Collision (`TicketService.php`):** `generateSequentialNumber()` does not filter out child tickets. Child ticket numbers (e.g., `TKT-261007-001-1`) match the prefix search and cause string truncation errors (`0-1` evaluates to integer `0`), risking unique constraint crashes on new ticket submissions.
3. **Severe Query Bottlenecks & Memory Leaks:**
   - Sequential count loops (e.g., 8 individual count queries on mobile ticket indexing, 10 count queries on web dashboard, 7 separate daily queries inside a `range(6, 0)` loop).
   - High-memory queries: `Ticket::toBase()->pluck('status')->countBy()` and `Consumer::tickets()->pluck('complaint_source')->countBy()` load thousands of records into PHP memory to perform counts that should be executed via SQL `GROUP BY`.
   - Missing critical database indexes on `tickets.status`, `tickets.reported_at`, and missing composite indexes for role-based scoping (`[department_id, status]`, `[team_id, status]`).
4. **Endorsed Parent Ticket Lifecycle Deadlock:** Parent tickets whose endorsement is approved enter `status = endorsed`. The system provides no operational mechanism (manual or automated) to ever transition an endorsed ticket to `resolved` or `closed`, leaving parent tickets perpetually open.
5. **Missing Audit Trails & Concurrency Locks in Mobile API:** Mobile ticket actions (`start`, `accomplish`, `verify`, `assign`) lack pessimistic row locks (`lockForUpdate()`), creating race conditions. Furthermore, `requestEndorsement()` updates ticket status to `pending_endorsement` but fails to write a corresponding `TicketStatusLog` milestone.
6. **Violations of DRY and Overly Deep Directory Tree:**
   - Model activity logging is duplicated across 5 models with boilerplate `getActivitylogOptions()` and match expressions.
   - Authentication rate-limiting and database lockout algorithms are duplicated word-for-word between `AuthService` and `MobileAuthService`.
   - Services are artificially separated by HTTP transport (`Services/Web` vs `Services/Api`) instead of domain boundaries, resulting in parallel classes (`TeamService`, `TicketService`, `TicketRemarkService`, `DashboardService`) with redundant logic.
   - Folder nesting reaches up to 6 directory levels deep (e.g., `app/Http/Requests/Web/Admin/Department/StoreDepartmentRequest.php`).

---

## 2. In-Depth Query & Performance Optimization Review

### 2.1. Memory Exhaustion via `pluck()->countBy()`
- **Location:** `app/Services/Web/Dashboard/DashboardService.php` (line 26), `app/Services/Web/Cwd/ConsumerService.php` (line 47).
- **The Issue:**
  ```php
  // Problematic Code in DashboardService:
  $statusCounts = Ticket::toBase()->pluck('status')->countBy();
  ```
  `Ticket::toBase()->pluck('status')` fetches the status value of **every single ticket in the database** into PHP memory, hydrates an array, creates a Laravel collection, and groups them in PHP memory. At 50,000 tickets, this wastes megabytes of memory and adds noticeable CPU latency to the dashboard.
- **The Optimization:** Replace with native SQL grouping:
  ```sql
  SELECT status, COUNT(*) as total FROM tickets GROUP BY status;
  ```
  In Eloquent: `Ticket::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');`.

### 2.2. N-Queries inside Loops (Trend Analytics)
- **Location:** `app/Services/Web/Dashboard/DashboardService.php` (lines 34–42).
- **The Issue:**
  A 7-day trend is compiled by iterating over a `range(6, 0)` loop and executing a separate `Ticket::query()->whereBetween(...)->count()` on every iteration.
- **The Optimization:**
  Execute a single query grouped by date over the past 7 days:
  ```sql
  SELECT DATE(created_at) as date, COUNT(*) as total 
  FROM tickets 
  WHERE created_at >= NOW() - INTERVAL 7 DAY 
  GROUP BY DATE(created_at);
  ```
  Then merge the results against the 7-day date range in PHP.

### 2.3. Sequential Count Queries on Every API Index Request
- **Location:** `app/Services/Api/Tickets/TicketService.php` (lines 57–78).
- **The Issue:**
  Whenever a mobile supervisor or field personnel views their inbox (`GET /api/tickets`), `getTicketStatusCount()` is executed:
  ```php
  return [
      'all' => (clone $baseQuery)->count(),
      'open' => (clone $baseQuery)->where('status', TicketStatus::OPEN)->count(),
      'assigned' => (clone $baseQuery)->where('status', TicketStatus::ASSIGNED)->count(),
      'in_progress' => (clone $baseQuery)->where('status', TicketStatus::IN_PROGRESS)->count(),
      'resolved' => (clone $baseQuery)->where('status', TicketStatus::RESOLVED)->count(),
      'closed' => (clone $baseQuery)->where('status', TicketStatus::CLOSED)->count(),
      'pending_endorsement' => (clone $baseQuery)->where('status', TicketStatus::PENDING_ENDORSEMENT)->count(),
      'endorsed' => (clone $baseQuery)->where('status', TicketStatus::ENDORSED)->count(),
  ];
  ```
  This fires **8 separate queries** on the `tickets` table on every pagination request or search.
- **The Optimization:**
  Consolidate into a single conditional aggregation query:
  ```sql
  SELECT 
      COUNT(*) as total_all,
      SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_count,
      SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as assigned_count,
      ...
  FROM tickets WHERE department_id = ?;
  ```
  This reduces database roundtrips by 87.5% per request.

### 2.4. Missing Database Indexing Strategy
- **Location:** `database/migrations/2026_09_24_135916_create_ticket_module_tables.php`.
- **The Issue:**
  The `tickets` table only has indexes on `system_id` (primary ULID), `ticket_number` (unique), and foreign keys (`category_id`, `department_id`, `created_by`).
  - `status` has **NO index**.
  - `reported_at`, `created_at`, and `closed_at` have **NO indexes**.
  - Composite access paths like `(department_id, status)` and `(team_id, status)` have **NO indexes**.
- **Impact:**
  Every time a supervisor views their inbox, the database performs a full table scan or inefficient index merge.
- **Recommendation:**
  Add targeted composite indexes:
  - `CREATE INDEX idx_tickets_dept_status ON tickets(department_id, status);`
  - `CREATE INDEX idx_tickets_team_status ON tickets(team_id, status);`
  - `CREATE INDEX idx_tickets_status_reported ON tickets(status, reported_at);`

---

## 3. Algorithmic, Concurrency & Business Logic Oversights

### 3.1. The `$this->isDirty()` Post-Save Bug in `Ticket.php`
- **Location:** `app/Models/Ticket.php` (lines 348, 355).
- **The Logic Oversight:**
  ```php
  // Inside getActivitylogOptions():
  ->setDescriptionForEvent(function (string $eventName) {
      if ($eventName === 'updated' && $this->isDirty('team_id')) { ... }
      if ($eventName === 'updated' && $this->isDirty('status')) { ... }
  ```
- **Why It Fails:**
  In Eloquent, when model events such as `saved` or `updated` fire, the model has already been written to the database and `syncChanges()` has executed. Therefore:
  - `$this->isDirty()` returns `false` for every attribute!
  - Eloquent moves modified attributes into `$this->changes`.
  - The correct method to check in post-save hooks is `$this->wasChanged('team_id')` and `$this->wasChanged('status')`.
- **Resulting Behavior:**
  Custom audit messages for ticket assignment, endorsement approval, work start, and completion *never fire*. The logic always drops down to the `default` fallback: `"Ticket {number} has been modified."`.

### 3.2. Child Ticket Contamination in `generateSequentialNumber()`
- **Location:** `app/Services/Web/Cwd/TicketService.php` (lines 395–412).
- **The Logic Oversight:**
  ```php
  private function generateSequentialNumber(): string
  {
      $dateCode = now()->format('ymd');
      $latestMatch = Ticket::where('ticket_number', 'like', "TKT-{$dateCode}-%")
          ->lockForUpdate()
          ->orderBy('ticket_number', 'desc')
          ->first();

      $nextSequence = 1;
      if ($latestMatch) {
          $lastAssignedDigits = (int) substr($latestMatch->ticket_number, -3);
          $nextSequence = $lastAssignedDigits + 1;
      }

      return sprintf('TKT-%s-%03d', $dateCode, $nextSequence);
  }
  ```
- **Why It Fails:**
  1. **Matches Child Tickets:** Child tickets share the format `TKT-261007-005-1`. Because the query searches `like "TKT-{$dateCode}-%"`, if a child ticket was created on that date, `$latestMatch` can match `TKT-261007-005-1`.
  2. **Substring Corruption:** `substr('TKT-261007-005-1', -3)` returns `5-1`. In PHP, `(int) '5-1'` evaluates to `5`. If ticket `005` had children, new tickets will attempt to create `TKT-261007-006` even if root tickets up to `020` already existed.
  3. **Lack of Root Filter:** The query must explicitly filter `whereNull('parent_ticket_id')`.
  4. **String-Sort Inversion:** `orderBy('ticket_number', 'desc')` sorts lexicographically. When ticket count exceeds 999 (`TKT-261007-1000`), `'TKT-261007-999'` sorts *after* `'TKT-261007-1000'`, corrupting the sequence.

### 3.3. Missing Concurrency Locking in Mobile API Operations
- **Location:** `app/Services/Api/Tickets/TicketService.php` (`assignTicket`, `startTicket`, `accomplishTicket`, `verifyAccomplishment`, `requestEndorsement`).
- **The Logic Oversight:**
  While Web CWD operations use `Ticket::where('system_id', ...)->lockForUpdate()->first()`, the Mobile API service operates on route-model bound `$ticket` instances without acquiring an exclusive row lock (`lockForUpdate()`).
- **Concurrency Risk:**
  If two field personnel on a team attempt to tap "Start Ticket" or submit accomplishment photos simultaneously on spotty cellular connections, both requests pass validation before either updates the database, leading to duplicate status logs and race conditions.

### 3.4. Missing Status Log Milestone in `requestEndorsement()`
- **Location:** `app/Services/Api/Tickets/TicketService.php` (lines 430–444).
- **The Logic Oversight:**
  When a supervisor submits an endorsement request, `$ticket->update(['status' => TicketStatus::PENDING_ENDORSEMENT])` is executed, but **no `TicketStatusLog` record is created**.
  In contrast, ticket assignment, work commencement, resolution, and closure all record a `TicketStatusLog`. This causes gaps in ticket history timelines.

### 3.5. Lifecycle Deadlock: Endorsed Parent Tickets Cannot Be Closed
- **The Logic Oversight:**
  When an endorsement is approved:
  1. The parent ticket is updated to `status = endorsed`.
  2. A child ticket is spawned for the destination department in `status = open`.
  3. The child ticket proceeds through `assigned -> in_progress -> resolved -> closed`.
- **The Defect:**
  There is no workflow in the system that allows an `endorsed` parent ticket to reach `closed`. 
  - `accomplishTicket()` only accepts tickets in `in_progress`.
  - `verifyAccomplishment()` only accepts tickets in `resolved`.
  - `assignTicket()` blocks tickets that are `endorsed`.
- **Recommended Architectural Solution:**
  Introduce an **Automated Hierarchy Completion Hook** or **Administrative Force-Closure Workflow**:
  - *Option A (Automated):* When the last remaining child ticket of a parent is verified and closed, evaluate whether all sibling child tickets are closed. If so, automatically cascade the parent ticket status to `closed`, logging `"Parent ticket automatically closed upon completion of all endorsed child tasks."`.
  - *Option B (Administrative Closure):* Add a dedicated CWD / Admin action to forcefully close endorsed parent tickets once reviewing department reports are finalized.

### 3.6. External Consumer Synchronization Staleness
- **Location:** `app/Services/External/ConsumerService.php` (`resolveConsumerId`, `verifyAccount`).
- **The Logic Oversight:**
  ```php
  $consumer = Consumer::where('acct_code', $accountCode)->first();
  if ($consumer) {
      return $consumer->id; // or toArray()
  }
  ```
  Once a consumer record is saved locally, future ticket submissions never query the PALECO billing API again. If the consumer changes their meter serial number, updates their billing name, or changes service status, the CRM database retains stale information indefinitely.
- **Recommended Solution:**
  Add a cache-expiration or daily freshness check (e.g. re-sync if `updated_at < now()->subDays(7)`).

### 3.7. GET Request Payload Pollution & Unvalidated Filter Scopes
- **Location:** `app/Http/Controllers/Api/Tickets/TicketController.php` (line 39) and `app/Models/Ticket.php` (scope `apiFilterByStatus`).
- **The Issue:**
  1. **Payload Body Leakage:** The controller extracts search and filter arguments using:
     ```php
     $request->only(['search', 'category', 'status', 'sort'])
     ```
     In Laravel, `$request->only()` reads from the merged input bag, which includes JSON request bodies in addition to URL query parameters. If an API client (like Postman or a mobile HTTP client) retains a leftover JSON body on a `GET` request (e.g. `{"status": "approved"}`), Laravel injects it into the filter array.
  2. **Unvalidated Filter Enums in Model Scopes:**
     `scopeApiFilterByStatus` directly accepts arbitrary strings and appends `WHERE status = ?` to the query without asserting that the requested status is a valid case of `TicketStatus`. Because `'approved'` is not a valid ticket status, the query executes `WHERE status = 'approved'` and matches 0 rows, while the independent `getTicketStatusCount()` correctly reports existing tickets.
- **The Fix:**
  1. Scope `GET` controllers strictly to query parameters: `$request->query('status')` (or `$request->only()` sourced strictly from `$request->query`).
  2. Implement a dedicated `GetTicketsRequest` form request (or validate in the controller) using `Rule::enum(TicketStatus::class)` so invalid statuses reject cleanly with 422 Unprocessable Entity or are ignored, rather than silently producing an empty dataset.

---

## 4. Non-DRY Concepts & Model-Based Activity Logging Refactoring

### 4.1. Audit of Current Activity Logging Duplication
Currently, 5 models (`Department`, `Team`, `Ticket`, `TicketCategory`, `User`) implement Spatie Activitylog by defining identical boilerplates:
- Every model manually calls `LogOptions::defaults()`.
- Every model defines `->logOnlyDirty()` and `->dontSubmitEmptyLogs()`.
- Every model duplicates soft-delete action mapping:
  ```php
  $action = match ($eventName) {
      'deleted' => $this->isForceDeleting() ? 'permanently deleted' : 'archived',
      'restored' => 'restored',
      default => $eventName,
  };
  ```
- Every model constructs a string description using the same sentence pattern.

### 4.2. Blueprint: Reusable `LogsModelActivity` Trait
Extract all activity log configuration into a reusable trait: `app/Traits/LogsModelActivity.php` (or `app/Concerns/Auditable.php`).

#### Trait Design:
1. **Convention over Configuration:**
   - Log name automatically defaults to `class_basename($this)` (e.g., `Department`, `Team`, `TicketCategory`).
   - If a model needs a custom log name, it overrides `public function getActivityLogName(): string`.
2. **Standardized Attributes:**
   - The model simply declares an array property or method: `protected array $activityLogAttributes = ['name', 'description'];`.
3. **Unified Event Descriptions:**
   - Handles `created`, `updated`, `deleted` (with `isForceDeleting()` distinction), and `restored`.
   - Uses a designated title attribute (e.g., `$this->getActivityTitleAttribute()` which checks `ticket_number`, `team_name`, `dept_name`, `username`, or `name`).
4. **Custom Event Hooks:**
   - For complex models like `Ticket` or `User`, provide a hook method `protected function getCustomEventDescription(string $eventName): ?string`.
   - If the hook returns `null`, the trait's uniform description takes over.

This trait eliminates approximately **150+ lines of duplicated code** across models, guarantees uniform descriptions across the entire audit log, and fixes the post-save `$this->isDirty()` defect in one central place.

---

## 5. Architectural App Folder Tree Restructuring

### 5.1. Current Structural Pain Points
1. **Excessive Vertical Nesting:**
   - `app/Http/Requests/Web/Admin/Department/StoreDepartmentRequest.php` (6 directory levels for 2 files).
   - `app/Http/Requests/Web/Cwd/TicketEndorsement/EndorsementDecisionRequest.php` (6 directory levels).
2. **Artificial Transport Splitting of Domain Services:**
   - Services represent core business domain logic, not HTTP interfaces.
   - Splitting services into `app/Services/Web/` and `app/Services/Api/` has caused severe duplication:
     - `Web/Admin/TeamService.php` vs `Api/Teams/TeamService.php` (both implement member syncing and optimistic concurrency).
     - `Web/Remarks/TicketRemarkService.php` vs `Api/Remarks/TicketRemarkService.php` (both implement ticket remark creation).
     - `Web/Dashboard/DashboardService.php` vs `Api/Dashboard/DashboardService.php` (both compute identical ticket metrics).
     - `Web/Auth/AuthService.php` vs `Api/Auth/MobileAuthService.php` (both implement identical IP rate limiting and database lockout handlers).

### 5.2. Proposed Clean Directory Structure

```
app/
├── Concerns/                          # Reusable traits & cross-cutting behavior
│   ├── Auditable.php                  # Centralized Spatie activity logging
│   ├── HandlesOptimisticLock.php      # Concurrency version validation
│   └── HasSequentialNumber.php        # Collision-safe sequence generator
├── Enums/                             # Strictly-typed string-backed enums
├── Events/                            # Application domain events
├── Http/
│   ├── Controllers/
│   │   ├── Api/                       # Thin JSON controllers (Auth, Dashboard, Teams, Tickets)
│   │   └── Web/                       # Thin Blade controllers (Admin, Auth, Cwd)
│   ├── Middleware/                    # HTTP middleware
│   ├── Requests/
│   │   ├── Admin/                     # Department, Team, Category, User requests (flattened)
│   │   ├── Api/                       # Mobile-specific validation requests
│   │   └── Cwd/                       # Ticket intake, child tickets, endorsements
│   └── Resources/                     # Mobile API JSON resources
├── Listeners/                         # Event listeners
├── Livewire/                          # Livewire components
├── Models/                            # Eloquent models (clean, lean, leveraging Concerns)
├── Policies/                          # Multi-line documented authorization policies
├── Providers/                         # Service providers
└── Services/                          # UNIFIED DOMAIN SERVICES (Independent of Web/API)
    ├── Auth/
    │   ├── AuthService.php            # Unified core auth (rate limits, lockouts, validation)
    │   └── MobileAuthService.php      # Mobile token issuance extending/using AuthService
    ├── Consumers/
    │   ├── ConsumerService.php        # Local consumer querying & analytics
    │   └── PalecoBillingClient.php    # External API client (renamed from External/ConsumerService)
    ├── Dashboard/
    │   └── DashboardMetricsService.php# Unified metric aggregations for Web & Mobile
    ├── Departments/
    │   └── DepartmentService.php      # Department CRUD and membership
    ├── Teams/
    │   └── TeamService.php            # Unified team management, roster syncing, assignments
    ├── Tickets/
    │   ├── TicketService.php          # Unified ticket lifecycle (intake, assign, start, accomplish)
    │   ├── TicketEndorsementService.php# Endorsement review & child ticket routing
    │   └── TicketRemarkService.php    # Unified internal/public remark processing
    └── Users/
        └── UserService.php            # User management and profile workflows
```

### 5.3. Benefits of the Restructured Tree
- **DRY Services:** Domain operations (e.g., assigning a team, starting a ticket, creating a remark) reside in a single class used by both Web and API controllers.
- **Flattened Requests:** Eliminates 3 layers of unnecessary folders under `Http/Requests/`.
- **Eliminated Namespace Aliasing:** Renaming `Services/External/ConsumerService` to `Services/Consumers/PalecoBillingClient` removes the confusing naming collision with `Web/Cwd/ConsumerService`.

---

## 6. Actionable Implementation Roadmap

### Phase 1: High-Priority Fixes (Critical Bugs & Data Integrity)
1. **Fix `Ticket.php` Activity Log Callback:** Change `$this->isDirty()` to `$this->wasChanged()` to immediately restore ticket audit trails.
2. **Fix `generateSequentialNumber()`:** Add `->whereNull('parent_ticket_id')` and fix integer sequence parsing to eliminate collision risks.
3. **Add Missing Row Lock in Mobile API:** Introduce `lockForUpdate()` across API ticket mutations (`startTicket`, `accomplishTicket`, `verifyAccomplishment`, `assignTicket`).
4. **Log Endorsement Transition:** Add `TicketStatusLog` entry inside `requestEndorsement()`.
5. **Scope `GET` Endpoints Strictly to Query Parameters:** Update `TicketController::index` to use `$request->query(...)` and validate filters with `Rule::enum(TicketStatus::class)` to prevent stray client payload bodies from breaking queries.

### Phase 2: Query Optimization & Indexing
1. **Add Migration for Database Indexes:** Add indexes on `tickets.status`, `tickets.reported_at`, `tickets.created_at`, `(department_id, status)`, and `(team_id, status)`.
2. **Optimize Dashboard Metrics:** Refactor `DashboardService` and `ConsumerService` to replace `pluck()->countBy()` with SQL `GROUP BY` queries.
3. **Consolidate Status Counts:** Replace 8 sequential `count()` queries in `Api\Tickets\TicketService` with a single conditional aggregation query.

### Phase 3: DRY Refactoring & Concerns Extraction
1. **Extract `Auditable` Trait:** Create `app/Concerns/Auditable.php` and apply it across `Department`, `Team`, `Ticket`, `TicketCategory`, and `User`.
2. **Extract Authentication Helpers:** Consolidate `handleDatabaseLockout` and `handleRateLimitExceeded` into a shared authentication trait or base service.

### Phase 4: Domain Service Consolidation & Folder Restructuring
1. **Unify Redundant Services:** Merge `Web\Admin\TeamService` with `Api\Teams\TeamService`, and merge `Web\Remarks\TicketRemarkService` with `Api\Remarks\TicketRemarkService`.
2. **Flatten Request Directories:** Reorganize `app/Http/Requests` into streamlined feature folders.
3. **Resolve Parent Ticket Lifecycle:** Implement automated parent closure upon child ticket completion or provide a formal closure workflow.

