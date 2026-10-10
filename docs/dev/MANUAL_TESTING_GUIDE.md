# PALECO OTRS-CRM: Exhaustive Progressive Manual Testing & QA Verification Guide

**Document Version:** 2.0.0 (Comprehensive Progressive Edition)  
**Target Environment:** Local Development / Staging  
**Tools Required:** Web Browser (Chrome / Edge / Firefox) & Postman Desktop / Web Agent  
**Date:** October 2026  

---

## Table of Contents
1. [Test Philosophy & Progressive Execution Model](#1-test-philosophy--progressive-execution-model)
2. [Prerequisites & Environment Setup](#2-prerequisites--environment-setup)
3. [Pre-Configured Test Accounts & Credentials](#3-pre-configured-test-accounts--credentials)
4. [Postman Environment & Global Configuration](#4-postman-environment--global-configuration)
5. [Phase 1: Authentication, Session Security & Lockout Testing](#phase-1-authentication-session-security--lockout-testing)
6. [Phase 2: Admin — Core Catalogs & Organizational Setup](#phase-2-admin--core-catalogs--organizational-setup)
   - [2.1 Department Management Module](#21-department-management-module)
   - [2.2 Field Team & Crew Management Module (Web & API)](#22-field-team--crew-management-module-web--api)
   - [2.3 Ticket Categories Catalog Module](#23-ticket-categories-catalog-module)
   - [2.4 Staff User Provisioning & Lifecycle Module](#24-staff-user-provisioning--lifecycle-module)
   - [2.5 System Monitoring & Spatie Audit Trail Module](#25-system-monitoring--spatie-audit-trail-module)
7. [Phase 3: CWD Operations — Consumer Intake & Root Ticket Creation](#phase-3-cwd-operations--consumer-intake--root-ticket-creation)
   - [3.1 CWD Live Operations Dashboard](#31-cwd-live-operations-dashboard)
   - [3.2 Consumer Verification & External Billing Integration](#32-consumer-verification--external-billing-integration)
   - [3.3 Service Ticket Intake & Sequential Numbering](#33-service-ticket-intake--sequential-numbering)
   - [3.4 Real-Time WebSocket Broadcasting Verification (Reverb)](#34-real-time-websocket-broadcasting-verification-reverb)
8. [Phase 4: Dedicated Manual Child Ticket Sub-Task Orders](#phase-4-dedicated-manual-child-ticket-sub-task-orders)
9. [Phase 5: Cross-Platform Ticket Remarks Timeline](#phase-5-cross-platform-ticket-remarks-timeline)
10. [Phase 6: Mobile Operations — Supervisor Dispatch & Management (via Postman)](#phase-6-mobile-operations--supervisor-dispatch--management-via-postman)
11. [Phase 7: Mobile Operations — Field Crew Execution & Proof Submission (via Postman)](#phase-7-mobile-operations--field-crew-execution--proof-submission-via-postman)
12. [Phase 8: Supervisory Verification & Ticket Final Closure](#phase-8-supervisory-verification--ticket-final-closure)
13. [Phase 9: Inter-Departmental Endorsement Pipeline](#phase-9-inter-departmental-endorsement-pipeline)
15. [Master Inventory of System Constraints, Business Rules & Invariants](#15-master-inventory-of-system-constraints-business-rules--invariants)
    - [15.1 Implemented Constraints, Invariants & Security Rules](#151-implemented-constraints-invariants--security-rules)
    - [15.2 Potential Missing Constraints, Unhandled Edge Cases & Business Rule Oversights](#152-potential-missing-constraints-unhandled-edge-cases--business-rule-oversights)

---

## 1. Test Philosophy & Progressive Execution Model

This testing runbook is **progressive**:
* Tests must be executed sequentially from Phase 1 through Phase 10.
* You cannot test dispatching without first creating a department, team, and ticket.
* You cannot test verification without first starting and accomplishing a ticket.
* You cannot test force-delete protections without having historical tickets linked to records.
* Every test case explicitly tests **two dimensions**:
  1. **Desired Outcome (Happy Path):** Valid inputs producing the expected state transition, database record, and audit log.
  2. **Unpredictability & Negative Boundaries (Sad Path / Edge Cases):** Empty fields, boundary string lengths, duplicate uniques, invalid enums, invalid state transitions, race conditions, cross-department intrusion, and security lockouts.

---

## 2. Prerequisites & Environment Setup

### Step 1: Database Preparation
For complete progressive verification from a clean slate:
```bash
php artisan migrate:fresh --seed
```
*Creates all tables, applies performance indexes, and seeds roles, departments, teams, categories, and test users.*

### Step 2: Storage Symlink
Ensure the public disk symlink exists for photos and digital signatures:
```bash
php artisan storage:link
```

### Step 3: Run Application Services
Open separate terminal windows and run:
1. **HTTP Web Server:**
   ```bash
   php artisan serve
   ```
   *(Running at `http://127.0.0.1:8000`)*
2. **Vite Frontend Bundler:**
   ```bash
   npm run dev
   ```
3. **Reverb WebSocket Server (Mandatory for Phase 3.4 Real-Time Tests):**
   ```bash
   php artisan reverb:start --debug
   ```

---

## 3. Pre-Configured Test Accounts & Credentials

The seeders configure four pre-linked test accounts (all passwords: `password`):

| Role | Username | Password | Operational Assignment | Authorized Channel |
| :--- | :--- | :--- | :--- | :--- |
| **Admin** | `allenglenn` | `password` | Global System Administrator | **Web Browser Only** |
| **CWD Officer** | `alliah` | `password` | Consumer Welfare Desk (Intake & Endorsements) | **Web Browser Only** |
| **Supervisor** | `mycka` | `password` | Technical Services Department (`department_id = 1`) | **Postman (Mobile API)** |
| **Field Personnel** | `ralph` | `password` | Alpha Emergency Response Crew (`team_id = 1`) | **Postman (Mobile API)** |

---

## 4. Postman Environment & Global Configuration

In Postman, create an environment named `PALECO CRM`:
* `base_url`: `http://127.0.0.1:8000/api`
* `web_url`: `http://127.0.0.1:8000`
* `supervisor_token`: *(Populated in Phase 1)*
* `field_token`: *(Populated in Phase 1)*
* `test_dept_id`: *(Populated in Phase 2)*
* `test_team_id`: *(Populated in Phase 2)*
* `test_category_id`: *(Populated in Phase 2)*
* `test_user_id`: *(Populated in Phase 2)*
* `parent_ticket_id`: *(Populated in Phase 3)*
* `child_ticket_id`: *(Populated in Phase 4)*
* `accomplishment_id`: *(Populated in Phase 7)*
* `endorsement_id`: *(Populated in Phase 9)*

### Global Request Headers for Postman:
```http
Accept: application/json
Content-Type: application/json
```

---

## Phase 1: Authentication, Session Security & Lockout Testing

### 1.1 Web Role Selection Screen
* **Route:** `GET /portal` (`portal`)
* **Test Case 1.1.1 (Render Portal):** Open `http://127.0.0.1:8000/portal`. Verify that role cards for "Administrator" and "Consumer Welfare Desk" are rendered.
* **Test Case 1.1.2 (Redirect /login):** Open `http://127.0.0.1:8000/login`. Verify automatic redirection to `/portal`.

### 1.2 Web Admin Login & Validation
* **Route:** `GET /login/admin` (`portal.login`) & `POST /login/admin` (`attemptLogin`)
* **Test Case 1.2.1 (Empty Submission):** Submit empty username and password.  
  *Expected:* Form displays validation errors: *"The username field is required."* and *"The password field is required."*
* **Test Case 1.2.2 (Invalid Credentials):** Enter `allenglenn` / `wrongpassword`.  
  *Expected:* Error flash: *"These credentials do not match our records."*
* **Test Case 1.2.3 (Role Mismatch Rejection):** Attempt to log into `/login/admin` using `alliah` / `password` (CWD role).  
  *Expected:* Login rejected with error: *"These credentials do not match our records."* (Role boundary enforcement).
* **Test Case 1.2.4 (Successful Admin Login):** Enter `allenglenn` / `password`.  
  *Expected:* Authenticates session, redirects to `http://127.0.0.1:8000/admin/dashboard`. Header displays "Administrator" badge and navigation items.

### 1.3 Web CWD Login & Session Segregation
* **Route:** `GET /login/cwd` & `POST /login/cwd`
* **Test Case 1.3.1 (Successful CWD Login):** Open private browser window, navigate to `http://127.0.0.1:8000/login/cwd`, submit `alliah` / `password`.  
  *Expected:* Redirects to `/cwd/dashboard`. Header displays "Consumer Welfare Desk" navigation.
* **Test Case 1.3.2 (Cross-Portal Access Obscurity):** In CWD session, navigate directly to `http://127.0.0.1:8000/admin/dashboard`.  
  *Expected:* HTTP `404 Not Found` (Deny-as-not-found security policy).

### 1.4 Web Logout
* **Route:** `POST /logout` (`logout`)
* **Test Case 1.4.1 (Logout Invalidation):** Click profile dropdown &rarr; click **"Log Out"**. Attempt browser Back button &rarr; Session is destroyed, redirected to `/portal`.

### 1.5 Mobile API Login (Supervisor via Postman)
* **Route:** `POST /api/login`
* **Test Case 1.5.1 (Missing Fields):** Send `{}`.  
  *Expected:* HTTP `422 Unprocessable Content` with validation errors for `username`, `password`, `device_name`.
* **Test Case 1.5.2 (Web-Only Role Rejection):**
  ```json
  {
    "username": "allenglenn",
    "password": "password",
    "device_name": "Postman"
  }
  ```
  *Expected:* HTTP `403 Forbidden` with message: *"Access denied. Mobile application access is restricted to field operations staff."*
* **Test Case 1.5.3 (Successful Supervisor Login):**
  ```json
  {
    "username": "mycka",
    "password": "password",
    "device_name": "Samsung Galaxy S24"
  }
  ```
  *Expected:* HTTP `200 OK`. Copy `data.token` and store in Postman `{{supervisor_token}}`.
* **Test Case 1.5.4 (Single Active Token Invalidation):** Send another login for `mycka` with `device_name: "Tablet"`.  
  *Expected:* Old token is revoked in database (`personal_access_tokens`), new token issued.

### 1.6 Mobile API Login (Field Personnel via Postman)
* **Route:** `POST /api/login`
* **Test Case 1.6.1 (Successful Field Login):**
  ```json
  {
    "username": "ralph",
    "password": "password",
    "device_name": "Rugged Phone 1"
  }
  ```
  *Expected:* HTTP `200 OK`. Copy `data.token` and store in Postman `{{field_token}}`.

### 1.7 Mobile Rate Limiting & Account Lockout
* **Route:** `POST /api/login`
* **Test Case 1.7.1 (5 Failed Attempts & 15-Minute Lockout):**
  Send 5 consecutive requests with `username: "ralph"`, `password: "badpass"`, `device_name: "Attacker"`.  
  *Expected on 5th failure:* HTTP `423 Locked` or `429 Too Many Requests` with message: *"Account locked due to too many failed attempts. Please try again in 15 minutes."*  
  *Database Verification:* Query `users` table &rarr; `ralph.locked_until` is populated with `now() + 15 minutes`.
* **Test Case 1.7.2 (Reset Lockout for Continued Testing):**
  Run in terminal to unlock Ralph:
  ```bash
  php artisan tinker --execute="App\Models\User::where('username', 'ralph')->update(['locked_until' => null]);"
  ```
  Re-authenticate Ralph via Test 1.6.1 to refresh `{{field_token}}`.

### 1.8 Mobile Logout
* **Route:** `POST /api/logout`
* **Test Case 1.8.1 (Token Revocation):**
  Log in temporary user, call `POST /api/logout` with Bearer token.  
  *Expected:* HTTP `200 OK`. Subsequent request with same token returns HTTP `401 Unauthorized`.

---

## Phase 2: Admin — Core Catalogs & Organizational Setup

*Prerequisite: Logged in on Web Browser as `allenglenn` / `password`.*

### 2.1 Department Management Module
* **Routes:** `/admin/departments/*`

* **Test Case 2.1.1 (View Department Index):** Navigate to `http://127.0.0.1:8000/admin/departments`.  
  *Expected:* Displays seeded departments (TSD, CWD, AOD, LCMD) with active badges and counters.
* **Test Case 2.1.2 (Create Department Form Validation):**
  Click **"+ New Department"** (`/admin/departments/create`). Submit empty form.  
  *Expected:* Validation errors for `dept_name` and `dept_code`.
* **Test Case 2.1.3 (Unique Constraint Validation):**
  Enter Name: `Technical Services Department` (Duplicate), Code: `TSD`. Submit.  
  *Expected:* Error: *"The department name has already been taken."* and *"The department code has already been taken."*
* **Test Case 2.1.4 (Create Valid Department):**
  Enter Name: `Substation Operations Division`, Code: `SOD`, Description: `High voltage switchgear maintenance`. Submit.  
  *Expected:* Flash: *"Department created successfully."* Redirects to index. Note the ID of SOD as `{{test_dept_id}}`.
* **Test Case 2.1.5 (View Department Details):**
  Click **"View"** on SOD (`/admin/departments/{{test_dept_id}}`).  
  *Expected:* Renders details page showing department metadata, 0 teams, 0 staff, and 0 tickets.
* **Test Case 2.1.6 (Edit Department):**
  Click **"Edit"** (`/admin/departments/{{test_dept_id}}/edit`). Change Description to `High voltage switchgear & grid dispatch`. Click **"Save"**.  
  *Expected:* Flash: *"Department updated successfully."*
* **Test Case 2.1.7 (Archive Department Prompt & Soft-Delete):**
  Click **"Archive"** (`/admin/departments/{{test_dept_id}}/archive`). Confirm prompt (`DELETE /admin/departments/{{test_dept_id}}`).  
  *Expected:* Flash: *"Department archived successfully."* Department badge shows "Archived".
* **Test Case 2.1.8 (Restore Department):**
  Click **"Restore"** (`PATCH /admin/departments/{{test_dept_id}}/restore`).  
  *Expected:* Flash: *"Department restored successfully."* Department badge returns to "Active".

### 2.2 Field Team & Crew Management Module (Web & API)
* **Routes:** `/admin/teams/*` (Web) & `/api/teams/*` (Mobile API)

#### Web Interface (Admin):
* **Test Case 2.2.1 (View Teams Index):** Navigate to `http://127.0.0.1:8000/admin/teams`.  
  *Expected:* Displays Alpha, Bravo, Charlie, Delta crews.
* **Test Case 2.2.2 (Team Form Validation):**
  Click **"+ New Team"** (`/admin/teams/create`). Submit empty form.  
  *Expected:* Validation errors for `team_name`, `department_id`, `shift_start`, `shift_end`.
* **Test Case 2.2.3 (Create Team with Shift & Roster Members):**
  Name: `Grid Substation Crew 1`, Department: `Substation Operations Division`, Shift Start: `06:00`, Shift End: `14:00`.  
  Attach Member: select `ralph` with role `Team Leader`. Submit.  
  *Expected:* Flash: *"Team and members created successfully."* Copy new team ULID as `{{test_team_id}}`.
* **Test Case 2.2.4 (View Team Profile & Roster):**
  Click **"View Details"** on the created team (`/admin/teams/{{test_team_id}}`).  
  *Expected:* Displays shift hours, department name, active status, and Ralph listed under members with "Team Leader" badge.
* **Test Case 2.2.5 (Edit Team):**
  Click **"Edit"** (`/admin/teams/{{test_team_id}}/edit`). Update shift end to `15:00`. Submit.  
  *Expected:* Flash: *"Team updated successfully."* Shift end reflects `03:00 PM`.

#### Mobile API (Supervisor via Postman):
* **Test Case 2.2.6 (Get Form Options via API):**
  ```http
  GET {{base_url}}/teams/team-options
  Authorization: Bearer {{supervisor_token}}
  ```
  *Expected:* HTTP `200 OK` with JSON arrays of available personnel and roles.
* **Test Case 2.2.7 (List Department Teams via API):**
  ```http
  GET {{base_url}}/teams
  Authorization: Bearer {{supervisor_token}}
  ```
  *Expected:* HTTP `200 OK`. Returns teams strictly within Supervisor's department (`TSD`). Teams belonging to other departments are excluded.
* **Test Case 2.2.8 (Cross-Department Access Guard):**
  Attempt to fetch `{{test_team_id}}` (which belongs to Substation Operations Division) as Supervisor Mycka:
  ```http
  GET {{base_url}}/teams/{{test_team_id}}
  Authorization: Bearer {{supervisor_token}}
  ```
  *Expected:* HTTP `404 Not Found` (Gate authorization obscure rejection).

### 2.3 Ticket Categories Catalog Module
* **Routes:** `/admin/ticket-categories/*`

* **Test Case 2.3.1 (View Categories Table):** Open `http://127.0.0.1:8000/admin/ticket-categories`.  
  *Expected:* Displays 8 seeded categories.
* **Test Case 2.3.2 (Category Form Validation):** Click **"+ New Category"**. Submit empty.  
  *Expected:* Validation error: *"The category name field is required."*
* **Test Case 2.3.3 (Create Category):**
  Category Name: `High Voltage Transformer Sparking`, Description: `Visible electrical arcing or oil leakage`. Submit.  
  *Expected:* Flash confirms creation. Save category ID as `{{test_category_id}}`.
* **Test Case 2.3.4 (Edit Category):** Click **"Edit"** on new category, update description, save.  
  *Expected:* Updates persist cleanly.

### 2.4 Staff User Provisioning & Lifecycle Module
* **Routes:** `/admin/users/*`

* **Test Case 2.4.1 (View Users Directory):** Open `http://127.0.0.1:8000/admin/users`.  
  *Expected:* Table displays seeded users (`allenglenn`, `alliah`, `mycka`, `ralph`) with role badges and active toggles.
* **Test Case 2.4.2 (User Form Validation):** Click **"+ New User"** (`/admin/users/create`). Submit empty.  
  *Expected:* Validation errors for `name`, `username`, `email`, `password`, `role_id`.
* **Test Case 2.4.3 (Create Staff User):**
  Name: `Carlos Lineman`, Username: `carlosl`, Email: `carlos@paleco.local`, Password: `password`, Role: `Field Personnel`, Department: `Substation Operations Division`. Click **"Save User"**.  
  *Expected:* User created with active status. Note ID as `{{test_user_id}}`.
* **Test Case 2.4.4 (User Details View):** Click **"View Profile"** on `carlosl` (`/admin/users/{{test_user_id}}`).  
  *Expected:* Profile view displays role, department, zero assigned tickets.
* **Test Case 2.4.5 (Deactivate User & Prompt):**
  Click **"Deactivate"** &rarr; Confirm prompt (`PATCH /admin/users/{{test_user_id}}/deactivate`).  
  *Expected:* Badge updates to "Inactive".
* **Test Case 2.4.6 (Blocked Deactivated Login via Postman):**
  Attempt login with `username: "carlosl"`, `password: "password"` on Postman `POST /api/login`.  
  *Expected:* Login rejected with error: *"Your account has been deactivated. Please contact your system administrator."*
* **Test Case 2.4.7 (Reactivate User):**
  On Web, click **"Reactivate"** on `carlosl` &rarr; Confirm prompt.  
  *Expected:* Status returns to "Active". Login via Postman now succeeds.

### 2.5 System Monitoring & Spatie Audit Trail Module
* **Routes:** `/admin/monitoring` (`admin.monitoring.index`)

* **Test Case 2.5.1 (Audit Timeline Inspection):** Navigate to `http://127.0.0.1:8000/admin/monitoring`.  
  *Expected:* Displays chronological table of all actions performed in Tests 2.1–2.4.
* **Test Case 2.5.2 (Causer Attribution & Metadata):** Verify that actions are attributed to `allenglenn`, showing client IP (`127.0.0.1`) and browser User-Agent.
* **Test Case 2.5.3 (Dirty Diff Inspection):** Click **"View Changes"** on the update log for `Substation Operations Division`.  
  *Expected:* Modal renders JSON diff showing `old.description` vs `new.description`.
* **Test Case 2.5.4 (Audit Log Search & Filters):**
  Filter by Subject: `Department` &rarr; Only department modifications appear. Filter by Action: `created` &rarr; Only creations appear.

---

## Phase 3: CWD Operations — Consumer Intake & Root Ticket Creation

*Prerequisite: Logged in on Web Browser as `alliah` / `password`.*

### 3.1 CWD Live Operations Dashboard
* **Route:** `GET /cwd/dashboard` (`cwd.dashboard`)
* **Test Case 3.1.1 (Dashboard KPI Metric Cards):** Open `http://127.0.0.1:8000/cwd/dashboard`.  
  *Expected:* Renders metric summary cards (Total Tickets, Open, Assigned, In Progress, Pending Endorsement, Resolved, Closed).
* **Test Case 3.1.2 (7-Day Daily Trend Chart):** Verify that the daily trend graph loads without console JavaScript errors.

### 3.2 Consumer Verification & External Billing Integration
* **Routes:** `/cwd/consumers/*`

* **Test Case 3.2.1 (Consumer Directory):** Open `http://127.0.0.1:8000/cwd/consumers`.  
  *Expected:* Table displays searchable consumer records.
* **Test Case 3.2.2 (Live Billing API Lookup):**
  Navigate to `http://127.0.0.1:8000/cwd/consumers/verify/0123456789`.  
  *Expected:*
  - If PALECO Billing API is reachable: Returns JSON payload with consumer name, account address, meter serial number.
  - If PALECO Billing API is offline: Gracefully returns local database consumer record or 404 without throwing an unhandled 500 server exception.

### 3.3 Service Ticket Intake & Sequential Numbering
* **Routes:** `/cwd/tickets/*`

* **Test Case 3.3.1 (Form Validation on Empty Submission):**
  Navigate to `http://127.0.0.1:8000/cwd/tickets/create`. Submit empty form.  
  *Expected:* Validation errors for `consumer_name`, `consumer_contact`, `purok`, `street`, `barangay`, `department_id`, `subject`, `complaint_description`.
* **Test Case 3.3.2 (Custom Category Toggle Validation):**
  Check the **"Other / Unlisted Category"** checkbox. Leave custom category name blank. Submit.  
  *Expected:* Validation error: *"Please specify the custom complaint category."*
* **Test Case 3.3.3 (Create Valid Root Ticket):**
  - Complaint Source: `Call-In`
  - Consumer Name: `Elena Dimasalang`
  - Contact: `09179876543`
  - Address: Purok `Sampaguita`, Street: `Malvar Road`, Barangay: `San Miguel`, Landmark: `Near Public Market`
  - Category: Select `High Voltage Transformer Sparking`
  - Department: Select `Technical Services Department` (Dept 1)
  - Subject: `Loud buzzing and visible sparks from pole transformer`
  - Description: `Sparking began during thunderstorm, power flickering intermittently.`
  - Submit form.  
  *Expected:*
  - Flash: *"Ticket created successfully."*
  - Redirects to `/cwd/tickets`.
  - Ticket Number matches pattern: `TKT-YYMMDD-001` (e.g. `TKT-261007-001`).
  - Status is **Open**.
  - Copy Ticket ULID from URL as `{{parent_ticket_id}}`.
* **Test Case 3.3.4 (Sequence Arithmetic & Collision Safety):**
  Immediately create a second ticket for another consumer.  
  *Expected:* Next sequence number is exactly `TKT-YYMMDD-002` (arithmetic increment without collisions).

### 3.4 Real-Time WebSocket Broadcasting Verification (Reverb)
* **Components:** Livewire `TicketTable` and `DashboardOverview` listening to `private-cwd.operations, .TicketCreated`
* **Test Case 3.4.1 (Live Ticket Table Update):**
  1. Open Browser Window A on `http://127.0.0.1:8000/cwd/tickets`. Open Browser Developer Console &rarr; Network &rarr; WS. Verify WebSocket connection to Reverb is `101 Switching Protocols`.
  2. Open Browser Window B (or Postman) and create a new ticket.
  *Expected:* In Window A, the newly created ticket automatically appears at the top of the ticket table **without manually refreshing the page**.
* **Test Case 3.4.2 (Live Dashboard Metrics Update):**
  1. Open Browser Window A on `http://127.0.0.1:8000/cwd/dashboard`.
  2. Create a ticket in Window B.
  *Expected:* The "Total Tickets" and "Open Tickets" metric counter cards increment automatically.

---

## Phase 4: Dedicated Manual Child Ticket Sub-Task Orders

*Prerequisite: Ticket `{{parent_ticket_id}}` exists from Phase 3.*

### 4.1 Spawn Child Ticket from Parent
* **Routes:** `/cwd/tickets/{ticket}/children/*`

* **Test Case 4.1.1 (Child Form Pre-fill):**
  Navigate to `http://127.0.0.1:8000/cwd/tickets/{{parent_ticket_id}}`. Click **"+ Create Child Ticket"**.  
  *Expected:* Form pre-fills consumer (`Elena Dimasalang`) and incident address.
* **Test Case 4.1.2 (Create Child Ticket 1):**
  - Department: Select `Line Construction & Maintenance Department`
  - Category: Select `Line Clearing / Tree Trimming`
  - Subject: `Tree trimming required near primary lead`
  - Description: `Branches touching high voltage lines preventing safe fuse replacement.`
  - Submit.  
  *Expected:*
  - Redirects to Child Ticket view.
  - Ticket number is formatted as `TKT-261007-001-1` (parent number with `-1` suffix).
  - Status is **Open**.
  - Save child ULID as `{{child_ticket_id}}`.
* **Test Case 4.1.3 (Create Child Ticket 2):**
  From parent ticket, spawn a second child ticket for another sub-task.  
  *Expected:* Second child number is exactly `TKT-261007-001-2`.
* **Test Case 4.1.4 (Parent Sequence Counter Isolation):**
  Create a brand new root ticket via `/cwd/tickets/create`.  
  *Expected:* Root ticket is numbered `TKT-261007-003`. The `-1` and `-2` child ticket suffixes **did not contaminate** the root ticket sequence arithmetic.
* **Test Case 4.1.5 (Hierarchy Tree Visual Verification):**
  Open parent ticket details (`/cwd/tickets/{{parent_ticket_id}}`).  
  *Expected:* **"Ticket Hierarchy Tree"** component renders showing:
  - Parent: `TKT-261007-001` (Active badge)
  - Subordinate Children: `TKT-261007-001-1` and `TKT-261007-001-2` with clickable navigation links.
* **Test Case 4.1.6 (Decoupled Lifecycle Guarantee):**
  Verify that the parent ticket status remains `Open` while children are `Open`. Closing or moving a child ticket does not force state transitions on the parent.

---

## Phase 5: Cross-Platform Ticket Remarks Timeline

### 5.1 Post Remark via Web
* **Route:** `POST /tickets/{ticket}/remarks` (`shared.tickets.remarks.store`)
* **Test Case 5.1.1 (Add Public Remark):**
  On `/cwd/tickets/{{parent_ticket_id}}`, scroll to Remarks section. Enter: *"Consumer called to confirm transformer sparking has ceased after feeder trip."* Click **"Post Remark"**.  
  *Expected:* Remark appears on the timeline with author `alliah` and timestamp.
* **Test Case 5.1.2 (Add Internal Remark):**
  Check **"Internal Staff Note"**. Enter: *"Dispatcher notified supervisor Mycka for emergency crew dispatch."* Submit.  
  *Expected:* Remark renders with "Internal Only" badge.

### 5.2 Mobile Remarks via Postman
* **Routes:** `GET /api/tickets/{ticket}/remarks` & `POST /api/tickets/{ticket}/remarks`
* **Test Case 5.2.1 (Retrieve Remarks List):**
  ```http
  GET {{base_url}}/tickets/{{parent_ticket_id}}/remarks
  Authorization: Bearer {{supervisor_token}}
  ```
  *Expected:* HTTP `200 OK` with JSON collection containing both remarks added via Web.
* **Test Case 5.2.2 (Create Mobile Remark):**
  ```http
  POST {{base_url}}/tickets/{{parent_ticket_id}}/remarks
  Authorization: Bearer {{supervisor_token}}

  {
    "remark": "Alpha crew en route to site with bucket truck."
  }
  ```
  *Expected:* HTTP `201 Created`. Refresh Web page &rarr; Mobile remark is visible immediately.

---

## Phase 6: Mobile Operations — Supervisor Dispatch & Management (via Postman)

*Prerequisite: Use `{{supervisor_token}}` (Supervisor Mycka, Dept 1 TSD).*

### 6.1 Supervisor Dashboard Metrics
* **Route:** `GET /api/dashboard`
* **Test Case 6.1.1 (Department Scoped Counts):**
  ```http
  GET {{base_url}}/dashboard
  Authorization: Bearer {{supervisor_token}}
  ```
  *Expected:* HTTP `200 OK`. `open_tickets` reflects tickets assigned to TSD. Tickets belonging to Line Construction or CWD are excluded.

### 6.2 Ticket Inbox & Query Parameters
* **Route:** `GET /api/tickets`
* **Test Case 6.2.1 (Status Filter 'all'):**
  ```http
  GET {{base_url}}/tickets?status=all
  Authorization: Bearer {{supervisor_token}}
  ```
  *Expected:* HTTP `200 OK`. Returns tickets with `meta.status_counts` single-query aggregation.
* **Test Case 6.2.2 (Search Filter):**
  ```http
  GET {{base_url}}/tickets?search=Dimasalang
  Authorization: Bearer {{supervisor_token}}
  ```
  *Expected:* HTTP `200 OK` matching consumer Elena Dimasalang.
* **Test Case 6.2.3 (Invalid Status Enum Resilience):**
  ```http
  GET {{base_url}}/tickets?status=approved
  Authorization: Bearer {{supervisor_token}}
  ```
  *Expected:* HTTP `200 OK`. Invalid status `'approved'` is ignored without corrupting SQL clauses or returning an empty result.

### 6.3 Ticket Detailed View & History
* **Routes:** `GET /api/tickets/{ticket}` & `GET /api/tickets/{ticket}/history`
* **Test Case 6.3.1 (Ticket Details):**
  ```http
  GET {{base_url}}/tickets/{{parent_ticket_id}}
  Authorization: Bearer {{supervisor_token}}
  ```
  *Expected:* HTTP `200 OK` returning full `TicketDetailedResource` with category, address, consumer, and child tickets count.
* **Test Case 6.3.2 (Ticket History Timeline):**
  ```http
  GET {{base_url}}/tickets/{{parent_ticket_id}}/history
  Authorization: Bearer {{supervisor_token}}
  ```
  *Expected:* HTTP `200 OK` returning initial creation status log.

### 6.4 Ticket Assignment (Dispatch)
* **Routes:** `GET /api/tickets/{ticket}/assign-options` & `POST /api/tickets/{ticket}/assign`
* **Test Case 6.4.1 (Get Assign Options):**
  ```http
  GET {{base_url}}/tickets/{{parent_ticket_id}}/assign-options
  Authorization: Bearer {{supervisor_token}}
  ```
  *Expected:* HTTP `200 OK`. Returns available teams in TSD (Alpha Crew, Bravo Crew). Copy Alpha Crew ULID.
* **Test Case 6.4.2 (Assign Ticket to Alpha Crew):**
  ```http
  POST {{base_url}}/tickets/{{parent_ticket_id}}/assign
  Authorization: Bearer {{supervisor_token}}

  {
    "team_id": "<alpha_crew_ulid>",
    "reason": "Immediate technical response to transformer hazard."
  }
  ```
  *Expected:* HTTP `200 OK`. Ticket status transitions to `assigned`.
* **Test Case 6.4.3 (Assign to Same Team Guard):**
  Send the exact same request again.  
  *Expected:* HTTP `422 Unprocessable Content` with error: *"This ticket is already assigned to the selected team. No changes were made."*

---

## Phase 7: Mobile Operations — Field Crew Execution & Proof Submission (via Postman)

*Prerequisite: Use `{{field_token}}` (Ralph, Alpha Emergency Response Crew).*

### 7.1 Field Personnel Profile & Inbox Scoping
* **Routes:** `GET /api/user/profile` & `GET /api/tickets`
* **Test Case 7.1.1 (Field Profile):**
  ```http
  GET {{base_url}}/user/profile
  Authorization: Bearer {{field_token}}
  ```
  *Expected:* HTTP `200 OK` showing user `ralph` and active team `Alpha Emergency Response Crew`.
* **Test Case 7.1.2 (Team-Scoped Field Inbox):**
  ```http
  GET {{base_url}}/tickets
  Authorization: Bearer {{field_token}}
  ```
  *Expected:* Returns `{{parent_ticket_id}}` (assigned to Alpha Crew). Tickets assigned to Bravo or other teams are not returned.

### 7.2 Start Work on Ticket
* **Route:** `PATCH /api/tickets/{ticket}/start`
* **Test Case 7.2.1 (Start Unassigned Ticket Guard):**
  Attempt to start Child Ticket `{{child_ticket_id}}` (which is still `Open` and unassigned):
  ```http
  PATCH {{base_url}}/tickets/{{child_ticket_id}}/start
  Authorization: Bearer {{field_token}}
  ```
  *Expected:* HTTP `422 Unprocessable Content`: *"Only assigned tickets can be started."*
* **Test Case 7.2.2 (Start Assigned Ticket - Success):**
  ```http
  PATCH {{base_url}}/tickets/{{parent_ticket_id}}/start
  Authorization: Bearer {{field_token}}
  ```
  *Expected:* HTTP `200 OK` with message: *"Work has started on ticket TKT-..."*. Status transitions to `in_progress`.
* **Test Case 7.2.3 (Already In Progress Guard):**
  Send the same request again on `{{parent_ticket_id}}`.  
  *Expected:* HTTP `422 Unprocessable Content`: *"This ticket is already in progress."*

### 7.3 Submit Accomplishment Report (Multipart Form-Data)
* **Route:** `POST /api/tickets/{ticket}/accomplish`
* **Test Case 7.3.1 (Status Guard - Must Be In Progress):**
  Attempt to submit accomplishment on an unstarted ticket &rarr; Rejected with HTTP `422`.
* **Test Case 7.3.2 (Submit Valid Accomplishment with Proof):**
  In Postman, select `Body` &rarr; `form-data`:
  - `remarks` (text, required): `Replaced defective 25kVA lightning arrester and high-voltage jumper wire. Passed insulation resistance test.`
  - `consumer_name` (text, optional): `Juan Dela Cruz`
  - `signature` (file, required): Upload sample image `.png` (Max 5MB)
  - `photos[]` (file, required): Upload image 1 `.jpg` (Max 10MB)
  - `photos[]` (file): Upload image 2 `.jpg` (Max 10MB)
  - Send request.  
  *Expected:*
  - HTTP `201 Created`.
  - Ticket status transitions to `resolved`.
  - Save `data.id` as `{{accomplishment_id}}`.
* **Test Case 7.3.3 (Duplicate Pending Submission Guard):**
  Attempt to submit another accomplishment report on the same ticket.  
  *Expected:* HTTP `422 Unprocessable Content`: *"This ticket already has a pending accomplishment report under supervisor review."*

---

## Phase 8: Supervisory Verification & Ticket Final Closure

*Prerequisite: Ticket is in `resolved` status with accomplishment `{{accomplishment_id}}`.*

### 8.1 List & Inspect Accomplishments (API & Web)
* **Routes:** `GET /api/tickets/{ticket}/accomplishments` & `GET /cwd/tickets/{ticket}/accomplishments/{id}`
* **Test Case 8.1.1 (Supervisor List Accomplishments via API):**
  ```http
  GET {{base_url}}/tickets/{{parent_ticket_id}}/accomplishments
  Authorization: Bearer {{supervisor_token}}
  ```
  *Expected:* HTTP `200 OK` returning submitted accomplishment details, photo links, and signature path.
* **Test Case 8.1.2 (CWD Web Accomplishment Inspection):**
  In Web browser as CWD Officer, open `/cwd/tickets/{{parent_ticket_id}}`. Click **"View Accomplishment Report"**.  
  *Expected:* Lightbox modal displays uploaded photos and customer digital signature.

### 8.2 Accomplishment Verification Rejection Flow
* **Route:** `POST /api/tickets/{ticket}/accomplishments/{id}/verify`
* **Test Case 8.2.1 (Missing Rejection Reason Guard):**
  ```http
  POST {{base_url}}/tickets/{{parent_ticket_id}}/accomplishments/{{accomplishment_id}}/verify
  Authorization: Bearer {{supervisor_token}}

  {
    "status": "rejected"
  }
  ```
  *Expected:* HTTP `422 Unprocessable Content`: *"The rejection reason field is required when status is rejected."*
* **Test Case 8.2.2 (Execute Rejection):**
  Send with `"rejection_reason": "Ground resistance test reading missing; please re-measure before signoff."`.  
  *Expected:*
  - HTTP `200 OK`.
  - Accomplishment status is `rejected`.
  - Ticket status **reverts back to `in_progress`**. `resolved_at` is reset to null.

### 8.3 Re-Accomplish & Approval (Final Ticket Closure)
* **Test Case 8.3.1 (Field Crew Re-submits Accomplishment):**
  Using Ralph's token (`{{field_token}}`), submit a new accomplishment via Test 7.3.2 with `remarks`: *"Ground test measured 4.2 ohms (passed). Final proof attached."*  
  *Expected:* HTTP `201 Created`. Status transitions back to `resolved`. Note new ID as `{{accomplishment_id}}`.
* **Test Case 8.3.2 (Supervisor Approves & Closes Ticket):**
  ```http
  POST {{base_url}}/tickets/{{parent_ticket_id}}/accomplishments/{{accomplishment_id}}/verify
  Authorization: Bearer {{supervisor_token}}

  {
    "status": "approved"
  }
  ```
  *Expected:*
  - HTTP `200 OK`.
  - Ticket status transitions to `closed`. `closed_at` is timestamped.
  - Active assignment record in `ticket_assignments` has `unassigned_at` timestamped (no dangling open assignment).
* **Test Case 8.3.3 (Double Evaluation Guard):**
  Attempt to verify the same accomplishment report again.  
  *Expected:* HTTP `422 Unprocessable Content`: *"This accomplishment report has already been evaluated."*

---

## Phase 9: Inter-Departmental Endorsement Pipeline

*Prerequisite: Create a fresh ticket `TKT-261007-004` assigned to TSD to test the endorsement workflow.*

### 9.1 Supervisor Submits Endorsement Request
* **Routes:** `GET /api/tickets/{ticket}/endorse-options` & `POST /api/tickets/{ticket}/endorse`
* **Test Case 9.1.1 (Get Endorse Candidate Departments):**
  ```http
  GET {{base_url}}/tickets/<fresh_ticket_id>/endorse-options
  Authorization: Bearer {{supervisor_token}}
  ```
  *Expected:* HTTP `200 OK` returning departments excluding TSD (AOD, LCMD, CWD).
* **Test Case 9.1.2 (Submit Endorsement Request):**
  ```http
  POST {{base_url}}/tickets/<fresh_ticket_id>/endorse
  Authorization: Bearer {{supervisor_token}}

  {
    "suggested_department_id": 4,
    "reason": "Requires heavy line construction crew and boom truck; outside TSD technical scope."
  }
  ```
  *Expected:*
  - HTTP `201 Created`.
  - Ticket status transitions to `pending_endorsement`.
  - Pre-endorsement status is stored on the endorsement record.
* **Test Case 9.1.3 (Duplicate Endorsement Guard):**
  Send the endorsement request again.  
  *Expected:* HTTP `422 Unprocessable Content`: *"This ticket already has a pending endorsement request."*

### 9.2 CWD Review & Decision (Web)
* **Routes:** `/cwd/endorsements/*`
* **Test Case 9.2.1 (Endorsement Queue Inspection):**
  On Web as CWD Officer, open `http://127.0.0.1:8000/cwd/endorsements`.  
  *Expected:* Pending tab displays the endorsement request with originating reason and suggested department.
* **Test Case 9.2.2 (Endorsement Details Page):**
  Click **"Review Endorsement"** (`/cwd/endorsements/{{endorsement_id}}`).  
  *Expected:* Renders review screen with Accept/Reject radio options and department selector.
* **Test Case 9.2.3 (Decision: Accept & Endorse):**
  Select **"Accept & Endorse"**, select target department `Line Construction & Maintenance Department`. Submit.  
  *Expected:*
  - Flash confirms approval.
  - Parent ticket transitions to `endorsed`. Active team assignment is closed (`unassigned_at = now()`).
  - Child ticket is **automatically spawned** for the target department (`LCMD`) with status `Open`.
  - Both parent and auto-child reflect linked hierarchy tree.

---

## Phase 10: Destructive Lifecycles, Referential Integrity & Force Deletes

*Prerequisite: Logged in on Web as Admin `allenglenn`.*

### 10.1 Active Dependency Deletion Guards
* **Test Case 10.1.1 (Cannot Archive Team with Active Tickets):**
  Attempt to archive Alpha Crew while it has active tickets.  
  *Expected:* Blocked with error: *"Cannot archive team while active tickets are assigned."*
* **Test Case 10.1.2 (Cannot Force Delete Team with Historical Tickets):**
  Attempt to permanently delete a team that has served historical tickets.  
  *Expected:* Blocked with error: *"Cannot permanently delete team with associated historical service tickets."*
* **Test Case 10.1.3 (Cannot Force Delete Department with Teams):**
  Attempt to force-delete `Substation Operations Division` while it has child teams.  
  *Expected:* Foreign key / policy constraint prevents deletion.

### 10.2 Clean Entity Purge (Unlinked Data)
* **Test Case 10.2.1 (Create Temporary Entity):** Create a test department `Temporary QA Division`.
* **Test Case 10.2.2 (Soft Archive):** Archive `Temporary QA Division`. Badge becomes "Archived".
* **Test Case 10.2.3 (Permanent Force Delete):** Click **"Delete"** &rarr; Confirm prompt (`DELETE /admin/departments/{id}/force-delete`).  
  *Expected:* Record is permanently purged from `departments` table. Spatie audit log records action as *"permanently deleted"*.

---

## Phase 11: Field Personnel Offline-Asynchronous & Idempotency Testing (Postman Suite)

*Prerequisite: Field personnel bearer token (`{{field_token}}` from logging in as `ralph` / `password`), and a ticket in `assigned` status assigned to Ralph's Alpha Crew.*

### Dynamic Data Injection Criteria for Testing
Do not hardcode static dates. Inspect your test ticket's `reported_at` (e.g. `15:24`) and your current server clock (e.g. `15:35`):
1. **Lower Bound:** `X-Client-Timestamp` must be $\ge$ `reported_at` (e.g. `2026-10-10T15:26:00+08:00`).
2. **Upper Bound:** `X-Client-Timestamp` must be $\le$ `now() + 5 minutes`.
3. **Offline Classification:** If `X-Client-Timestamp` is older than `now() - 5 minutes`, the backend flags `is_offline_synced = true` and records `synced_at = now()`.

### 11.1 Simulating Offline `/start`
* **Method:** `PATCH`
* **URL:** `http://127.0.0.1:8000/api/tickets/{{ticket_id}}/start`
* **Headers:**
  ```http
  Authorization: Bearer {{field_token}}
  Accept: application/json
  X-Idempotency-Key: {{$guid}}
  X-Client-Timestamp: <Valid timestamp >= reported_at and <= now + 5m>
  ```
* **Expected Response (`200 OK`):**
  - `status: "in_progress"`
  - `is_offline_synced: true`
  - `synced_at`: Current server timestamp
  - `started_at`: Matches injected `X-Client-Timestamp`
  - Header: `X-Cache: MISS`

### 11.2 Duplicate Replay on `/start` (Network Interruption Simulation)
* **Action:** Re-send the exact same request from 11.1 without changing the `X-Idempotency-Key`.
* **Expected Response (`200 OK`):**
  - HTTP `200 OK` returned immediately from idempotency cache.
  - Header: `X-Cache: HIT`.
  - Database verification: Only **one** status log exists in `ticket_status_logs`.

### 11.3 Simulating Offline `/accomplish` (Multipart Upload)
* **Method:** `POST`
* **URL:** `http://127.0.0.1:8000/api/tickets/{{ticket_id}}/accomplish`
* **Headers:**
  ```http
  Authorization: Bearer {{field_token}}
  Accept: application/json
  X-Idempotency-Key: {{$guid}}
  X-Client-Timestamp: <Timestamp >= started_at and <= now + 5m>
  ```
* **Body (`form-data`):**
  - `remarks`: `"Replaced high voltage fuse cutoff and restored feeder power."`
  - `consumer_name`: `"Juan Dela Cruz"`
  - `signature`: File (`signature.png`)
  - `photos[]`: File (`photo1.jpg`)
* **Expected Response (`201 Created`):**
  - `status: "pending"`
  - `is_offline_synced: true`
  - `synced_at`: Current server timestamp
  - `accomplished_at`: Matches injected `X-Client-Timestamp`
  - Header: `X-Cache: MISS`

### 11.4 Replay Protection on Multipart Upload
* **Action:** Re-send the exact request from 11.3 with the same `X-Idempotency-Key`.
* **Expected Response (`201 Created`):**
  - HTTP `201 Created` returned from cache (`X-Cache: HIT`).
  - No duplicate photos written to disk storage.

### 11.5 Chronological Barrier Validation (Negative Test)
* **Action:** Send `/start` with `X-Client-Timestamp` earlier than `reported_at` (e.g. `08:00 AM` when ticket was created at `03:24 PM`).
* **Expected Response (`422 Unprocessable Content`):**
  - Error: `"The client timestamp cannot be earlier than ticket creation date."`

### 11.6 Future Date Tampering Guardrail (Negative Test)
* **Action:** Send `/start` with `X-Client-Timestamp` set to tomorrow (`now() + 24 hours`).
* **Expected Response (`422 Unprocessable Content`):**
  - Error: `"The client timestamp cannot be in the future."`

---

## 15. Master Inventory of System Constraints, Business Rules & Invariants

### 15.1. Implemented Constraints, Invariants & Security Rules
This table consolidates all underlying business invariants, database constraints, and edge-case guards currently enforced across the backend:

| Scope | Constraint / Invariant Rule | Backend Enforcement Mechanism |
| :--- | :--- | :--- |
| **Authentication** | Mobile API is strictly barred to Web-only roles (`admin`, `cwd_officer`). | Gate check in `MobileAuthService` &rarr; returns `403 Forbidden`. |
| **Security** | 5 failed login attempts triggers persistent database lockout. | `HandlesAuthSecurity` trait sets `users.locked_until = now() + 15 mins`. |
| **Session Control** | Single active mobile session per user. | `MobileAuthService::login` runs `$user->tokens()->delete()` before issuing new token. |
| **Authorization** | Privileged route enumeration protection. | Policies use `Response::denyAsNotFound()` returning HTTP `404` instead of `403`. |
| **Data Privacy** | Supervisors can only view and manage tickets/teams in their assigned department. | Scope query `$query->where('department_id', $user->department_id)` in `TicketService` & `TeamService`. |
| **Data Privacy** | Field personnel can only view tickets assigned to their active teams. | Scope query `$query->whereIn('team_id', $user->teams()->pluck('teams.id'))`. |
| **Ticket Numbering** | Root ticket sequence arithmetic must never match child tickets. | `TicketService::generateSequentialNumber` uses `whereNull('parent_ticket_id')`. |
| **Concurrency** | Sequential ticket number collision mitigation. | Database transaction with 3-attempt exponential retry loop on MySQL Error `1062`. |
| **Concurrency** | State mutations protected against simultaneous field-crew races. | `Ticket::lockForUpdate()->firstOrFail()` across assign, start, accomplish, verify, endorse. |
| **State Machine** | Tickets can only be started if currently in `assigned` status. | `TicketService::startTicket` rejects any non-assigned ticket with `ValidationException`. |
| **State Machine** | Cannot start a ticket that is already `in_progress`. | `TicketService::startTicket` explicitly checks `status === IN_PROGRESS`. |
| **State Machine** | Tickets can only be accomplished if currently `in_progress`. | `TicketAccomplishmentService::accomplishTicket` checks `status !== IN_PROGRESS`. |
| **State Machine** | Cannot submit multiple pending accomplishment reports on one ticket. | `TicketAccomplishmentService` checks `$ticket->accomplishments()->where('status', PENDING)->exists()`. |
| **State Machine** | Cannot endorse tickets that are resolved, closed, or already pending endorsement. | `TicketEndorsementService::requestEndorsement` enforces `allowedStatuses` whitelist. |
| **Assignment Lifecycle** | Open team assignment history records must be closed on transfer/closure. | Updates active assignment `unassigned_at = now()` on endorsement and final closure. |
| **Accomplishment Proof** | Storage failure during upload must not leave orphaned database records or files. | `TicketAccomplishmentService` catches storage exceptions and runs disk cleanup rollbacks. |
| **Accomplishment Review** | Rejection requires a mandatory written reason and resets ticket to `in_progress`. | `VerifyAccomplishmentRequest` enforces `required_if:status,rejected` and resets `resolved_at = null`. |
| **Endorsement Reversal** | Rejected endorsements must return the ticket to its exact pre-endorsement status. | `TicketEndorsementService` restores parent ticket to `pre_endorsement_status`. |
| **Child Ticket Decoupling** | Child tickets have independent lifecycles; closing a parent never force-closes children. | Child tickets are standalone entities with foreign `parent_ticket_id` references. |
| **Broadcasting Safety** | WebSocket events must only fire if the database transaction commits. | `DB::afterCommit(fn () => TicketCreated::dispatch(...))` in `TicketService`. |
| **Broadcasting Safety** | Null-safe department resolution during unassigned ticket events. | `$this->ticket->department?->slug ?? 'cwd'` in `TicketCreated.php`. |
| **Team Management** | Cannot archive a team that has active assigned tickets (`open`, `assigned`, `in_progress`, `resolved`). | `TeamService::archiveTeam` validates active ticket associations. |
| **Referential Integrity** | Cannot permanently purge a team that has served historical tickets. | `TeamService::forceDeleteTeam` checks `Ticket::where('team_id', ...)->exists()`. |
| **Route Model Binding** | Trashed teams and categories must resolve type-hinted Eloquent model bindings. | `routes/web.php` and `routes/api.php` apply `->withTrashed()` on restore/destroy endpoints. |
| **Auditing Integrity** | Spatie activity logs must only record genuine attribute modifications. | Centralized `Auditable` trait calls `logOnlyDirty()` and `dontLogEmptyChanges()`. |

---

### 15.2. Potential Missing Constraints, Unhandled Edge Cases & Business Rule Oversights
The following table outlines potential missing constraints, unhandled business rules, and subtle operational oversights identified during the architectural audit. These represent areas where user unpredictability or malicious payloads could bypass intended workflow rules:

| # | Feature / Subsystem | Potential Missing Constraint or Oversight | Real-World Risk & Consequence | Recommended Backend Remediation |
| :-: | :--- | :--- | :--- | :--- |
| **1** | **Ticket Assignment** (`AssignTicketRequest`) | **No Cross-Department Team Validation:** The request validates `'team_id' => 'exists:teams,id'`, but does not verify that `teams.department_id === tickets.department_id`. | A rogue or malfunctioning API client can assign a Technical Services ticket to an Area Operations or Consumer Welfare team. | Add `Rule::exists('teams', 'id')->where('department_id', $ticket->department_id)->whereNull('deleted_at')` to `AssignTicketRequest`. |
| **2** | **Ticket Assignment** (`AssignTicketRequest`) | **No Soft-Delete Exclusion on Team ID:** Standard `exists:teams,id` passes even if `deleted_at IS NOT NULL`. | Tickets can be dispatched to archived/decommissioned field crews via direct API calls. | Scope the validation rule with `->whereNull('deleted_at')`. |
| **3** | **Endorsement Pipeline** (`TicketEndorsementService`) | **No Same-Department Rejection:** `requestEndorsement()` does not reject endorsements where `suggested_department_id === ticket.department_id`. | A supervisor can initiate an endorsement proposing a transfer back into their own department. | Add guard: `if ($data['suggested_department_id'] === $ticket->department_id) throw ValidationException::withMessages(['suggested_department_id' => 'Cannot endorse to current department.']);`. |
| **4** | **Child Ticket Spawning** (`TicketPolicy::createChild`) | **Unbounded Child Nesting Depth:** `createChild` can be invoked on tickets where `parent_ticket_id IS NOT NULL`. | Users can spawn children from children recursively (`TKT-YYMMDD-001-1-1-1`), breaking standard 2-level reporting and UI trees. | In `TicketPolicy::createChild`, enforce `is_null($ticket->parent_ticket_id)` to mandate that only root tickets can spawn children. |
| **5** | **Ticket Intake** (`StoreTicketRequest`) | **No Soft-Delete Exclusion on Category ID:** `category_id` checks `exists:ticket_categories,id` without checking `deleted_at`. | If an admin archives a category, existing open forms or cached requests can still assign tickets to the archived category. | Update rule to: `Rule::exists('ticket_categories', 'id')->whereNull('deleted_at')`. |
| **6** | **Staff Management** (`UserController::deactivate`) | **Deactivating Users with Active Field Duties:** No check prevents deactivating a lineman who is the sole member of a crew or has in-progress tickets. | Field crews become empty while tickets remain `in_progress` under an unreachable worker account. | Check `Ticket::where('team_id', ...)->whereIn('status', [ASSIGNED, IN_PROGRESS])` and warn the administrator before deactivation. |
| **7** | **Crew Rosters** (`TeamService` / `StoreTeamRequest`) | **Overlapping Shifts for Multi-Team Members:** Linemen can be added to multiple teams with overlapping shift times. | A field worker could be scheduled to work on two different crews simultaneously (e.g., 08:00–17:00 and 12:00–20:00). | Add a shift conflict detector in `TeamService` when syncing team members. |
| **8** | **Crew Shifts** (`StoreTeamRequest`) | **Zero-Minute Shift Durations & Overnight Shifts:** Validates only `date_format:H:i`. Does not require `shift_end` to differ from `shift_start`. | Admins can enter `shift_start: 08:00` and `shift_end: 08:00`. Overnight crews (e.g. 22:00 to 06:00 where end < start) lack explicit midnight-wrap flags. | Add `different:shift_start` validation rule and add an `is_overnight` boolean flag. |
| **9** | **Ticket Intake** (`StoreTicketRequest`) | **Unvalidated Consumer Phone Number Format:** `consumer_contact` accepts any string up to 50 characters (`string, max:50`). | Operators can enter `"none"`, `"N/A"`, or invalid characters, preventing SMS dispatch or follow-up calls. | Validate with Philippine mobile regex: `regex:/^(09|\+639)\d{9}$/` or `regex:/^[0-9+\-\s()]{7,20}$/`. |
| **10** | **Consumer Intake** (`StoreTicketRequest`) | **No Duplicate Intake Warning on Same Account/Meter:** Unlimited tickets can be created for the same meter/account code within minutes. | Massive duplicate ticket sprawl during neighborhood blackouts where 20 neighbors call about the same transformer. | Display an advisory warning if `Ticket::where('account_code', ...)->whereIn('status', ['open', 'assigned', 'in_progress'])->exists()`. |
| **11** | **Accomplishment Review** (`TicketAccomplishmentService`) | **Segregation of Duties (Self-Verification):** Does not check if the supervisor verifying the report is the same person who accomplished it. | In smaller branches where a supervisor also works as a lineman, they could approve their own repair work without independent oversight. | In `verifyAccomplishment()`, enforce `if ($accomplishment->accomplished_by_id === $supervisor->id) throw ValidationException...`. |
| **12** | **Accomplishment Uploads** (`SubmitAccomplishmentReportRequest`) | **Total Request Payload vs PHP `post_max_size`:** Allows 10 photos up to 10MB each (100MB) + 5MB signature. If `post_max_size` is 8MB, PHP wipes `$_POST`. | Submissions from mobile devices with high-res photos silently fail or cause HTTP 422 with empty fields. | Add client-side compression or enforce total payload size check; verify server `php.ini` has `post_max_size = 120M` or adjust maximum upload size rules. |
| **13** | **Ticket Lifecycle** (`TicketStatus`) | **No Cancellation / Voiding Pathway for False Alarms:** Because force-closure is deferred, tickets currently have NO path to cancellation. | If a caller reports a blackout that turns out to be their own main breaker, the ticket must still undergo full dispatch, photos, and verification to close. | Introduce a `cancelled` status with mandatory written justification for CWD supervisors. |
| **14** | **Ticket SLA** (`TicketService`) | **No Dormancy Escalation Timer on Open Tickets:** Tickets can sit in `Open` status indefinitely if a supervisor never opens their mobile app. | Urgent public hazard tickets (e.g., fallen live electric post) can be forgotten without automated escalation alerts. | Implement a scheduled console command checking tickets in `Open` status older than $X$ hours and sending notification alerts. |

