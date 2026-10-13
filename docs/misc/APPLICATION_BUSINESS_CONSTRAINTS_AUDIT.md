# PALECO OTRS-CRM: Application Business Constraints Audit & Proposal

**Document Target:** `docs/misc/APPLICATION_BUSINESS_CONSTRAINTS_AUDIT.md`  
**Document Version:** 1.0  
**Context:** Verification audit of the 14 potential missing constraints identified in `docs/dev/MANUAL_TESTING_GUIDE.md` (Section 15.2) against the active codebase, paired with an exhaustive proposal for crucial electric utility domain and application-layer constraints.  
**Target Codebase:** `PALECO_OTRS-CRM` (Laravel 13.x / PHP 8.4+ / Livewire 4 / Tailwind CSS v4)  

---

## 1. Executive Summary & Verification Scorecard

An exhaustive scan was conducted across all FormRequests (`app/Http/Requests/*`), Domain Services (`app/Services/*`), Model Policies (`app/Policies/*`), and Database Enums (`app/Enums/*`).

### 1.1 Summary of the 14 Manual Testing Guide Constraints

| # | Feature / Subsystem | Potential Constraint from Testing Guide | Status in Codebase | Concrete Code Evidence & File Location |
| :-: | :--- | :--- | :---: | :--- |
| **1** | **Ticket Assignment** | Cross-Department Team Validation | ❌ **NOT APPLIED** | [`AssignTicketRequest.php:29`](file:///c:/Users/allen/Desktop/Capstone/-NEW-CRM-PALECO/PALECO_OTRS-CRM/app/Http/Requests/Tickets/AssignTicketRequest.php#L29) checks `exists:teams,id` only; [`TicketService.php:363`](file:///c:/Users/allen/Desktop/Capstone/-NEW-CRM-PALECO/PALECO_OTRS-CRM/app/Services/Tickets/TicketService.php#L363) allows assigning teams from foreign departments. |
| **2** | **Ticket Assignment** | Soft-Delete Exclusion on Team ID | ❌ **NOT APPLIED** | [`AssignTicketRequest.php:29`](file:///c:/Users/allen/Desktop/Capstone/-NEW-CRM-PALECO/PALECO_OTRS-CRM/app/Http/Requests/Tickets/AssignTicketRequest.php#L29) lacks `whereNull('deleted_at')`. Archived crews can still receive assignments via direct API payload. |
| **3** | **Endorsement Pipeline** | Same-Department Rejection Guard | ✅ **ALREADY APPLIED** | [`StoreEndorsementRequest.php:32`](file:///c:/Users/allen/Desktop/Capstone/-NEW-CRM-PALECO/PALECO_OTRS-CRM/app/Http/Requests/Tickets/StoreEndorsementRequest.php#L32) enforces `'not_in:'.$this->route('ticket')?->department_id` with custom error message: *"The suggested department cannot be the same as the current department."* |
| **4** | **Child Ticket Spawning** | Unbounded Child Nesting Depth Guard | ❌ **NOT APPLIED** | [`TicketPolicy.php:53-62`](file:///c:/Users/allen/Desktop/Capstone/-NEW-CRM-PALECO/PALECO_OTRS-CRM/app/Policies/TicketPolicy.php#L53-L62) only verifies user role and non-trashed state; does not enforce `is_null($ticket->parent_ticket_id)`. Grandchildren can be spawned recursively. |
| **5** | **Ticket Intake** | Soft-Delete Exclusion on Category ID | ❌ **NOT APPLIED** | [`StoreTicketRequest.php:58`](file:///c:/Users/allen/Desktop/Capstone/-NEW-CRM-PALECO/PALECO_OTRS-CRM/app/Http/Requests/Tickets/StoreTicketRequest.php#L58) checks `exists:ticket_categories,id` without checking `deleted_at`. |
| **6** | **Staff Management** | Active Field Duty Deactivation Guard | ⚠️ **PARTIALLY APPLIED** | [`UserService.php:124-126`](file:///c:/Users/allen/Desktop/Capstone/-NEW-CRM-PALECO/PALECO_OTRS-CRM/app/Services/Users/UserService.php#L124-L126) automatically detaches the user from teams and revokes tokens upon deactivation, but does not check if tickets are currently `in_progress` under that user's crew. |
| **7** | **Crew Rosters** | Multi-Team Member Overlapping Shifts | ❌ **NOT APPLIED** | [`StoreTeamRequest.php:54-63`](file:///c:/Users/allen/Desktop/Capstone/-NEW-CRM-PALECO/PALECO_OTRS-CRM/app/Http/Requests/Teams/StoreTeamRequest.php#L54-L63) and `TeamService.php` sync members without checking for concurrent shift schedules across teams. |
| **8** | **Crew Shifts** | Zero-Minute & Overnight Shift Validation | ❌ **NOT APPLIED** | [`StoreTeamRequest.php:50-51`](file:///c:/Users/allen/Desktop/Capstone/-NEW-CRM-PALECO/PALECO_OTRS-CRM/app/Http/Requests/Teams/StoreTeamRequest.php#L50-L51) validates `date_format:H:i` only; lacks `different:shift_start` or cross-midnight wrap handling. |
| **9** | **Ticket Intake** | Philippine Mobile Phone Regex | ✅ **ALREADY APPLIED** | [`StoreTicketRequest.php:44`](file:///c:/Users/allen/Desktop/Capstone/-NEW-CRM-PALECO/PALECO_OTRS-CRM/app/Http/Requests/Tickets/StoreTicketRequest.php#L44) explicitly enforces `'consumer_contact' => ['required', 'string', 'regex:/^(09|\+639)\d{9}$/']`. |
| **10** | **Consumer Intake** | Duplicate Active Ticket Warning | ❌ **NOT APPLIED** | `TicketService.php::createTicket()` does not check if an active ticket (`open`, `assigned`, `in_progress`) already exists for the same consumer/meter. |
| **11** | **Accomplishments** | Segregation of Duties (Self-Verification) | ❌ **NOT APPLIED** | [`TicketAccomplishmentService.php:192-237`](file:///c:/Users/allen/Desktop/Capstone/-NEW-CRM-PALECO/PALECO_OTRS-CRM/app/Services/Tickets/TicketAccomplishmentService.php#L192-L237) does not verify whether `accomplishment.accomplished_by_id === supervisor.id`. |
| **12** | **Accomplishments** | Aggregate Upload Payload vs `post_max_size` | ❌ **NOT APPLIED** | [`SubmitAccomplishmentReportRequest.php:30-38`](file:///c:/Users/allen/Desktop/Capstone/-NEW-CRM-PALECO/PALECO_OTRS-CRM/app/Http/Requests/Tickets/SubmitAccomplishmentReportRequest.php#L30-L38) validates individual files (10MB photos, 5MB signature), but 10 photos can total ~105MB, potentially exceeding server-level `post_max_size`. |
| **13** | **Ticket Lifecycle** | Cancellation / Voiding State Pathway | ❌ **NOT APPLIED** | [`TicketStatus.php:12-20`](file:///c:/Users/allen/Desktop/Capstone/-NEW-CRM-PALECO/PALECO_OTRS-CRM/app/Enums/TicketStatus.php#L12-L20) does not contain a `CANCELLED` case. False alarms must undergo full dispatch and closure. |
| **14** | **Ticket SLA** | Dormancy Escalation Scheduler on Open Tickets | ❌ **NOT APPLIED** | `routes/console.php` does not schedule an automated ticket aging or SLA monitor command. |

---

## 2. Detailed Forensic Analysis of the 14 Guide Constraints

### Constraint 1: Cross-Department Team Assignment Validation (Status: NOT APPLIED)
* **Current State:**
  In [`AssignTicketRequest.php:29`](file:///c:/Users/allen/Desktop/Capstone/-NEW-CRM-PALECO/PALECO_OTRS-CRM/app/Http/Requests/Tickets/AssignTicketRequest.php#L29):
  ```php
  'team_id' => ['required', 'string', 'exists:teams,id'],
  ```
  In [`TicketService.php:363-380`](file:///c:/Users/allen/Desktop/Capstone/-NEW-CRM-PALECO/PALECO_OTRS-CRM/app/Services/Tickets/TicketService.php#L363-L380), the service checks ticket status and ensures the ticket is not already assigned to the same team, but does not compare `teams.department_id` with `tickets.department_id`.
* **Business Risk:** A dispatch supervisor in Technical Services can assign an outage ticket to an Area Operations or Consumer Accounts team via direct API calls.
* **Remediation Recipe:**
  In `AssignTicketRequest.php`:
  ```php
  'team_id' => [
      'required',
      'string',
      Rule::exists('teams', 'id')
          ->where('department_id', $this->route('ticket')?->department_id)
          ->whereNull('deleted_at'),
  ],
  ```

---

### Constraint 2: Soft-Delete Exclusion on Team ID (Status: NOT APPLIED)
* **Current State:**
  Because the rule is simply `'exists:teams,id'`, Laravel executes `SELECT COUNT(*) FROM teams WHERE id = ?`. If a team was soft-deleted (`deleted_at IS NOT NULL`), it still exists in the table and validation passes.
* **Business Risk:** Tickets can be assigned to archived, dissolved, or inactive crews.
* **Remediation Recipe:** Add `->whereNull('deleted_at')` to the rule as shown in Constraint 1.

---

### Constraint 3: Same-Department Endorsement Rejection (Status: ALREADY APPLIED ✅)
* **Current State:**
  In [`StoreEndorsementRequest.php:28-34`](file:///c:/Users/allen/Desktop/Capstone/-NEW-CRM-PALECO/PALECO_OTRS-CRM/app/Http/Requests/Tickets/StoreEndorsementRequest.php#L28-L34):
  ```php
  'suggested_department_id' => [
      'nullable',
      'integer',
      'exists:departments,id',
      'not_in:'.$this->route('ticket')?->department_id,
  ],
  ```
  Lines 45–47 provide the custom message:
  ```php
  'suggested_department_id.not_in' => 'The suggested department cannot be the same as the current department.',
  ```
* **Verdict:** Fully implemented and functioning as intended.

---

### Constraint 4: Unbounded Child Nesting Depth Guard (Status: NOT APPLIED)
* **Current State:**
  In [`TicketPolicy.php:53-62`](file:///c:/Users/allen/Desktop/Capstone/-NEW-CRM-PALECO/PALECO_OTRS-CRM/app/Policies/TicketPolicy.php#L53-L62):
  ```php
  public function createChild(User $user, Ticket $ticket): Response
  {
      if ($user->role->slug_identifier !== 'cwd_officer') {
          return Response::deny('This action is unauthorized.');
      }

      return ! $ticket->trashed()
          ? Response::allow()
          : Response::denyAsNotFound('The requested ticket was not found.');
  }
  ```
* **Business Risk:** A CWD Officer can open ticket `TKT-261013-001-1` (which is already a child) and spawn `TKT-261013-001-1-1`. Recursive nesting breaks tree layouts, database joins, and reporting aggregations.
* **Remediation Recipe:**
  In `TicketPolicy.php:59`:
  ```php
  if (! is_null($ticket->parent_ticket_id)) {
      return Response::deny('Child tickets cannot spawn sub-tasks. Only root origin tickets can spawn child tickets.');
  }
  ```

---

### Constraint 5: Soft-Delete Exclusion on Category ID (Status: NOT APPLIED)
* **Current State:**
  In [`StoreTicketRequest.php:58`](file:///c:/Users/allen/Desktop/Capstone/-NEW-CRM-PALECO/PALECO_OTRS-CRM/app/Http/Requests/Tickets/StoreTicketRequest.php#L58):
  ```php
  'category_id' => ['required_if:other_category,false', 'nullable', 'exists:ticket_categories,id'],
  ```
* **Business Risk:** Archived categories remain selectable via stale browser tabs or direct API calls.
* **Remediation Recipe:**
  ```php
  'category_id' => [
      'required_if:other_category,false',
      'nullable',
      Rule::exists('ticket_categories', 'id')->whereNull('deleted_at'),
  ],
  ```

---

### Constraint 6: Deactivating Users with Active Field Duties (Status: PARTIALLY APPLIED ⚠️)
* **Current State:**
  In [`UserService.php:124-126`](file:///c:/Users/allen/Desktop/Capstone/-NEW-CRM-PALECO/PALECO_OTRS-CRM/app/Services/Users/UserService.php#L124-L126):
  ```php
  if (! $isActive) {
      $user->tokens()->delete();
      if ($user->role->slug_identifier === 'field_personnel') {
          $user->teams()->detach();
      }
  }
  ```
* **Analysis:**
  - Token revocation and team detachment prevent unauthorized logins and clean up rosters.
  - However, if the lineman is assigned to an active ticket currently `in_progress`, detaching them leaves the active ticket stranded without alerting the administrator.
* **Remediation Recipe:** In `UserController::deactivate`, check if `$user->teams()->whereHas('tickets', fn($q) => $q->whereIn('status', ['assigned', 'in_progress']))->exists()` and display a confirmation prompt warning about active field work.

---

### Constraint 7 & 8: Crew Shift & Overlapping Schedule Constraints (Status: NOT APPLIED)
* **Current State:**
  In [`StoreTeamRequest.php:50-51`](file:///c:/Users/allen/Desktop/Capstone/-NEW-CRM-PALECO/PALECO_OTRS-CRM/app/Http/Requests/Teams/StoreTeamRequest.php#L50-L51):
  ```php
  'shift_start' => ['required', 'date_format:H:i'],
  'shift_end' => ['required', 'date_format:H:i'],
  ```
* **Business Risk:**
  - Admins can enter `shift_start: 08:00` and `shift_end: 08:00` (a 0-minute shift).
  - Night patrol crews (e.g. `22:00` to `06:00`) have `shift_end < shift_start` without a cross-midnight indicator.
  - A lineman can be added to Team A (08:00–17:00) and Team B (13:00–21:00) simultaneously.
* **Remediation Recipe:**
  Add `'shift_end' => ['required', 'date_format:H:i', 'different:shift_start']` and add a shift overlap validation rule when syncing team rosters.

---

### Constraint 9: Philippine Mobile Phone Regex (Status: ALREADY APPLIED ✅)
* **Current State:**
  In [`StoreTicketRequest.php:44`](file:///c:/Users/allen/Desktop/Capstone/-NEW-CRM-PALECO/PALECO_OTRS-CRM/app/Http/Requests/Tickets/StoreTicketRequest.php#L44):
  ```php
  'consumer_contact' => ['required', 'string', 'regex:/^(09|\+639)\d{9}$/'],
  ```
* **Verdict:** Strictly validates standard Philippine cellular formats (`09XXXXXXXXX` or `+639XXXXXXXXX`).

---

### Constraint 10: Duplicate Intake Warning for Same Meter/Account (Status: NOT APPLIED)
* **Current State:** `TicketService::createTicket()` processes all valid requests without checking whether open tickets already exist for the consumer.
* **Business Risk:** During a feeder trip or distribution transformer outage, dozens of consumers on the same street file identical tickets, creating ticket bloat.
* **Remediation Recipe:** Add an advisory check in CWD ticket creation: if `Ticket::where('consumer_id', $consumerId)->whereIn('status', ['open', 'assigned', 'in_progress'])->exists()`, return a notice or display existing open tickets to the dispatcher.

---

### Constraint 11: Segregation of Duties / Self-Verification (Status: NOT APPLIED)
* **Current State:**
  In [`TicketAccomplishmentService.php:192-237`](file:///c:/Users/allen/Desktop/Capstone/-NEW-CRM-PALECO/PALECO_OTRS-CRM/app/Services/Tickets/TicketAccomplishmentService.php#L192-L237), any user with supervisory authority can approve an accomplishment report.
* **Business Risk:** If a supervisor conducts field repairs in an emergency and submits an accomplishment report, they can approve their own report without independent verification.
* **Remediation Recipe:**
  In `verifyAccomplishment`:
  ```php
  if ($lockedAccomplishment->accomplished_by_id === $supervisor->id) {
      throw ValidationException::withMessages([
          'supervisor' => 'Segregation of duties violation: You cannot verify an accomplishment report that you submitted.',
      ]);
  }
  ```

---

### Constraint 12: Aggregate Request Payload vs PHP `post_max_size` (Status: NOT APPLIED)
* **Current State:**
  In [`SubmitAccomplishmentReportRequest.php`](file:///c:/Users/allen/Desktop/Capstone/-NEW-CRM-PALECO/PALECO_OTRS-CRM/app/Http/Requests/Tickets/SubmitAccomplishmentReportRequest.php): individual photos are capped at 10MB, up to 10 photos + 5MB signature.
* **Business Risk:** Submitting 10 raw camera images can total ~100MB. If the web server or PHP configuration has `post_max_size = 8M` or `16M`, PHP clears `$_POST` and `$_FILES` before Laravel executes, triggering generic HTTP 422 or empty-payload errors.
* **Remediation Recipe:** Enforce total batch size validation in the request or compress images on the mobile client before upload.

---

### Constraint 13: Cancellation / Voiding State Pathway (Status: NOT APPLIED)
* **Current State:**
  In [`TicketStatus.php`](file:///c:/Users/allen/Desktop/Capstone/-NEW-CRM-PALECO/PALECO_OTRS-CRM/app/Enums/TicketStatus.php), the active statuses are: `OPEN`, `ASSIGNED`, `IN_PROGRESS`, `PENDING_ENDORSEMENT`, `ENDORSED`, `RESOLVED`, `CLOSED`.
* **Business Risk:** If a customer calls reporting a power outage that turns out to be their own tripped main breaker, dispatchers have no way to cancel or void the ticket. The ticket must be dispatched, photographed, and approved to reach `closed`.
* **Remediation Recipe:** Introduce a `CANCELLED` status with mandatory written justification.

---

### Constraint 14: Dormancy SLA Escalation Timer on Open Tickets (Status: NOT APPLIED)
* **Current State:** No scheduled console command exists in `routes/console.php`.
* **Business Risk:** Low-priority tickets can sit in `open` status for weeks without administrative detection.
* **Remediation Recipe:** Register a scheduled command (`php artisan tickets:check-sla`) to flag tickets exceeding SLA thresholds (e.g., > 24 hours in `open` status).

---

## 3. Crucial Domain Constraints for Electric Utility Operations (PALECO CRM)

Beyond the 14 testing guide items, an electric distribution utility requires specific business logic constraints to ensure crew safety, accounting accuracy, and operational integrity.

Below are **8 crucial application-layer constraints** designed specifically for the PALECO context:

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│                         CRITICAL DOMAIN CONSTRAINTS FOR ELECTRIC UTILITIES                       │
├────────┬───────────────────────────────────────────┬─────────────────────────────────────────────┤
│ Code   │ Constraint Name                           │ Operational Domain & Purpose                │
├────────┼───────────────────────────────────────────┼─────────────────────────────────────────────┤
│ B-01   │ Disconnected Consumer Dispatch Guard      │ Prevent bogus repair runs on cut-off meters │
│ B-02   │ Parent Ticket Closure Invariant           │ Prevent closing parent with open children   │
│ B-03   │ Concurrent Field Workload Cap per Crew    │ Prevent a single crew in 2 places at once   │
│ B-04   │ Offline Timestamp Anti-Tampering Guard    │ Enforce chronological integrity on mobile   │
│ B-05   │ Distinct Photo Evidence Hash Invariant    │ Prevent duplicate identical photo uploads   │
│ B-06   │ Endorsement State Mutability Freeze       │ Lock tickets while endorsement is pending   │
│ B-07   │ Active Shift Availability Dispatch Check  │ Prevent assigning tickets to off-duty crews │
│ B-08   │ Electrical Hazard Emergency Escalation    │ Force immediate triage on life-safety posts │
└────────┴───────────────────────────────────────────┴─────────────────────────────────────────────┘
```

---

### Constraint B-01: Disconnected / Meter-Pulled Consumer Dispatch Guard
* **Domain Context:**
  When consumers call to report a "power outage", the external billing database returns their account `status` (e.g. `'Active'`, `'Disconnected'`, `'Meter Pulled'`, `'Apprehended'`).
* **The Business Problem:**
  If a consumer whose service was legally disconnected for non-payment reports an outage, dispatching a lineman crew wastes fuel, vehicle wear, and labor hours on an account that has no power by design.
* **Recommended Application Constraint:**
  In [`StoreTicketRequest.php`](file:///c:/Users/allen/Desktop/Capstone/-NEW-CRM-PALECO/PALECO_OTRS-CRM/app/Http/Requests/Tickets/StoreTicketRequest.php):
  If the resolved consumer record has `status !== 'Active'`, require the dispatcher to check an **"Acknowledge Disconnected Status"** checkbox and provide a mandatory override justification (e.g. *"Reconnection fee paid at cashier; verifying transformer jumper"*).

---

### Constraint B-02: Parent Ticket Closure Invariant (Active Child Sub-Tasks)
* **Domain Context:**
  A parent ticket (e.g., *"Feeder 4 Main Line Down"*) spawns 2 child tickets:
  1. *Child A:* Tree trimming / vegetation clearing.
  2. *Child B:* Transformer fuse replacement.
* **The Business Problem:**
  If a supervisor closes the parent ticket while Child A or Child B is still `in_progress`, the system reports the main incident as resolved even though tree branches are still resting on live primary wires!
* **Recommended Application Constraint:**
  In [`TicketAccomplishmentService.php:215-237`](file:///c:/Users/allen/Desktop/Capstone/-NEW-CRM-PALECO/PALECO_OTRS-CRM/app/Services/Tickets/TicketAccomplishmentService.php#L215-L237) and `TicketService`:
  ```php
  if ($ticket->childTickets()->whereNotIn('status', [TicketStatus::RESOLVED, TicketStatus::CLOSED])->exists()) {
      throw ValidationException::withMessages([
          'status' => 'Cannot close parent ticket: Active child sub-tasks are still pending resolution.',
      ]);
  }
  ```

---

### Constraint B-03: Concurrent Field Workload Cap per Lineman Crew
* **Domain Context:**
  When field personnel tap **"Start Work"** (`PATCH /api/tickets/{ticket}/start`), the ticket transitions to `in_progress` and records `started_at = now()`.
* **The Business Problem:**
  A 2-man field crew cannot physically climb two different utility poles in two different barangays at the exact same minute.
  If a crew starts Ticket #1, drives to another site, and starts Ticket #2 without completing Ticket #1, SLA response times and on-site duration metrics become invalid.
* **Recommended Application Constraint:**
  In `TicketService::startTicket()`:
  ```php
  $activeCount = Ticket::where('team_id', $teamId)
      ->where('status', TicketStatus::IN_PROGRESS)
      ->where('id', '!=', $ticket->id)
      ->count();

  if ($activeCount >= 1) {
      throw ValidationException::withMessages([
          'team_id' => 'This crew already has an in-progress ticket. Complete or pause the ongoing job before commencing work on another.',
      ]);
  }
  ```

---

### Constraint B-04: Offline Mobile Sync Clock Skew & Anti-Tampering Guard
* **Domain Context:**
  The Flutter mobile client records `client_started_at` and `client_accomplished_at` during offline field dead-zone operations, syncing them back via `X-Client-Timestamp`.
* **The Business Problem:**
  If a lineman's device clock is incorrectly set (e.g., set to yesterday, or fast-forwarded to next month), the system will write corrupted chronological logs, resulting in impossible metrics (such as a ticket being accomplished before it was reported!).
* **Recommended Application Constraint:**
  In [`HandleIdempotency.php`](file:///c:/Users/allen/Desktop/Capstone/-NEW-CRM-PALECO/PALECO_OTRS-CRM/app/Http/Middleware/HandleIdempotency.php) and `TicketAccomplishmentService`:
  ```php
  // 1. Cannot be in the future (allowing max 5 minutes for slight clock drift)
  if ($clientTimestamp > now()->addMinutes(5)) {
      throw ValidationException::withMessages(['client_timestamp' => 'Device timestamp cannot be in the future.']);
  }

  // 2. Cannot precede ticket creation time
  if ($clientTimestamp < $ticket->created_at->subMinutes(5)) {
      throw ValidationException::withMessages(['client_timestamp' => 'Accomplishment timestamp cannot precede ticket intake time.']);
  }
  ```

---

### Constraint B-05: Distinct Photo Evidence Hash Invariant
* **Domain Context:**
  [`SubmitAccomplishmentReportRequest.php:34`](file:///c:/Users/allen/Desktop/Capstone/-NEW-CRM-PALECO/PALECO_OTRS-CRM/app/Http/Requests/Tickets/SubmitAccomplishmentReportRequest.php#L34) mandates `min:1, max:10` photos to prove that repairs were physically completed.
* **The Business Problem:**
  A dishonest or rushed worker can upload the exact same photograph 5 times under different filenames (`photo1.jpg`, `photo2.jpg`, etc.) to fulfill the multiple-photo requirement.
* **Recommended Application Constraint:**
  In [`TicketAccomplishmentService.php:140-150`](file:///c:/Users/allen/Desktop/Capstone/-NEW-CRM-PALECO/PALECO_OTRS-CRM/app/Services/Tickets/TicketAccomplishmentService.php#L140-L150):
  Calculate the SHA-256 or MD5 hash of each uploaded photo:
  ```php
  $hashes = array_map(fn($file) => md5_file($file->getRealPath()), $data['photos']);
  if (count(array_unique($hashes)) !== count($hashes)) {
      throw ValidationException::withMessages([
          'photos' => 'Duplicate photos detected. All attached evidence photos must be distinct photographs.',
      ]);
  }
  ```

---

### Constraint B-06: Endorsement State Mutability Freeze
* **Domain Context:**
  When a supervisor determines that a ticket belongs to another department, they initiate an endorsement, placing the ticket in `pending_endorsement` status.
* **The Business Problem:**
  While the endorsement is awaiting review by the receiving department, the ticket's assignment or accomplishment should be strictly frozen. If a dispatcher reassigns the crew while an endorsement is pending, responsibility becomes ambiguous.
* **Recommended Application Constraint:**
  Ensure that all mutation routes (`assign`, `start`, `accomplish`, `createChild`) strictly assert:
  ```php
  if ($ticket->status === TicketStatus::PENDING_ENDORSEMENT) {
      throw ValidationException::withMessages([
          'status' => 'Ticket is locked pending inter-departmental endorsement review.',
      ]);
  }
  ```
  *(Note: This is already partially guarded in `assignTicket` and `requestEndorsement`, but should be enforced globally across all mutation endpoints).*

---

### Constraint B-07: Active Shift Availability & After-Hours Dispatch Flag
* **Domain Context:**
  Teams have assigned operational shifts (`shift_start` and `shift_end`).
* **The Business Problem:**
  Dispatching a non-emergency ticket at 23:00 to a crew whose shift ended at 17:00 leads to tickets sitting untouched overnight without consumer notification.
* **Recommended Application Constraint:**
  In `TicketService::assignTicket()`:
  - If the current time is outside the team's shift window:
    - If the ticket is standard priority: Require dispatcher confirmation or suggest active on-duty crews.
    - If emergency: Automatically tag the assignment as `is_overtime_dispatch = true`.

---

### Constraint B-08: Electrical Hazard Emergency Escalation & Life-Safety SLA
* **Domain Context:**
  Categories such as *"Fallen High-Voltage Primary Wire"*, *"Sparking Transformer"*, or *"Electric Post Fire"* represent life-safety emergencies.
* **The Business Problem:**
  Treating fallen high-voltage lines with the same standard 24-hour SLA as a billing inquiry creates severe public safety liability.
* **Recommended Application Constraint:**
  - Introduce an `is_emergency` flag on `ticket_categories`.
  - When an emergency category is selected:
    - SLA response timer drops to **30 minutes**.
    - System dispatches an immediate high-priority WebSocket alert on `cwd.operations`.
    - Prevents archiving or de-escalating the ticket without managerial signoff.

---

## 4. Prioritized Implementation Roadmap

To avoid disrupting the upcoming User Acceptance Testing (UAT) milestone, constraints should be implemented in three deliberate phases:

```
┌────────────────────────────────────────────────────────────────────────┐
│                   CONSTRAINT IMPLEMENTATION PHASES                     │
└──────────────────────────────────┬─────────────────────────────────────┘
                                   │
                                   ▼
 1. [Phase 1: Pre-UAT Safe Validation Fixes] (Zero Risk, FormRequests Only)
    - Add Cross-Department Team Validation (Constraint 1).
    - Add Soft-Delete Exclusion on Category & Team IDs (Constraints 2 & 5).
    - Enforce Single-Level Child Nesting in Policy (Constraint 4).
                                   │
                                   ▼
 2. [Phase 2: Mobile & Audit Integrity] (Field Operational Safety)
    - Offline Clock Skew Guard (Constraint B-04).
    - Duplicate Photo Hash Guard (Constraint B-05).
    - Segregation of Duties on Accomplishment Review (Constraint 11).
                                   │
                                   ▼
 3. [Phase 3: Post-UAT Workflow Extensions] (Schema & Lifecycle Migrations)
    - Introduce CANCELLED status (Constraint 13).
    - Parent-Child Cascading Closure Invariant (Constraint B-02).
    - SLA Dormancy Escalation Console Scheduler (Constraint 14).
    - Disconnected Consumer Warning (Constraint B-01).
```

---

## 5. Conclusion

The codebase already possesses robust validation in several areas: **Constraint 3** (same-department endorsement rejection) and **Constraint 9** (Philippine mobile phone regex) are **already fully applied and functioning**. 

Applying the remaining Tier 1 validation guards (Constraints 1, 2, 4, and 5) will eliminate cross-department dispatch anomalies and recursive nesting with zero risk of breaking existing frontend or mobile contracts. The proposed electrical utility constraints (B-01 through B-08) provide the definitive roadmap for enterprise-grade cooperative operations.

