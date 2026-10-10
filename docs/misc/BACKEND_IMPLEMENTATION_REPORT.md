# PALECO OTRS-CRM Backend Optimization & Complete Refactor Implementation Report

**Date:** October 7, 2026  
**Scope:** Complete Backend Layer Implementation (Audit Remediation, Domain-Driven Folder Restructuring, Query Optimizations, DRY Traits, Concurrency Integrity, Route Model Binding Fixes, Seeders & Performance Migration)  
**Status:** Complete — Ready for Review (Uncommitted Working Tree)

---

## 1. Executive Summary

In response to the comprehensive backend audit and architectural requirements, all recommended architectural fixes, domain-driven directory reorganizations, query optimizations, DRY trait extractions, concurrency row locks, route model binding enhancements, and developer database seeds have been successfully implemented.

Per explicit project instructions:
- **No git commits were made** (all modifications remain unstaged in the working tree for single-commit developer review).
- **No migrations were executed** (`php artisan migrate` was not run; files are prepared and waiting for developer execution).
- **No automated tests were written, edited, or executed**.
- **The force-closure feature was deliberately excluded** to allow dedicated design time.
- **Frontend remains Blade + Tailwind** with zero extraneous SPA framework dependencies.

---

## 2. Domain-Driven Architecture Restructuring

### 2.1. Why the Restructure Was Necessary
Previously, backend services and form requests were artificially bifurcated into separate `Web/` and `Api/` directory silos (e.g., `app/Services/Web/Admin/TeamService.php` vs. `app/Services/Api/Teams/TeamService.php`). This caused:
1. **Severe Code Duplication:** `TeamService`, `TicketRemarkService`, `StoreTeamRequest`, `UpdateTeamRequest`, and `StoreTicketRemarkRequest` existed twice with nearly identical validation rules and business logic.
2. **Maintenance Drag:** Bug fixes applied to Web admin endpoints were frequently omitted from Mobile API endpoints.
3. **Deep, Inconsistent Nesting:** Form requests were deeply nested under `Requests/Web/Admin/Department/` while services were under `Services/Web/Admin/`.

### 2.2. Restructured Directory Layout

#### Services Layer (`app/Services/`)
Consolidated from channel-based (`Web/`, `Api/`) to **Domain-Driven Contexts**:
```
app/Services/
├── ActivityLogs/
│   └── ActivityLogService.php            (Admin polymorphic audit viewer with eager loading)
├── Auth/
│   ├── AuthService.php                   (Web session authentication)
│   └── MobileAuthService.php             (Mobile Sanctum token authentication)
├── Categories/
│   └── TicketCategoryService.php         (Ticket category CRUD & cache handling)
├── Consumers/
│   └── ConsumerService.php               (CWD consumer profiles & query aggregations)
├── Dashboard/
│   ├── ApiDashboardService.php           (Mobile supervisor dashboard metrics)
│   └── WebDashboardService.php           (CWD & Admin web dashboard metrics & single-query trends)
├── Departments/
│   └── DepartmentService.php             (Department CRUD & relational integrity)
├── External/
│   └── ConsumerService.php               (PALECO Billing API integration with 7-day cache & fallback)
├── Teams/
│   └── TeamService.php                   (Unified single service for Web Admin & Mobile API)
├── Tickets/
│   ├── TicketAccomplishmentService.php   (Field accomplishment reports & supervisor verification)
│   ├── TicketEndorsementService.php      (Department-to-department transfers & supervisor decisions)
│   ├── TicketRemarkService.php           (Unified single service for Web & Mobile remark timeline)
│   └── TicketService.php                 (Unified core service: CWD intake, child spawning, sequence retry, mobile inbox, & lifecycle locking)
└── Users/
    ├── ProfileService.php                (Mobile user profile management)
    └── UserService.php                   (Admin staff user CRUD & role assignments)
```

