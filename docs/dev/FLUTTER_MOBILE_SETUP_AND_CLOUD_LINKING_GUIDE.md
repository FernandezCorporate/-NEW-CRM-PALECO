# PALECO OTRS-CRM: Flutter Mobile Integration & Cloud Context Spec

> **Target Audience:** Team Mobile Developers & AI Coding Agents  
> **Purpose:** Provides all connection parameters, live cloud endpoints, authentication contracts, WebSocket channel schemas, and test accounts required to build or configure the Flutter mobile client.

---

## 1. Cloud Infrastructure & Endpoint Inventory

The backend is deployed live on Render.com with TiDB Cloud (MySQL 8.0), Supabase Storage (S3), and Pusher Channels (WebSockets).

| Parameter | Cloud Production Value | Local Development Value | Description |
| :--- | :--- | :--- | :--- |
| **REST API Base URL** | `https://paleco-otrs.onrender.com/api/v1` | `http://10.0.2.2:8000/api/v1` *(Emulator)*<br>`http://192.168.x.x:8000/api/v1` *(Phone)* | Versioned API root. All mobile endpoints require `/api/v1/`. |
| **WebSocket Provider** | **Pusher Channels Cloud** | Laravel Reverb (Port 8080) | Managed WebSocket server on standard Port 443. |
| **Pusher App Key** | `ccb7b854b71323e17dac` | Local Reverb Key (`8ygjucohu35acd17ckbm`) | Client-side connection key. |
| **Pusher Cluster** | `ap1` (Singapore) | `mt1` | Regional cluster. |
| **WebSocket TLS** | `true` (WSS on Port 443) | `false` (WS on Port 8080) | TLS encryption mode. |
| **Broadcasting Auth URL** | `https://paleco-otrs.onrender.com/api/v1/broadcasting/auth` | `http://10.0.2.2:8000/api/v1/broadcasting/auth` | Authorizes private channel subscriptions. |
| **Media Storage** | **Supabase Storage S3** (`paleco-media`) | Local `/storage/` | Accomplishment photos & signatures stream to Supabase. |

---

## 2. Authentication Contract (`POST /api/v1/login`)

### Request Payload:
```json
POST https://paleco-otrs.onrender.com/api/v1/login
Headers:
  Accept: application/json
  Content-Type: application/json

Body:
{
  "username": "ralph",
  "password": "Password",
  "device_name": "Infinix-X6816" // REQUIRED: any unique string or device model
}
```

### Response Payload (HTTP 200):
```json
{
  "success": true,
  "message": "Authentication successful.",
  "access_token": "1|ROy0aSiARLD9kz7eRkxp9OLSBUjEK8t8bBvaadR72f1a98ab",
  "token_type": "Bearer",
  "data": {
    "access_token": "1|ROy0aSiARLD9kz7eRkxp9OLSBUjEK8t8bBvaadR72f1a98ab",
    "user": {
      "id": "01m54s2ebc75t8nkddmb2c3grx",
      "username": "ralph",
      "name": "Ralph Personnel",
      "role_slug": "field_personnel", // or "supervisor"
      "department_id": 1,
      "teams": [
        {
          "id": "01m54s2apas3s97ngh05x70708",
          "team_name": "Alpha Emergency Response Crew",
          "role_name": "Team Leader"
        }
      ]
    }
  }
}
```

> **Header for subsequent requests:**  
> `Authorization: Bearer <access_token>`  
> `Accept: application/json`

---

## 3. Seeded Accounts for Testing

| Role | Username | Password | Intended Screen Flow |
| :--- | :--- | :--- | :--- |
| **Supervisor** | `mycka` | `Password` | View dispatched tickets, assign crew to tickets, endorse inter-department transfers, review & approve accomplishments. |
| **Field Lineman** | `ralph` | `Password` | View crew tickets, start work order, capture consumer signature, upload accomplishment photos. |

---

## 4. Real-Time WebSocket Channels & Events (Pusher)

When the app initializes Pusher, subscribe to the following private channels using the user's role and team IDs:

