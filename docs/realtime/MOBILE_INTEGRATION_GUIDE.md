# Mobile Integration Guide (Flutter)

**For:** the agent or developer who updates the Flutter app for supervisors and field personnel.
**Assumes you know:** the app already connects to Pusher / Reverb, authorizes private channels at `POST /api/v1/broadcasting/auth` with the Sanctum bearer token, and handles the older events.
**Assumes nothing about:** the app's screens, widgets or state management. Reactions below are written in terms of *data the app shows*, so map each one onto whatever screen or provider holds that data.

Read [EVENT_CATALOG.md](EVENT_CATALOG.md) for full payloads. This guide says what to subscribe to and what to do.

---

## 1. Summary of what the app must change

| # | Change | Required? |
| --- | --- | --- |
| 1 | Subscribe every signed-in account to **`private-user.{userId}`** | Required |
| 2 | Field personnel: **join and leave `private-team.{teamId}` at runtime** on `TeamUpdated` | Required |
| 3 | Handle **`AccountUpdated`**: sign out when `requires_sign_out` is true; resubscribe on `department_changed` | Required |
| 4 | Handle **`TicketReportRejected`** (rejection with reason). Stop treating `TicketWorkStarted` as a rejection signal. | **Breaking**, required |
| 5 | Handle `TicketEndorsementInitiated` / `TicketEndorsementDecided` on the **team and department** channels (crews receive them now) | Required |
| 6 | Handle `TicketIntakeUpdated`, `TicketRemarkAdded`, `TeamUpdated`, `ReferenceDataUpdated` | Required |
| 7 | **De-duplicate**: the same event can arrive on two channels | Required for supervisors |
| 8 | Show the API fields `is_endorsed`, `created_via`, `endorsement_origin`, `old_dept` / `new_dept` and the extended `ticket_stats` | Recommended (API already returns them) |

## 2. Subscriptions per role

| Role | Subscribe on sign-in | Notes |
| --- | --- | --- |
| Supervisor | `private-user.{userId}`, `private-department.{departmentId}`, `private-cwd.operations` | `cwd.operations` is still needed for `ReferenceDataUpdated` about **other** departments (endorsement targets) and category changes. Most ticket events arrive on both the department channel and `cwd.operations`: de-duplicate. |
| Field personnel | `private-user.{userId}`, `private-team.{teamId}` for **every** team the user belongs to | Team list comes from `GET /api/v1/user/profile` (`data.teams`). Keep the set of team channels in sync with `TeamUpdated` (section 4). |

`userId` is the ULID `id` from the login or profile response. A subscription to someone else's `private-user.*` is refused with 403.

**Reconnect / resume:** after any reconnect, resubscribe to the full set above and re-fetch whatever is on screen. Events sent while disconnected are not replayed.

**De-duplication:** treat two deliveries of the same broadcast name with an identical payload, received within a few seconds, as one. (The simulator keys on `eventName + JSON payload` for 5 seconds.)

## 3. Ticket events: what to refresh

"Ticket list" means any list of tickets the app shows (inbox, filtered lists, dashboard buckets and counters). "Open ticket" means a ticket whose details are on screen; also refresh when the payload's `parent_ticket_id` or `child_ticket_id` matches it. Refresh by re-requesting the API (`GET /api/v1/tickets`, `GET /api/v1/tickets/{id}`, `GET /api/v1/dashboard`). Payloads are notifications, not full records.

| Event | Supervisor | Field personnel |
| --- | --- | --- |
| `TicketCreated` | Refresh ticket list and dashboard; optional "new request" notice | Not received (crews only see assigned tickets) |
| `TicketIntakeUpdated` *(new)* | Refresh list, open ticket, dashboard. If `department_changed` and `previous_department_id` is the supervisor's department, the ticket **left** their department (remove it, or close it if open). If it changed into their department, it is new to them. | Not received (only Open tickets can be edited) |
| `TicketAssigned` | Refresh list and open ticket | If `team_id` is one of the user's teams, the ticket is now theirs. If `previous_team_id` is theirs and `team_id` is not, it was taken away: remove it and leave its detail view. |
| `TicketWorkStarted` | Refresh list, open ticket, dashboard | Refresh (a teammate started) |
| `TicketAccomplished` | Refresh; a report now awaits verification | Refresh |
| `TicketReportRejected` *(new, replaces the rejection use of WorkStarted)* | Refresh | **Prominent alert** with `rejection_reason`; the ticket is In Progress again and needs a new report. Refresh. |
| `TicketVerifiedAndClosed` | Refresh | Refresh; the ticket is done |
| `TicketCancelled` | Refresh | If the ticket is open on screen, show a stop-work alert with `cancellation_reason`. Refresh. |
| `TicketForceClosed` | Refresh | Stop-work alert with `closure_reason` (existing "emergency halt" behaviour). Refresh. |
| `TicketEndorsementInitiated` *(now also on team / department)* | Refresh; the ticket shows as frozen | The ticket is **frozen**: disable start and report actions for it until decided. Refresh. |
| `TicketEndorsementDecided` *(now also on team / department)* | Refresh. On `approved`, the ticket is `endorsed` and moves out of active work; `new_department_id` received it. | On `approved`, `previous_team_id` is the crew that **lost** the ticket: remove it. On `rejected`, the ticket is back to `parent_status`: re-enable actions. Refresh. |
| `TicketRemarkAdded` *(new)* | If the ticket is open, reload its remarks (`GET /api/v1/tickets/{id}/remarks`); optional notice | Same. Ignore payloads with `is_internal: true` (body is `null`; mobile never lists internal remarks). Skip the notice for the user's own remark. |