#### Form Requests Layer (`app/Http/Requests/`)
Flattened from channel-specific nesting to unified domain directories:
```
app/Http/Requests/
├── Auth/
│   ├── LoginRequest.php                  (Web auth validation)
│   └── MobileLoginRequest.php            (Mobile auth validation)
├── Categories/
│   ├── StoreTicketCategoryRequest.php
│   └── UpdateTicketCategoryRequest.php
├── Departments/
│   ├── StoreDepartmentRequest.php
│   └── UpdateDepartmentRequest.php
├── Teams/
│   ├── StoreTeamRequest.php              (Unified across Web Admin & Mobile API)
│   └── UpdateTeamRequest.php             (Unified across Web Admin & Mobile API)
├── Tickets/
│   ├── AssignTicketRequest.php           (Supervisor team assignment)
│   ├── EndorsementDecisionRequest.php    (Accept/Reject endorsement decision)
│   ├── StoreChildTicketRequest.php       (CWD child ticket creation)
│   ├── StoreEndorsementRequest.php       (Supervisor endorsement initiation)
│   ├── StoreTicketRemarkRequest.php      (Unified across Web & Mobile remarks)
│   ├── StoreTicketRequest.php            (CWD root ticket creation)
│   ├── SubmitAccomplishmentReportRequest.php (Field crew completion submission)
│   └── VerifyAccomplishmentRequest.php   (Supervisor verification decision)
└── Users/
    ├── StoreUserRequest.php
    └── UpdateUserRequest.php
```

#### Controller & Component Namespace Alignment
All **23 Web and API Controllers** and the Livewire component `DashboardOverview.php` were updated to inject these new domain-driven services and unified form requests. All legacy folders (`app/Services/Web`, `app/Services/Api`, `app/Http/Requests/Web`, `app/Http/Requests/Api`) were cleanly removed.

---

## 3. Centralized DRY Concerns & Security Architecture

| Trait File | Namespace | Purpose & Architectural Impact |
| :--- | :--- | :--- |
| **`Auditable.php`** | `App\Concerns\Auditable` | **Centralized Spatie Activity Logging Trait:** Replaced over 150 lines of duplicate configuration across `Department`, `Team`, `Ticket`, `TicketCategory`, and `User`. Configures `useLogName`, `logOnlyDirty()`, and `dontLogEmptyChanges()`. Distinguishes between permanent deletion and soft-delete archiving, standardizes entity titles, and provides a customizable `getCustomActivityDescription()` hook. |
| **`HandlesAuthSecurity.php`** | `App\Concerns\HandlesAuthSecurity` | **Shared Authentication & Security Trait:** Consolidates identical database lockout (`handleDatabaseLockout()`) and rate-limiting enforcement (`handleRateLimitExceeded()`) previously duplicated between `AuthService` and `MobileAuthService`. |

---

## 4. Comprehensive Bug Remediations & Oversights Resolved

### Fix 1: Post-Save Activity Log Milestone Detection (`app/Models/Ticket.php`)
* **Problem:** `Ticket.php` attempted to check `$this->isDirty('team_id')` and `$this->isDirty('status')` inside an Eloquent `saved` / `updated` observer. Because Eloquent executes `syncChanges()` before firing `updated`, `isDirty()` always returned `false`, silently suppressing custom audit logs for ticket assignments, starts, completions, and closures.
* **Resolution:** Replaced `$this->isDirty(...)` with `$this->wasChanged(...)`. Custom activity log entries now generate reliably on every state change.

### Fix 2: Ticket Status Filtering Scoping & Robust Enum Validation (`app/Models/Ticket.php`)
* **Problem:** Passing residual HTTP request parameters (such as `{"status": "approved"}` from Postman tests) or filter query string `'all'` into `scopeApiFilterByStatus` resulted in invalid SQL queries (`WHERE status = 'approved'`), returning empty ticket lists.
* **Resolution:** Updated `scopeApiFilterByStatus` to ignore `'all'` and strictly validate candidate statuses against `TicketStatus::tryFrom($status)`. Non-matching status strings are safely discarded without corrupting SQL clauses.

### Fix 3: Concurrency Safeguard in Endorsement Decisions (`app/Services/Tickets/TicketEndorsementService.php`)
* **Problem:** Concurrent review of the same pending endorsement by multiple CWD officers could result in duplicate state transitions and double child ticket creation.
* **Resolution:** Replaced fragile optimistic checks with pessimistic database locking (`TicketEndorsement::lockForUpdate()`) and atomic status verification (`status !== PENDING`), guaranteeing that only the first committed decision succeeds while subsequent attempts are gracefully rejected.

### Fix 4: Sequential Ticket Number Collision & Child Ticket Isolation (`app/Services/Tickets/TicketService.php`)
* **Problem:** 
  1. Root ticket sequence numbers were calculated using string operations that could match child tickets (e.g., `TKT-261007-005-1`), causing substring arithmetic errors.
  2. High-concurrency ticket submissions could attempt duplicate sequential numbers within the same millisecond.
