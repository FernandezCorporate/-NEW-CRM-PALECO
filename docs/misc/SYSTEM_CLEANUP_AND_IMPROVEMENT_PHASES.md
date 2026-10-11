# PALECO OTRS-CRM: Architectural Cleanup & Hardening Implementation Plan (6 Phases)

**Document Target:** `docs/misc/SYSTEM_CLEANUP_AND_IMPROVEMENT_PHASES.md`  
**Document Version:** 1.0  
**Context:** Technical analysis, architectural critique, and execution blueprint for 6 key system cleanup concerns across API, query scopes, cybersecurity error handling, Web UX, profile hydration, and authentication.  
**Execution Strategy:** 6 isolated phases corresponding to **6 atomic Git commits**.  
**Current Code State:** Phases 1-3 committed by user. Phase 4 implemented and verified in working tree. Phase 5-6 pending implementation. Git commits deferred to user.

---

## Executive Summary & Unfiltered Overview

This document presents a comprehensive, unfiltered technical review of the 6 concerns raised. It provides architectural justifications, identifies potential security or UX pitfalls, and outlines exact file modifications for each phase.

| Phase | Concern / Scope | Primary Impact Surface | Commit Scope |
| :---: | :--- | :--- | :--- |
| **Phase 1** | Reassignment Reason Visibility | API Resources (`TicketHistoryResource`, `TicketDetailedResource`, `TicketAssignmentController`) | Expose dispatch/reassignment reasons across detail & history endpoints |
| **Phase 2** | Model Scope Unification | Eloquent Models (`Ticket.php`, `Team.php`), Livewire Web Components, API Services | Unify `scope...` and `scopeApi...` into polymorphic, single-source query filters |
| **Phase 3** | The 404 Paradox & Information Leakage | Authorization Policies (`TicketPolicy`, `TicketEndorsementPolicy`, `TeamPolicy`) | Harden authorization to return 404 for object-level privacy while keeping semantic HTTP codes for client logic |
| **Phase 4** | Universal Web 404 UX & Smart Navigation | Blade Templates (`errors/404.blade.php`), Route Exception Handlers | Brand-consistent 404 page with dynamic history backtracking and dashboard fallbacks |
| **Phase 5** | Field Personnel Profile Team Hydration | User Services & Resources (`ProfileService.php`, `UserResource.php`) | Conditionally eager-load and expose assigned teams for field personnel in `/user/profile` |
| **Phase 6** | Auth Hardening & Remaining Attempts | Authentication Services (`AuthService.php`, `MobileAuthService.php`, `HandlesAuthSecurity.php`) | Eradicate portal enumeration/oracle attacks and provide user-friendly remaining attempt countdowns |

---

## Phase 1: Reassignment Reason Visibility Across API Endpoints [STATUS: IMPLEMENTED & VERIFIED]

### 1.1 Unfiltered Architectural Analysis & Verification
* **Current State in `/api/tickets/{ticket}/history`:**
  - In `PALECO_OTRS-CRM/app/Http/Resources/Api/TicketHistoryResource.php`, the history endpoint returns:
    ```json
    {
      "assignments": [
        {
          "team_name": "Alpha Response Crew",
          "assigned_by": "Mycka Supervisor",
          "assigned_by_role": "Supervisor",
          "assignment_reason": "Replaced blown fuse on feeder 2",
          "assigned_at": "Oct 10, 2026 09:30 AM",
          "unassigned_at": null
        }
      ]
    }
    ```
  - **Verdict:** `/api/tickets/{ticket}/history` **does** return the reason, but it maps `$this->reason` to the key `"assignment_reason"` in `TicketAssignmentResource`.
* **The Missing Gap in the System:**
  1. **`GET /api/tickets/{ticket}` (`TicketDetailedResource`):**
     - When a field lineman or supervisor opens the ticket detail screen, `TicketDetailedResource` returns the complainant, the category, the assigned team, and status logs.
     - **It completely omits the active assignment reason.** The lineman sees they are assigned to the ticket, but they **cannot see why** or read the supervisor's dispatch instructions without firing an extra network request to `/history`.
  2. **`POST /api/tickets/{ticket}/assign` (`TicketAssignmentController`):**
     - When a supervisor assigns or reassigns a ticket, the controller responds with `TicketResource`, which only returns general summary fields (ticket number, status, team_name), but does not return the newly saved assignment reason or assignment record in the response payload.

