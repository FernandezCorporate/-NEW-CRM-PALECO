# PALECO OTRS-CRM: Architectural Cleanup & Hardening Implementation Plan (6 Phases)

**Document Target:** `docs/misc/SYSTEM_CLEANUP_AND_IMPROVEMENT_PHASES.md`  
**Document Version:** 1.0  
**Context:** Technical analysis, architectural critique, and execution blueprint for 6 key system cleanup concerns across API, query scopes, cybersecurity error handling, Web UX, profile hydration, and authentication.  
**Execution Strategy:** 6 isolated phases corresponding to **6 atomic Git commits**.  
**Current Code State:** Phase 1 and Phase 2 implemented and verified in working tree. Phase 3-6 pending implementation. Git commits deferred to user.

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

## Phase 3: Error Code Strategy — The 404 Paradox & Information Disclosure Hardening

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
- `PALECO_OTRS-CRM/app/Policies/TicketPolicy.php`
- `PALECO_OTRS-CRM/app/Policies/TicketEndorsementPolicy.php`
- `PALECO_OTRS-CRM/app/Policies/TeamPolicy.php`
- `docs/dev/SYSTEM_DOCUMENTATION.md`

### 3.4 Verification & Testing Plan
- Authenticate in Postman as `mycka` (Supervisor in TSD).
- Query a ticket belonging to `LCMD` or `AOD`.
- Verify the server returns `404 Not Found` with message: `"Resource not found."` instead of `403 Forbidden`.

### 3.5 Proposed Commit Message
```text
security(policies): harden object-level authorization to deny as not found (anti-enumeration)

- Update TicketPolicy, TeamPolicy, and TicketEndorsementPolicy to use Response::denyAsNotFound()
- Prevent resource enumeration attacks by obscuring existence of unauthorized records
- Preserve standard 401, 409, 422, and 429 status codes for client-side protocol integrity
```

---

## Phase 4: Universal Web 404 UX & Smart Navigation

### 4.1 Unfiltered Architectural Analysis & UX Critique
* **Current State in Web App:**
  - The repository has **no custom error templates** (`resources/views/errors/` does not exist).
  - When a user enters an invalid URL, attempts to access an unauthorized route, or hits a resource denied as not found, Laravel serves the raw, unstyled framework 404 page.
  - This looks unpolished for a Capstone presentation and leaves the user stranded with no contextual return path.
* **UX Strategy for the 404 View:**
  - A dedicated view at `resources/views/errors/404.blade.php`.
  - Styled with the PALECO theme (primary green accents, clean neutral background, SVGs, responsive layout).
  - **Smart Return Button:**
    - If the user arrived from within the web app (`document.referrer` contains the host or `window.history.length > 1`), clicking **"Go Back"** calls `history.back()`.
    - If the user landed on the URL directly (e.g. typed in address bar or clicked an external link), the button intelligently falls back to their authenticated home dashboard:
      - Admin $\rightarrow$ `/admin/dashboard`
      - CWD $\rightarrow$ `/cwd/tickets`
      - Guest $\rightarrow$ `/portal`

### 4.2 Proposed Implementation Plan
1. **Create `resources/views/errors/404.blade.php`:**
   - Clean, modern layout utilizing the existing Vite/Tailwind build.
   - Include clear messaging: *"Page or Record Not Found — The page you requested doesn't exist, has been moved, or you don't have access to view it."*
   - Interactive action buttons:
     - Primary Button: **"Return to Previous Page"** (`onclick="window.history.length > 1 ? window.history.back() : window.location.href='{{ auth()->check() ? route('dashboard') : url('/') }}'"`).
     - Secondary Button: **"Go to Dashboard / Portal"**.
2. **Create `resources/views/errors/403.blade.php` (or alias to 404):**
   - For web routes that explicitly throw 403, render the same friendly view or redirect seamlessly.

