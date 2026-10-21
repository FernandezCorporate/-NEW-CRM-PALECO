# PALECO OTRS-CRM Mobile API Documentation

> **Base URL:** `http://<host-or-domain>/api/v1`  
> **Protocol:** HTTPS (Production) / HTTP (Local Development)  
> **Authentication Scheme:** HTTP Bearer Token via Laravel Sanctum  
> **API Version:** 1.0 (Mobile Client Optimized)

---

## Table of Contents
1. [Architectural Overview & Standards](#1-architectural-overview--standards)
2. [Global Response Envelopes](#2-global-response-envelopes)
3. [Error Handling & HTTP Status Codes](#3-error-handling--http-status-codes)
4. [Authentication & Session Endpoints](#4-authentication--session-endpoints)
5. [User Profile Endpoints](#5-user-profile-endpoints)
6. [Ticket Lifecycle Endpoints](#6-ticket-lifecycle-endpoints)
7. [Ticket Remarks Endpoints](#7-ticket-remarks-endpoints)
8. [Ticket Assignment Endpoints](#8-ticket-assignment-endpoints)
9. [Ticket Endorsement Endpoints](#9-ticket-endorsement-endpoints)
10. [Ticket Accomplishment & Verification Endpoints](#10-ticket-accomplishment--verification-endpoints)
11. [Supervisor Dashboard Endpoints](#11-supervisor-dashboard-endpoints)
12. [Team Roster Management Endpoints](#12-team-roster-management-endpoints)

---

## 1. Architectural Overview & Standards

The PALECO OTRS-CRM Mobile API delivers a robust, stateless interface designed specifically for consumption by the Flutter mobile application.

### Key Tenets
- **Uniformity:** Every single API endpoint adheres to standardized top-level keys (`success`, `message`, `data`, `meta`, `errors`).
- **Encapsulated Business Logic:** Controllers function as thin HTTP orchestrators; all validation, state transitions, transactions, and guards reside within domain Form Requests and dedicated Service classes.
- **Role-Based Access Control (RBAC):** Guarded at both route-level middleware and Gate policies (`access-supervisor`, `access-field_personnel`).
- **Data Privacy & Scoping:** Supervisor accounts can only query tickets and teams belonging to their assigned department. Field personnel can only access tickets assigned to their active team memberships. Web-only administrative roles (Admin, CWD) are blocked at login.
- **Offline-Asynchronous Resilience:** Field personnel endpoints (`/start` and `/accomplish`) support store-and-forward offline execution via `X-Idempotency-Key` (UUIDv4) and `X-Client-Timestamp` (ISO-8601). Network drops and automatic retries are handled idempotently without duplicate records (`X-Cache: HIT`). Key re-use with differing payloads is cryptographically detected and rejected (`HTTP 422`), expired records are pruned automatically on a daily schedule, and client clock skew is gracefully resolved via lifecycle-aware clamping (`started_at >= assigned_at`, `resolved_at >= started_at`).

---

## 2. Global Response Envelopes

### 2.1. Single Resource or Non-Paginated Success (Query)
```json
{
  "success": true,
  "data": {
    "id": "01JM4C7W1EXAMPLEULID",
    "ticket_number": "2026-0001"
  }
}
```

### 2.2. Mutation Success (Create / Update / Delete / State Change)
```json
{
  "success": true,
  "message": "Work has started on ticket 2026-0001.",
  "data": {
    "id": "01JM4C7W1EXAMPLEULID",
    "status": "In Progress"
  }
}
```

### 2.3. Paginated Collection Success
```json
{
  "data": [
    { "id": "01JM4C7W1...", "ticket_number": "2026-0001" }
  ],
  "links": {
    "first": "http://crm.paleco.local/api/v1/tickets?page=1",
    "last": "http://crm.paleco.local/api/v1/tickets?page=5",
    "prev": null,
    "next": "http://crm.paleco.local/api/v1/tickets?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 5,
    "per_page": 10,
    "to": 10,
    "total": 48,
    "status_counts": {
      "all": 48,
      "open": 5,
      "assigned": 12,
      "in_progress": 8,
      "resolved": 10,
      "closed": 10,
      "pending_endorsement": 2,
      "endorsed": 1
    }
  },
  "success": true
}
```

---

## 3. Error Handling & HTTP Status Codes

All errors rendered under the `/api/*` route namespace are converted to standardized JSON envelopes via `bootstrap/app.php`:

### Standard Error Formats

#### 422 Unprocessable Content (Validation Failure or Business State Violation)
```json
{
  "success": false,
  "message": "Only assigned tickets can be started.",
  "errors": {
    "status": [
      "Only assigned tickets can be started."
    ]
  }
}
```

#### 401 Unauthorized (Missing or Invalid Sanctum Token)
```json
{
  "success": false,
  "message": "Unauthenticated. A valid Bearer token is required."
}
```

#### 403 Forbidden (Insufficient Role Permissions)
Returned when an authenticated user's role does not have permission to execute an operation (e.g., field personnel attempting to access supervisor-only administrative endpoints).
```json
{
  "success": false,
  "message": "This action is unauthorized."
}
```

#### 404 Not Found (Missing Resource or Authorization Anti-Enumeration)
Returned when a requested resource does not exist, or when an authenticated user attempts to access an existing record belonging to another department or unassigned team. In compliance with OWASP anti-enumeration standards, unauthorized object requests deliberately yield 404 to obscure entity existence in the cooperative database.
```json
{
  "success": false,
  "message": "The requested resource was not found."
}
```

#### 409 Conflict (Optimistic Concurrency or Duplicate Deletion Collision)
```json
{
  "success": false,
  "message": "Conflict: This team was modified by another user while you were editing."
}
```

---

## 4. Authentication & Session Endpoints

### 4.1. Mobile Login
Authenticates mobile users, validates device identification, verifies role authorization (`supervisor` or `field_personnel`), revokes prior active sessions, and issues a fresh Laravel Sanctum bearer token.

- **Method:** `POST`
- **URL:** `/api/v1/login`
- **Access Level:** Public (Guest)
- **Headers:** `Accept: application/json`, `Content-Type: application/json`

#### Request Body
| Field | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `username` | `string` | **Yes** | User account username. |
| `password` | `string` | **Yes** | User account password. |
| `device_name` | `string` | **Yes** | Device identifier (e.g., `Samsung SM-G998B`, `Pixel 7 Pro`). |

> [!TIP]
> **Pre-Configured Test Accounts (Database Seeded):**
> - **Supervisor:** `username: mycka` | `password: Password` (Assigned to Technical Services Department)
> - **Field Personnel:** `username: ralph` | `password: Password` (Assigned to Alpha Emergency Response Crew)

#### Request Example
```json
{
  "username": "mycka",
  "password": "Password",
  "device_name": "Samsung Galaxy S23"
}
```

#### Response: `200 OK`
```json
{
  "success": true,
  "message": "Authentication successful.",
  "access_token": "1|qX8Z3AbCdEfGhIjKlMnOpQrStUvWxYz",
  "token_type": "Bearer",
  "data": {
    "access_token": "1|qX8Z3AbCdEfGhIjKlMnOpQrStUvWxYz",
    "token_type": "Bearer",
    "user": {
      "id": 14,
      "username": "supervisor_john",
      "name": "John Doe",
      "first_name": "John",
      "middle_name": "M",
      "last_name": "Doe",
      "full_name_ext": "John Doe",
      "avatar": "JD",
      "name_ext": null,
      "email": "johndoe@paleco.com",
      "contact": "09171234567",
      "role_slug": "supervisor",
      "role_name": "Department Supervisor",
      "department_id": 2,
      "department": "Engineering and Technical Operations"
    }
  },
  "user": {
    "id": 14,
    "username": "supervisor_john",
    "name": "John Doe"
  }
}
```

#### Response: `401 Unauthorized` (Invalid Credentials or Role Mismatch)
> [!NOTE]
> **Anti-Enumeration Hardening:** To prevent credential harvesting and role discovery attacks, any failed authentication—including valid web administrator credentials attempting to access the mobile API—returns HTTP `401 Unauthorized` with an accurate countdown (4, 3, 2, 1) starting from the very first failure.

```json
{
  "success": false,
  "message": "The provided credentials do not match our records. You have 4 attempt(s) remaining."
}
```

#### Response: `423 Locked` (Account Lockout Enacted)
When 5 failed attempts occur against an account identity, the account is temporarily frozen for 15 minutes.
```json
{
  "success": false,
  "message": "This account is temporarily locked due to multiple failed attempts. Please wait 15 minute(s)."
}
```

#### Response: `429 Too Many Requests` (Network IP Velocity Throttled)
When an IP address exceeds the velocity ceiling of 10 requests within 60 seconds (independent of account status).
```json
{
  "success": false,
  "message": "Too many attempts from this network. Please wait 60 second(s)."
}
```

---

### 4.2. Mobile Logout
Terminates the authenticated session by destroying the current Sanctum access token.

- **Method:** `POST`
- **URL:** `/api/v1/logout`
- **Access Level:** Authenticated (`auth:sanctum`)
- **Headers:** `Authorization: Bearer <token>`, `Accept: application/json`

#### Response: `200 OK`
```json
{
  "success": true,
  "message": "Successfully logged out."
}
```

---

## 5. User Profile Endpoints

### 5.1. Get Authenticated User Profile
Fetches the profile details, department affiliation, and role meta for the active user.

- **Method:** `GET`
- **URL:** `/api/v1/user/profile`
- **Access Level:** Authenticated (`auth:sanctum`)
- **Headers:** `Authorization: Bearer <token>`, `Accept: application/json`

> [!NOTE]
> **Role-Aware Hydration & Department Fallback:**
> - When authenticated as **Field Personnel** (`role_slug: "field_personnel"`), the response conditionally eager-loads and exposes the `teams` array with active assignments, team roles, and each team's respective `department_id` and `department`.
> - If a field personnel record has `department_id: null` on the user row, the top-level `department_id` and `department` automatically fall back to the department of their first assigned team.
> - When authenticated as a **Supervisor** (`role_slug: "supervisor"`), the `teams` key is automatically omitted from the payload.

#### Response Example: Field Personnel (`200 OK`)
```json
{
  "success": true,
  "data": {
    "id": "01JM4G1P000000000000000001",
    "username": "ralph",
    "name": "Ralph C. Field",
    "first_name": "Ralph",
    "middle_name": "C",
    "last_name": "Field",
    "full_name_ext": "Ralph C. Field",
    "avatar": "RF",
    "name_ext": null,
    "email": "ralph@paleco.com",
    "contact": "09123456789",
    "role_slug": "field_personnel",
    "role_name": "Field Personnel",
    "department_id": 2,
    "department": "Engineering and Technical Operations",
    "teams": [
      {
        "id": "01JM4G1T000000000000000001",
        "team_name": "Alpha Emergency Response Crew",
        "team_desc": "Primary high-voltage line response crew",
        "shift_start": "08:00",
        "shift_end": "17:00",
        "team_role_id": 1,
        "role_name": "Team Leader",
        "department_id": 2,
        "department": "Engineering and Technical Operations"
      }
    ]
  }
}
```

#### Response Example: Supervisor (`200 OK`)
```json
{
  "success": true,
  "data": {
    "id": "01JM4G1P000000000000000002",
    "username": "mycka",
    "name": "Mycka Supervisor",
    "first_name": "Mycka",
    "middle_name": null,
    "last_name": "Supervisor",
    "full_name_ext": "Mycka Supervisor",
    "avatar": "MS",
    "name_ext": null,
    "email": "mycka@paleco.com",
    "contact": "09171234567",
    "role_slug": "supervisor",
    "role_name": "Department Supervisor",
    "department_id": 2,
    "department": "Engineering and Technical Operations"
  }
}
```

---

## 6. Ticket Lifecycle Endpoints

### 6.1. Get Inbox Tickets (Paginated List)
Retrieves a paginated list of service tickets scoped to the user's role:
- **Supervisor:** Scoped to the supervisor's `department_id`.
- **Field Personnel:** Scoped to the teams where the user is an active member.

Includes real-time global status counters in the pagination `meta` block.

- **Method:** `GET`
- **URL:** `/api/v1/tickets`
- **Access Level:** Authenticated (`auth:sanctum`)
- **Headers:** `Authorization: Bearer <token>`, `Accept: application/json`

#### Query Parameters
| Parameter | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `page` | `integer` | No | Target page number (default: `1`). |
| `search` | `string` | No | Multi-field search term matching ticket number, complaint source, description, address (purok, street, barangay), status, or category name. |
| `category` | `string\|integer` | No | Filter by ticket category name (e.g., `Power Interruption / Blackout`), numeric category ID, or `other` for custom unlisted categories. |
| `status` | `string` | No | Filter by ticket status (`open`, `assigned`, `in_progress`, `resolved`, `closed`, `pending_endorsement`, `endorsed`, or `all` to return all tickets). Unrecognized status strings are safely ignored. |
| `sort` | `string` | No | Sort order: `newest` (default), `oldest`, `ticket_number_asc`, `ticket_number_desc`, or `status`. |

#### Response: `200 OK`
```json
{
  "data": [
    {
      "id": "01JM4C7W1ABCDEF1234567890",
      "ticket_number": "2026-0042",
      "consumer_contact": "09189876543",
      "complaint_source": "phone_call",
      "ticket_subject": "Low Voltage Complaint - Brgy. San Pedro",
      "complaint_description": "Transformer tripping intermittently during peak load hours.",
      "department_id": 1,
      "department_name": "Technical Services Department",
      "category_id": 4,
      "category_name": "Power Quality / Voltage Problem",
      "is_other_category": false,
      "other_category_name": null,
      "is_child": false,
      "is_endorsed": false,
      "created_via": null,
      "parent_ticket_id": null,
      "parent_ticket_number": null,
      "child_tickets_count": 0,
      "purok": "Purok 3",
      "street": "Malvar Street",
      "barangay": "San Pedro",
      "landmark": "Near San Pedro Chapel",
      "team_id": "01JM4BT01TEAM1234567890",
      "team_name": "Alpha Response Crew",
      "created_by": "01JM4ABCDE1234567890",
      "created_by_name": "Dispatcher Jane",
      "reported_at": "Oct 06, 2026 09:15 AM",
      "started_at": "Oct 06, 2026 10:00 AM",
      "resolved_at": null,
      "closed_at": null,
      "created_at": "Oct 06, 2026 09:15 AM",
      "updated_at": "Oct 06, 2026 10:00 AM",
      "status": "in_progress",
      "is_force_closed": false,
      "closure_reason": null,
      "cancellation_reason": null,
      "cancelled_at": null,
      "is_offline_synced": false,
      "synced_at": null,
      "client_started_at": null
    }
  ],
  "links": {
    "first": "http://crm.paleco.local/api/v1/tickets?page=1",
    "last": "http://crm.paleco.local/api/v1/tickets?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "per_page": 10,
    "to": 1,
    "total": 1,
    "status_counts": {
      "all": 25,
      "open": 4,
      "assigned": 6,
      "in_progress": 8,
      "resolved": 4,
      "closed": 2,
      "pending_endorsement": 1,
      "endorsed": 0,
      "cancelled": 0
    }
  },
  "success": true
}
```

#### Hierarchy & Endorsement Fields
| Field | Type | Description |
| :--- | :--- | :--- |
| `is_child` | `boolean` | `true` when the ticket has a parent (origin) ticket. |
| `is_endorsed` | `boolean` | `true` once an endorsement of this ticket was approved and its work moved to another department. Stays `true` after the ticket is later force-closed, unlike the `endorsed` status. |
| `created_via` | `string\|null` | How the ticket was created: `null` for an origin ticket, `endorsement` for a child created by an approved endorsement, `manual` for a child created by a CWD officer. |

---

### 6.2. Get Ticket Detailed Information
Returns the full ticket details including complainant particulars, assigned crew, status logs timeline, parent/child hierarchy, force closure / cancellation exceptions, and linked child tickets.

- **Method:** `GET`
- **URL:** `/api/v1/tickets/{ticket}`
- **Access Level:** Authenticated (`auth:sanctum`)
- **Headers:** `Authorization: Bearer <token>`, `Accept: application/json`

#### URL Parameters
| Parameter | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `ticket` | `string` (ULID) | **Yes** | System ULID identifier of the ticket. |

#### Response: `200 OK`
```json
{
  "success": true,
  "data": {
    "id": "01JM4C7W1ABCDEF1234567890",
    "ticket_number": "2026-0042",
    "status": "in_progress",
    "department_id": 1,
    "department_name": "Technical Services Department",
    "is_child": false,
    "is_endorsed": false,
    "parent_ticket_id": null,
    "parent_ticket_number": null,
    "created_via": null,
    "endorsement_origin": null,
    "reported_at": "Oct 06, 2026 09:15 AM",
    "started_at": "Oct 06, 2026 10:00 AM",
    "resolved_at": null,
    "closed_at": null,
    "is_force_closed": false,
    "closure_reason": null,
    "closed_by": null,
    "cancellation_reason": null,
    "cancelled_at": null,
    "is_offline_synced": false,
    "synced_at": null,
    "client_started_at": null,
    "creator": {
      "id": "01JM4ABCDE1234567890",
      "name": "Jane Dispatcher",
      "role": "Call Center Agent"
    },
    "consumer": {
      "id": "01JM4CONSUMER1234567890",
      "acct_no": "01-2345-6789",
      "acct_code": "RES-01",
      "name": "Maria Santos",
      "address": "Purok 3, San Pedro, Puerto Princesa City",
      "status": "Active",
      "meter_serial": "PAL-998811"
    },
    "complaint": {
      "source": "phone_call",
      "consumer_contact": "09189876543",
      "category_id": 4,
      "category_name": "Power Quality / Voltage Problem",
      "is_other_category": false,
      "other_category_name": null,
      "description": "Transformer tripping intermittently during peak load hours.",
      "full_address": "Purok 3, Malvar Street, San Pedro, Puerto Princesa City",
      "purok": "Purok 3",
      "street": "Malvar Street",
      "barangay": "San Pedro",
      "landmark": "Near San Pedro Chapel"
    },
    "team": {
      "id": "01JM4BT01TEAM1234567890",
      "team_name": "Alpha Response Crew",
      "shift_start": "08:00",
      "shift_end": "17:00",
      "personnel": [
        { "id": "01JM4USER12345678901234", "name": "Pedro Lineman" },
        { "id": "01JM4USER12345678901235", "name": "Juan Electrician" }
      ]
    },
    "active_assignment": {
      "team_id": "01JM4BT01TEAM1234567890",
      "team_name": "Alpha Response Crew",
      "assigned_by": "Mycka Supervisor",
      "assigned_by_role": "Supervisor",
      "reason": "Emergency response for burning fuse cutout",
      "assigned_at": "Oct 06, 2026 09:30 AM"
    },
    "status_logs": [
      {
        "id": 101,
        "old_status": "open",
        "new_status": "assigned",
        "changed_by_name": "John Doe",
        "created_at": "Oct 06, 2026 09:30 AM"
      },
      {
        "id": 102,
        "old_status": "assigned",
        "new_status": "in_progress",
        "changed_by_name": "Pedro Lineman",
        "created_at": "Oct 06, 2026 10:00 AM"
      }
    ],
    "child_tickets_count": 0,
    "child_tickets": []
  }
}
```

#### Endorsement Origin (Child Tickets)
`is_endorsed` and `created_via` mean the same as in the inbox (§6.1). For a child created by an approved endorsement, `endorsement_origin` tells the receiving department where the work came from and why; for a manual child or an origin ticket it is `null`.

```json
{
  "is_child": true,
  "parent_ticket_number": "TKT-261020-004",
  "created_via": "endorsement",
  "endorsement_origin": {
    "endorsement_id": "01JM4ENDORSE1234567890ABC",
    "reason": "Inspection reveals defective digital meter dial. Requires meter laboratory recalibration.",
    "from_dept": "Technical Services Department",
    "approved_by": "Alliah Officer",
    "approved_at": "Oct 06, 2026 01:10 PM"
  }
}
```

---

### 6.3. Start Work on Ticket
Transitions an assigned ticket to `In Progress` status upon field crew arrival or work start. Supports **offline-asynchronous store-and-forward** and **idempotent retries**.

- **Method:** `PATCH`
- **URL:** `/api/v1/tickets/{ticket}/start`
- **Access Level:** Field Personnel (`can:access-field_personnel`)
- **Headers:**
  - `Authorization: Bearer <token>`
  - `Accept: application/json`
  - `X-Idempotency-Key: <UUIDv4>` *(Optional / Recommended for mobile)* — Unique token preventing duplicate execution upon network retry.
  - `X-Client-Timestamp: <ISO-8601>` *(Optional)* — The exact timestamp when the lineman tapped start in the field (e.g. `2026-10-10T09:30:00+08:00`).

#### URL Parameters
| Parameter | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `ticket` | `string` (ULID) | **Yes** | System ULID identifier of the ticket. |

#### Request Body (Alternative for Timestamp)
| Field | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `client_timestamp` | `string` (ISO-8601) | No | Alternative to `X-Client-Timestamp` header. |

#### Response: `200 OK`
*Includes header `X-Cache: MISS` (fresh action) or `X-Cache: HIT` (served from idempotency cache).*
```json
{
  "success": true,
  "message": "Work has started on ticket TKT-261010-001.",
  "data": {
    "id": "01JM4C7W1ABCDEF1234567890",
    "ticket_number": "TKT-261010-001",
    "consumer_contact": "09694547493",
    "complaint_source": "walk_in",
    "ticket_subject": "Power Interruption / Blackout @ POBLACION",
    "complaint_description": "No electricity.",
    "category_name": "Power Interruption / Blackout",
    "purok": null,
    "street": null,
    "barangay": "Poblacion",
    "landmark": null,
    "team_id": "01m4jazwpgehzgcnqq3dwc20pc",
    "team_name": "Alpha Emergency Response Crew",
    "created_by": "01m4jazxcwbm2rw0p8x799eknh",
    "created_by_name": "Alliah Officer",
    "reported_at": "Oct 10, 2026 09:15 AM",
    "started_at": "Oct 10, 2026 09:30 AM",
    "resolved_at": null,
    "closed_at": null,
    "created_at": "Oct 10, 2026 09:15 AM",
    "updated_at": "Oct 10, 2026 03:30 PM",
    "status": "in_progress",
    "child_tickets_count": 0,
    "is_offline_synced": true,
    "synced_at": "Oct 10, 2026 03:30 PM"
  }
}
```

#### Timestamp Resolution & Lifecycle Clamping
When `X-Client-Timestamp` or `client_timestamp` is provided:
1. **Format:** It must parse as a date-time (ISO-8601 recommended); otherwise the request is rejected with `HTTP 422`.
2. **Future Ceiling:** A timestamp more than 5 minutes ahead of server time is rejected with `HTTP 422`; it is not clamped.
3. **Lifecycle Clamping (`started_at >= assigned_at`):** A timestamp earlier than the moment the ticket was assigned to the crew (the active assignment's time, or `reported_at` / `created_at` when there is none) is **not rejected**: the server moves it forward to that moment. A slow phone clock therefore never fails the sync and never produces a start before the dispatch.

#### Error Response: `422 Unprocessable Content` (Future Client Timestamp)
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

#### Error Response: `422 Unprocessable Content` (Unparseable Client Timestamp)
```json
{
  "success": false,
  "message": "The client timestamp format is invalid. Must be a valid ISO-8601 string.",
  "errors": {
    "client_timestamp": [
      "The client timestamp format is invalid. Must be a valid ISO-8601 string."
    ]
  }
}
```

#### Error Response: `422 Unprocessable Content` (Idempotency Key Reuse Mismatch)
*Returned if the same `X-Idempotency-Key` is re-sent with a differing request URL or payload.*
```json
{
  "success": false,
  "message": "Idempotency key has already been used with a different request payload."
}
```

#### Error Response: `409 Conflict` (In-Flight Concurrency Lock)
*Returned if the same `X-Idempotency-Key` is currently executing on a parallel worker thread.*
```json
{
  "success": false,
  "message": "Request currently processing, please retry shortly."
}
```

---

### 6.4. View Ticket Assignment and Endorsement History
Fetches the full timeline of historical crew assignments and cross-department endorsements.

- **Method:** `GET`
- **URL:** `/api/v1/tickets/{ticket}/history`
- **Access Level:** Supervisor (`can:access-supervisor`)
- **Headers:** `Authorization: Bearer <token>`, `Accept: application/json`

#### URL Parameters
| Parameter | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `ticket` | `string` (ULID) | **Yes** | System ULID identifier of the ticket. |

#### Response: `200 OK`
```json
{
  "success": true,
  "message": "Assignment and endorsement history for 2026-0042 has been retrieved.",
  "data": {
    "assignments": [
      {
        "team_name": "Alpha Response Crew",
        "assigned_by": "John Doe",
        "assigned_by_role": "Department Supervisor",
        "assignment_reason": "Urgent dispatched due to safety report",
        "assigned_at": "Oct 06, 2026 09:30 AM",
        "unassigned_at": "Oct 06, 2026 01:10 PM"
      }
    ],
    "endorsements": [
      {
        "suggested_dept": "Meter Management Department",
        "old_dept": "Technical Services Department",
        "new_dept": "Meter Management Department",
        "created_by": "John Doe",
        "creator_role": "Department Supervisor",
        "endorsement_reason": "Inspection reveals defective digital meter dial. Requires meter laboratory recalibration.",
        "time_requested": "Oct 06, 2026 12:00 PM",
        "request_status": "approved",
        "pre_endorsement_status": "assigned",
        "reviewed_by": "Alliah Officer",
        "rejection_reason": null,
        "verified_at": "Oct 06, 2026 01:10 PM"
      }
    ]
  }
}
```

#### Endorsement Record Fields
| Field | Type | Description |
| :--- | :--- | :--- |
| `suggested_dept` | `string\|null` | The department the supervisor suggested. Only a suggestion; CWD picks the real target. |
| `old_dept` | `string\|null` | The ticket's department when the request was made (taken from the ticket, not the requester, so it stays correct if the supervisor later moves). |
| `new_dept` | `string\|null` | The department CWD transferred the work to on approval. `null` while pending and after a rejection. |
| `request_status` | `string` | `pending`, `approved` or `rejected`. |
| `pre_endorsement_status` | `string` | The ticket's status when the request was made: `open` or `assigned`. Rejection returns the ticket to this status and its team. |

---

## 7. Ticket Remarks Endpoints

### 7.1. List Ticket Remarks
Retrieves all public remarks/comments posted on the ticket. Internal administrative notes are filtered out automatically.

- **Method:** `GET`
- **URL:** `/api/v1/tickets/{ticket}/remarks`
- **Access Level:** Authenticated (`auth:sanctum`)
- **Headers:** `Authorization: Bearer <token>`, `Accept: application/json`

#### URL Parameters
| Parameter | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `ticket` | `string` (ULID) | **Yes** | System ULID identifier of the ticket. |

#### Response: `200 OK`
```json
{
  "success": true,
  "data": [
    {
      "id": 88,
      "body": "Crew has arrived at the location. Commenced transformer check.",
      "created_at_value": "2026-10-06T10:05:00+08:00",
      "created_at_display": "Oct 06, 2026 10:05 AM",
      "author": {
        "id": 22,
        "full_name": "Pedro Lineman",
        "role": "Field Personnel"
      }
    }
  ]
}
```

---

### 7.2. Create Ticket Remark
Posts a new public remark to the ticket timeline. **Closed and cancelled tickets are read-only:** posting on them returns `HTTP 403` with `"Closed and cancelled tickets are read-only. Remarks can no longer be added."`

> [!IMPORTANT]
> **Online-Only Operational Boundary:** Ticket remarks are strictly online-only interactive communications. Unlike `/start` and `/accomplish`, remarks do **not** support offline queueing, idempotency keys, or client-side timestamps. When a mobile device is in an offline dead zone, the remarks UI must disable the submit button and instruct the user: *"An active internet connection is required to post remarks."*

- **Method:** `POST`
- **URL:** `/api/v1/tickets/{ticket}/remarks`
- **Access Level:** Authenticated (`auth:sanctum`)
- **Headers:** `Authorization: Bearer <token>`, `Content-Type: application/json`, `Accept: application/json`

#### Request Body
| Field | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `body` | `string` | **Yes** | Content of the remark (between 2 and 5000 characters). |
| `is_internal` | `boolean` | No | Overridden to `false` automatically by the mobile service. |

#### Request Example
```json
{
  "body": "Replaced fuse link on transformer pole 42. Voltage restored to 220V."
}
```

#### Response: `201 Created`
```json
{
  "success": true,
  "message": "Remark posted successfully.",
  "data": {
    "id": 89,
    "body": "Replaced fuse link on transformer pole 42. Voltage restored to 220V.",
    "created_at_value": "2026-10-06T11:30:00+08:00",
    "created_at_display": "Oct 06, 2026 11:30 AM",
    "author": {
      "id": 22,
      "full_name": "Pedro Lineman",
      "role": "Field Personnel"
    }
  }
}
```

---

## 8. Ticket Assignment Endpoints

### 8.1. Get Assign Options (Available Teams)
Retrieves the list of operational teams in the supervisor's department, including active ticket counts and an `is_current` flag indicating the ticket's current team.

- **Method:** `GET`
- **URL:** `/api/v1/tickets/{ticket}/assign-options`
- **Access Level:** Supervisor (`can:access-supervisor`)
- **Headers:** `Authorization: Bearer <token>`, `Accept: application/json`

#### Response: `200 OK`
```json
{
  "success": true,
  "data": [
    {
      "id": "01JM4BT01TEAM1234567890",
      "team_name": "Alpha Response Crew",
      "shift_start": "08:00 AM",
      "shift_end": "05:00 PM",
      "members_count": 4,
      "tickets": 2,
      "is_current": true
    },
    {
      "id": "01JM4BT02TEAM9876543210",
      "team_name": "Bravo Hotline Team",
      "shift_start": "01:00 PM",
      "shift_end": "10:00 PM",
      "members_count": 3,
      "tickets": 0,
      "is_current": false
    }
  ]
}
```

---

### 8.2. Assign / Reassign Ticket
Assigns or reassigns a service ticket to a designated team within the supervisor's department.

> [!IMPORTANT]
> **Strict Lifecycle Reassignment Policy:**
> - **Initial Assignment:** Permitted strictly when ticket status is `OPEN`.
> - **Reassignment:** Permitted strictly when ticket status is `ASSIGNED`.
> - **In-Progress Tickets Prohibited:** Tickets in `IN_PROGRESS` status can **never** be reassigned. Once a field crew commences work on site, the ticket is actively being addressed and reassignment is locked. Attempting to reassign returns `HTTP 422 Unprocessable Content`.
> - **Terminal & Frozen States Prohibited:** Tickets in `RESOLVED`, `CLOSED`, or `PENDING_ENDORSEMENT` cannot be assigned or reassigned.
> - **Reason Requirement:** A non-empty `reason` (max 1000 characters) is **mandatory** when reassigning (`ASSIGNED` status), and optional on initial assignment (`OPEN` status).
> - **Target Team:** The ticket cannot be reassigned to the currently assigned team (returns `HTTP 422`).

- **Method:** `POST`
- **URL:** `/api/v1/tickets/{ticket}/assign`
- **Access Level:** Supervisor (`can:access-supervisor`)
- **Headers:** `Authorization: Bearer <token>`, `Content-Type: application/json`, `Accept: application/json`

#### Request Body
| Field | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `team_id` | `string` | **Yes** | ID of the target team within the supervisor's department. |
| `reason` | `string` | Conditional | **Required** if reassigning an already assigned ticket; optional on initial assignment. Max 1000 characters. |

#### Request Example
```json
{
  "team_id": "01JM4BT02TEAM9876543210",
  "reason": "Previous crew called to an emergency feeder outage."
}
```

#### Response: `200 OK`
```json
{
  "success": true,
  "message": "Ticket 2026-0042 has been successfully reassigned.",
  "data": {
    "id": "01JM4C7W1ABCDEF1234567890",
    "ticket_number": "2026-0042",
    "team_id": "01JM4BT02TEAM9876543210",
    "team_name": "Bravo Hotline Team",
    "status": "Assigned",
    "active_assignment": {
      "team_id": "01JM4BT02TEAM9876543210",
      "team_name": "Bravo Hotline Team",
      "assigned_by": "Mycka Supervisor",
      "assigned_by_role": "Supervisor",
      "reason": "Previous crew called to an emergency feeder outage.",
      "assigned_at": "Oct 06, 2026 10:15 AM"
    }
  }
}
```

#### Error Response: `422 Unprocessable Content` (In-Progress Ticket Reassignment Blocked)
```json
{
  "success": false,
  "message": "Cannot reassign an in-progress ticket. Field crews are actively addressing this issue.",
  "errors": {
    "status": [
      "Cannot reassign an in-progress ticket. Field crews are actively addressing this issue."
    ]
  }
}
```

#### Error Response: `422 Unprocessable Content` (Assigned to Same Team)
```json
{
  "success": false,
  "message": "This ticket is already assigned to the selected team. No changes were made.",
  "errors": {
    "team_id": [
      "This ticket is already assigned to the selected team. No changes were made."
    ]
  }
}
```

---

## 9. Ticket Endorsement Endpoints

### 9.1. Get Endorsement Options (Candidate Departments)
Retrieves all candidate departments available for ticket transfer, marking the supervisor's current department with `is_current: true`.

- **Method:** `GET`
- **URL:** `/api/v1/tickets/{ticket}/endorse-options`
- **Access Level:** Supervisor (`can:access-supervisor`)
- **Headers:** `Authorization: Bearer <token>`, `Accept: application/json`

#### Response: `200 OK`
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "department_name": "Consumer Welfare Desk",
      "department_description": "Handles primary customer complaints and billing inquiries",
      "is_current": false
    },
    {
      "id": 2,
      "department_name": "Engineering and Technical Operations",
      "department_description": "Field maintenance, distribution lines, and transformers",
      "is_current": true
    },
    {
      "id": 3,
      "department_name": "Meter Management Department",
      "department_description": "Meter testing, calibration, and replacement",
      "is_current": false
    }
  ]
}
```

---

### 9.2. Submit Endorsement Request
Submits a formal endorsement request. The ticket transitions to `Pending Endorsement` status and is frozen until reviewed by CWD administration.

> [!IMPORTANT]
> **Endorsement Eligibility:**
> - **Only `OPEN` and `ASSIGNED` tickets can be endorsed.** Once a crew starts work (`IN_PROGRESS`), the job is accepted as within the department's scope and stays there; if more work turns up for another department, CWD creates a child ticket instead. `RESOLVED`, `CLOSED`, `CANCELLED`, `PENDING_ENDORSEMENT` and `ENDORSED` tickets are refused too.
> - **Origin tickets only:** child tickets cannot be endorsed (`HTTP 403`).
> - **One request at a time:** a ticket with a pending request cannot be endorsed again until CWD decides it.
> - **Approval** (by CWD on the web) marks the ticket `endorsed` with `is_endorsed: true`, records `new_dept`, and opens a child ticket in the target department whose `created_via` is `endorsement` (see §6.2). **Rejection** returns the ticket to `pre_endorsement_status` with the same team.

- **Method:** `POST`
- **URL:** `/api/v1/tickets/{ticket}/endorse`
- **Access Level:** Supervisor (`can:access-supervisor`)
- **Headers:** `Authorization: Bearer <token>`, `Content-Type: application/json`, `Accept: application/json`

#### Request Body
| Field | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `reason` | `string` | **Yes** | Detailed explanation for the endorsement request. Max 1000 characters. |
| `suggested_department_id` | `integer` | No | ID of the suggested target department (cannot match the ticket's current department). |

#### Request Example
```json
{
  "reason": "Inspection reveals defective digital meter dial. Requires meter laboratory recalibration.",
  "suggested_department_id": 3
}
```

#### Response: `201 Created`
```json
{
  "success": true,
  "message": "An endorsement request has been submitted. Ticket 2026-0042 is now frozen pending CWD review.",
  "data": {
    "suggested_dept": "Meter Management Department",
    "old_dept": "Technical Services Department",
    "created_by": "John Doe",
    "creator_role": "Department Supervisor",
    "endorsement_reason": "Inspection reveals defective digital meter dial. Requires meter laboratory recalibration.",
    "time_requested": "Oct 06, 2026 12:00 PM",
    "request_status": "pending",
    "pre_endorsement_status": "assigned",
    "reviewed_by": null,
    "rejection_reason": null,
    "verified_at": null
  }
}
```

#### Error Response: `422 Unprocessable Content` (Ticket Not Open or Assigned)
```json
{
  "success": false,
  "message": "Only Open or Assigned tickets can be endorsed. Once work has started, the ticket stays with this department.",
  "errors": {
    "status": [
      "Only Open or Assigned tickets can be endorsed. Once work has started, the ticket stays with this department."
    ]
  }
}
```

#### Error Response: `422 Unprocessable Content` (Request Already Pending)
```json
{
  "success": false,
  "message": "This ticket already has a pending endorsement request.",
  "errors": {
    "status": [
      "This ticket already has a pending endorsement request."
    ]
  }
}
```

#### Error Response: `403 Forbidden` (Child Ticket)
```json
{
  "success": false,
  "message": "Child tickets cannot be endorsed. Ask the CWD officer to create a new child ticket from the origin ticket instead."
}
```

---

## 10. Ticket Accomplishment & Verification Endpoints

### 10.1. List Accomplishment Reports
Retrieves all accomplishment submissions for a ticket.

- **Method:** `GET`
- **URL:** `/api/v1/tickets/{ticket}/accomplishments`
- **Access Level:** Authenticated (`auth:sanctum`)
- **Headers:** `Authorization: Bearer <token>`, `Accept: application/json`

#### Response: `200 OK`
```json
{
  "success": true,
  "data": [
    {
      "id": 15,
      "ticket_id": "01JM4C7W1ABCDEF1234567890",
      "remarks": "Completed line repair and tested voltage stability.",
      "consumer_name": "Maria Santos",
      "signature_url": "http://crm.paleco.local/storage/accomplishments/01JM4C7W1/signature/sig_abc.png",
      "photos": [
        {
          "id": 41,
          "url": "http://crm.paleco.local/storage/accomplishments/01JM4C7W1/photos/photo_1.jpg"
        }
      ],
      "status": "Pending",
      "rejection_reason": null,
      "accomplished_at": "Oct 06, 2026 01:30 PM",
      "worker": {
        "id": 22,
        "name": "Pedro Lineman"
      },
      "rejected_by": null,
      "approved_by": null
    }
  ]
}
```

---

### 10.2. Show Accomplishment Report Details
Retrieves an individual accomplishment report with photo evidence and evaluator notes.

- **Method:** `GET`
- **URL:** `/api/v1/tickets/{ticket}/accomplishments/{accomplishment}`
- **Access Level:** Authenticated (`auth:sanctum`)
- **Headers:** `Authorization: Bearer <token>`, `Accept: application/json`

#### Response: `200 OK`
```json
{
  "success": true,
  "data": {
    "id": 15,
    "ticket_id": "01JM4C7W1ABCDEF1234567890",
    "remarks": "Completed line repair and tested voltage stability.",
    "consumer_name": "Maria Santos",
    "signature_url": "http://crm.paleco.local/storage/accomplishments/01JM4C7W1/signature/sig_abc.png",
    "photos": [
      {
        "id": 41,
        "url": "http://crm.paleco.local/storage/accomplishments/01JM4C7W1/photos/photo_1.jpg"
      }
    ],
    "status": "Pending",
    "rejection_reason": null,
    "accomplished_at": "Oct 06, 2026 01:30 PM",
    "worker": {
      "id": 22,
      "name": "Pedro Lineman"
    },
    "rejected_by": null,
    "approved_by": null
  }
}
```

---

### 10.3. Submit Accomplishment Report
Submits an accomplishment report with required photo evidence (1–10 images) and consumer e-signature. Upon submission, the ticket status changes to `Resolved`. Supports **offline-asynchronous store-and-forward** and **idempotent retries**.

- **Method:** `POST`
- **URL:** `/api/v1/tickets/{ticket}/accomplish`
- **Access Level:** Field Personnel (`can:access-field_personnel`)
- **Headers:**
  - `Authorization: Bearer <token>`
  - `Content-Type: multipart/form-data`
  - `Accept: application/json`
  - `X-Idempotency-Key: <UUIDv4>` *(Optional / Recommended for mobile)* — Unique token ensuring multipart photo uploads are not duplicated upon network retries.
  - `X-Client-Timestamp: <ISO-8601>` *(Optional)* — The exact timestamp when work finished and the report was signed in the field (e.g. `2026-10-10T11:15:00+08:00`).

#### Form Data Parameters
| Field | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `remarks` | `string` | **Yes** | Detailed field accomplishment report. Max 1000 characters. |
| `consumer_name` | `string` | No | Full name of consumer or representative on site. |
| `signature` | `file` (image) | **Yes** | Signature image file (`jpeg`, `png`, `jpg`). Max 5MB. |
| `photos[]` | `file[]` (image) | **Yes** | Array of photographic evidence (min 1, max 10). Each file max 10MB. |
| `client_timestamp` | `string` (ISO-8601) | No | Alternative to `X-Client-Timestamp` header. |

#### Response: `201 Created`
*Includes header `X-Cache: MISS` (fresh creation) or `X-Cache: HIT` (cached idempotent replay).*
```json
{
  "success": true,
  "message": "Accomplishment report for ticket TKT-261010-001 has been submitted.",
  "data": {
    "id": 15,
    "ticket_id": "01JM4C7W1ABCDEF1234567890",
    "remarks": "Replaced blown transformer secondary fuse and verified 220V grid stability.",
    "consumer_name": "Maria Santos",
    "signature_url": "http://crm.paleco.local/storage/accomplishments/signatures/sig_abc.png",
    "photos": [
      {
        "id": 41,
        "url": "http://crm.paleco.local/storage/accomplishments/photos/photo_1.jpg"
      }
    ],
    "status": "pending",
    "rejection_reason": null,
    "accomplished_at": "Oct 10, 2026 11:15 AM",
    "is_offline_synced": true,
    "synced_at": "Oct 10, 2026 03:35 PM",
    "worker": {
      "id": "01m4jazx...ulid",
      "name": "Pedro Lineman"
    }
  }
}
```

#### Timestamp Resolution & Lifecycle Clamping
When `X-Client-Timestamp` or `client_timestamp` is provided:
1. **Format:** It must parse as a date-time (ISO-8601 recommended); otherwise the request is rejected with `HTTP 422`.
2. **Future Ceiling:** A timestamp more than 5 minutes ahead of server time is rejected with `HTTP 422` (`"The client timestamp cannot be in the future."`); it is not clamped.
3. **Lifecycle Clamping (`resolved_at >= started_at`):** A timestamp earlier than the ticket's `started_at` (or the assignment time, then `reported_at` / `created_at`, when that is missing) is **not rejected**: the server moves it forward to that moment, so a report never precedes the work start and a slow phone clock never fails the upload.

#### Retries & Resubmissions
- **Same `X-Idempotency-Key`:** the first successful response is replayed (`X-Cache: HIT`); no duplicate report or files are stored.
- **Ticket already `RESOLVED`, new or missing key:** if this worker's latest report is still **pending** review, it is treated as a retry and that report is returned with `201`. If not (the worker's report was rejected and another report is now pending, or the pending report belongs to someone else), the request is refused with `422` and the uploaded files are discarded. This stops an offline queue from silently replacing new evidence with an old rejected report.
- **Ticket `PENDING_ENDORSEMENT`:** refused with `422` (`"Ticket is locked pending inter-departmental endorsement review."`).

#### Error Response: `422 Unprocessable Content` (Stale Resubmission While Another Report Is Under Review)
```json
{
  "success": false,
  "message": "This ticket already has a pending accomplishment report under supervisor review.",
  "errors": {
    "status": [
      "This ticket already has a pending accomplishment report under supervisor review."
    ]
  }
}
```

#### Error Response: `422 Unprocessable Content` (Future Client Timestamp)
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

#### Error Response: `422 Unprocessable Content` (Idempotency Key Reuse Mismatch)
*Returned if the same `X-Idempotency-Key` is re-sent to a different endpoint or with different form fields. Files are not part of the fingerprint.*
```json
{
  "success": false,
  "message": "Idempotency key has already been used with a different request payload."
}
```

#### Error Response: `409 Conflict` (Duplicate In-Flight Submission)
*Returned if the same `X-Idempotency-Key` upload is actively being processed by another thread.*
```json
{
  "success": false,
  "message": "Request currently processing, please retry shortly."
}
```

---

### 10.4. Verify Accomplishment Report
Evaluates a pending accomplishment report.
- **`APPROVED`**: Ticket status transitions to `Closed` (ticket lifecycle terminates).
- **`REJECTED`**: Requires a `rejection_reason`. Ticket status transitions back to `In Progress` for field personnel rework.

- **Method:** `POST`
- **URL:** `/api/v1/tickets/{ticket}/accomplishments/{accomplishment}/verify`
- **Access Level:** Supervisor (`can:access-supervisor`)
- **Headers:** `Authorization: Bearer <token>`, `Content-Type: application/json`, `Accept: application/json`

#### Request Body
| Field | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `status` | `string` | **Yes** | Must be either `APPROVED` or `REJECTED`. |
| `rejection_reason` | `string` | Conditional | **Required if status is `REJECTED`**. Max 500 characters. |

#### Request Example (Approval)
```json
{
  "status": "APPROVED"
}
```

#### Response: `200 OK`
```json
{
  "success": true,
  "message": "Accomplishment report successfully approved.",
  "data": {
    "id": 15,
    "status": "APPROVED",
    "rejection_reason": null,
    "approved_by": {
      "id": 14,
      "name": "John Doe"
    }
  }
}
```

#### Request Example (Rejection)
```json
{
  "status": "REJECTED",
  "rejection_reason": "Photos do not clearly show the replaced transformer serial plate. Please retake photo evidence."
}
```

#### Response: `200 OK`
```json
{
  "success": true,
  "message": "Accomplishment report successfully rejected.",
  "data": {
    "id": 15,
    "status": "REJECTED",
    "rejection_reason": "Photos do not clearly show the replaced transformer serial plate. Please retake photo evidence.",
    "rejected_by": {
      "id": 14,
      "name": "John Doe"
    }
  }
}
```

---

## 11. Supervisor Dashboard Endpoints

### 11.1. Get Supervisor Dashboard Metrics
Fetches high-level KPIs and four accordion ticket groups for supervisor monitoring:
1. `needs_assignment`: Open tickets lacking an assigned team.
2. `in_progress`: Tickets actively being addressed in the field.
3. `pending_verification`: Resolved tickets awaiting supervisor approval.
4. `endorsement_review`: Tickets currently frozen under endorsement review.

- **Method:** `GET`
- **URL:** `/api/v1/dashboard`
- **Access Level:** Supervisor (`can:access-supervisor`)
- **Headers:** `Authorization: Bearer <token>`, `Accept: application/json`

#### Response: `200 OK`
```json
{
  "success": true,
  "data": {
    "kpis": {
      "total": 35,
      "needs_assignment": 4,
      "in_progress": 8,
      "closed": 15
    },
    "accordions": {
      "needs_assignment": {
        "total_count": 4,
        "tickets": [
          { "id": "01JM4...", "ticket_number": "2026-0044", "status": "Open" }
        ]
      },
      "in_progress": {
        "total_count": 8,
        "tickets": [
          { "id": "01JM4...", "ticket_number": "2026-0042", "status": "In Progress" }
        ]
      },
      "pending_verification": {
        "total_count": 3,
        "tickets": [
          { "id": "01JM4...", "ticket_number": "2026-0038", "status": "Resolved" }
        ]
      },
      "endorsement_review": {
        "total_count": 1,
        "tickets": [
          { "id": "01JM4...", "ticket_number": "2026-0029", "status": "Pending Endorsement" }
        ]
      }
    }
  }
}
```

---

## 12. Team Roster Management Endpoints

### 12.1. Get Team Form Options
Retrieves available active field personnel and team roles to populate team creation/editing dropdowns.

- **Method:** `GET`
- **URL:** `/api/v1/teams/team-options`
- **Access Level:** Supervisor (`can:access-supervisor`)
- **Headers:** `Authorization: Bearer <token>`, `Accept: application/json`

#### Response: `200 OK`
```json
{
  "success": true,
  "data": {
    "personnel": [
      {
        "id": 22,
        "first_name": "Pedro",
        "middle_name": "A",
        "last_name": "Lineman",
        "name_ext": null
      },
      {
        "id": 23,
        "first_name": "Juan",
        "middle_name": "B",
        "last_name": "Electrician",
        "name_ext": "Jr."
      }
    ],
    "memberRoles": [
      { "id": 1, "role_name": "Team Leader" },
      { "id": 2, "role_name": "Lineman" },
      { "id": 3, "role_name": "Driver" }
    ]
  }
}
```

---

### 12.2. List Department Teams
Retrieves a paginated list of teams belonging to the supervisor's department, complete with workload counts and member rosters.

- **Method:** `GET`
- **URL:** `/api/v1/teams`
- **Access Level:** Supervisor (`can:access-supervisor`)
- **Headers:** `Authorization: Bearer <token>`, `Accept: application/json`

#### Query Parameters
| Parameter | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `page` | `integer` | No | Target page number. |
| `search` | `string` | No | Filter by team name or description. |
| `filter` | `string` | No | `active` (default) or `archived`. |
| `sort` | `string` | No | `name_asc`, `name_desc`, `newest`, `oldest`. |

#### Response: `200 OK`
```json
{
  "data": [
    {
      "id": "01JM4BT01TEAM1234567890",
      "team_name": "Alpha Response Crew",
      "team_desc": "Main emergency quick response unit",
      "shift_start": "08:00",
      "shift_end": "17:00",
      "deleted_at": null,
      "is_archived": false,
      "updated_at_value": "2026-10-06T09:00:00.000000Z",
      "updated_at_display": "Oct 06, 2026 09:00 AM",
      "members_count": 2,
      "ticket_stats": {
        "total": 5,
        "open": 0,
        "assigned": 2,
        "in_progress": 2,
        "resolved": 1,
        "closed": 0
      },
      "members": [
        {
          "id": 22,
          "full_name": "Pedro Lineman",
          "team_role_id": 1,
          "role_name": "Team Leader"
        }
      ]
    }
  ],
  "links": { ... },
  "meta": { ... },
  "success": true
}
```

---

### 12.3. Get Team Details
Retrieves details, member list, and workload stats for a specific team.

- **Method:** `GET`
- **URL:** `/api/v1/teams/{team}`
- **Access Level:** Supervisor (`can:access-supervisor`)
- **Headers:** `Authorization: Bearer <token>`, `Accept: application/json`

#### Response: `200 OK`
```json
{
  "success": true,
  "data": {
    "id": "01JM4BT01TEAM1234567890",
    "team_name": "Alpha Response Crew",
    "team_desc": "Main emergency quick response unit",
    "shift_start": "08:00",
    "shift_end": "17:00",
    "deleted_at": null,
    "is_archived": false,
    "updated_at_value": "2026-10-06T09:00:00.000000Z",
    "updated_at_display": "Oct 06, 2026 09:00 AM",
    "members_count": 2,
    "ticket_stats": {
      "total": 5,
      "open": 0,
      "assigned": 2,
      "in_progress": 2,
      "resolved": 1,
      "closed": 0
    },
    "members": [
      {
        "id": 22,
        "full_name": "Pedro Lineman",
        "team_role_id": 1,
        "role_name": "Team Leader"
      }
    ]
  }
}
```

---

### 12.4. Create New Team
Creates a new operational team under the supervisor's department and syncs initial members.

- **Method:** `POST`
- **URL:** `/api/v1/teams` *(Alias: `/api/v1/teams/create`)*
- **Access Level:** Supervisor (`can:access-supervisor`)
- **Headers:** `Authorization: Bearer <token>`, `Content-Type: application/json`, `Accept: application/json`

#### Request Body
```json
{
  "team_name": "Charlie Maintenance Unit",
  "team_desc": "Scheduled maintenance and substation crew",
  "shift_start": "07:00",
  "shift_end": "16:00",
  "members": [
    { "user_id": 22, "team_role_id": 1 },
    { "user_id": 23, "team_role_id": 2 }
  ]
}
```

#### Response: `201 Created`
```json
{
  "success": true,
  "message": "Team and members created successfully.",
  "data": {
    "id": "01JM4XYZ09876543210ABCDEF",
    "team_name": "Charlie Maintenance Unit",
    "members_count": 2
  }
}
```

> [!NOTE]
> Every `members[].user_id` must be an **active** Field Personnel account (the same rule applies to §12.5). A deactivated account is rejected with `HTTP 422` on `members.N.user_id`: `"One or more of the selected members are not active Field Personnel."`

---

### 12.5. Update Team
Updates team metadata and modifies member rosters with optimistic locking protection.

> [!IMPORTANT]
> **Roster Rules:**
> - **Leaving out `members` keeps the current roster.** A rename or shift change can be sent without the `members` field and no member is removed.
> - **`members: []` clears the roster**, but only while the team has no `ASSIGNED`, `IN_PROGRESS`, `RESOLVED` or `PENDING_ENDORSEMENT` tickets. Otherwise the update is refused with `HTTP 422` and the message lists the blocking ticket numbers. Removing some members (keeping at least one) is always allowed.
> - **Members must be active** Field Personnel accounts (see §12.4).
> - The team's department is always the supervisor's own; a `department_id` in the body is ignored.

- **Method:** `PUT`
- **URL:** `/api/v1/teams/{team}` *(Alias: `/api/v1/teams/{team}/update`)*
- **Access Level:** Supervisor (`can:access-supervisor`)
- **Headers:** `Authorization: Bearer <token>`, `Content-Type: application/json`, `Accept: application/json`

#### Request Body
```json
{
  "original_updated_at": "2026-10-06T09:00:00.000000Z",
  "team_name": "Charlie Maintenance Unit",
  "team_desc": "Updated description",
  "shift_start": "08:00",
  "shift_end": "17:00",
  "members": [
    { "user_id": 22, "team_role_id": 1 }
  ]
}
```

#### Response: `200 OK`
```json
{
  "success": true,
  "message": "Team updated successfully.",
  "data": {
    "id": "01JM4XYZ09876543210ABCDEF",
    "team_name": "Charlie Maintenance Unit",
    "members_count": 1
  }
}
```

#### Error Response: `422 Unprocessable Content` (Emptying a Busy Team)
```json
{
  "success": false,
  "message": "Cannot remove every member while the team still has active tickets: TKT-261020-002. Reassign or finish them first."
}
```

#### Error Response: `409 Conflict` (Stale Edit or Archived Team)
*Returned when `original_updated_at` no longer matches (someone else saved the team first) or the team was archived.*
```json
{
  "success": false,
  "message": "Conflict: This team was modified by another user while you were editing."
}
```

---

### 12.6. Archive Team (Soft Delete)
Soft deletes a team only while it has no `ASSIGNED`, `IN_PROGRESS`, `RESOLVED` or `PENDING_ENDORSEMENT` tickets. `RESOLVED` and `PENDING_ENDORSEMENT` tickets count because a rejected report or endorsement sends the ticket back to this team. Otherwise the request is refused with `HTTP 409`: `"Cannot archive team. It still has tickets that are assigned, in progress, awaiting report verification, or pending endorsement."`

- **Method:** `DELETE`
- **URL:** `/api/v1/teams/{team}/archive`
- **Access Level:** Supervisor (`can:access-supervisor`)
- **Headers:** `Authorization: Bearer <token>`, `Accept: application/json`

#### Response: `200 OK`
```json
{
  "success": true,
  "message": "Team archived successfully.",
  "data": {
    "id": "01JM4XYZ09876543210ABCDEF",
    "is_archived": true
  }
}
```

---

### 12.7. Restore Archived Team
Restores an archived team back to active status. Refused with `HTTP 409` while the team's department is archived (`"Cannot restore team. Its department is archived; restore the department first."`) or when an active team in the department already uses the same name.

- **Method:** `PATCH`
- **URL:** `/api/v1/teams/{team}/restore`
- **Access Level:** Supervisor (`can:access-supervisor`)
- **Headers:** `Authorization: Bearer <token>`, `Accept: application/json`

#### Response: `200 OK`
```json
{
  "success": true,
  "message": "Team restored successfully.",
  "data": {
    "id": "01JM4XYZ09876543210ABCDEF",
    "is_archived": false
  }
}
```

---

### 12.8. Force Delete Team (Permanent Purge)
Permanently deletes a previously archived team from the database. Meant only for undoing a team created by mistake: it is refused with `HTTP 409` if the team was **ever** assigned to a ticket, including tickets later reassigned to another team (`"Cannot permanently delete <team> because it has been assigned to service tickets. Keep it archived instead."`). Teams that have done real work stay archived, so ticket assignment history is never lost.

- **Method:** `DELETE`
- **URL:** `/api/v1/teams/{team}/force-delete`
- **Access Level:** Supervisor (`can:access-supervisor`)
- **Headers:** `Authorization: Bearer <token>`, `Accept: application/json`

#### Response: `200 OK`
```json
{
  "success": true,
  "message": "Team permanently deleted successfully."
}
```