### 1.2 Proposed Architecture & Implementation Plan
1. **Define Active Assignment Relationship on `Ticket` Model:**
   - In `app/Models/Ticket.php`, add a dedicated relationship:
     ```php
     public function activeAssignment(): HasOne
     {
         return $this->hasOne(TicketAssignment::class)->whereNull('unassigned_at')->latestOfMany();
     }
     ```
2. **Update `TicketService::getDetailedTicketMobile`:**
   - Eager-load `activeAssignment.assigner.role` in addition to `team.members`.
3. **Enhance `TicketDetailedResource`:**
   - Add an `assignment` block inside `TicketDetailedResource`:
     ```php
     'active_assignment' => $this->whenLoaded('activeAssignment', function () {
         if (! $this->activeAssignment) return null;
         return [
             'team_name' => $this->activeAssignment->team?->team_name,
             'assigned_by' => $this->activeAssignment->assigner?->full_name,
             'assigned_by_role' => $this->activeAssignment->assigner?->role?->role_name,
             'reason' => $this->activeAssignment->reason,
             'assigned_at' => $this->activeAssignment->created_at?->format('M d, Y h:i A'),
         ];
     }),
     ```
4. **Update `TicketAssignmentController::assign` Response:**
   - Include the assignment reason and assigner in the mutation response payload so mobile clients get immediate state confirmation without re-fetching.

### 1.3 Files Affected
- `PALECO_OTRS-CRM/app/Models/Ticket.php`
- `PALECO_OTRS-CRM/app/Services/Tickets/TicketService.php`
- `PALECO_OTRS-CRM/app/Http/Resources/Api/TicketDetailedResource.php`
- `PALECO_OTRS-CRM/app/Http/Controllers/Api/Tickets/TicketAssignmentController.php`
- `docs/dev/API_DOCUMENTATION.md`

### 1.4 Verification & Testing Plan
- Execute `GET /api/tickets/{ticket}` as field personnel: Verify `active_assignment.reason` is populated with the supervisor's reassignment note.
- Execute `GET /api/tickets/{ticket}/history`: Verify full chronological array retains `assignment_reason`.
- Re-run Pest/PHPUnit test suite to ensure existing JSON snapshots pass.

### 1.5 Proposed Commit Message
```text
feat(api): expose active assignment and dispatch reason on ticket detail and mutation endpoints

- Add activeAssignment hasOne relationship to Ticket model
- Eager load activeAssignment in TicketService::getDetailedTicketMobile
- Expose active_assignment payload including reason in TicketDetailedResource
- Document updated API response envelopes in API_DOCUMENTATION.md
```

---

## Phase 2: Unification of Query Scopes Between Web and Mobile [STATUS: IMPLEMENTED & VERIFIED]

### 2.1 Unfiltered Architectural Analysis: Should We Unify Them?
* **Current State in `Ticket.php` & `Team.php`:**
  - `Ticket.php` currently maintains two parallel sets of scopes:
    - Web: `scopeSearch`, `scopeFilterByCategory`, `scopeFilterByStatus`, `scopeSort`
    - API: `scopeApiSearch`, `scopeApiFilterByCategoryName`, `scopeApiFilterByStatus`, `scopeApiSort`
  - `Team.php` maintains:
    - Web: `scopeFilter`, `scopeSort`
    - API: `scopeApiSearch`, `scopeApiFilterStatus`, `scopeApiSort`