### 4.3 Files Affected
- `PALECO_OTRS-CRM/resources/views/errors/404.blade.php` (New File)
- `PALECO_OTRS-CRM/resources/views/errors/403.blade.php` (New File)
- `PALECO_OTRS-CRM/resources/views/errors/layout.blade.php` (Optional shared layout)

### 4.4 Verification & Testing Plan
- Navigate in browser to `http://127.0.0.1:8000/cwd/non-existent-route`.
- Confirm branded 404 page renders properly with PALECO styling.
- Click "Return to Previous Page" and verify it navigates back without reloading or crashing.
- Test responsive view on mobile browser resolution.

### 4.5 Proposed Commit Message
```text
feat(web): add branded universal 404 error page with intelligent navigation backtracking

- Create resources/views/errors/404.blade.php matching PALECO branding and Tailwind styling
- Implement smart browser history backtracking with authenticated dashboard fallback
- Handle 403 forbidden web exceptions with graceful user navigation
```

---

## Phase 5: Field Personnel Profile Team Hydration

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
       if ($user->role?->slug_identifier === 'field_personnel') {
           return $user->load(['role', 'department', 'teams']);
       }

       return $user->load(['role', 'department']);
   }
   ```
2. **Update `MobileAuthService::processLogin`:**
   - When returning user payload upon successful mobile login, load `'teams'` if role is `field_personnel`.
3. **Update `UserResource::toArray`:**
   ```php
   'teams' => $this->whenLoaded('teams', function () {
       return $this->teams->map(fn ($team) => [
           'id' => $team->id,
           'team_name' => $team->team_name,
           'team_desc' => $team->team_desc,
           'shift_start' => $team->shift_start?->format('H:i'),
           'shift_end' => $team->shift_end?->format('H:i'),
       ]);
   }),
   ```

### 5.3 Files Affected
- `PALECO_OTRS-CRM/app/Services/Users/ProfileService.php`
- `PALECO_OTRS-CRM/app/Services/Auth/MobileAuthService.php`
- `PALECO_OTRS-CRM/app/Http/Resources/Api/UserResource.php`
- `docs/dev/API_DOCUMENTATION.md`

### 5.4 Verification & Testing Plan
- Log in to Mobile API as `ralph` (Field Personnel) and call `GET /api/user/profile`.
  - Verify `'teams'` array is present with Alpha Crew details.
- Log in to Mobile API as `mycka` (Supervisor) and call `GET /api/user/profile`.
  - Verify `'teams'` key is absent from the JSON response.

### 5.5 Proposed Commit Message
```text
feat(api): eager-load and expose assigned teams for field personnel in profile and auth resources

