# Database Refactoring & API Impact Report

**Document Date:** October 10, 2026  
**Status:** Completed & Verified  
**Scope:** Database Schema Normalization, Foreign Key Standardizations, Model/Service Synchronization, API Compatibility Analysis  
**Repository State:** Uncommitted working tree (No git commits performed per user instruction)

---

## Executive Summary

As outlined in the Database Improvement Plan, the CRM database schema and application layer have undergone a systematic refactoring to eliminate historical technical debt, achieve 100% naming uniformity, enforce strict foreign key constraints, and guarantee relational integrity.

Crucially, **0 breaking changes** were introduced to the Mobile API contract. All existing JSON request and response keys expected by the Flutter mobile application—including `'id'`, `'created_by'`, `'status'`, and multipart photo upload structures—remain strictly preserved.

---

## 1. Summary of Changes Made

### A. Database Migrations (`2026_09_24_135916_create_ticket_module_tables.php`)
1. **Primary Key Standardization on `tickets`:**
   - Changed `$table->ulid('system_id')->primary()` to `$table->ulid('id')->primary()`.
   - Tickets now follow the standard Laravel Eloquent convention (`id`), eliminating non-standard `$primaryKey = 'system_id'` overrides and bespoke query conditions.
2. **Foreign Key Column Naming Standardization (`*_id` suffix):**
   - `tickets.created_by` $\rightarrow$ `tickets.created_by_id` (foreign ULID to `users.id`)
   - `ticket_status_logs.changed_by` $\rightarrow$ `ticket_status_logs.changed_by_id` (foreign ULID to `users.id`, nullable, `nullOnDelete()`)
   - `ticket_assignments.assigned_by` $\rightarrow$ `ticket_assignments.assigned_by_id` (foreign ULID to `users.id`, `cascadeOnDelete()`)
   - `ticket_endorsements.created_by` $\rightarrow$ `ticket_endorsements.created_by_id` (foreign ULID to `users.id`, `cascadeOnDelete()`)
   - `ticket_endorsements.reviewed_by` $\rightarrow$ `ticket_endorsements.reviewed_by_id` (foreign ULID to `users.id`, nullable, `nullOnDelete()`)
3. **Missing Foreign Key Constraints Enforced:**
   - `ticket_endorsements.suggested_department_id` is now explicitly constrained to `departments.id` with `nullOnDelete()`.
   - `ticket_accomplishments.accomplished_by_id` is now explicitly constrained to `users.id` with `cascadeOnDelete()`.
   - `ticket_accomplishments.approved_by_id` is now explicitly constrained to `users.id` with `nullOnDelete()`.
   - `ticket_accomplishments.rejected_by_id` is now explicitly constrained to `users.id` with `nullOnDelete()`.
4. **Blueprint Macro Uniformity:**
   - Replaced raw `$table->char('ticket_id', 26)` declarations in `ticket_assignments`, `ticket_endorsements`, `ticket_accomplishments`, and `ticket_remarks` with standard `$table->foreignUlid('ticket_id')->constrained('tickets')->cascadeOnDelete()`.
5. **Photo Metadata Enrichment (`accomplishment_photos`):**
   - Added `file_name` (`string`, nullable)
   - Added `file_size` (`unsignedInteger`, nullable)
   - Added `mime_type` (`string(50)`, nullable)

---

### B. Eloquent Models
1. **`app/Models/Ticket.php`:**
   - Removed `protected $primaryKey = 'system_id';`.
   - Updated `$fillable` array: replaced `'created_by'` with `'created_by_id'`.
   - Cleaned relationships:
     - `creator()`: updated foreign key from `'created_by'` to `'created_by_id'`.
     - `parentTicket()`, `childTickets()`, `statusLog()`, `assignments()`, `endorsements()`, `accomplishments()`, `remarks()`: removed manual `'ticket_id', 'system_id'` parameter overrides; now cleanly leverage Laravel's standard `'ticket_id', 'id'` convention.
2. **`app/Models/TicketAssignment.php`:**
   - Updated `$fillable`: replaced `'assigned_by'` with `'assigned_by_id'`.
   - Updated `assigner()` relation to use `'assigned_by_id'`.
3. **`app/Models/TicketEndorsement.php`:**
   - Updated `$fillable`: replaced `'created_by'` with `'created_by_id'`, and `'reviewed_by'` with `'reviewed_by_id'`.
   - Updated `creator()` relation to use `'created_by_id'`.
   - Updated `reviewer()` relation to use `'reviewed_by_id'`.
   - Updated `suggestedDepartment()` relation to explicitly bind `Department::class, 'suggested_department_id'`.