## 4. Team events (`TeamUpdated`)

Received by supervisors on their department channel and `cwd.operations`, by crews on the team channel, and by affected members on `private-user.{id}`.

**Field personnel: keep team channels in sync**

| Condition (compare with the signed-in user's id) | Do |
| --- | --- |
| User id is in `added_member_ids` | Subscribe to `private-team.{team_id}`, add the team to local state, refresh the ticket list (the team's tickets are now visible) |
| User id is in `removed_member_ids` | **Unsubscribe** from `private-team.{team_id}`, remove the team locally, refresh the ticket list, leave any open ticket of that team |
| `action` is `archived` and the user is a member | Notice that the crew was archived (no new tickets); keep the subscription until removal |
| `action` is `restored` | Notice that the crew is active again |
| `action` is `deleted` | Same as removal (members are listed in `removed_member_ids`) |

This matters for security as well as freshness: channel access is checked only when subscribing, so a removed member's app must leave the channel itself.

**Supervisor:** refresh the team list (`GET /api/v1/teams`) and any open team detail (`GET /api/v1/teams/{id}`); if an open ticket belongs to that team, refresh it (its crew roster changed).

## 5. Account events (`AccountUpdated`, only on `private-user.{id}`)

| `change` | Do |
| --- | --- |
| `deactivated` or `password_reset` (`requires_sign_out: true`) | The token is already revoked server-side: clear the session, disconnect the socket, return to sign-in, and explain why ("account deactivated" / "password was reset"). Do not retry API calls with the old token. |
| `department_changed` | Unsubscribe `private-department.{previous_department_id}`, subscribe `private-department.{department_id}`, update the stored user, reload all ticket data (the visible tickets are now a different department's). |
| `reactivated` | Optional notice. |

## 6. Reference data (`ReferenceDataUpdated`)

`type` is `department` or `category`; `action` is created / updated / archived / restored / deleted. Invalidate any cached option lists (endorsement target departments, category filters) so the next open re-fetches them (`GET /api/v1/tickets/{id}/endorse-options`). If such a picker is open, refresh it or show that the list changed. No other reaction is needed.

## 7. API fields that pair with these events

These are already returned by the REST API (see `docs/dev/API_DOCUMENTATION.md`); the events above tell the app when to re-fetch them.

| Endpoint | Field | Use |
| --- | --- | --- |
| `GET /api/v1/tickets`, `GET /api/v1/tickets/{id}` | `is_endorsed` | The work was transferred through an approved endorsement. Stays true after the ticket is closed. |
| same | `created_via` | `null` (origin ticket), `endorsement` or `manual` (child created by CWD) |
| `GET /api/v1/tickets/{id}` | `endorsement_origin` | For endorsement children: `reason`, `from_dept`, `approved_by`, `approved_at`; tell the receiving supervisor where the work came from |
| `GET /api/v1/tickets/{id}/history` | `endorsements[].old_dept`, `new_dept` | Show "From → To" for each request |
| `GET /api/v1/teams`, `GET /api/v1/teams/{id}` | `ticket_stats.pending_endorsement`, `ticket_stats.cancelled` | New buckets; all buckets now add up to `total` |
| `POST /api/v1/tickets/{id}/endorse` | rule | Only Open and Assigned tickets can be endorsed (422 otherwise); hide the action for In Progress tickets |

## 8. Implementation checklist

- [ ] Subscribe `private-user.{id}` for both roles; resubscribe after reconnect
- [ ] De-duplicate identical events received on two channels
- [ ] Crew: dynamic team channel join / leave on `TeamUpdated`
- [ ] `AccountUpdated`: sign out on `requires_sign_out`; resubscribe on `department_changed`
- [ ] Replace rejection handling: `TicketReportRejected` with `rejection_reason`
- [ ] Endorsement events on team / department channels: freeze and unfreeze ticket actions; drop the ticket for `previous_team_id` on approval
- [ ] `TicketIntakeUpdated`: handle re-routing in and out of the supervisor's department
- [ ] `TicketRemarkAdded`: reload remarks of the open ticket; ignore internal
- [ ] `ReferenceDataUpdated`: invalidate option caches
- [ ] After any reconnect, re-fetch what is on screen
- [ ] Show `is_endorsed`, `created_via` / `endorsement_origin`, history From → To, new team stats

## 9. Verifying against a working reference

`mobile-simulator/` (repo root) implements every row above against the same backend and is the quickest way to see the expected behaviour.

```bash
php -S localhost:5500 -t mobile-simulator
```

Open `http://localhost:5500`, switch to **Local**, sign in with a preset account, and run the backend with `composer run dev` (it starts Reverb). The simulator's Network Inspector logs every subscription and every received event with its channel and payload. The relevant code is in `mobile-simulator/app.js`: `subscribeToRoleChannels`, `isDuplicateBroadcast`, `handleIncomingBroadcastEvent`, `handleTeamUpdatedEvent`, `handleAccountUpdatedEvent`.
