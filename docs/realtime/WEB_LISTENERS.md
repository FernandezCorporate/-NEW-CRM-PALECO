# Web Listeners (CWD and Admin)

How the web portal reacts to broadcasts. All web listeners use the `private-cwd.operations` channel, which CWD officers and admins are allowed to join.

There are two mechanisms:

1. **Livewire components** re-render themselves through `#[On('echo-private:cwd.operations,.EventName')]` attributes. This is the original mechanism, extended with the new events.
2. **Live refresh** (`resources/js/live-refresh.js`, new) for pages built from plain Blade. A page marks regions with data attributes; on a matching event the script fetches a fresh copy of the same URL and swaps those regions' inner HTML.

## 1. Livewire components

| Component (file) | Appears on | Re-renders on |
| --- | --- | --- |
| `Cwd\DashboardOverview` | CWD dashboard | All ticket events, now including `TicketIntakeUpdated`, `TicketReportRejected` |
| `Shared\TicketTable` | Ticket list (CWD and Admin) | All ticket events, now including `TicketIntakeUpdated`, `TicketReportRejected`; also `ReferenceDataUpdated` (department and category filter options) |
| `Shared\TicketStatusBadge` | Ticket details header | Ticket status events, now including `TicketReportRejected` |
| `Shared\TicketOperationalControls` | Ticket details action buttons | Ticket status events, now including `TicketIntakeUpdated`, `TicketReportRejected` |
| `Shared\TicketAssignedPersonnel` | Ticket details personnel card | Previous events plus `TicketIntakeUpdated` (department supervisors change on re-route); **new** `handleTeamChange` on `TeamUpdated` for the ticket's own team (roster edits) |
| `Shared\TicketHierarchy` | Ticket details hierarchy panel | Previous events plus `TicketIntakeUpdated`, `TicketReportRejected` |
| `Shared\TicketHistoryTabs` | Ticket details history tabs, **including the remarks thread** | Previous events plus `TicketIntakeUpdated`, `TicketReportRejected`, **`TicketRemarkAdded`** (remarks now appear live). The thread partial (`shared/pages/partials/ticket-remarks-thread.blade.php`) used to carry `wire:ignore`, which stopped Livewire from ever updating it; only its form keeps `wire:ignore` now, so a remark being typed survives updates. |

Every per-ticket component ignores events whose `ticket_id` is another ticket.

**Re-initialising scripts after a Livewire update.** `history-tabs.js` (restores the open history tab), `dashboard-pie-chart.js` and `livewire-animations.js` re-run after every update through `Livewire.hook('morphed', ...)`. They previously used the `commit` hook's `succeed()` callback, registered inside `livewire:initialized`; in the installed Livewire version that callback never fires, so after any live update every history panel became visible and the open tab was lost. Registration now also happens immediately when `window.Livewire` already exists.

## 2. Live refresh for non-Livewire pages

### Attributes

| Attribute | On | Meaning |
| --- | --- | --- |
| `data-live-region="key"` | any element | Its inner HTML is replaced by the inner HTML of the element with the same key in a fresh `GET` of the current URL |
| `data-live-notice` | any element (usually `<x-live-notice>`) | Hidden; revealed instead of swapping content. Used on forms so typed input is never lost. |
| `data-live-events="A,B"` | both | Broadcast names that trigger it (no leading dot) |
| `data-live-channel="..."` | both, optional | Channel (default `cwd.operations`) |
| `data-live-match="key:value\|key2:value2"` | both, optional | Only react when the payload has one of these values (e.g. `ticket_id:01m5...`) |

### Behaviour

- Events for several regions arriving within 400 ms trigger **one** fetch.
- The container element itself is kept, so classes set by other scripts (card or table view toggles) survive.
- **Unsaved input is protected.** Form values in a region are snapshotted after the page finishes loading (after Tom Select and other enhancers run). If they differ when an event arrives, or a field has focus, the region is not swapped; an amber "updated by someone else — Reload" notice is shown instead.
- If the fresh request redirects (for example the record became unreachable), nothing is swapped.

### Where it is used

