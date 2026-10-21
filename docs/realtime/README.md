# Real-time Layer — Overview and Change Log

**Audience:** developers and AI agents that maintain the PALECO OTRS-CRM backend, the CWD web portal, or the Flutter mobile app.
**Status:** implemented in the Laravel backend and the CWD web portal; the Flutter app has **not** been updated yet (see [MOBILE_INTEGRATION_GUIDE.md](MOBILE_INTEGRATION_GUIDE.md)).
**Date:** October 21, 2026

## Documents in this folder

| File | What it covers |
| --- | --- |
| [README.md](README.md) | This overview: the rules, what changed, and every file that changed |
| [EVENT_CATALOG.md](EVENT_CATALOG.md) | Every broadcast event: trigger, channels and full payload, marked new / changed / unchanged |
| [MOBILE_INTEGRATION_GUIDE.md](MOBILE_INTEGRATION_GUIDE.md) | What the Flutter app must subscribe to and how each screen must react; written without assuming anything about the app's screens |
| [WEB_LISTENERS.md](WEB_LISTENERS.md) | How the CWD and Admin web pages listen and re-render, including the new live-refresh mechanism for non-Livewire pages |

The older spec `docs/dev/FLUTTER_REALTIME_WEBSOCKET_SPECIFICATION.md` still describes the connection handshake correctly, but its event list is incomplete. **Where the two disagree, this folder is the source of truth.**

## The rule this change implements

| Account | Broadcasts its actions | Listens and re-renders |
| --- | --- | --- |
| CWD officer (web) | Real time | Real time on every CWD page |
| Supervisor (mobile) | Real time | Real time (Flutter work pending; contract in this folder) |
| Field personnel (mobile) | Real time | Real time (Flutter work pending; contract in this folder) |
| Admin (web) | Real time (team, account, department, category changes are broadcast) | **Semi real-time:** only the ticket list and ticket details re-render. Dashboard, users, teams, departments, categories and the audit log do not listen and are refreshed by hand. |

## How broadcasting works (unchanged mechanics)

- Every event implements `ShouldBroadcastNow`: it is pushed during the request, with no queue worker involved.
- Events are dispatched inside `DB::afterCommit(...)` and wrapped in `rescue(...)`, so a broadcast never fires for a rolled-back change and a failed broadcast never fails the user's action.
- Transport: Pusher Channels in production (Render), Laravel Reverb locally. Clients use the Pusher protocol either way.
- Nothing is replayed. A client that was disconnected must re-fetch the data it shows when it reconnects ("catch-up fetch").
- Broadcast names are the class names (`broadcastAs()`), for example `TicketRemarkAdded`. With Laravel Echo listen with a leading dot (`.TicketRemarkAdded`); with a raw Pusher client bind the plain name.

## Channels

All channels are private. Authorization lives in `PALECO_OTRS-CRM/routes/channels.php`; mobile clients authorize at `POST /api/v1/broadcasting/auth` with their Sanctum bearer token.

| Channel | Who may join | Status |
| --- | --- | --- |
| `private-cwd.operations` | CWD officers, admins, supervisors | Unchanged |
| `private-department.{departmentId}` | CWD officers, admins, supervisors of that department | Unchanged |
| `private-team.{teamId}` | CWD officers, admins, supervisors of the team's department, members of the team | Unchanged |
| `private-user.{userId}` | **Only that account** (user ids are ULID strings) | **New** |

`private-user.{userId}` exists for two reasons: account notices (deactivation, password reset, department move), and team membership changes. A crew member who is **added** to a team is not on that team's channel yet, and one who is **removed** keeps their existing subscription until their app leaves it; both learn about it on their own channel.

Channel authorization is only checked when a client subscribes. A removed member's open connection keeps receiving that team's events until the app unsubscribes, which is why clients must act on `TeamUpdated` (see the mobile guide).

## What changed

### New events (6)

| Event | Fired when | Channels |
| --- | --- | --- |
| `TicketIntakeUpdated` | CWD edits an Open ticket's intake details, including moving it to another department | cwd.operations, new department, old department (if moved) |
| `TicketRemarkAdded` | Anyone posts a remark (CWD web, supervisor or crew mobile) | cwd.operations; plus team and department only for public remarks. Internal remarks never carry their text. |
| `TicketReportRejected` | A supervisor rejects an accomplishment report | cwd.operations, team, department |
| `TeamUpdated` | A team is created, edited (details or roster), archived, restored or permanently deleted, by a supervisor (mobile) or admin (web); also when deactivating a crew member removes them from their teams | cwd.operations, team, department (and the old department if the team moved), plus `user.{id}` of every member added, removed, or notified |
| `AccountUpdated` | An admin deactivates, reactivates, moves (department change) or resets the password of an account | `user.{id}` of that account |
| `ReferenceDataUpdated` | An admin creates, edits, archives, restores or deletes a department or ticket category | cwd.operations; plus `department.{id}` for department changes |

### Changed events (2) — channels added, payload extended

| Event | Before | Now |
| --- | --- | --- |
| `TicketEndorsementInitiated` | cwd.operations only | cwd.operations, **team, department**; payload adds `team_id`, `department_id` |
| `TicketEndorsementDecided` | cwd.operations only | cwd.operations, **team (or the team that just lost the ticket on approval), department**; payload adds `team_id`, `previous_team_id`, `department_id`, `new_department_id` |

### Breaking change (1)