* **Resolution:** 
  1. Added `->whereNull('parent_ticket_id')` to sequence queries so child tickets never contaminate the counter.
  2. Replaced fragile substring offsets with `explode('-', $ticketNumber)` and `(int) end($parts) + 1`.
  3. Wrapped sequence generation in a database transaction retry loop (up to 3 attempts with random exponential jitter) catching `QueryException` unique key violations (MySQL error 1062).

### Fix 5: Unclosed Team Assignments on Endorsement & Closure (`app/Services/Tickets/`)
* **Problem:** When tickets were endorsed to another department or resolved/closed, the existing record in `ticket_assignments` remained with `unassigned_at = null`, creating ghost active assignments in historical records.
* **Resolution:** Updated `TicketEndorsementService` and `TicketAccomplishmentService` to find any active assignment where `unassigned_at IS NULL` and set `unassigned_at = now()`.

### Fix 6: Broadcast Event Null-Safety & Display Payload (`app/Events/TicketCreated.php`)
* **Problem:** `TicketCreated` broadcast event directly referenced `$this->ticket->department->slug`. In automated seeding, console commands, or unassigned ticket scenarios, this threw a fatal PHP `TypeError: null->slug`. Additionally, the event lacked an explicit `broadcastWith()` method.
* **Resolution:** 
  1. Applied the PHP 8 nullsafe operator: `$this->ticket->department?->slug ?? 'cwd'`.
  2. Implemented `broadcastWith()` returning essential payload fields (`ticket_number`, `title`, `priority`, `department_id`, `created_at`) for frontend toast/notification channels.

### Fix 7: Transaction-Safe Deferred Broadcasting (`app/Services/Tickets/TicketService.php`)
* **Problem:** Broadcasting `TicketCreated` directly inside the database transaction meant WebSockets emitted messages to consumers and operators before the transaction committed. If a rollback occurred, clients received notifications for phantom tickets.
* **Resolution:** Deferred broadcasting using Laravel's `DB::afterCommit(fn () => broadcast(new TicketCreated($ticket)))`. Guarantees events only leave the server when the ticket is safely committed.

### Fix 8: IDE Red Squiggly Line Resolution via Route Model Binding (`app/Http/Controllers/Web/Admin/TeamController.php` & `routes/web.php`)
* **Problem:** The IDE displayed a red squiggly error warning on `$id` parameter in `TeamController::restore` and `TeamController::destroy` because the route definition lacked route-model-binding soft-delete resolution.
* **Resolution (Option 1 Applied):** 
  1. Updated `routes/web.php` with `->withTrashed()` on the restore route:
     ```php
     Route::patch('/{team}/restore', [TeamController::class, 'restore'])
         ->name('admin.teams.restore')
         ->whereUlid('team')
         ->withTrashed();
     ```
  2. Refactored `TeamController` signatures to use type-hinted Eloquent model injection:
     ```php
     public function restore(Team $team): RedirectResponse
     public function destroy(Team $team): RedirectResponse
     ```
  3. Provides full IDE static analysis cleanliness, eliminates raw ULID string lookups, and adheres to native Laravel route model binding conventions.

---

## 5. Query Optimizations & Performance Migration

### 5.1. Eloquent & SQL Query Optimizations

| Area | Before Refactor | After Refactor | Performance Gain |
| :--- | :--- | :--- | :--- |
| **Mobile Ticket Status Counts** | 8 sequential `Ticket::where('status', ...)->count()` queries | Single `selectRaw('status, count(*)...')->groupBy('status')` | **87.5% reduction** in SQL queries per mobile inbox load |
| **Pessimistic Concurrency Locking** | Plain `findOrFail()` susceptible to race conditions | `lockForUpdate()->firstOrFail()` across all state transitions | Prevents double-assignment & concurrent endorsement races |
| **Web Dashboard Daily Trends** | Looped `range(6, 0)` executing 7 separate count queries | Single query grouped by `DATE(created_at)` over 7-day interval | **85.7% reduction** in trend graph query count |
| **Web Dashboard Status Breakdown** | Memory-heavy `Ticket::toBase()->pluck('status')->countBy()` | Direct database aggregation `selectRaw('status, count(*)...')->groupBy('status')` | Constant memory overhead regardless of ticket volume |
| **Consumer Source Aggregation** | In-memory `pluck('complaint_source')->countBy()` | Direct database aggregation `selectRaw('complaint_source, count(*)...')->groupBy('complaint_source')` | Prevents PHP memory exhaustion on large consumer datasets |
| **Admin Activity Log Viewer** | Eager-loaded only `causer.role`, triggering N+1 queries on polymorphic subject | Eager-loaded `['causer.role', 'subject']` | Eliminates N+1 queries across all activity log pagination tables |