- Conditionally load teams relationship in ProfileService and MobileAuthService for field workers
- Add whenLoaded('teams') transformation block to UserResource
- Update API documentation with field personnel profile payload specification
```

---

## Phase 6: Authentication Hardening — Remaining Attempts Display & Oracle Attack Elimination

### 6.1 Unfiltered Architectural Analysis: Oracle Attacks & User Feedback
* **Problem 1: The Role Enumeration Oracle Attack (Critical Security Flaw):**
  - In `AuthService.php` (lines 59–64):
    ```php
    if ($userRoleSlug !== $expectedRole) {
        LoginEvents::dispatch(NonModelActions::LOGIN_FAILED, $loggedUser);
        $this->terminateSession($request);

        return ['success' => false, 'message' => 'Access Denied: You do not have permission to access this specific portal.'];
    }
    ```
  - In `MobileAuthService.php` (lines 67–71):
    ```php
    if (! in_array($userRoleSlug, $allowedMobileRoles)) {
        LoginEvents::dispatch(NonModelActions::LOGIN_FAILED, $user);

        return ['success' => false, 'status' => Response::HTTP_FORBIDDEN, 'message' => 'Access Denied: Web-based accounts cannot access the mobile application.'];
    }
    ```
  - **The Severe Security Consequence:**
    - If an attacker guesses passwords on `/login/admin` with username `ralph` and password `password`:
      - If the password is wrong $\rightarrow$ `"The provided credentials do not match our records."`
      - If the password is RIGHT $\rightarrow$ `"Access Denied: You do not have permission to access this specific portal."`
    - **The attacker now knows with 100% certainty that the password for `ralph` is `password`!**
    - This violates **OWASP Authentication Verification Standard (V2.1.2)**: Authentication responses must never confirm whether an account exists or has a specific role.
* **Problem 2: Missing Remaining Attempts Feedback:**
  - When a user mistypes their password, the app simply says *"The provided credentials do not match our records."*
  - The user has no idea how close they are to being locked out until their account suddenly gets locked for 15 minutes!

### 6.2 Proposed Implementation Plan
1. **Eradicate the Authentication Oracle:**
   - In both `AuthService.php` and `MobileAuthService.php`, if credentials authenticate successfully but the role is disallowed for that portal:
     - Terminate session / do not issue token.
     - Increment the rate limiter as a failed attempt.
     - Return the exact same generic error: `"The provided credentials do not match our records."`
     - **Result:** An attacker cannot distinguish between bad passwords, wrong roles, or non-existent usernames.
2. **Implement User-Friendly Remaining Attempts Countdown:**
   - The system allows **5 attempts** (triggers lockout on attempt 5).
   - In `HandlesAuthSecurity.php`, add a helper method to format the warning:
     ```php
     protected function getRemainingAttemptsMessage(string $rateLimitKey, int $maxAttempts = 5): string
     {
         $retriesLeft = RateLimiter::retriesLeft($rateLimitKey, $maxAttempts);
         if ($retriesLeft > 0) {
             return "The provided credentials do not match our records. You have {$retriesLeft} attempt(s) remaining.";
         }
         return "The provided credentials do not match our records.";
     }
     ```
   - In `AuthService::processLogin` and `MobileAuthService::processLogin`:
     - When password check fails:
       ```php
       RateLimiter::hit($rateLimitKey, 60);
       $message = $this->getRemainingAttemptsMessage($rateLimitKey, 4);
       return ['success' => false, 'message' => $message];
       ```
3. **Lockout Clarity:**
   - Keep the existing database lockout warning when limit is reached:
     `"This account is temporarily locked due to multiple failed attempts. Please wait 15 minute(s)."`

### 6.3 Files Affected
- `PALECO_OTRS-CRM/app/Concerns/HandlesAuthSecurity.php`
- `PALECO_OTRS-CRM/app/Services/Auth/AuthService.php`
- `PALECO_OTRS-CRM/app/Services/Auth/MobileAuthService.php`
- `docs/dev/API_DOCUMENTATION.md`
- `docs/dev/MANUAL_TESTING_GUIDE.md`

### 6.4 Verification & Testing Plan
- Attempt web login with wrong password:
  - Attempt 1: Verify message says *"You have 3 attempt(s) remaining."*
  - Attempt 2: Verify message says *"You have 2 attempt(s) remaining."*
  - Attempt 4: Triggers rate limit lockout; verify lockout message displays minutes remaining.
- Attempt web login at `/login/admin` using valid credentials for a field worker (`ralph` / `password`):
  - Verify server returns `"The provided credentials do not match our records."` instead of revealing portal denial.
- Attempt mobile login using valid credentials for an admin (`allenglenn` / `password`):
  - Verify server returns HTTP 401 `"The provided credentials do not match our records."` instead of revealing web account status.

### 6.5 Proposed Commit Message
```text
security(auth): eliminate role enumeration oracle and display remaining login attempts

- Remove portal-specific authorization rejection messages to prevent credential harvesting
- Return generic credential error when authenticated user lacks portal role
- Add dynamic remaining attempts counter to rate-limiting failures before lockout
- Update HandlesAuthSecurity with centralized attempt calculation
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