### Private Channels to Subscribe:
1. `private-cwd.operations`: Broadcasts all operational ticket events across PALECO.
2. `private-department.{deptId}`: Broadcasts tickets specific to the supervisor's department (`user.department_id`).
3. `private-team.{teamId}`: Broadcasts tickets assigned to the lineman's team (`user.teams[0].id`).

### Events Dispatched by Backend:
- `.TicketCreated`: Dispatched when CWD creates a complaint.
- `.TicketAssigned`: Dispatched when a supervisor assigns a crew.
- `.TicketWorkStarted`: Dispatched when field crew taps **Start Work**.
- `.TicketAccomplished`: Dispatched when field crew submits report with photo evidence.
- `.TicketVerifiedAndClosed`: Dispatched when supervisor approves accomplishment.
- `.TicketCancelled`: Dispatched if CWD cancels the ticket.
- `.TicketForceClosed`: Dispatched if CWD closes an abandoned ticket.

> **Note on Channel Authorization:**  
> Pusher private channels automatically POST to `ApiConfig.broadcastAuthUrl` with the client's `socket_id` and `channel_name`. The mobile client must attach `Authorization: Bearer <token>` to this authorization call.

---

## 5. Core Mobile REST API Endpoints

All routes require `Authorization: Bearer <token>` and `Accept: application/json`:

| Action | HTTP Method | Endpoint | Key Body / Query Params |
| :--- | :---: | :--- | :--- |
| **User Profile** | `GET` | `/api/v1/user/profile` | Returns current user details and teams. |
| **Tickets List** | `GET` | `/api/v1/tickets` | Query: `?status=all&page=1&search=keyword` |
| **Ticket Details** | `GET` | `/api/v1/tickets/{ticketId}` | Returns ticket info, consumer, category, accomplishments. |
| **Start Work** | `PATCH` | `/api/v1/tickets/{ticketId}/start` | Headers: `X-Idempotency-Key: <ulid>`, `X-Client-Timestamp: <iso>` |
| **Submit Accomplishment** | `POST` | `/api/v1/tickets/{ticketId}/accomplish` | **Multipart Form-Data:**<br>`remarks`: string<br>`consumer_name`: string (optional)<br>`signature`: image file (optional)<br>`photos[]`: array of image files |
| **Assign Crew** | `POST` | `/api/v1/tickets/{ticketId}/assign` | Body: `{"team_id": "<teamId>"}` *(Supervisor only)* |
| **Assign Form Options**| `GET` | `/api/v1/tickets/{ticketId}/assign-options` | Returns list of available crews in department. |
| **Endorse Transfer** | `POST` | `/api/v1/tickets/{ticketId}/endorse` | Body: `{"target_department_id": 2, "remarks": "..."}` |
| **Verify Accomplishment** | `POST` | `/api/v1/tickets/{ticketId}/accomplishments/{accId}/verify` | Body: `{"status": "approved"}` or `{"status": "rejected", "rejection_reason": "..."}` |
| **Ticket History** | `GET` | `/api/v1/tickets/{ticketId}/history` | Returns complete audit trail & status timeline. |
| **Logout** | `POST` | `/api/v1/logout` | Revokes the current Sanctum access token. |

---

## 6. Photo Evidence & Supabase Storage Context

- When the mobile app uploads photos via `POST /api/v1/tickets/{ticketId}/accomplish`, the backend automatically saves them to **Supabase Storage S3** (`paleco-media`).
- The backend stores them under `accomplishments/photos/<hash>.jpg` and `accomplishments/signatures/<hash>.png`.
- The API responses automatically return full public CDN URLs:
  ```
  https://uiwbrakbkjbqkbasbnyp.supabase.co/storage/v1/object/public/paleco-media/accomplishments/photos/...
  ```
- The mobile app can display these URLs directly using standard image loading widgets (`Image.network(url)`).

---

## 7. APK Build Quick Reference

```bash
# Fetch dependencies
flutter pub get

# Run on connected phone (debug)
flutter run

# Compile production APK for distribution
flutter build apk --release

# Output path:
# build/app/outputs/flutter-apk/app-release.apk
```