* **Unfiltered Assessment:**
  - **The Anti-Pattern:** Maintaining dual scopes violates DRY (Don't Repeat Yourself) and introduces **divergent search behavior**. For instance:
    - On Web, `scopeSearch` only searches `ticket_number`, `complaint_description`, `barangay`, and `other_category_name`.
    - On Mobile API, `scopeApiSearch` searches those PLUS `complaint_source`, `purok`, `street`, `status`, and joins `ticket_categories.category_name`!
    - A web user typing `"Poblacion"` or searching a category name might get zero results, while a mobile user gets all results for the identical database record!
  - **The Catch (Why They Were Split Initially):**
    - The Web Livewire dropdown sends `category_id` (numeric `1, 2, 3`), whereas the Mobile API client was designed to send `category_name` (string `"Power Outage"`).
    - If you naively delete `scopeApiFilterByCategoryName` and force the API to take integer IDs, you break the mobile contract.
* **The Unified Architectural Solution:**
  - Unify into single, polymorphic scopes:
    1. **`scopeSearch($query, ?string $term)`:** Adopt the comprehensive multi-field search across both Web and Mobile.
    2. **`scopeFilterByCategory($query, ?string $filter)`:** Make it polymorphic:
       - If `$filter` is numeric $\rightarrow$ `where('category_id', $filter)` (Web Livewire dropdown).
       - If `$filter === 'other'` $\rightarrow$ `where('other_category', true)`.
       - If `$filter` is a string name $\rightarrow$ `whereRelation('category', 'category_name', $filter)` (Mobile API).
    3. **`scopeFilterByStatus($query, ?string $status)`:** Accept either string value or `TicketStatus` enum instance; gracefully ignore `'all'` or empty values.
    4. **`scopeSort($query, ?string $sort)`:** Standardize sorting keywords (`oldest`, `latest`, `ticket_number_asc`, `ticket_number_desc`).
  - **Safe Transition Mechanism:** Maintain `scopeApiSearch`, `scopeApiFilterByStatus`, etc. as thin 1-line aliases pointing to the unified scope (e.g. `return $this->scopeSearch($query, $search);`), preventing breaking changes while eliminating duplicated query logic.

### 2.2 Files Affected
- `PALECO_OTRS-CRM/app/Models/Ticket.php`
- `PALECO_OTRS-CRM/app/Models/Team.php`
- `PALECO_OTRS-CRM/app/Services/Tickets/TicketService.php`
- `PALECO_OTRS-CRM/app/Services/Teams/TeamService.php`
- `PALECO_OTRS-CRM/app/Livewire/Cwd/TicketTable.php` (if applicable)

### 2.3 Verification & Testing Plan
- Test Web Livewire ticket search by category ID, purok, street, and category name.
- Test Mobile API `GET /api/tickets?category=Power+Outage` and `GET /api/tickets?search=Malvar`.
- Confirm both surfaces return identical query result counts for identical criteria.

**Automated Verification Completed:**
- Created `tests/Feature/UnifiedQueryScopeTest.php` covering:
  1. Multi-field search across 8 fields + category relation name.
  2. Polymorphic category filter handling integer IDs (Livewire dropdown), numeric strings (URL query), string names (Mobile API), and `'other'`.
  3. Status filter accepting string and `TicketStatus` enums, ignoring `'all'`.
  4. Ticket sorting across `ticket_number_asc`, `ticket_number_desc`, `oldest`, `status`, and `latest`.
  5. Team word-based search, status lifecycle filter (`active`, `archived`, `all`), and directional sorting.
  6. Backward-compatibility of deprecated `api*` scope aliases.
- Test suite execution: 28 tests passed (141 assertions).

### 2.4 Proposed Commit Message
```text
refactor(models): unify web and mobile query scopes with polymorphic filter controls

- Consolidate Ticket and Team scopes into polymorphic search, filter, and sort methods
- Support both numeric IDs and string category names in unified scopeFilterByCategory
- Deprecate parallel scopeApi* implementations into thin aliases to preserve API contracts
```

---

## Phase 3: Error Code Strategy — The 404 Paradox & Information Disclosure Hardening [STATUS: IMPLEMENTED & VERIFIED]

### 3.1 Unfiltered Architectural Critique: Should ALL Errors Point to 404?
> **Direct Answer:** **NO.** Making *all* errors return 404 is a dangerous anti-pattern that cripples client applications, degrades developer experience, and causes silent data synchronization failures.
>
> However, returning **404 instead of 403 for unauthorized resource lookups** is the gold standard for security hardening (OWASP Top 10 - Broken Object Level Authorization).

#### Detailed Breakdown of Status Codes:

| HTTP Status | Turn into 404? | Why / Architectural Justification |
| :---: | :---: | :--- |
| **`403 Forbidden` on Specific Entities** | **YES (Recommended)** | **Security Hardening (Prevents ID Enumeration & Oracle Attacks):** If Supervisor A requests a ticket from another department, returning 403 confirms to an attacker that ticket `01m4...` exists in the cooperative's database. Returning **404 Not Found** completely obscures whether the record exists or is simply unauthorized. |
| **`401 Unauthorized`** | **NO (Do Not Change)** | **Client Auth Lifecycle:** Mobile apps inspect `401` to trigger automatic token refresh or redirect to the login screen. Turning `401` into `404` makes the app think the endpoint was deleted or moved, breaking session management. |
| **`422 Unprocessable Content`** | **NO (Do Not Change)** | **Validation Integrity:** Field personnel submitting a form with an invalid phone number, bad date, or missing photo *must* receive validation errors. Turning validation errors into 404 leaves the user with zero feedback on what went wrong. |
| **`409 Conflict`** | **NO (Do Not Change)** | **Concurrency & Idempotency:** The offline sync module returns 409 when an idempotency key is currently in flight. If turned into 404, the Flutter sync engine cannot know it should perform exponential backoff. |
| **`429 Too Many Requests` / `423 Locked`** | **NO (Do Not Change)** | **Brute-Force & Lockout:** The client must display the lockout timer (e.g. "Account locked for 15 minutes"). A 404 would hide the lockout entirely. |
| **`500 Internal Server Error`** | **NO (Do Not Change)** | **Observability:** Error monitors (Sentry, Bugsnag, Laravel Log) and developers rely on 500 to catch unhandled exceptions. Masking crashes as 404 destroys system observability. |

### 3.2 Proposed Implementation Plan for Authorization Hardening
To prevent resource enumeration while keeping API protocol integrity:
1. **Update `TicketPolicy.php` and `TeamPolicy.php`:**
   - In Laravel Policies, instead of returning boolean `false` (which triggers HTTP 403), use:
     ```php
     use Illuminate\Auth\Access\Response;
     
     public function view(User $user, Ticket $ticket): Response
     {
         if ($user->role->slug_identifier === 'supervisor') {
             return $user->department_id === $ticket->department_id
                 ? Response::allow()
                 : Response::denyAsNotFound(); // Returns 404 Not Found!
         }
         ...
     }
     ```
2. **Result:**
   - Any attempt by an authenticated user to view, edit, or mutate a ticket/team outside their permitted scope returns **HTTP 404 Not Found**.
   - Attackers cannot probe ULID sequences to discover other consumers' tickets or internal records.

### 3.3 Files Affected
- `PALECO_OTRS-CRM/bootstrap/app.php` (Preserve 404 status in `AuthorizationException` and normalize JSON error message)
- `PALECO_OTRS-CRM/app/Policies/TicketPolicy.php` (All object authorization methods updated to `Response::denyAsNotFound()`)
- `PALECO_OTRS-CRM/app/Policies/TeamPolicy.php` (Mobile department team inspection and mutation methods updated to `Response::denyAsNotFound()`)
- `PALECO_OTRS-CRM/app/Policies/TicketRemarkPolicy.php` (`mobileCreate` updated to `Response::denyAsNotFound()`)
- `PALECO_OTRS-CRM/app/Policies/UserPolicy.php` (`viewProfile` updated to `Response::denyAsNotFound()`)
- `PALECO_OTRS-CRM/tests/Feature/AuthorizationAntiEnumerationTest.php` (Automated feature tests)

### 3.4 Verification & Testing Plan
- Execute automated tests verifying:
  1. Supervisor accessing cross-department ticket returns 404 Not Found (anti-enumeration).
  2. Supervisor accessing own-department ticket returns 200 OK.
  3. Field personnel accessing ticket not assigned to their team returns 404 Not Found.
  4. Supervisor inspecting/archiving cross-department team returns 404 Not Found.
  5. Unauthenticated request retains semantic 401 Unauthorized.
  6. Non-supervisor accessing supervisor-only route returns 404 Not Found (route hidden).
  7. Standard non-404 authorization denials retain 403 Forbidden.
- **Automated Verification Completed:** All 8 test cases passed. Full test suite: 36 passed, 0 failed, 157 assertions.

### 3.5 Proposed Commit Message
```text
security(policies): harden object-level authorization to deny as not found (anti-enumeration)

- Update TicketPolicy, TeamPolicy, TicketRemarkPolicy, and UserPolicy to use Response::denyAsNotFound()
- Enhance bootstrap/app.php exception renderer to preserve 404 status on AuthorizationException
- Prevent resource enumeration attacks by obscuring existence of unauthorized records
- Preserve standard 401, 409, 422, and 429 status codes for client-side protocol integrity
```

---

## Phase 4: Universal Web 404 UX & Smart Navigation [STATUS: IMPLEMENTED & VERIFIED]

### 4.1 Unfiltered Architectural Analysis & UX Critique
* **Current State in Web App:**
  - The repository has **no custom error templates** (`resources/views/errors/` does not exist).
  - When a user enters an invalid URL, attempts to access an unauthorized route, or hits a resource denied as not found, Laravel serves the raw, unstyled framework 404 page.
  - This looks unpolished for a Capstone presentation and leaves the user stranded with no contextual return path.
* **UX Strategy for the 404 View:**
  - A dedicated view at `resources/views/errors/404.blade.php` backed by a reusable `resources/views/errors/layout.blade.php`.
  - Styled with the PALECO theme (primary green accents, clean neutral background, SVGs, responsive layout, PALECO cooperative logo).
  - **Smart Return Button:**
    - If the user arrived from within the web app (`document.referrer` contains the host or `window.history.length > 1`), clicking **"Return to Previous Page"** calls `window.history.back()`.
    - If the user landed on the URL directly (e.g. typed in address bar or clicked an external link), the button intelligently falls back to their authenticated home dashboard:
      - Admin $\rightarrow$ `/admin/dashboard`
      - CWD $\rightarrow$ `/cwd/tickets`
      - Guest $\rightarrow$ `/portal`

### 4.2 Proposed Implementation Plan
1. **Create `resources/views/errors/layout.blade.php`:**
   - Shared layout featuring PALECO brand logo, responsive card shell, Vite asset pipeline (`resources/css/app.css`, `resources/js/app.js`), balanced dual-action buttons with `data-smart-back` and fallback URLs, and zero inline JavaScript.
2. **Create `resources/views/errors/404.blade.php`:**
   - Extends error layout, displays status code 404 badge, contextual missing message, smart back button, and dynamic dashboard fallback.
3. **Create `resources/views/errors/403.blade.php`:**
   - Extends error layout, displays access forbidden badge for unauthorized web access.
4. **Create `resources/js/smart-navigation.js` & Import in `resources/js/app.js`:**
   - Standalone single-responsibility navigation module bound via `[data-smart-back]` data-attribute.
   - Evaluates internal origin referrer and history stack before gracefully falling back to target route.
   - Strictly keeps `app.js` as an import manifest without business logic.

### 4.3 Files Affected
- `PALECO_OTRS-CRM/resources/views/errors/layout.blade.php` (New File - Universal error layout with enhanced, balanced buttons)
- `PALECO_OTRS-CRM/resources/views/errors/404.blade.php` (New File - Branded 404 template)
- `PALECO_OTRS-CRM/resources/views/errors/403.blade.php` (New File - Branded 403 template)
- `PALECO_OTRS-CRM/resources/js/smart-navigation.js` (New File - Single-responsibility history backtracking)
- `PALECO_OTRS-CRM/resources/js/app.js` (Import manifest updated)
- `PALECO_OTRS-CRM/tests/Feature/WebErrorViewsTest.php` (New File - Feature tests for authenticated/guest views)

### 4.4 Verification & Testing Plan
- Execute automated tests verifying:
  1. Unauthenticated visitor visiting missing route receives 404 and sees PALECO brand + "Back to Portal" (`/portal`) fallback.
  2. Authenticated user visiting missing route receives 404 and sees PALECO brand + "Go to Dashboard" (`/`) fallback.
  3. Direct rendering of 403 error template renders PALECO brand + "Access Forbidden".
- **Asset Compilation Verified:** Vite build ran cleanly (`npm run build`, 56 modules transformed, zero errors).
- **Automated Verification Completed:** All 3 test cases passed (19 assertions). Full test suite: 39 passed, 0 failed, 176 assertions.

### 4.5 Proposed Commit Message
```text
feat(web): add branded universal 404 error page with intelligent navigation backtracking

- Create resources/views/errors/layout.blade.php with PALECO theme and balanced action buttons
- Create resources/views/errors/404.blade.php and 403.blade.php error templates
- Implement single-responsibility resources/js/smart-navigation.js and register in app.js
- Add WebErrorViewsTest feature test verifying authenticated dashboard and guest portal fallbacks
```

---

## Phase 5: Field Personnel Profile Team Hydration [STATUS: IMPLEMENTED & VERIFIED]

### 5.1 Unfiltered Architectural Analysis & Verification
* **Current State:**
  - In `PALECO_OTRS-CRM/app/Services/Users/ProfileService.php`:
    ```php
    public function getProfileData(User $user): User
    {
        return $user->load(['role', 'department']);
    }
    ```
  - In `PALECO_OTRS-CRM/app/Http/Resources/Api/UserResource.php`:
    - The output exposes user biographical information, role name, and department name.
    - It **does not expose teams**, even though field personnel belong to one or more field teams!
* **User's Suggestion Evaluation:**
  > *"I think we should also retrieve the teams a field personnel is assigned to when loading his /user/profile. I believe whenLoaded() is the best approach since only field personnel can only be part of a team."*
  - **Verdict:** **100% Correct and Architecturally Sound.**
  - Supervisors, CWD officers, and administrators do not belong to field crews (teams).
  - Using `$this->whenLoaded('teams')` in `UserResource` guarantees:
    - If the user is a field worker $\rightarrow$ `teams` is eager loaded and formatted into the response.
    - If the user is a supervisor or admin $\rightarrow$ `teams` is never queried or loaded, and the key is automatically omitted from the JSON envelope.

### 5.2 Proposed Implementation Plan
1. **Update `ProfileService::getProfileData`:**
   ```php
   public function getProfileData(User $user): User
   {
       $user->load(['role', 'department']);

       if ($user->role?->slug_identifier === 'field_personnel') {
           $user->load('teams');
       }

       return $user;
   }
   ```
2. **Update `MobileAuthService::processLogin`:**
   - When returning user payload upon successful mobile login, conditionally load `'teams'` if role is `field_personnel`.
3. **Update `UserResource::toArray`:**
   ```php
   'teams' => $this->whenLoaded('teams', function () {
       $teamRoles = TeamRole::pluck('role_name', 'id')->toArray();

       return $this->teams->map(function ($team) use ($teamRoles) {
           $roleId = $team->pivot?->team_role_id;

           return [
               'id' => $team->id,
               'team_name' => $team->team_name,
               'team_desc' => $team->team_desc,
               'shift_start' => $team->shift_start?->format('H:i'),
               'shift_end' => $team->shift_end?->format('H:i'),
               'team_role_id' => $roleId ? (int) $roleId : null,
               'role_name' => $roleId ? ($teamRoles[$roleId] ?? null) : null,
           ];
       });
   }),
   ```

### 5.3 Files Affected
- `PALECO_OTRS-CRM/app/Services/Users/ProfileService.php` (Conditionally eager-loads teams for field personnel)
- `PALECO_OTRS-CRM/app/Services/Auth/MobileAuthService.php` (Eager-loads teams for field personnel upon login)
- `PALECO_OTRS-CRM/app/Http/Resources/Api/UserResource.php` (Includes whenLoaded teams transformation with role names)
- `PALECO_OTRS-CRM/tests/Feature/FieldPersonnelProfileTeamsTest.php` (Feature tests for profile and auth endpoints)
- `docs/dev/API_DOCUMENTATION.md` (Updated endpoint specification and JSON schema)

### 5.4 Verification & Testing Plan
- Execute automated tests verifying:
  1. `ProfileService` and `GET /api/user/profile` eager-load and return `teams` array with team role names for field personnel.
  2. `ProfileService` and `GET /api/user/profile` omit `teams` key completely for supervisor accounts.
  3. `POST /api/login` hydrates `teams` on both `data.user` and root `user` for field personnel, and omits for supervisors.
- **Automated Verification Completed:** All 3 test cases passed (30 assertions). Full test suite: 42 passed, 0 failed, 206 assertions.

### 5.5 Proposed Commit Message
```text
feat(api): eager-load and expose assigned teams for field personnel in profile and auth resources

- Conditionally load teams relationship in ProfileService and MobileAuthService for field personnel
- Add whenLoaded('teams') transformation block with team role names in UserResource
- Add FieldPersonnelProfileTeamsTest feature test suite verifying profile and login responses
- Update API documentation with role-aware response specifications
```

---

## Phase 6: Authentication Hardening — Remaining Attempts Display & Oracle Attack Elimination [STATUS: IMPLEMENTED & VERIFIED]

### 6.1 Unfiltered Architectural Analysis: Oracle Attacks & User Feedback
* **Problem 1: The Role Enumeration Oracle Attack (Critical Security Flaw):**
  - In previous versions of `AuthService.php`:
    ```php
    if ($userRoleSlug !== $expectedRole) {
        return ['success' => false, 'message' => 'Access Denied: You do not have permission to access this specific portal.'];
    }
    ```
  - In previous versions of `MobileAuthService.php`:
    ```php
    if (! in_array($userRoleSlug, $allowedMobileRoles)) {
        return ['success' => false, 'status' => Response::HTTP_FORBIDDEN, 'message' => 'Access Denied: Web-based accounts cannot access the mobile application.'];
    }
    ```
  - **The Severe Security Consequence:**
    - If an attacker guesses passwords on `/login/admin` with username `ralph` and password `password`:
      - If the password is wrong $\rightarrow$ `"The provided credentials do not match our records."`
      - If the password is RIGHT $\rightarrow$ `"Access Denied: You do not have permission to access this specific portal."`
    - The attacker now knows with 100% certainty that the password for `ralph` is `password`!
    - This violates **OWASP Authentication Verification Standard (V2.1.2)**: Authentication responses must never confirm whether an account exists or has a specific role.
* **Problem 2: Missing Remaining Attempts Feedback:**
  - When a user mistypes their password, the app simply said *"The provided credentials do not match our records."*
  - The user had no idea how close they were to being locked out until their account suddenly was locked for 15 minutes!

### 6.2 Proposed Implementation Plan
1. **Eradicate the Authentication Oracle:**
   - In both `AuthService.php` and `MobileAuthService.php`, if credentials authenticate successfully but the role is disallowed for that portal:
     - Terminate session / do not issue token.
     - Increment the rate limiter as a failed attempt.
     - Return the exact same generic error: `"The provided credentials do not match our records. You have X attempt(s) remaining."`
     - **Result:** An attacker cannot distinguish between bad passwords, wrong roles, or non-existent usernames.
2. **Implement User-Friendly Remaining Attempts Countdown (Decoupled from IP):**
   - The system allows **5 attempts** per account identity across a 15-minute sliding window.
   - In `HandlesAuthSecurity.php`:
     - Added `checkIpVelocityLimit($ip, 10, 60)` for silent Layer 1 infrastructure/DoS defense (10 requests/min).
     - Added `handleAccountLoginFailure($username, $user, 5, 900)` for Layer 2 account-based tracking and lockout.
     - Failed attempts decrement from 5:
       - Attempt 1: 4 attempts remaining
       - Attempt 2: 3 attempts remaining
       - Attempt 3: 2 attempts remaining
       - Attempt 4: 1 attempt remaining
       - Attempt 5: Account temporarily locked for 15 minutes in database.
3. **Lockout Clarity, Anti-Proxy Hopping, & Virtual Cache Lockout:**
   - Account lockout is tracked per `username` over 15 minutes, completely defeating proxy-hopping and VPN rotation.
   - When 5 attempts are breached, real accounts receive `$user->locked_until = now()->addMinutes(15)` in the database, and ALL usernames (including non-existent/gibberish usernames) receive a 15-minute virtual cache lock (`auth:account:locked:{username}`).
   - On attempt 6, fake usernames remain locked for 15 minutes, completely eliminating username enumeration timing or response leaks.

### 6.3 Files Affected
- `PALECO_OTRS-CRM/app/Concerns/HandlesAuthSecurity.php` (Decoupled `checkIpVelocityLimit`, `handleAccountLockout`, and `handleAccountLoginFailure` with virtual cache lock)
- `PALECO_OTRS-CRM/app/Services/Auth/AuthService.php` (Dual-layer flow, oracle removal, accurate countdown)
- `PALECO_OTRS-CRM/app/Services/Auth/MobileAuthService.php` (Dual-layer flow, HTTP 401/423/429 status codes)
- `PALECO_OTRS-CRM/tests/Feature/AuthSecurityHardeningTest.php` (7 automated feature tests covering velocity, proxy hopping, and non-existent accounts)
- `docs/dev/API_DOCUMENTATION.md` (Updated login endpoint error responses: 401, 423, and 429)

### 6.4 Verification & Testing Plan
- Execute automated tests verifying:
  1. Failed web login countdown: Attempt 1 $\rightarrow$ 4 remaining; Attempt 2 $\rightarrow$ 3 remaining; Attempt 3 $\rightarrow$ 2 remaining; Attempt 4 $\rightarrow$ 1 remaining; Attempt 5 $\rightarrow$ 15-minute database lockout applied.
  2. Web role oracle elimination: Logging in at `/login/admin` with valid field personnel credentials returns `"The provided credentials do not match our records. You have 4 attempt(s) remaining."` and leaves user as guest.
  3. Mobile role oracle elimination: Logging in at `POST /api/login` with valid web administrator credentials returns HTTP 401 with generic credential error and countdown, not HTTP 403.
  4. Successful authentication clears account failed attempts.
  5. Layer 1 IP velocity limiter blocks rapid-fire flood requests (>10/min) independent of account status.
  6. Layer 2 account lockout defeats proxy hopping across multiple rotating IP addresses.
  7. Non-existent gibberish username undergoes virtual cache lockout on 5th attempt and remains locked on attempt 6, preventing username enumeration.
- **Automated Verification Completed:** All 7 test cases passed (54 assertions). Full test suite: 49 passed, 0 failed, 260 assertions.

### 6.5 Proposed Commit Message
```text
security(auth): decouple IP rate limiting from account lockout and harden authentication

- Introduce silent Layer 1 IP velocity limiter (10 req/min) for infrastructure and DoS protection
- Implement Layer 2 account-level failure tracking (5 attempts over 15 min window)
- Implement virtual cache lockout for non-existent usernames to prevent enumeration leaks
- Fix attempt countdown to display 4 remaining on first failure (decrementing to lockout)
- Eliminate portal-specific role mismatch disclosure oracles across web and mobile
- Add comprehensive AuthSecurityHardeningTest feature tests verifying proxy hopping defense
```

---

## 7. Execution Order & Git Commit Roadmap

When you are ready to begin execution, we will execute and test each phase sequentially, allowing you to create 6 clean, atomic Git commits:

```text
Commit 1 (Phase 1): feat(api): expose active assignment and dispatch reason on ticket detail and mutation endpoints
Commit 2 (Phase 2): refactor(models): unify web and mobile query scopes with polymorphic filter controls
Commit 3 (Phase 3): security(policies): harden object-level authorization to deny as not found (anti-enumeration)
Commit 4 (Phase 4): feat(web): add branded universal 404 error page with intelligent navigation backtracking
Commit 5 (Phase 5): feat(api): eager-load and expose assigned teams for field personnel in profile and auth resources
Commit 6 (Phase 6): security(auth): eliminate role enumeration oracle and display remaining login attempts
```

---
*This document serves as the formal architectural blueprint and will be maintained in `docs/misc/SYSTEM_CLEANUP_AND_IMPROVEMENT_PHASES.md`.*

