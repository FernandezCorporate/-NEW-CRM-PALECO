# Real-time Event Catalog

Every event the backend broadcasts, as of October 21, 2026. Source of truth: `PALECO_OTRS-CRM/app/Events/*.php` (`broadcastOn()` for channels, `broadcastWith()` for payloads).

**Conventions**
- Channel names are shown without the `private-` prefix. Clients subscribe to `private-<name>`.
- The broadcast name is the class name (Echo: listen to `.Name`; raw Pusher: bind `Name`).
- Ticket, team and user ids are ULID strings. Department and category ids are integers.
- Dates are pre-formatted strings such as `"Oct 21, 2026 09:04 PM"` (Asia/Manila), not ISO timestamps.
- `status` is the enum value (`open`, `assigned`, `in_progress`, `pending_endorsement`, `endorsed`, `resolved`, `closed`, `cancelled`); `status_label` is its display text.
- A payload field can be `null` when the value does not exist (for example `team_id` on an Open ticket).

| # | Event | Status | Fired by |
| --- | --- | --- | --- |
| 1 | [TicketCreated](#1-ticketcreated) | Unchanged | CWD creates a ticket or a child ticket; an endorsement approval creates a child |
| 2 | [TicketIntakeUpdated](#2-ticketintakeupdated) | **New** | CWD edits an Open ticket |
| 3 | [TicketAssigned](#3-ticketassigned) | Unchanged | Supervisor assigns or reassigns |
| 4 | [TicketWorkStarted](#4-ticketworkstarted) | **Changed meaning** | Crew starts work (no longer sent on report rejection) |
| 5 | [TicketAccomplished](#5-ticketaccomplished) | Unchanged | Crew submits a report |
| 6 | [TicketReportRejected](#6-ticketreportrejected) | **New** | Supervisor rejects a report |
| 7 | [TicketVerifiedAndClosed](#7-ticketverifiedandclosed) | Unchanged | Supervisor approves a report |
| 8 | [TicketCancelled](#8-ticketcancelled) | Unchanged | CWD cancels an Open ticket |
| 9 | [TicketForceClosed](#9-ticketforceclosed) | Unchanged | CWD force-closes |
| 10 | [TicketEndorsementInitiated](#10-ticketendorsementinitiated) | **Changed** | Supervisor requests an endorsement |
| 11 | [TicketEndorsementDecided](#11-ticketendorsementdecided) | **Changed** | CWD approves or rejects an endorsement |
| 12 | [TicketRemarkAdded](#12-ticketremarkadded) | **New** | Anyone posts a remark |
| 13 | [TeamUpdated](#13-teamupdated) | **New** | Supervisor or admin changes a team; deactivation removes a crew member |
| 14 | [AccountUpdated](#14-accountupdated) | **New** | Admin deactivates, reactivates, moves or resets an account |
| 15 | [ReferenceDataUpdated](#15-referencedataupdated) | **New** | Admin changes a department or category |

---

## 1. TicketCreated

**Channels:** `cwd.operations`, `department.{ticket department}`

```json
{
  "ticket_id": "01m5en8x...", "ticket_number": "TKT-261021-004", "parent_ticket_id": null,
  "status": "open", "status_label": "Open",
  "source": "phone_call", "address": "Purok 3, Malvar Street, San Pedro",
  "category": "Power Interruption / Blackout",
  "department_id": 1, "department": "Technical Services Department (TSD)",
  "created_at": "Oct 21, 2026 09:04 PM"
}
```
A child ticket has `parent_ticket_id` set. A child created by an endorsement approval is followed by `TicketEndorsementDecided` with the same `child_ticket_id`.

## 2. TicketIntakeUpdated

**New.** CWD saved changes to an Open ticket's intake (contact, category, address, description, consumer link or department).

**Channels:** `cwd.operations`, `department.{new department}`, and `department.{previous department}` when it moved.

```json
{
  "ticket_id": "01m5en8x...", "ticket_number": "TKT-261021-004", "parent_ticket_id": null,
  "status": "open", "status_label": "Open",
  "department_id": 3, "department": "Area Operations Department (AOD)",
  "previous_department_id": 1,
  "department_changed": true,
  "updated_at": "Oct 21, 2026 09:10 PM"
}
```
| Field | Meaning |
| --- | --- |
| `department_changed` | `true` when the ticket was re-routed. The previous department's supervisors lose the ticket; the new department gains it. |
| `previous_department_id` | The department before the edit (equal to `department_id` when it did not move). |

## 3. TicketAssigned

**Channels:** `cwd.operations`, `team.{new team}`, `team.{previous team}` (reassignment), `department.{id}`

```json
{
  "ticket_id": "...", "ticket_number": "...", "parent_ticket_id": null,
  "status": "assigned", "status_label": "Assigned",
  "team_id": "01m4...", "team_name": "Alpha Emergency Response Crew",
  "previous_team_id": "01m4...",
  "department": "Technical Services Department (TSD)", "updated_at": "..."
}
```
`previous_team_id` is `null` on a first assignment. On reassignment the displaced crew receives this event on its old team channel and should drop the ticket.

## 4. TicketWorkStarted

**Changed meaning:** now only sent when a crew member starts work. It used to also be sent when a supervisor rejected a report; that case is now `TicketReportRejected`.

**Channels:** `cwd.operations`, `team.{id}`, `department.{id}`

Fields: `ticket_id`, `ticket_number`, `parent_ticket_id`, `status` (`in_progress`), `status_label`, `team_id`, `team_name`, `department`, `started_at`, `updated_at`.

## 5. TicketAccomplished

**Channels:** `cwd.operations`, `team.{id}`, `department.{id}`

Fields: `ticket_id`, `ticket_number`, `parent_ticket_id`, `status` (`resolved`), `status_label`, `team_id`, `team_name`, `department`, `resolved_at`, `updated_at`. The supervisor now has a report to verify.

## 6. TicketReportRejected

**New.** A supervisor rejected the crew's accomplishment report; the ticket is back In Progress and the crew must redo or complete the work.

**Channels:** `cwd.operations`, `team.{id}`, `department.{id}`

```json
{
  "ticket_id": "...", "ticket_number": "...", "parent_ticket_id": null,
  "status": "in_progress", "status_label": "In Progress",
  "team_id": "01m4...", "team_name": "Alpha Emergency Response Crew",
  "department_id": 1, "department": "Technical Services Department (TSD)",
  "accomplishment_id": 15,
  "rejection_reason": "Photos are blurry; retake the meter close-up.",
  "rejected_by": "Mycka Supervisor",
  "updated_at": "..."
}
```

## 7. TicketVerifiedAndClosed

**Channels:** `cwd.operations`, `team.{id}`, `department.{id}`

Fields: `ticket_id`, `ticket_number`, `parent_ticket_id`, `status` (`closed`), `status_label`, `team_id`, `team_name`, `department`, `closed_at`, `updated_at`.

## 8. TicketCancelled

**Channels:** `cwd.operations`, `team.{id}` (if any), `department.{id}`

Fields: `ticket_id`, `ticket_number`, `parent_ticket_id`, `status` (`cancelled`), `status_label`, `cancellation_reason`, `cancelled_at`, `department`, `updated_at`.

## 9. TicketForceClosed

**Channels:** `cwd.operations`, `team.{team that had it}`, `department.{id}`

Fields: `ticket_id`, `ticket_number`, `parent_ticket_id`, `team_id`, `status` (`closed`), `status_label`, `is_force_closed` (`true`), `closure_reason`, `closed_by` (name), `closed_at`, `department`, `updated_at`. Field crews should stop work immediately.

## 10. TicketEndorsementInitiated

**Changed:** previously only `cwd.operations`.

**Channels:** `cwd.operations`, `team.{id}` (if the ticket was Assigned), `department.{id}`

```jsonc
{
  "ticket_id": "...", "ticket_number": "...", "parent_ticket_id": null,
  "endorsement_id": "01m5f8vr...",
  "team_id": "01m4...",            // NEW (null for an Open ticket)
  "department_id": 1,              // NEW
  "status": "pending_endorsement", "status_label": "Pending Endorsement",
  "suggested_department_id": 3, "suggested_department_name": "Area Operations Department (AOD)",
  "reason": "Needs a crane bucket.", "requested_by": "Mycka Supervisor",
  "created_at": "..."
}
```
The ticket is frozen: no start, report, assignment or second request until CWD decides. Only Open and Assigned tickets can be endorsed, so a crew is never mid-work when this arrives.

## 11. TicketEndorsementDecided

**Changed:** previously only `cwd.operations`.

**Channels:** `cwd.operations`, `team.{team}` (after rejection: the restored team; after approval: the team that just lost the ticket), `department.{ticket department}`

```jsonc
{
  "ticket_id": "...", "ticket_number": "...", "parent_ticket_id": null,
  "endorsement_id": "...",
  "decision": "approved", "decision_label": "Approved",
  "rejection_reason": null,
  "parent_status": "endorsed", "parent_status_label": "Endorsed",
  "child_ticket_id": "01m5...", "child_ticket_number": "TKT-261021-004-1",
  "team_id": null,                 // NEW: the ticket's team now (null after approval)
  "previous_team_id": "01m4...",   // NEW: the team that lost the ticket on approval
  "department_id": 1,              // NEW: the ticket's (origin) department
  "new_department_id": 3,          // NEW: where the work went (null on rejection)
  "reviewed_by": "Alliah Officer", "reviewed_at": "..."
}
```
| Decision | What it means for listeners |
| --- | --- |
| `approved` | The ticket becomes `endorsed`, its team is cleared, and a child ticket opens in `new_department_id` (that department also gets `TicketCreated`). The previous crew should drop the ticket. |
| `rejected` | The ticket returns to `parent_status` with the same team; work can continue. `rejection_reason` is CWD's note. |

## 12. TicketRemarkAdded

**New.**

**Channels:** always `cwd.operations`. For **public** remarks also `team.{id}` (if the ticket has a team) and `department.{id}`. **Internal** remarks (CWD notes) go only to `cwd.operations` and their `body` is always `null`, because supervisors also listen there.

```json
{
  "ticket_id": "...", "ticket_number": "...", "parent_ticket_id": null,
  "team_id": "01m4...", "department_id": 1,
  "remark_id": 89,
  "is_internal": false,
  "body": "Crew on site, replacing the fuse.",
  "author": "Pedro Lineman", "author_role": "Field Personnel",
  "created_at": "..."
}
```
Mobile apps list only public remarks, so they should ignore payloads where `is_internal` is `true`.

## 13. TeamUpdated

**New.**

**Channels:** `cwd.operations`, `team.{id}`, `department.{id}`, `department.{previous department}` (if the team moved; admin-only action), and `user.{id}` for every member in `added_member_ids`, `removed_member_ids` or the notified set (archive / restore).

```json
{
  "team_id": "01m4...", "team_name": "Alpha Emergency Response Crew",
  "department_id": 1, "previous_department_id": null,
  "action": "updated",
  "added_member_ids": ["01m6..."],
  "removed_member_ids": ["01m5..."],
  "is_archived": false,
  "updated_at": "..."
}
```
| `action` | Sent when | Members told on `user.{id}` |
| --- | --- | --- |
| `created` | Supervisor or admin creates a team | Every initial member (all in `added_member_ids`) |
| `updated` | Name, description, shift, department or roster changed; also when deactivating a crew member removes them | Members in `added_member_ids` and `removed_member_ids` |
| `archived` | Team archived | Every member (they stay on the roster; ids are not listed in the payload) |
| `restored` | Team restored | Every member |
| `deleted` | Team permanently deleted (only teams that never handled a ticket) | Every former member (in `removed_member_ids`) |

## 14. AccountUpdated

**New.** Sent only to the affected account.

**Channel:** `user.{id}`

```json
{
  "user_id": "01m5...",
  "change": "deactivated",
  "is_active": false,
  "department_id": 1,
  "previous_department_id": null,
  "requires_sign_out": true,
  "updated_at": "..."
}
```
| `change` | `requires_sign_out` | Meaning |
| --- | --- | --- |
| `deactivated` | `true` | The account was deactivated; its API token is already revoked. |
| `password_reset` | `true` | An admin set a new password; all tokens are already revoked. |
| `department_changed` | `false` | A supervisor was moved. `previous_department_id` → `department_id`. Leave the old department channel, join the new one, reload. |
| `reactivated` | `false` | The account is active again (only reachable if the app is still connected, which is rare). |

## 15. ReferenceDataUpdated

**New.**

**Channels:** `cwd.operations`; for departments also `department.{id}` (the department that changed)

```json
{ "type": "department", "action": "archived", "id": 3, "name": "Area Operations Department (AOD)", "updated_at": "..." }
```
| Field | Values |
| --- | --- |
| `type` | `department` or `category` |
| `action` | `created`, `updated`, `archived`, `restored`, `deleted` |

Use it to refresh option lists such as endorsement target departments and category filters. Supervisors receive department changes made anywhere through `cwd.operations`, because the endorsement options list every department.