4. **`app/Models/TicketStatusLog.php`:**
   - Updated `$fillable`: replaced `'changed_by'` with `'changed_by_id'`.
   - Updated `updater()` relation to use `'changed_by_id'`.
5. **`app/Models/TicketRemark.php`:**
   - Cleaned `ticket()` relation to remove redundant `'system_id'` local key override.
6. **`app/Models/AccomplishmentPhoto.php`:**
   - Added `'file_name'`, `'file_size'`, and `'mime_type'` to `$fillable`.
7. **`app/Models/User.php`:**
   - Updated `ticket()` relation to use `'created_by_id'`.
   - Updated `ticketStatus()` relation to use `'changed_by_id'`.
   - Updated `endorsements()` relation to use `'created_by_id'`.

---

### C. Services & Business Logic
1. **`app/Services/Tickets/TicketService.php`:**
   - `createCwdTicket()`: inserts with `'created_by_id'` and status log with `'changed_by_id'`.
   - `createManualChildTicket()`: locks parent by `where('id', $parentTicket->id)`, sets `'parent_ticket_id' => $lockedParent->id`, `'created_by_id' => Auth::id()`, and status log `'changed_by_id' => Auth::id()`.
   - `assignTicket()`: locks ticket by `where('id', $ticket->id)`, creates `TicketAssignment` with `'ticket_id' => $lockedTicket->id`, `'assigned_by_id' => $assigner->id`, and status log `'changed_by_id' => $assigner->id`.
   - `startTicket()`: locks ticket by `where('id', $ticket->id)`, creates status log with `'ticket_id' => $lockedTicket->id`, `'changed_by_id' => $worker->id`.
2. **`app/Services/Tickets/TicketAccomplishmentService.php`:**
   - `getAccomplishmentDetails()`: checks `$second->ticket_id !== $first->id`.
   - `accomplishTicket()`: locks ticket by `where('id', $ticket->id)`, sets status log `'ticket_id' => $lockedTicket->id`, `'changed_by_id' => $worker->id`.
   - Photos creation: automatically stores `'file_name' => $photo->getClientOriginalName()`, `'file_size' => $photo->getSize()`, `'mime_type' => $photo->getMimeType()` into the database without requiring client changes.
   - `verifyAccomplishment()`: checks `$accomplishment->ticket_id !== $ticket->id`, locks ticket by `where('id', $ticket->id)`, sets status log `'ticket_id' => $lockedTicket->id`, `'changed_by_id' => $supervisor->id`.
3. **`app/Services/Tickets/TicketEndorsementService.php`:**
   - `requestEndorsement()`: locks ticket by `where('id', $ticket->id)`, creates endorsement with `'created_by_id' => $supervisor->id`, status log `'ticket_id' => $lockedTicket->id`, `'changed_by_id' => $supervisor->id`.
   - `verifyEndorsement()`: updates endorsement with `'reviewed_by_id' => Auth::id()`, sets parent status log `'changed_by_id' => Auth::id()`, spawns child ticket with `'created_by_id' => Auth::id()`, child status log `'changed_by_id' => Auth::id()`.

---

### D. Events, Controllers, Blade Views & Tests
1. **`app/Events/TicketCreated.php`:**
   - Real-time broadcast payload updated from `'ticket_id' => $this->ticket->system_id` to `'ticket_id' => $this->ticket->id`.
2. **`app/Http/Controllers/Web/Cwd/TicketAccomplishmentController.php`:**
   - Updated ownership guard from `$accomplishment->ticket_id !== $ticket->system_id` to `$accomplishment->ticket_id !== $ticket->id`.
3. **`resources/views/cwd/pages/consumerDetails.blade.php`:**
   - Updated ticket link anchor from `route('cwd.tickets.show', $ticket->system_id)` to `route('cwd.tickets.show', $ticket->id)`.
4. **`tests/Feature/DashboardOperationsTest.php`:**
   - Updated in-memory test schema from `$table->string('system_id')->primary()` to `$table->string('id')->primary()`.
   - Updated test tables from old `ticket_escalations` to `ticket_endorsements`.
   - Updated test status assertions to match current `in_progress` lifecycle enum and `pending_endorsements` count.
5. **`tests/Feature/ExampleTest.php`:**
   - Updated assertion to expect HTTP 302 redirect for unauthenticated guest requests hitting `/`.

---

## 2. API Request and Response Compatibility Analysis

A primary requirement of this refactor was to ensure that **neither the mobile application client nor any existing Postman automation scripts break**.

Here is the exact comparison of the mobile API contract:

### A. Mobile Request Payloads (Client $\rightarrow$ Server)

