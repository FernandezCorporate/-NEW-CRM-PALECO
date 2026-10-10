# PALECO OTRS-CRM Mobile API Documentation

> **Base URL:** `http://<host-or-domain>/api`  
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
- **Offline-Asynchronous Resilience:** Field personnel endpoints (`/start` and `/accomplish`) support store-and-forward offline execution via `X-Idempotency-Key` (UUIDv4) and `X-Client-Timestamp` (ISO-8601). Network drops and automatic retries are handled idempotently without duplicate records or 422 conflicts (`X-Cache: HIT`).

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
    "first": "http://crm.paleco.local/api/tickets?page=1",
    "last": "http://crm.paleco.local/api/tickets?page=5",
    "prev": null,
    "next": "http://crm.paleco.local/api/tickets?page=2"
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

#### 403 Forbidden (Insufficient Role Permissions or Department Mismatch)
```json
{
  "success": false,
  "message": "This action is unauthorized."
}
```

#### 404 Not Found (Missing Resource or ULID Mismatch)
```json
{
  "success": false,
  "message": "Accomplishment report not found."
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
- **URL:** `/api/login`
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
> - **Supervisor:** `username: mycka` | `password: password` (Assigned to Technical Services Department)
> - **Field Personnel:** `username: ralph` | `password: password` (Assigned to Alpha Emergency Response Crew)

#### Request Example
```json
{
  "username": "mycka",
  "password": "password",
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

---

### 4.2. Mobile Logout
Terminates the authenticated session by destroying the current Sanctum access token.

- **Method:** `POST`
- **URL:** `/api/logout`
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
- **URL:** `/api/user/profile`
- **Access Level:** Authenticated (`auth:sanctum`)
- **Headers:** `Authorization: Bearer <token>`, `Accept: application/json`

#### Response: `200 OK`
```json
{
  "success": true,
  "data": {
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
- **URL:** `/api/tickets`
- **Access Level:** Authenticated (`auth:sanctum`)
- **Headers:** `Authorization: Bearer <token>`, `Accept: application/json`

#### Query Parameters
| Parameter | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `page` | `integer` | No | Target page number (default: `1`). |
| `search` | `string` | No | Search term matching ticket number, consumer name, subject, or landmark. |
| `category` | `string` | No | Filter by ticket category name. |
| `status` | `string` | No | Filter by ticket status (`open`, `assigned`, `in_progress`, `resolved`, `closed`, `pending_endorsement`, `endorsed`, or `all` to return all tickets). Unrecognized status strings are safely ignored. |
| `sort` | `string` | No | Sort order: `newest` (default) or `oldest`. |

#### Response: `200 OK`
```json
{
  "data": [
    {
      "id": "01JM4C7W1ABCDEF1234567890",
      "ticket_number": "2026-0042",
      "consumer_contact": "09189876543",
      "complaint_source": "Phone Call",
      "ticket_subject": "Low Voltage Complaint - Brgy. San Pedro",
      "complaint_description": "Transformer tripping intermittently during peak load hours.",
      "category_name": "Power Quality / Voltage Problem",
      "purok": "Purok 3",
      "street": "Malvar Street",
      "barangay": "San Pedro",
      "landmark": "Near San Pedro Chapel",
      "team_id": "01JM4BT01TEAM1234567890",
      "team_name": "Alpha Response Crew",
      "created_by": 5,
      "created_by_name": "Dispatcher Jane",
      "reported_at": "Oct 06, 2026 09:15 AM",
      "started_at": "Oct 06, 2026 10:00 AM",
      "resolved_at": null,
      "closed_at": null,
      "created_at": "Oct 06, 2026 09:15 AM",
      "updated_at": "Oct 06, 2026 10:00 AM",
      "status": "In Progress",
      "child_tickets_count": 0
    }
  ],
  "links": {
    "first": "http://crm.paleco.local/api/tickets?page=1",
    "last": "http://crm.paleco.local/api/tickets?page=1",
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
      "endorsed": 0
    }
  },
  "success": true
}
```

---

### 6.2. Get Ticket Detailed Information
Returns the full ticket details including complainant particulars, assigned crew, status logs timeline, and linked child tickets.

- **Method:** `GET`
- **URL:** `/api/tickets/{ticket}`
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
    "status": "In Progress",
    "reported_at": "Oct 06, 2026 09:15 AM",
    "started_at": "Oct 06, 2026 10:00 AM",
    "resolved_at": null,
    "closed_at": null,
    "creator": {
      "id": 5,
      "name": "Jane Dispatcher",
      "role": "Call Center Agent"
    },
    "consumer": {
      "id": 1042,
      "acct_no": "01-2345-6789",
      "acct_code": "RES-01",
      "name": "Maria Santos",
      "address": "Purok 3, San Pedro, Puerto Princesa City",
      "status": "Active",
      "meter_serial": "PAL-998811"
    },
    "complaint": {
      "source": "Phone Call",
      "consumer_contact": "09189876543",
      "category_name": "Power Quality / Voltage Problem",
      "description": "Transformer tripping intermittently during peak load hours.",
      "full_address": "Purok 3, Malvar Street, San Pedro, Puerto Princesa City",
      "landmark": "Near San Pedro Chapel"
    },
    "team": {
      "id": "01JM4BT01TEAM1234567890",
      "team_name": "Alpha Response Crew",
      "shift_start": "08:00",
      "shift_end": "17:00",
      "personnel": [
        { "id": 22, "name": "Pedro Lineman" },
        { "id": 23, "name": "Juan Electrician" }
      ]
    },
    "status_logs": [
      {
        "id": 101,
        "old_status": "Open",
        "new_status": "Assigned",
        "changed_by_name": "John Doe",
        "created_at": "Oct 06, 2026 09:30 AM"
      },
      {
        "id": 102,
        "old_status": "Assigned",
        "new_status": "In Progress",
        "changed_by_name": "Pedro Lineman",
        "created_at": "Oct 06, 2026 10:00 AM"
      }
    ],
    "child_tickets_count": 0,
    "child_tickets": []
  }
}
```