A supervisor **rejecting a report** used to broadcast `TicketWorkStarted`. It now broadcasts **`TicketReportRejected`**, which carries `rejection_reason`. `TicketWorkStarted` is now only sent when a crew actually starts work. Mobile code that relied on `TicketWorkStarted` to notice a rejection must switch to `TicketReportRejected`.

### Unchanged events (7)

`TicketCreated`, `TicketAssigned`, `TicketWorkStarted`, `TicketAccomplished`, `TicketVerifiedAndClosed`, `TicketCancelled`, `TicketForceClosed` keep their channels and payloads.

## Files changed

### Backend (`PALECO_OTRS-CRM/`)

| File | Change |
| --- | --- |
| `app/Events/TicketIntakeUpdated.php` | New event |
| `app/Events/TicketRemarkAdded.php` | New event |
| `app/Events/TicketReportRejected.php` | New event |
| `app/Events/TeamUpdated.php` | New event |
| `app/Events/AccountUpdated.php` | New event |
| `app/Events/ReferenceDataUpdated.php` | New event |
| `app/Events/TicketEndorsementInitiated.php` | Team + department channels; payload `team_id`, `department_id` |
| `app/Events/TicketEndorsementDecided.php` | Team + department channels; `$previousTeamId`; payload additions |
| `app/Actions/Tickets/UpdateTicketIntake.php` | Dispatches `TicketIntakeUpdated` with the previous department |
| `app/Actions/Tickets/AddTicketRemark.php` | Dispatches `TicketRemarkAdded` |
| `app/Actions/Accomplishments/VerifyAccomplishment.php` | Rejection dispatches `TicketReportRejected` instead of `TicketWorkStarted` |
| `app/Actions/Endorsements/DecideEndorsement.php` | Passes the team that lost the ticket on approval |
| `app/Actions/Teams/CreateTeam.php`, `UpdateTeam.php`, `ArchiveTeam.php`, `RestoreTeam.php`, `ForceDeleteTeam.php` | Dispatch `TeamUpdated` (with added / removed / notified member ids) |
| `app/Actions/Users/ToggleUserActiveStatus.php` | Dispatches `AccountUpdated`; on crew deactivation also `TeamUpdated` per team they were removed from |
| `app/Actions/Users/UpdateUser.php` | Dispatches `AccountUpdated` (`department_changed`) when a department changes |
| `app/Actions/Users/ResetUserPassword.php` | Dispatches `AccountUpdated` (`password_reset`) |
| `app/Actions/Departments/*` and `app/Actions/Categories/*` (create, update, archive, restore, force delete) | Dispatch `ReferenceDataUpdated` |
| `routes/channels.php` | New `user.{userId}` channel |

### CWD / Admin web (`PALECO_OTRS-CRM/`)

| File | Change |
| --- | --- |
| `app/Livewire/Cwd/DashboardOverview.php` and `app/Livewire/Shared/*` | Listen to the new events (details in WEB_LISTENERS.md) |
| `resources/js/live-refresh.js` | **New**: re-renders marked regions of non-Livewire pages from broadcasts |
| `resources/js/app.js` | Imports `live-refresh.js` |
| `resources/views/components/live-notice.blade.php` | **New** component: a hidden "reload" notice for forms |
| `resources/views/cwd/pages/endorsementDashboard.blade.php`, `endorsementDetails.blade.php`, `consumerManagement.blade.php`, `consumerDetails.blade.php`, `dashboard.blade.php` | Live regions |
| `resources/views/shared/pages/ticketDetails.blade.php` | Live regions for the subject line and the intake column |
| `resources/views/cwd/forms/ticketForm.blade.php`, `ticketEditForm.blade.php`, `childTicketForm.blade.php` | Live notices |
| `resources/views/cwd/pages/ticketAccomplishmentDetails.blade.php` | Live region for the report summary |
| `resources/views/cwd/prompts/ticketCancelConfirm.blade.php`, `resources/views/shared/prompts/ticketForceCloseConfirm.blade.php` | Live notices |
| `resources/views/shared/pages/partials/ticket-remarks-thread.blade.php` | Removed `wire:ignore` from the thread (it blocked live remarks); kept on the form only |
| `resources/js/history-tabs.js`, `dashboard-pie-chart.js`, `livewire-animations.js` | Re-initialise on Livewire's `morphed` hook (the old `commit`/`succeed()` hook never fired, so the open history tab was lost on every live update) |

### Tests

`PALECO_OTRS-CRM/tests/Feature/Realtime/RealtimeBroadcastTest.php` (13 tests): every new or changed broadcast, its channels and payload; internal remarks carry no text; `user.{id}` authorization; a Livewire panel re-rendering on `TicketRemarkAdded`; CWD pages carrying live regions and admin pages carrying none; the remarks thread not being frozen by `wire:ignore`; the report details page and the cancel / force-close prompts listening.

### Mobile simulator (`mobile-simulator/`)

The simulator is a browser stand-in for the Flutter app, used for manual QA. It now subscribes to `private-user.{id}`, handles all 15 events, joins and leaves team channels on `TeamUpdated`, signs out on `AccountUpdated`, de-duplicates events received on two channels, and gained the missing supervisor features (team details, create / edit with roster, archive, restore, delete, single report view, endorsement origin and transfer cards). It is a reference for expected behaviour, not code to port.

## Deploying

No migrations, no dependency changes, no new environment variables. The existing `BROADCAST_CONNECTION` (Pusher on Render, Reverb locally) carries the new events.