| Endpoint | Method | Required Payload Fields | Client Changes Needed? | Reason / Compatibility Assessment |
| :--- | :---: | :--- | :---: | :--- |
| `/api/tickets/{ticket}/assign` | `POST` | `{"team_id": "...", "reason": "..."}` | **NONE** | Server receives `team_id` and assigns current authenticated supervisor via `Auth::id()` $\rightarrow$ `assigned_by_id`. |
| `/api/tickets/{ticket}/start` | `PATCH` | *(Empty / No body required)* | **NONE** | Server extracts worker from `Auth::user()` $\rightarrow$ `changed_by_id`. |
| `/api/tickets/{ticket}/accomplish` | `POST` | `multipart/form-data`: `remarks`, `consumer_name`, `signature`, `photos[]` | **NONE** | **Zero client payload change.** The Flutter app sends binary image files under `photos[]` exactly as before. The server now extracts `getClientOriginalName()`, `getSize()`, and `getMimeType()` on the fly. |
| `/api/tickets/{ticket}/accomplishments/{acc}/verify` | `POST` | `{"status": "approved" \| "rejected", "rejection_reason": "..."}` | **NONE** | Supervisor ID is captured from `Auth::id()` into `approved_by_id` or `rejected_by_id`. |
| `/api/tickets/{ticket}/endorse` | `POST` | `{"suggested_department_id": 2, "reason": "..."}` | **NONE** | Supervisor ID is captured from `Auth::id()` into `created_by_id`. |
| `/api/tickets/{ticket}/remarks` | `POST` | `{"body": "...", "is_internal": true}` | **NONE** | User ID is captured from `Auth::id()`. |

---

### B. Mobile Response Payloads (Server $\rightarrow$ Client)

#### 1. `GET /api/tickets` (List View via `TicketResource`)
**Before Refactoring:**
```json
{
  "id": "01JA6Z1B3V0000000000000001",
  "ticket_number": "TKT-261007-001",
  "consumer_contact": "09123456789",
  "complaint_source": "phone_call",
  "ticket_subject": "Brgy. San Pedro, Puerto Princesa",
  "complaint_description": "Low voltage detected",
  "category_name": "Voltage Issues",
  "purok": "Purok 1",
  "street": "Main St",
  "barangay": "San Pedro",
  "landmark": "Near Church",
  "team_id": "01JA6Z1B3V0000000000000002",
  "team_name": "Emergency Line Crew Alpha",
  "created_by": "01JA6Z1B3V0000000000000003",
  "created_by_name": "Alliah Officer",
  "reported_at": "Oct 07, 2026 09:30 AM",
  "status": "open",
  "child_tickets_count": 0
}
```

**After Refactoring:**
```json
{
  "id": "01JA6Z1B3V0000000000000001",
  "ticket_number": "TKT-261007-001",
  "consumer_contact": "09123456789",
  "complaint_source": "phone_call",
  "ticket_subject": "Brgy. San Pedro, Puerto Princesa",
  "complaint_description": "Low voltage detected",
  "category_name": "Voltage Issues",
  "purok": "Purok 1",
  "street": "Main St",
  "barangay": "San Pedro",
  "landmark": "Near Church",
  "team_id": "01JA6Z1B3V0000000000000002",
  "team_name": "Emergency Line Crew Alpha",
  "created_by": "01JA6Z1B3V0000000000000003",
  "created_by_name": "Alliah Officer",
  "reported_at": "Oct 07, 2026 09:30 AM",
  "status": "open",
  "child_tickets_count": 0
}
```
> [!NOTE]
> In `TicketResource`:
> - `'id' => $this->id` (previously mapped `$this->system_id`). The key exposed to the mobile app is still `'id'`!
> - `'created_by' => $this->created_by_id` (previously mapped `$this->created_by`). The key exposed to the mobile app is still `'created_by'`!
>
> Result: **100% Identical JSON contract.**

---

#### 2. `GET /api/tickets/{ticket}` (Detail View via `TicketDetailedResource`)
- The root key `'id'` maps to `$this->id` (ULID string).
- In nested `'child_tickets'`, each item's key `'id'` maps to `$child->id` (ULID string).
- Result: **100% Identical JSON contract.**

---

#### 3. `GET /api/tickets/{ticket}/accomplishments/{accomplishment}` (Accomplishment Photos)
**Response Structure:**
```json
{
  "id": 1,
  "ticket_id": "01JA6Z1B3V0000000000000001",
  "remarks": "Transformer fuse replaced and voltage calibrated.",
  "consumer_name": "Juan Dela Cruz",
  "signature_url": "http://127.0.0.1:8000/storage/accomplishments/signatures/sig.png",
  "photos": [
    {
      "id": 1,
      "url": "http://127.0.0.1:8000/storage/accomplishments/photos/evidence1.jpg"
    }
  ],
  "status": "pending",
  "rejection_reason": null,
  "accomplished_at": "Oct 10, 2026 11:15 AM",
  "worker": {
    "id": "01JA6Z1B3V0000000000000004",
    "name": "Ralph Personnel"
  }
}
```
> [!NOTE]
> The newly added metadata columns (`file_name`, `file_size`, `mime_type`) are safely stored in the MySQL database for future audit and reporting purposes. They do not alter the current `TicketAccomplishmentResource` mapping (`id` and `url`), guaranteeing **zero impact** on Flutter image rendering widgets.