---

### 6.3. Start Work on Ticket
Transitions an assigned ticket to `In Progress` status upon field crew arrival or work start. Supports **offline-asynchronous store-and-forward** and **idempotent retries**.

- **Method:** `PATCH`
- **URL:** `/api/tickets/{ticket}/start`
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

#### Error Response: `422 Unprocessable Content` (Client Timestamp Validation)
```json
{
  "success": false,
  "message": "The client timestamp cannot be earlier than ticket creation date.",
  "errors": {
    "client_timestamp": [
      "The client timestamp cannot be earlier than ticket creation date."
    ]
  }
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
- **URL:** `/api/tickets/{ticket}/history`
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
        "unassigned_at": null
      }
    ],
    "endorsements": []
  }
}
```

---

## 7. Ticket Remarks Endpoints

### 7.1. List Ticket Remarks
Retrieves all public remarks/comments posted on the ticket. Internal administrative notes are filtered out automatically.

- **Method:** `GET`
- **URL:** `/api/tickets/{ticket}/remarks`
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
Posts a new public remark to the ticket timeline.

- **Method:** `POST`
- **URL:** `/api/tickets/{ticket}/remarks`
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
- **URL:** `/api/tickets/{ticket}/assign-options`
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
Assigns or reassigns a service ticket to a designated team. If the ticket is being reassigned from an existing team, a mandatory `reason` is required.

- **Method:** `POST`
- **URL:** `/api/tickets/{ticket}/assign`
- **Access Level:** Supervisor (`can:access-supervisor`)
- **Headers:** `Authorization: Bearer <token>`, `Content-Type: application/json`, `Accept: application/json`

#### Request Body
| Field | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `team_id` | `string` | **Yes** | ID of the target team. |
| `reason` | `string` | Conditional | **Required** if reassigning from an existing team; optional on initial assignment. Max 1000 characters. |

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
    "status": "Assigned"
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
- **URL:** `/api/tickets/{ticket}/endorse-options`
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

- **Method:** `POST`
- **URL:** `/api/tickets/{ticket}/endorse`
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
    "created_by": "John Doe",
    "creator_role": "Department Supervisor",
    "endorsement_reason": "Inspection reveals defective digital meter dial. Requires meter laboratory recalibration.",
    "time_requested": "Oct 06, 2026 12:00 PM",
    "request_status": "Pending",
    "pre_endorsement_status": "In Progress",
    "reviewed_by": null,
    "rejection_reason": null,
    "verified_at": null
  }
}
```

---

## 10. Ticket Accomplishment & Verification Endpoints

### 10.1. List Accomplishment Reports
Retrieves all accomplishment submissions for a ticket.

- **Method:** `GET`
- **URL:** `/api/tickets/{ticket}/accomplishments`
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
- **URL:** `/api/tickets/{ticket}/accomplishments/{accomplishment}`
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
- **URL:** `/api/tickets/{ticket}/accomplish`
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

#### Error Response: `422 Unprocessable Content` (Client Timestamp Chronology)
```json
{
  "success": false,
  "message": "The client timestamp cannot be earlier than ticket creation date.",
  "errors": {
    "client_timestamp": [
      "The client timestamp cannot be earlier than ticket creation date."
    ]
  }
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
- **URL:** `/api/tickets/{ticket}/accomplishments/{accomplishment}/verify`
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
- **URL:** `/api/dashboard`
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
- **URL:** `/api/teams/team-options`
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
- **URL:** `/api/teams`
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
- **URL:** `/api/teams/{team}`
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
- **URL:** `/api/teams` *(Alias: `/api/teams/create`)*
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

---

### 12.5. Update Team
Updates team metadata and modifies member rosters with optimistic locking protection.

- **Method:** `PUT`
- **URL:** `/api/teams/{team}` *(Alias: `/api/teams/{team}/update`)*
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

---

### 12.6. Archive Team (Soft Delete)
Soft deletes a team if no active tickets (`Open`, `Assigned`, `In Progress`, `Resolved`) are currently assigned.

- **Method:** `DELETE`
- **URL:** `/api/teams/{team}/archive`
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
Restores an archived team back to active status.

- **Method:** `PATCH`
- **URL:** `/api/teams/{team}/restore`
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
Permanently deletes a previously archived team from the database. Blocked if the team has any historical service ticket associations.

- **Method:** `DELETE`
- **URL:** `/api/teams/{team}/force-delete`
- **Access Level:** Supervisor (`can:access-supervisor`)
- **Headers:** `Authorization: Bearer <token>`, `Accept: application/json`

#### Response: `200 OK`
```json
{
  "success": true,
  "message": "Team permanently deleted successfully."
}
```