| Page | Regions / notices | Events |
| --- | --- | --- |
| CWD dashboard (`cwd/pages/dashboard`) | `cwd-queue` (the "Keep an eye on the queue" counts outside the Livewire island) | All ticket events |
| Endorsement queue (`cwd/pages/endorsementDashboard`) | `endorsement-pending-count`, `endorsement-tabs`, `endorsement-rows`, `endorsement-pagination` | `TicketEndorsementInitiated`, `TicketEndorsementDecided` |
| Endorsement details (`cwd/pages/endorsementDetails`) | `endorsement-record` (the whole card, matched on `endorsement_id`) | `TicketEndorsementDecided`: when another officer decides, the open form turns into the decision record; if this officer had started filling the form, a reload notice appears instead |
| Report details (`cwd/pages/ticketAccomplishmentDetails`) | `report-summary` (status, approver, rejection reason; matched on `ticket_id`) | `TicketReportRejected`, `TicketVerifiedAndClosed`, `TicketForceClosed` |
| Consumer list (`cwd/pages/consumerManagement`) | `consumer-cards`, `consumer-pagination` | All ticket events |
| Consumer details (`cwd/pages/consumerDetails`) | `consumer-sources`, `consumer-tickets` | All ticket events |
| Ticket details (`shared/pages/ticketDetails`, CWD and Admin) | `ticket-subject` (subject and age chip; matched on `ticket_id`), `ticket-intake` (consumer card and complaint details; `TicketIntakeUpdated` for this ticket) | Ticket events for this ticket |
| New ticket form (`cwd/forms/ticketForm`) | Notice | `ReferenceDataUpdated` |
| Edit intake form (`cwd/forms/ticketEditForm`) | Three notices | `ReferenceDataUpdated`; `TicketIntakeUpdated` for this ticket (another officer edited it); `TicketAssigned` / `TicketCancelled` / `TicketEndorsementInitiated` for this ticket (no longer editable) |
| Child ticket form (`cwd/forms/childTicketForm`) | Two notices | `ReferenceDataUpdated`; status changes of the origin ticket that may block a child |
| Cancel prompt (`cwd/prompts/ticketCancelConfirm`) | Notice | `TicketAssigned`, `TicketCancelled`, `TicketEndorsementInitiated` for this ticket, or `TicketCreated` of a child (matched on `ticket_id` or `parent_ticket_id`) |
| Force-close prompt (`shared/prompts/ticketForceCloseConfirm`) | Notice | `TicketForceClosed`, `TicketAccomplished`, `TicketVerifiedAndClosed`, `TicketEndorsementInitiated`, `TicketCancelled`, `TicketCreated` for this ticket or one of its children |

Forms only show notices, and the server still validates on save (stale intake edits are refused with a conflict message).

## 3. Admin: semi real-time by design

Admin actions broadcast in real time (`TeamUpdated`, `AccountUpdated`, `ReferenceDataUpdated`, plus every ticket event an admin can cause). On the receiving side, admin pages only re-render ticket data:

| Admin page | Live? |
| --- | --- |
| Ticket list (`admin.tickets`) | Yes, through the shared `TicketTable` component |
| Ticket details (`admin.tickets.show`) | Yes, through the shared ticket components and the ticket details live regions. These also follow `TeamUpdated` for the ticket's own team and `ReferenceDataUpdated` for the filter options, because both change what the ticket views show. |
| Dashboard, users, teams, departments, categories, system monitoring and their detail pages | **No**: no listeners or live regions; refresh by hand |

`tests/Feature/Realtime/RealtimeBroadcastTest.php` checks that those admin pages render no `data-live-` attributes.

## 4. Adding real-time to another page

- **Livewire component:** add `#[On('echo-private:cwd.operations,.EventName')]` to a handler and filter on the payload's `ticket_id` (or `team_id`) when the component shows a single record.
- **Plain Blade page:** add `data-live-region="unique-key"` and `data-live-events="..."` (plus `data-live-match` for a single record) to the element that holds the data, outside any filter form. For forms use `<x-live-notice events="..." match="..." message="..." />` instead of a region.