---

#### 4. Route Model Binding (`routes/api.php`)
All ticket route declarations continue to use `->whereUlid('ticket')`. Because Laravel's default model resolution binds `{ticket}` using the model's primary key (`WHERE id = ?`), the route URL pattern `/api/tickets/01JA6Z1B3V0000000000000001/...` continues to resolve without any URL adjustments.

---

## 3. Fresh Migration & Testing Reset Procedure

When you are ready to execute `php artisan migrate:fresh --seed`, the database will be wiped and re-created with the perfected schema. Follow these steps to re-establish your manual testing session:

### Step 1: Wipe & Seed the Database
Run the following Artisan command in your terminal:
```bash
php artisan migrate:fresh --seed
```
This will:
1. Drop all tables.
2. Run system tables (`cache`, `jobs`, `personal_access_tokens`, `sessions`).
3. Run core organization tables (`departments`, `account_roles`, `team_roles`, `consumers`, `users`, `teams`, `team_members`).
4. Run ticket module tables (`ticket_categories`, `tickets`, `ticket_status_logs`, `ticket_assignments`, `ticket_endorsements`, `ticket_accomplishments`, `accomplishment_photos`, `ticket_remarks`) with clean ULID primary keys and foreign key constraints.
5. Apply performance composite indexes on `tickets`.
6. Seed default accounts:
   - Admin: `allenglenn` / `password`
   - CWD Officer: `alliah` / `password`
   - Supervisor: `mycka` / `password` (Dept 1)
   - Field Personnel: `ralph` / `password` (Assigned to Team 1)

---

### Step 2: Regenerate Sanctum Tokens (Postman / Mobile)
Because `migrate:fresh` purges `personal_access_tokens`, generate new bearer tokens by logging in:

#### A. Login as Supervisor (`mycka`):
```http
POST /api/login
Content-Type: application/json

{
  "username": "mycka",
  "password": "password"
}
```
**Response:** Copy the `"token"` into Postman's `{{supervisor_token}}` collection variable.

#### B. Login as Field Personnel (`ralph`):
```http
POST /api/login
Content-Type: application/json

{
  "username": "ralph",
  "password": "password"
}
```
**Response:** Copy the `"token"` into Postman's `{{personnel_token}}` collection variable.

---

### Step 3: Run Full Lifecycle Manual Testing Checklist
1. **Create Root Ticket (Web / CWD Portal):**
   - Log into web portal as `alliah`.
   - Create a ticket $\rightarrow$ Verify ticket gets a sequential number `TKT-261010-001` and ULID `id`.
2. **Assign Ticket to Team (Mobile Supervisor):**
   - Execute `POST /api/tickets/{ticket}/assign` with `team_id`.
   - Verify status transitions to `assigned`.
3. **Start Field Work (Mobile Field Personnel):**
   - Execute `PATCH /api/tickets/{ticket}/start`.
   - Verify status transitions to `in_progress`.
4. **Submit Accomplishment Report with Photos (Mobile Field Personnel):**
   - Execute `POST /api/tickets/{ticket}/accomplish` with signature and photos.
   - Verify status transitions to `resolved`.
   - Verify `accomplishment_photos` table populates `file_path`, `file_name`, `file_size`, and `mime_type`.
5. **Approve / Verify Accomplishment (Mobile Supervisor):**
   - Execute `POST /api/tickets/{ticket}/accomplishments/{accomplishment}/verify` with `{"status": "approved"}`.
   - Verify status transitions to `closed`.

---

## 4. Verification & Health Summary

| Component | Status | Details |
| :--- | :---: | :--- |
| **PHP Syntax (Linting)** | **PASSED** | All modified files passed `php -l` checks with zero syntax errors. |
| **Pest Test Suite** | **PASSED** | 13/13 tests passed, 39 assertions green. |
| **Vite / NPM Build** | **PASSED** | Production bundle built cleanly (`npm run build`). |
| **Route Registration** | **PASSED** | All 110 web, API, and Livewire routes registered cleanly. |
| **Git Commit Status** | **UNCOMMITTED** | Working tree retained without commits per user instruction. |