### 5.2. New Performance Migration (`database/migrations/`)
Created migration `2026_10_07_203000_add_performance_indexes_to_tickets_table.php` adding the following targeted composite and single-column indexes:
1. `idx_tickets_status` on `status`
2. `idx_tickets_reported_at` on `reported_at`
3. `idx_tickets_created_at` on `created_at`
4. `idx_tickets_closed_at` on `closed_at`
5. `idx_tickets_department_status` on `[department_id, status]` (High-frequency filter for supervisor inbox queues)
6. `idx_tickets_team_status` on `[team_id, status]` (High-frequency filter for field crew mobile queries)
7. `idx_tickets_status_reported_at` on `[status, reported_at]` (High-frequency sorting for chronological queue dispatching)

---

## 6. Developer Seeders (`database/seeders/`)

To eliminate repetitive manual database configuration during local development and testing, comprehensive seeders were created:

| Seeder File | Entities Seeded |
| :--- | :--- |
| **`DepartmentSeeder.php`** | Technical Services Department (TSD), Consumer Welfare Department (CWD), Area Operations Department (AOD), Line Construction & Maintenance Department (LCMD). |
| **`TeamSeeder.php`** | Alpha Emergency Response Crew, Bravo Maintenance Crew, Charlie Heavy Construction Unit, Delta Night Dispatch Crew (with realistic shift times linked to departments). |
| **`TicketCategorySeeder.php`** | Power Interruption / Blackout, Fluctuating / Low Voltage, Defective / Damaged Meter, Sparking / Burning Equipment, Leaning / Rotten Electric Post, Line Clearing / Tree Trimming, Reconnection Request, Billing & Account Dispute. |
| **`UserSeeder.php`** | Updated to associate supervisor `mycka` with Department 1 (TSD) and field technician `ralph` with Team 1 (Alpha Crew). |
| **`DatabaseSeeder.php`** | Master seeder orchestrated in correct relational dependency order: `RoleSeeder` &rarr; `TeamRoleSeeder` &rarr; `DepartmentSeeder` &rarr; `TeamSeeder` &rarr; `TicketCategorySeeder` &rarr; `UserSeeder`. |

---

## 7. Developer Runbook: How to Execute Migrations & Seeders

Because migrations were **intentionally not run**, choose one of the two options when ready:

### Option A: Apply Only the Performance Indexes (Preserving Existing Data)
If you have existing test consumers, tickets, and user accounts you wish to keep:
```bash
php artisan migrate
```
*Laravel will detect and run only the new `2026_10_07_203000_add_performance_indexes_to_tickets_table` migration, adding the composite indexes without touching existing records.*

### Option B: Fresh Database Rebuild with Full Seed Dataset
If you prefer a clean slate with all departments, teams, categories, roles, and pre-linked user accounts:
```bash
php artisan migrate:fresh --seed
```

#### Pre-Configured Test Accounts After Seeding:
| Role | Username | Password | Relational Assignment |
| :--- | :--- | :--- | :--- |
| **Admin** | `allenglenn` | `password` | Global System Administrator |
| **CWD Officer** | `alliah` | `password` | Consumer Welfare Department |
| **Supervisor** | `mycka` | `password` | Technical Services Department (Department 1) |
| **Field Crew** | `ralph` | `password` | Alpha Emergency Response Crew (Team 1) |

---

## 8. Verification & Health Summary

1. **PHP Syntax Validation (`php -l`):** 100% clean across all 45+ newly created and modified files with 0 syntax errors.
2. **Code Style (`./vendor/bin/pint`):** All files formatted according to Laravel Pint and PSR-12 coding standards.
3. **Route Discovery (`php artisan route:list`):** Confirmed all **110 web, api, and authentication routes** compile cleanly with zero container resolution faults or missing controller bindings.
4. **Git Safety Check:** 0 commits made; working tree remains 100% unstaged for developer review and inclusion in your planned single commit.
