# PALECO OTRS-CRM: Frontend Architecture Audit, Code Inspection & Comprehensive Cleanup Plan

> **Document Type:** Architecture Audit & Modernization Roadmap  
> **Target Scope:** Entire `resources/` directory (Blade Templates, CSS Architecture, JavaScript Modules)  
> **Status:** Proposal & Technical Specification  
> **Author:** Antigravity / Engineering Team  
> **Target Release:** System Cleanup Phase 1 through Phase 7  

---

## Table of Contents
1. [Section 1: Exhaustive File-by-File Code Inspection & Concept Explainer](#section-1-exhaustive-file-by-file-code-inspection--concept-explainer)
   - [1.1 CSS Architecture & Stylesheets (10 Files)](#11-css-architecture--stylesheets-10-files)
   - [1.2 JavaScript Modules & Interactivity (22 Files)](#12-javascript-modules--interactivity-22-files)
   - [1.3 Blade View Templates & Partials (53 Files)](#13-blade-view-templates--partials-53-files)
2. [Section 2: General Architectural Cleanup & System Uniformity](#section-2-general-architectural-cleanup--system-uniformity)
   - [2.1 Architectural & Interface Uniformity](#21-architectural--interface-uniformity)
   - [2.2 Naming Uniformity (Filenames & CSS Classes)](#22-naming-uniformity-filenames--css-classes)
   - [2.3 Interface Component Unification (Icons, Buttons, Containers)](#23-interface-component-unification-icons-buttons-containers)
   - [2.4 Controller-Passed Data vs. Inline Blade PHP Imports](#24-controller-passed-data-vs-inline-blade-php-imports)
   - [2.5 Modularization of Reusable UI Sections](#25-modularization-of-reusable-ui-sections)
   - [2.6 Icon System Migration Plan](#26-icon-system-migration-plan)
   - [2.7 CSS Purge, Conflict Resolution & Modern Tailwind CSS Strategy](#27-css-purge-conflict-resolution--modern-tailwind-css-strategy)
   - [2.8 JavaScript Simplification & Performance Optimization](#28-javascript-simplification--performance-optimization)
   - [2.9 Target Folder Restructuring](#29-target-folder-restructuring)
3. [Section 3: File-Specific Cleanup, Logical Grouping & Phased Roadmap](#section-3-file-specific-cleanup-logical-grouping--phased-roadmap)
   - [Group 1: Foundation & Design Tokens (Phase 1)](#group-1-foundation--design-tokens-phase-1)
   - [Group 2: Shells, Layouts & Global Navigation (Phase 2)](#group-2-shells-layouts--global-navigation-phase-2)
   - [Group 3: Input Forms & Dynamic Mutation Controls (Phase 3)](#group-3-input-forms--dynamic-mutation-controls-phase-3)
   - [Group 4: Confirmation Dialogs & Destructive Workflows (Phase 4)](#group-4-confirmation-dialogs--destructive-workflows-phase-4)
   - [Group 5: Registries, Queues & Livewire Tables (Phase 5)](#group-5-registries-queues--livewire-tables-phase-5)
   - [Group 6: Detail Views, Timelines & Profile Inspections (Phase 6)](#group-6-detail-views-timelines--profile-inspections-phase-6)
   - [Group 7: Dashboards, Metric Cards & Telemetry Islands (Phase 7)](#group-7-dashboards-metric-cards--telemetry-islands-phase-7)
   - [Group 8: Authentication & Error Boundary Pages (Phase 8)](#group-8-authentication--error-boundary-pages-phase-8)
   - [Semantic HTML Transition Matrix](#semantic-html-transition-matrix)

---

# Section 1: Exhaustive File-by-File Code Inspection & Concept Explainer

This section audits every single file found inside `resources/` across stylesheets, client scripts, and Blade view templates. For each file, the primary concept, functional tags, classes, and styling hooks are broken down in detail.

---

## 1.1 CSS Architecture & Stylesheets (10 Files)

### 1. `resources/css/app.css`
- **Concept & Role:** The primary CSS entrypoint compiled by Vite. It orchestrates the import pipeline for Tailwind CSS v4, third-party vendor stylesheets (`tom-select`), modular component stylesheets, and custom typography theme definitions.
- **Key Code & Directives:**
  - `@import 'tailwindcss';`: Loads Tailwind v4 engine.
  - `@import 'tom-select/dist/css/tom-select.default.css';`: Ingests vendor styles.
  - `@source '../views/**/*.blade.php';`: Explicitly points Vite scanner to all Blade views to eliminate missing utility purge issues.
  - `@theme { --font-sans: 'Instrument Sans', ui-sans-serif, ... }`: Registers the system brand typeface token.

### 2. `resources/css/base-theme.css`
- **Concept & Role:** Defines core CSS custom properties (color tokens) under the `@layer base` scope, establishing the brand green palette across the entire application and setting foundational body ergonomics.
- **Key Code & Styling:**
  - CSS Variables: `--paleco-950: #062d21`, `--paleco-900: #073b2a`, `--paleco-700: #08794e`, `--paleco-600: #09975d`, `--paleco-500: #12ad6b`.
  - Body rules: `background-color: #f5f7f6`, text color `#17221d`, with `-webkit-font-smoothing: antialiased`.
  - Global `::selection` highlighting matching brand emerald (`var(--paleco-600)`).
  - Global interactive focus outline color binding to `var(--paleco-500)`.

### 3. `resources/css/layout-shell.css`
- **Concept & Role:** Encapsulates the macro page structure and responsive shell behavior, including the textured app background, fixed sidebar layout, mobile app bar header, and mobile slide-out navigation overlay.
- **Key Classes & Rules:**
  - `.app-shell`: Applies a composite background with multi-stop radial and linear gradients (`linear-gradient(120deg, ...)`, `radial-gradient(...)`).
  - `.sidebar-surface`: Renders the high-contrast dark green sidebar container with subtle radial ambient glow.
  - `.mobile-app-bar`: Fixed header bar for viewports `< 768px` featuring `backdrop-filter: blur(16px)` and glassmorphic transparency.
  - `.sidebar-overlay`: Darkened backdrop `rgb(6 24 18 / 58%)` triggered when `body.sidebar-open` is toggled.
  - Media query (`max-width: 767px`): Off-canvas navigation slide-in via `transform: translateX(-102%)` transitioning to `translateX(0)`.

### 4. `resources/css/workspace-refined.css`
- **Concept & Role:** A massive centralized stylesheet created during historical UI iterations to enforce visual parity across CWD and Admin modules. It covers pagination styling, record tab bars, ticket history tables, form container caps, and input focus outlines.
- **Key Classes & Rules:**
  - `.system-pagination`: Flex container handling responsive pagination steps, ellipses, and current page pills.
  - `.record-tabs`: Navigation tab bar at the top of queue pages with border indicators and active pills (`[aria-current="page"]`).
  - `.ticket-history-nav`, `.ticket-history-section`, `.ticket-history-table`: Complex scoped rules for sticky headers, horizontal scrolling containers, and anchor-target highlights.
  - `.refined-shell`: Defines `--workspace-ink`, `--workspace-accent`, `--workspace-border`.
  - Heavy usage of `!important` declarations (e.g. `border-color: var(--workspace-border) !important`, `padding-inline: 1rem !important`) to override conflicting Tailwind inline utilities.

### 5. `resources/css/appearance.css`
- **Concept & Role:** Manages runtime theme customization, color accent overrides, and dark mode theming via HTML dataset attributes (`data-theme`, `data-mode="dark"`, `data-pattern`).
- **Key Classes & Rules:**
  - `html[data-mode="dark"]`: Sets dark system tokens (`--night-base: #101820`, `--night-panel: #19242f`, `--night-border: #3c4c5b`).
  - Scoped overrides for dark mode surfaces: `.panel`, `.metric-card`, `.system-table`, `.system-field`.
  - Semantic badge color locks: Ensures warning/danger badges remain legible in dark mode without turning into unreadable low-contrast pills.

### 6. `resources/css/preferences.css`
- **Concept & Role:** Handles user accessibility adjustments including font-size scaling, compact table density, and motion reduction.
- **Key Classes & Rules:**
  - `html[data-text-size="large"] { font-size: 18px; }`, `html[data-text-size="larger"] { font-size: 20px; }`.
  - `html[data-compact="true"] .system-table td { padding-block: .5rem; }`: Tighter vertical density for high-throughput operational review.
  - `html[data-reduce-motion="true"] *`: Forcefully collapses animation/transition durations to `.01ms` for motion-sensitive users.
  - `.preference-choice`: Radio/checkbox option card styling with checked state borders.

### 7. `resources/css/dashboard-cards.css`
- **Concept & Role:** Houses component primitives for dashboard metric cards, action links, eyebrow badges, and operational summary panels.
- **Key Classes & Rules:**
  - `.panel`: Rounded card container (`border-radius: 1.25rem`, subtle dual drop-shadow).
  - `.eyebrow`: Upper-case micro-heading (`letter-spacing: .14em`, `font-size: .7rem`, bold green font).
  - `.metric-card`: Interactive stat card featuring hover lift (`transform: translateY(-2px)`) and border glow.
  - `.quick-link`: Horizontal shortcut cards with icon square and title/description stack.

### 8. `resources/css/dashboard-charts.css`
- **Concept & Role:** Lightweight pure-CSS chart engine powering responsive 7-day intake column graphs and horizontal status distribution bar tracks without external JavaScript charting libraries.
- **Key Classes & Rules:**
  - `.chart-columns`: CSS grid with 7 tracks, alignment at bottom, and CSS background grid lines (`linear-gradient`).
  - `.chart-column-fill`: Scalable bar utilizing CSS custom variable `--chart-height` with linear gradient `#34d399` to `#059669`.
  - `.chart-row-track` & `.chart-row-fill`: Horizontal progress track with `--chart-width`.
  - `.status-segment`: Segmented multi-colored bar track driven by dynamic `--segment-width`.

### 9. `resources/css/animations.css`
- **Concept & Role:** Declares keyframe animations for page content reveals, chart bar expansion, pulse rings, and respects OS-level `prefers-reduced-motion`.
- **Key Classes & Rules:**
  - `@keyframes ui-reveal-fade`: Smooth upward translate and opacity fade for staggered list/card loading.
  - `@keyframes scale-y-up`: Vertical bar chart expansion from bottom origin.
  - `@keyframes expand-row-width`: Horizontal bar expansion.
  - `@keyframes soft-pulse`: Ambient beacon pulse on `.status-live`.
  - `@media (prefers-reduced-motion: reduce)`: Nullifies all transforms and delays.

### 10. `resources/css/custom-tom-select.css`
- **Concept & Role:** Custom presentation layer that transforms default TomSelect DOM elements into Tailwind-native form controls.
- **Key Classes & Rules:**
  - `.ts-control`: Matches standard Tailwind input height (`min-height: 42px`), padding, border-radius (`0.5rem`), and focus outline.
  - `.ts-wrapper.focus .ts-control`: Emulates `ring-1 ring-emerald-500`.
  - `@keyframes dropdownFadeIn`: Smooth popup reveal for `.ts-dropdown`.
  - `.ts-wrapper.single .ts-control .item`: Text truncation and cursor overflow fixes for long category and department labels.

---

## 1.2 JavaScript Modules & Interactivity (22 Files)

### 1. `resources/js/app.js`
- **Concept & Role:** Master JavaScript manifest imported by Vite. Imports all client modules, WebSocket broadcasting engines, interactive handlers, and initialization routines.
- **Structure:** Imports 21 modular scripts sequentially; runs no raw inline logic.

### 2. `resources/js/echo.js`
- **Concept & Role:** Configures client-side real-time event broadcasting using Laravel Echo and Pusher/Reverb driver.
- **Key Logic:** Reads `VITE_REVERB_APP_KEY`, `VITE_REVERB_HOST`, `VITE_REVERB_PORT`, initializing `window.Echo`. Bails out gracefully with an info log if broadcasting keys are absent.

### 3. `resources/js/consumerLinkToggle.js`
- **Concept & Role:** Controls consumer account linkage on ticket creation forms. Handles input reveal, live regex validation (`XX-XXXX-XXXX`), and asynchronous fetch verification against `/cwd/consumers/verify/{accountCode}`.
- **Key Logic:** Async `fetch()` with JSON decoding; updates UI with consumer name, meter serial number, and active status pill; handles Enter keypress and error previews.

### 4. `resources/js/dashboard-controls.js`
- **Concept & Role:** Manages client-side customizable dashboard action shortcuts stored in `localStorage`.
- **Key Logic:** Queries `[data-dashboard-controls]`, controls native HTML `<dialog>` open/close/cancel/save states, enforces a maximum of 4 selectable shortcuts, and updates live previews without page reload.

### 5. `resources/js/disableDeptForFieldPerson.js`
- **Concept & Role:** Enforces organizational logic on the user account creation/edit form. If a user's role is not `supervisor`, the department selector is cleared and disabled.
- **Key Logic:** Watches `#user-role-select` change events; interfaces with the underlying `tomselect` JavaScript API (`tsInstance.clear()`, `tsInstance.disable()`); includes a 100ms `setTimeout` to await TomSelect boot.

### 6. `resources/js/filter-aria.js`
- **Concept & Role:** Accessibility enhancer for search and filter forms across index pages.
- **Key Logic:** Iterates over `.workspace-surface form[method="GET"]`, scans for unlabelled input and select elements, and dynamically injects descriptive `aria-label` tags based on element names.

### 7. `resources/js/history-tabs.js`
- **Concept & Role:** Tab controller for the Ticket Details view. Manages switching between status history, assignment records, endorsements, accomplishments, and remark threads.
- **Key Logic:** Binds to `.ticket-history-nav [data-tab-target]`; updates `aria-selected`, toggles `.hidden` on panels, synchronizes the browser URL hash (`history.replaceState`), and listens for `hashchange` events.

### 8. `resources/js/lightbox.js`
- **Concept & Role:** Fullscreen image modal for inspecting proof-of-work photos, accomplishment attachments, and damaged equipment evidence.
- **Key Logic:** Listens to `.js-lightbox-trigger`; injects the image URL into `#lightbox-image`; freezes `document.body.style.overflow = 'hidden'`; provides click-to-zoom (`scale-[2]`) and ESC keyboard dismissal.

### 9. `resources/js/livewire-animations.js`
- **Concept & Role:** Smooth intersection observer triggering animations on elements with `[data-animate]`.
- **Key Logic:** Connects to `Livewire.hook('commit')` to re-trigger observer bindings after Livewire DOM morphing occurs.

### 10. `resources/js/preferences.js`
- **Concept & Role:** Manages persistent user interface preferences (dark mode, text sizing, reduced motion, compact density, pattern display).
- **Key Logic:** Reads/writes to `localStorage` under a user-keyed identifier; applies dataset attributes to `document.documentElement`; syncs live across browser tabs via `window.addEventListener('storage')`.

### 11. `resources/js/preventDoubleSubmit.js`
- **Concept & Role:** Anti-double-submission guard for all standard forms and `.action-link` anchor tags.
- **Key Logic:** Listens to form `submit` events; disables submit buttons; appends an animated SVG spinner with `data-loading-text`; handles browser bfcache (`pageshow` event with `event.persisted`) by forcing reload if stuck in a loading state.

### 12. `resources/js/sidebar-nav.js`
- **Concept & Role:** Responsive mobile sidebar drawer toggle controller.
- **Key Logic:** Listens to `[data-sidebar-toggle]` and `[data-sidebar-overlay]`; toggles `body.sidebar-open`; closes drawer upon ESC keypress or window resize above 768px.

### 13. `resources/js/smart-navigation.js`
- **Concept & Role:** Smart backtracking engine for detail and form views.
- **Key Logic:** Inspects `document.referrer` to ensure internal origin backtracking via `window.history.back()`; falls back safely to `data-fallback-url` for direct bookmarked visits or fresh tabs.

### 14. `resources/js/system-fields.js`
- **Concept & Role:** DOM class normalizer that scans `.workspace-surface` for raw inputs and textareas, automatically appending the `.system-field` styling hook if not inside a TomSelect wrapper.

### 15. `resources/js/table-wrapper.js`
- **Concept & Role:** Table accessibility and responsive scroll wrapper generator.
- **Key Logic:** Wraps unwrapped `<table>` tags inside a `.system-table-scroll` container; injects `tabindex="0"`, `role="region"`, and horizontal scrolling ARIA notices for keyboard navigation.

### 16. `resources/js/teamInlines.js`
- **Concept & Role:** Dynamic member management repeater on the Team creation/edit form (`teamForm.blade.php`).
- **Key Logic:** Clones an HTML `<template id="member-row-template">`; replaces index placeholders (`__INDEX__`); instantiates new `TomSelect` instances on newly injected rows; disables user options that have already been selected in sibling rows to prevent duplicate team members; handles row removal and instance destruction.

### 17. `resources/js/ticket-form.js`
- **Concept & Role:** Controls category toggling on the service ticket creation form.
- **Key Logic:** Listens to the `#other_category` checkbox; if checked, disables and clears the standard category TomSelect dropdown and enables the manual text input `#other_category_name`; includes a 50ms `setTimeout` workaround to await TomSelect instance attachment.

### 18. `resources/js/title-aria.js`
- **Concept & Role:** Scans icons and buttons with `title` attributes that lack visible text content, copying their `title` into an `aria-label` for screen reader compliance.

### 19. `resources/js/toggleCardTable.js`
- **Concept & Role:** Toggles between tabular data view and grid card view on entity management indexes (Users, Departments, Teams).
- **Key Logic:** Switches `.hidden` and `.grid` between `#list-view-container` and `#card-view-container`; persists preference per page route in `localStorage`.

### 20. `resources/js/togglePassword.js`
- **Concept & Role:** Password visibility toggle for authentication and user creation forms.
- **Key Logic:** Toggles `type="password"` and `type="text"` on password inputs; toggles visibility between eye-open and eye-closed SVG icons.

### 21. `resources/js/tomselect-filters.js`
- **Concept & Role:** Initializes searchable select dropdowns for filter toolbars (`.ts-filter-dropdown`).
- **Key Logic:** Initializes `new TomSelect()` with `controlInput: null` and triggers automated form submission (`form.submit()`) upon option change.

### 22. `resources/js/tomSelect-input_with_autoSuggest.js`
- **Concept & Role:** General initializer for searchable select dropdowns across form views (`.tom-select-sync`).
- **Key Logic:** Initializes TomSelect instances with auto-submit capabilities if `data-autosubmit="true"`.

---

## 1.3 Blade View Templates & Partials (53 Files)

### Layout Shells & Error Boundaries (6 Files)
1. **`resources/views/welcome.blade.php`:** Default Laravel splash template. Unused in production; targeted for removal or conversion to marketing/guest redirect.
2. **`resources/views/admin/base/base.blade.php`:** Master layout for Administrator users. Contains HTML `<head>`, `@vite` links, livewire directives, mobile header, fixed dark green sidebar navigation, user profile avatar pill, and main content yield container.
3. **`resources/views/cwd/base/base.blade.php`:** Master layout for CWD Officers. Mirror structure to Admin base, adjusted for CWD role links (Tickets, Endorsements, Consumers). Contains minor HTML syntax flaw (missing `<li>` wrapper on consumer profiles link).
4. **`resources/views/errors/layout.blade.php`:** Minimalist centered layout for HTTP error pages. Provides PALECO logo badge, error illustration container, and smart back navigation.
5. **`resources/views/errors/403.blade.php`:** Access Forbidden error view. Extends error layout; shows 403 status code, explanation, and action button to return to dashboard.
6. **`resources/views/errors/404.blade.php`:** Not Found error view. Extends error layout; displays clean 404 alert and smart return button.

### Shared & Foundation Views (3 Files)
7. **`resources/views/shared/settings.blade.php`:** User workspace preferences interface. Accessible by both Admin and CWD roles via `$layout` injection. Renders radio option cards for appearance, text scaling, motion, and layout density.
8. **`resources/views/shared/pages/ticketManagement.blade.php`:** Shared ticket queue index page. Dynamically inherits Admin or CWD layout shell; embeds the real-time `<livewire:shared.ticket-table />`; hides the "New Ticket" button from Admin users using `@can('ticketForm')`.
9. **`resources/views/shared/pages/ticketDetails.blade.php`:** Shared comprehensive ticket inspection page (588 lines). Displays ticket status, caller metadata, assigned team, geographic coordinates, child tickets, and tabbed history timeline. Renders a neutral "Read-Only" badge and hides child creation and remark posting forms when viewed by an Administrator.

### Authentication Views (2 Files)
10. **`resources/views/auth/login.blade.php`:** Clean, centered portal login view. Contains username and password inputs with password toggle icon, remember-me checkbox, session alert prompt, and animated submit button.
11. **`resources/views/auth/roleSelection.blade.php`:** Role choice intermediary view for users possessing multiple organizational roles.

### Reusable Blade Components (4 Files)
12. **`resources/views/components/dashboard-controls.blade.php`:** Interactive dashboard shortcut panel. Displays grid of pinned operational actions and renders a native `<dialog>` for choosing shortcuts.
13. **`resources/views/components/dashboard-operations.blade.php`:** Operational overview list component for dashboard queues.
14. **`resources/views/components/settings-nav.blade.php`:** Reusable sidebar navigation link pointing to `/settings`, rendering role-specific active state classes.
15. **`resources/views/components/ticket-history-table.blade.php`:** Standardized wrapper for ticket history tables. Renders accessible `<header>`, horizontal scroll hint, and `<table>` with dynamic column headers.

### Livewire Components (3 Files)
16. **`resources/views/livewire/cwd/dashboard-overview.blade.php`:** Real-time metrics overview island on the CWD dashboard. Displays metric cards, 7-day intake column chart, and status distribution progress track.
17. **`resources/views/livewire/cwd/ticket-table.blade.php`:** Backward-compatible wrapper that includes the shared ticket table.
18. **`resources/views/livewire/shared/ticket-table.blade.php`:** Real-time searchable and filterable ticket queue table. Includes status tab bar, search debounce input, category/department/sort selects, responsive table layout, and role-aware ticket show routes.

### Pagination Templates (2 Files)
19. **`resources/views/vendor/pagination/simple-tailwind.blade.php`:** Next/Previous pagination controls.
20. **`resources/views/vendor/pagination/tailwind.blade.php`:** Full numeric pagination bar styled with `.system-pagination` classes.

### Admin Module Pages (9 Files)
21. **`resources/views/admin/pages/dashboard.blade.php`:** Central Admin Control Center dashboard. Contains 6 system metric cards, 7-day trend graph, ticket mix bar, and customizable shortcut controls.
22. **`resources/views/admin/pages/userManagement.blade.php`:** User accounts directory. Includes search bar, role and department filters, view toggle (table vs cards), and user table.
23. **`resources/views/admin/pages/userDetails.blade.php`:** Detailed user profile view. Displays account credentials, contact info, role pill, department membership, and team associations.
24. **`resources/views/admin/pages/departmentManagement.blade.php`:** Organizational divisions registry with active/archived filters.
25. **`resources/views/admin/pages/departmentDetails.blade.php`:** Department profile view showing supervisors, linked teams, and operational statistics.
26. **`resources/views/admin/pages/teamManagement.blade.php`:** Field operations team registry with department filters and team lead indicators.
27. **`resources/views/admin/pages/teamDetails.blade.php`:** Detailed team profile view showing shift schedules, supervisors, and roster of active personnel.
28. **`resources/views/admin/pages/ticketCategoryManagement.blade.php`:** Service complaint category registry with active and archived record filters.
29. **`resources/views/admin/pages/ticketCategoryDetails.blade.php`:** Category profile view detailing usage counts and active tickets.
30. **`resources/views/admin/pages/monitoring.blade.php`:** System audit logging dashboard. Search by actor/action, server-provided category dropdown, and paginated event table.
31. **`resources/views/admin/pages/activityLogDetails.blade.php`:** In-depth audit log record inspector showing actor profile, model diff visualizer (Old vs New), client IP, and User Agent.

### Admin Module Forms (4 Files)
32. **`resources/views/admin/forms/userForm.blade.php`:** User account creation and editing form. Comprehensive 2-column layout with first/middle/last name, username, contact number, role selection, and conditional department dropdown.
33. **`resources/views/admin/forms/departmentForm.blade.php`:** Department registration and update form. Handles department name and optional operational description.
34. **`resources/views/admin/forms/teamForm.blade.php`:** Team configuration form. Includes department dropdown, shift start/end times, and dynamic inline member repeater.
35. **`resources/views/admin/forms/ticketCategoryForm.blade.php`:** Category creation and editing form.

### Admin Confirmation Prompts (6 Files)
36. **`resources/views/admin/prompts/alert.blade.php`:** Global flash message alert banner rendering success, error, and validation notices.
37. **`resources/views/admin/prompts/userDeactivateConfirm.blade.php`:** Deactivation confirmation dialog card for user accounts.
38. **`resources/views/admin/prompts/userReactivateConfirm.blade.php`:** Reactivation confirmation prompt.
39. **`resources/views/admin/prompts/departmentDeleteConfirm.blade.php`:** Archive/Force-Delete confirmation prompt for departments.
40. **`resources/views/admin/prompts/teamDeleteConfirm.blade.php`:** Archive/Force-Delete confirmation prompt for teams.
41. **`resources/views/admin/prompts/ticketCategoryDeleteConfirm.blade.php`:** Archive/Force-Delete confirmation prompt for ticket categories.

### CWD Module Pages (8 Files)
42. **`resources/views/cwd/pages/dashboard.blade.php`:** CWD Service Desk dashboard embedding the Livewire overview island and quick actions.
43. **`resources/views/cwd/pages/ticketManagement.blade.php`:** Forwards to `shared.pages.ticketManagement`.
44. **`resources/views/cwd/pages/ticketDetails.blade.php`:** Forwards to `shared.pages.ticketDetails`.
45. **`resources/views/cwd/pages/consumerManagement.blade.php`:** Consumer directory registry with search, verification status filters, and consumer data table.
46. **`resources/views/cwd/pages/consumerDetails.blade.php`:** Consumer profile view showing meter serial, account code, address, and history of reported tickets.
47. **`resources/views/cwd/pages/endorsementDashboard.blade.php`:** Departmental endorsement queue index page.
48. **`resources/views/cwd/pages/endorsementDetails.blade.php`:** Endorsement review page with decision actions (Approve/Reject rerouting).
49. **`resources/views/cwd/pages/ticketAccomplishmentDetails.blade.php`:** Inspection page for completed field team accomplishment reports, supervisor approval controls, and photo lightbox triggers.

### CWD Module Forms (2 Files)
50. **`resources/views/cwd/forms/ticketForm.blade.php`:** Intake form for logging new service complaints. Fields for complaint source, consumer account code verification, category toggle, location details, and landmark.
51. **`resources/views/cwd/forms/childTicketForm.blade.php`:** Sub-task / child ticket creation form pre-populating parent ticket details.

### CWD Prompts (1 File)
52. **`resources/views/cwd/prompts/alert.blade.php`:** Flash message alert partial for CWD routes.

---

# Section 2: General Architectural Cleanup & System Uniformity

---

## 2.1 Architectural & Interface Uniformity

### Current Divergences & Pain Points
1. **Inconsistent Container Bounds & Padding:**
   - Admin views alternate between `max-w-7xl mx-auto`, `max-w-5xl`, and unconstrained widths.
   - CWD views frequently use `p-6 sm:p-10 bg-white rounded-lg border border-gray-200`, whereas Admin forms use `rounded-xl border border-slate-200 shadow-sm p-6 lg:p-8`.
2. **Color Palette Fragmentation:**
   - CWD views use hardcoded hex codes (`#008f5d`, `#007a4f`, `#00a86b`) and default Tailwind `gray-*` utilities.
   - Admin views use standard Tailwind `emerald-*` and `slate-*` utilities.
   - The CSS contains redundant fallback classes (`.bg-green-600`, `.bg-emerald-600`, `[class~="bg-[#008f5d]"]`) to bridge these differences.
3. **Heading Typography Inconsistencies:**
   - Page titles vary between `.page-title` (clamp formula), `text-2xl font-bold text-slate-800`, and `text-3xl font-bold text-gray-900`.

### Proposed Uniform Architecture Standard
- **Layout Shell:** Single unified application wrapper (`<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">`).
- **Brand Palette:** Standardize on Tailwind's semantic `emerald` scale as the PALECO primary brand:
  - Primary Brand Button: `bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-lg shadow-sm`.
  - Brand Accent Outline: `focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600`.
- **Card Primitives:** Every panel, form container, or table wrapper will use the uniform token:
  `bg-white border border-slate-200/80 rounded-xl shadow-xs`.

---

## 2.2 Naming Uniformity (Filenames & Class Names)

### Current Inconsistencies
| Category | Current Discrepant Names | Issue | Target Standard Name |
| :--- | :--- | :--- | :--- |
| **JS Files** | `tomSelect-input_with_autoSuggest.js` | Mixed CamelCase & underscore | `tom-select-sync.js` |
| **JS Files** | `teamInlines.js` vs `ticket-form.js` | CamelCase vs kebab-case | `team-members-repeater.js`, `ticket-category-toggle.js` |
| **Blade Views** | `ticketManagement.blade.php` | CamelCase view filename | `index.blade.php` (RESTful standard) |
| **Blade Views** | `ticketDetails.blade.php` | CamelCase view filename | `show.blade.php` (RESTful standard) |
| **Blade Prompts**| `userDeactivateConfirm.blade.php` | Verb-first camelCase | `deactivate-confirm.blade.php` |
| **CSS Classes** | `.record-tabs` vs `.ticket-history-nav` | Fragmented navigation naming | `.app-tabs`, `.app-tab-item` |

### Uniformity Rules
1. **File Naming Rule:** All Blade templates and JavaScript modules MUST follow strict **`kebab-case.blade.php`** and **`kebab-case.js`**.
2. **RESTful View Conventions:** Resources under `views/{domain}/{resource}/` should follow standard Laravel RESTful naming:
   - `index.blade.php` (List / Queue / Registry)
   - `show.blade.php` (Detail / Inspection)
   - `create.blade.php` or `form.blade.php` (Forms)
   - `confirm-delete.blade.php` (Destructive action confirmation)

---

## 2.3 Interface Component Unification (Icons, Buttons, Containers)

### 1. Button System Tokenization
Currently, button styles are duplicated with 15+ inline Tailwind classes in every template. We establish a clean 5-tier button design system:

```html
<!-- Primary Action -->
<button class="btn btn-primary">Save Changes</button>
<!-- Secondary / Cancel -->
<button class="btn btn-secondary">Cancel</button>
<!-- Destructive Action -->
<button class="btn btn-danger">Deactivate Account</button>
<!-- Subtle Ghost Action -->
<button class="btn btn-ghost">Reset Filters</button>
<!-- Icon Only Button -->
<button class="btn btn-icon" aria-label="Settings"><x-icons.gear /></button>
```

Under Tailwind v4, these are expressed cleanly in `@layer components`:
```css
@layer components {
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.625rem 1rem;
        font-size: 0.875rem;
        font-weight: 600;
        line-height: 1.25rem;
        border-radius: 0.5rem;
        transition: all 0.15s ease-in-out;
        cursor: pointer;
        outline: none;
    }
    .btn-primary {
        background-color: var(--color-emerald-600);
        color: #ffffff;
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
    }
    .btn-primary:hover { background-color: var(--color-emerald-700); }
    .btn-secondary {
        background-color: #ffffff;
        color: var(--color-slate-700);
        border: 1px solid var(--color-slate-300);
    }
    .btn-secondary:hover { background-color: var(--color-slate-50); }
    .btn-danger {
        background-color: var(--color-rose-600);
        color: #ffffff;
    }
    .btn-danger:hover { background-color: var(--color-rose-700); }
    .btn-ghost {
        background-color: transparent;
        color: var(--color-slate-600);
    }
    .btn-ghost:hover { background-color: var(--color-slate-100); }
    .btn-icon {
        padding: 0.5rem;
        border-radius: 0.5rem;
        color: var(--color-slate-500);
    }
    .btn-icon:hover { background-color: var(--color-slate-100); color: var(--color-slate-800); }
}
```

### 2. Elimination of `<div>` Soup
Replace indiscriminate nested `<div>` wrappers with semantic HTML5 elements:
- Outer view wrappers: `<main id="main-content">`
- Header titles and action bars: `<header class="page-header">`
- Filters and search rows: `<search class="filter-toolbar">` or `<nav aria-label="Filters">`
- Content sections: `<section aria-labelledby="...">`
- Data grids: `<article class="card">` or `<figure class="chart-container">`
- Dialogue confirmation boxes: Native `<dialog>` instead of full-page `<div>` overlays.

---

## 2.4 Controller-Passed Data vs. Inline Blade PHP Imports

### The Architectural Problem
Currently, several views contain inline PHP declarations that instantiate or import backend Enums and Models directly:
```blade
<!-- Found in monitoring.blade.php -->
@php
    $authAction = \App\Enums\NonModelActions::tryFrom($log->log_name);
@endphp

<!-- Found in ticketDetails.blade.php -->
@can('create', [\App\Models\TicketRemark::class, $ticket])

<!-- Found in dashboard-overview.blade.php -->
@php
    $chartBarClass = \App\Enums\TicketStatus::tryFrom($status['key'])?->chartClass();
@endphp
```

### Architectural Verdict: **Controller / ViewModel-Passed Data is Strictly Superior**
In enterprise Laravel architecture, templates must serve exclusively as **presentation views**. Direct instantiation or resolution of model classes and enums within Blade creates several issues:

1. **Tight Coupling:** The template directly couples to namespace paths and backend database domain logic. If an Enum or Model namespace refactors, view templates crash silently until visited at runtime.
2. **Impaired Unit / View Testing:** When views contain inline backend imports, rendering them in isolated view tests requires bootstrapping full database dependencies rather than asserting against a simple view array.
3. **Logic Duplication:** Logic such as status badge resolution ends up replicated across multiple Blade templates rather than being unified in a Service, Resource, or ViewModel.

### Standard Rule Going Forward
- **Enums & Form Options:** Controllers / Services must pass prepared option arrays (e.g. `$categories`, `$statuses`, `$eventTypes`) as pure associative arrays or DTOs to the view.
- **Model Policies:** For authorization checks, use policy method names with the model instance (`@can('update', $user)`) or registered Gate aliases (`@can('create-ticket')`) rather than explicit FQCNs (`\App\Models\Ticket::class`).
- **Badge Classes:** Model Accessors or ViewModel Presenters (e.g. `$ticket->status_badge_class`) must resolve badges before passing the object to Blade.

---

## 2.5 Modularization of Reusable UI Sections

The following repeating blocks must be extracted into `<x-...>` components:

1. **Page Header Component (`<x-page-header>`):**
   - Combines title, subtitle, breadcrumb / back link, and primary action slot.
2. **Data Table Wrapper (`<x-table>` & `<x-table.th>`):**
   - Standardizes sticky headers, zebra stripes, padding, responsive horizontal scroll wrapping, and empty state fallbacks.
3. **Status Badge Component (`<x-status-badge :status="$status" />`):**
   - Replaces inline `@if/@elseif` chains for ticket, endorsement, and user account statuses.
4. **Filter Toolbar (`<x-filter-bar>`):**
   - Standardizes search input, TomSelect dropdown slots, clear button, and counter.
5. **Confirmation Card (`<x-confirm-card>`):**
   - Replaces the 5 identical confirmation prompt templates with one parameterized component accepting title, warning text, consequences list, confirm route, and cancel route.

---

## 2.6 Icon System Migration Plan

### Problem Statement
Currently, raw inline SVG strings (15–30 lines each) are copy-pasted across dozens of views and even hardcoded into PHP arrays (e.g., `$cwdControls` in `cwd/pages/dashboard.blade.php`). This inflates template size, creates maintainability headaches, and prevents system-wide icon updates.

### Solution: Dedicated Icon Blade Components
Create `resources/views/components/icons/`:

```
resources/views/components/icons/
├── ticket.blade.php
├── user.blade.php
├── team.blade.php
├── department.blade.php
├── check-circle.blade.php
├── exclamation-triangle.blade.php
├── arrow-left.blade.php
├── chevron-right.blade.php
├── eye.blade.php
├── eye-slash.blade.php
├── plus.blade.php
├── search.blade.php
├── trash.blade.php
└── gear.blade.php
```

#### Component Pattern Example (`resources/views/components/icons/ticket.blade.php`):
```blade
@props(['size' => 'w-5 h-5', 'stroke' => '1.5'])

<svg {{ $attributes->merge(['class' => "$size text-current shrink-0"]) }} fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="{{ $stroke }}" d="M6 3h12a2 2 0 012 2v14a2 2 0 01-2 2H6a2 2 0 01-2-2V5a2 2 0 012-2zm3 5h6m-6 4h6m-6 4h4" />
</svg>
```

#### Usage in Blade:
```blade
<x-icons.ticket class="text-emerald-600" size="w-6 h-6" />
```

---

## 2.7 CSS Purge, Conflict Resolution & Modern Tailwind CSS Strategy

### 1. Eliminating Conflicting & Gibberish CSS
In `workspace-refined.css`:
- Rules like `.refined-shell .refined-workspace .system-table td { padding-block: .85rem; }` and `.refined-shell .refined-workspace .ticket-queue :is(th, td) { padding-inline: 1rem !important; }` fight directly with Tailwind's utility classes (`px-6 py-4`).
- Multiple nested selectors with `!important` tags break Tailwind specificity.
- **Action:** Remove all global `!important` paddings and border-radius rules. Let Tailwind utility classes manage element spacing.

### 2. What CSS Should Be Kept
CSS should be strictly reserved for:
1. **Design Tokens & Theme Root (`base-theme.css`):** Core brand variables (`--paleco-*`, fonts).
2. **Complex CSS Keyframe Animations (`animations.css`):** Reveal transitions, skeleton loaders, and pulse effects.
3. **Third-Party Library Resets (`custom-tom-select.css`):** Restyling vendor DOM trees (TomSelect dropdown menus and focus rings).
4. **User Accessibility Overrides (`preferences.css`):** Motion suppression and text zoom.

Everything else (buttons, cards, tables, grids, layout flexbox) should be handled via Tailwind utility classes and `@layer components`.

---

## 2.8 JavaScript Simplification & Performance Optimization

### 1. Eliminating Race Condition `setTimeout` Hacks
In `ticket-form.js`:
```javascript
// Current anti-pattern:
setTimeout(toggleCategoryFields, 50);
```
In `disableDeptForFieldPerson.js`:
```javascript
// Current anti-pattern:
setTimeout(toggleDepartmentInput, 100);
```
**Why this is dangerous:** On slower devices or during heavy script execution, 50ms or 100ms may elapse *before* TomSelect finishes initialization, causing the handler to silently fail and leaving invalid form states visible.

**Modern Production Fix:**
Event-driven initialization. Emit a custom DOM event `tomselect:ready` when TomSelect attaches, or inspect the instance synchronously:
```javascript
const ensureTomSelectReady = (element, callback) => {
    if (element.tomselect) {
        callback(element.tomselect);
    } else {
        element.addEventListener('tomselect:initialized', (e) => callback(e.detail), { once: true });
    }
};
```

### 2. Consolidated Event Delegation
Currently, scripts like `preventDoubleSubmit.js` and `smart-navigation.js` iterate through `document.querySelectorAll()` on initial load. If elements are inserted dynamically (such as by Livewire updates), they require manual re-binding.

**Modern Production Fix:** Use top-level document event delegation:
```javascript
document.addEventListener('click', (event) => {
    const actionLink = event.target.closest('.action-link');
    if (actionLink) {
        handleActionLinkLoading(actionLink, event);
    }

    const smartBack = event.target.closest('[data-smart-back]');
    if (smartBack) {
        event.preventDefault();
        handleSmartBack(smartBack);
    }
});
```

---

## 2.9 Target Folder Restructuring

Below is the proposed clean directory layout for `resources/`:

```
resources/
├── css/
│   ├── app.css                    (Master Vite manifest)
│   ├── theme.css                  (Brand color tokens & typography variables)
│   ├── components.css             (Shared component layers: .btn, .card, .badge)
│   ├── vendor-tom-select.css      (Tailwind adaptation for TomSelect)
│   └── animations.css             (Keyframe animations & accessibility motion rules)
│
├── js/
│   ├── app.js                     (Master bundle entrypoint)
│   ├── core/                      (Shared system utilities)
│   │   ├── double-submit-guard.js
│   │   ├── smart-navigation.js
│   │   └── preferences.js
│   ├── components/                (Component-level interactions)
│   │   ├── lightbox.js
│   │   ├── modal-dialog.js
│   │   ├── tabs.js
│   │   └── view-toggle.js
│   └── forms/                     (Form-specific dynamic handlers)
│       ├── consumer-verification.js
│       ├── tom-select-manager.js
│       └── team-repeater.js
│
└── views/
    ├── layouts/                   (Renamed from base/)
    │   ├── admin.blade.php        (Admin base layout)
    │   ├── cwd.blade.php          (CWD base layout)
    │   └── guest.blade.php        (Auth and Error base layout)
    │
    ├── components/                (Atomic UI components)
    │   ├── icons/                 (Dedicated SVG icon library)
    │   ├── button.blade.php
    │   ├── card.blade.php
    │   ├── table.blade.php
    │   ├── status-badge.blade.php
    │   ├── page-header.blade.php
    │   ├── filter-bar.blade.php
    │   ├── confirm-card.blade.php
    │   └── alert.blade.php        (Unified flash message banner)
    │
    ├── admin/                     (Admin portal views)
    │   ├── dashboard/
    │   ├── users/
    │   ├── departments/
    │   ├── teams/
    │   ├── categories/
    │   └── monitoring/
    │
    ├── cwd/                       (CWD portal views)
    │   ├── dashboard/
    │   ├── consumers/
    │   └── endorsements/
    │
    ├── shared/                    (Shared between Admin & CWD)
    │   ├── settings.blade.php
    │   └── tickets/
    │       ├── index.blade.php
    │       ├── show.blade.php
    │       └── forms/
    │
    └── livewire/                  (Reactive Livewire islands)
        ├── tickets-table.blade.php
        └── dashboard-telemetry.blade.php
```

---

# Section 3: File-Specific Cleanup, Logical Grouping & Phased Roadmap

To execute this migration without disruption, the work is organized into **logical file groups** mapped to sequential phases.

---

### Group 1: Foundation & Design Tokens (Phase 1)
- **Target Files:**
  - `resources/css/app.css`
  - `resources/css/base-theme.css`
  - `resources/css/appearance.css`
  - `resources/css/preferences.css`
  - `resources/css/animations.css`
  - `resources/js/preferences.js`
- **Phase Objective:** Unify CSS tokens under modern Tailwind v4 `@theme`, eliminate legacy color overrides, and create shared component layer classes (`.btn`, `.card`, `.badge`).
- **Semantic HTML Upgrades:** Not applicable (stylesheets and root settings).

---

### Group 2: Shells, Layouts & Global Navigation (Phase 2)
- **Target Files:**
  - `resources/views/admin/base/base.blade.php`
  - `resources/views/cwd/base/base.blade.php`
  - `resources/views/components/settings-nav.blade.php`
  - `resources/views/admin/prompts/alert.blade.php`
  - `resources/views/cwd/prompts/alert.blade.php`
  - `resources/css/layout-shell.css`
  - `resources/js/sidebar-nav.js`
- **Phase Objective:**
  1. Fix the unclosed `<li>` tag on line 85 of `cwd/base/base.blade.php`.
  2. Unify Admin and CWD layout shells into a shared layout architecture.
  3. Consolidate `admin/prompts/alert.blade.php` and `cwd/prompts/alert.blade.php` into a single `<x-alert />` component.
  4. Ensure skip-links, mobile app bars, and sidebars utilize semantic `<header>`, `<nav>`, `<aside>`, and `<main>` tags.

---

### Group 3: Input Forms & Dynamic Mutation Controls (Phase 3)
- **Target Files:**
  - `resources/views/admin/forms/userForm.blade.php`
  - `resources/views/admin/forms/departmentForm.blade.php`
  - `resources/views/admin/forms/teamForm.blade.php`
  - `resources/views/admin/forms/ticketCategoryForm.blade.php`
  - `resources/views/cwd/forms/ticketForm.blade.php`
  - `resources/views/cwd/forms/childTicketForm.blade.php`
  - `resources/js/consumerLinkToggle.js`
  - `resources/js/disableDeptForFieldPerson.js`
  - `resources/js/ticket-form.js`
  - `resources/js/teamInlines.js`
  - `resources/js/tomSelect-input_with_autoSuggest.js`
- **Phase Objective:**
  1. Standardize form wrappers (`<form class="space-y-6">`), fieldsets (`<fieldset>`), and legends (`<legend>`).
  2. Eliminate 50ms/100ms `setTimeout` hacks in form scripts.
  3. Replace repeated input and label classes with `<x-form.input>`, `<x-form.select>`, and `<x-form.label>` components.
  4. Unify CWD ticket form styling (remove hardcoded `#008f5d` hex codes in favor of `emerald-600`).

---

### Group 4: Confirmation Dialogs & Destructive Workflows (Phase 4)
- **Target Files:**
  - `resources/views/admin/prompts/userDeactivateConfirm.blade.php`
  - `resources/views/admin/prompts/userReactivateConfirm.blade.php`
  - `resources/views/admin/prompts/departmentDeleteConfirm.blade.php`
  - `resources/views/admin/prompts/teamDeleteConfirm.blade.php`
  - `resources/views/admin/prompts/ticketCategoryDeleteConfirm.blade.php`
- **Phase Objective:**
  1. Replace 5 duplicate full-page confirmation views with a single, reusable `<x-confirm-card>` component.
  2. Transition confirmation surfaces to accessible `<section role="alertdialog">` markup.
  3. Standardize warning badges, impact bullets, and action button pairings.

---

### Group 5: Registries, Queues & Livewire Tables (Phase 5)
- **Target Files:**
  - `resources/views/admin/pages/userManagement.blade.php`
  - `resources/views/admin/pages/departmentManagement.blade.php`
  - `resources/views/admin/pages/teamManagement.blade.php`
  - `resources/views/admin/pages/ticketCategoryManagement.blade.php`
  - `resources/views/admin/pages/monitoring.blade.php`
  - `resources/views/cwd/pages/consumerManagement.blade.php`
  - `resources/views/cwd/pages/endorsementDashboard.blade.php`
  - `resources/views/shared/pages/ticketManagement.blade.php`
  - `resources/views/livewire/shared/ticket-table.blade.php`
  - `resources/js/toggleCardTable.js`
  - `resources/js/tomselect-filters.js`
  - `resources/js/table-wrapper.js`
- **Phase Objective:**
  1. Wrap all index filter toolbars in `<search>` or `<nav aria-label="Filters">`.
  2. Standardize all data tables to use semantic `<caption>`, `<thead>`, `<tbody>`, and `<th scope="col">`.
  3. Replace inline Enums and model badge lookups with controller-passed data.
  4. Ensure card/list view toggles utilize ARIA pressed states (`aria-pressed="true"`).

---

### Group 6: Detail Views, Timelines & Profile Inspections (Phase 6)
- **Target Files:**
  - `resources/views/admin/pages/userDetails.blade.php`
  - `resources/views/admin/pages/departmentDetails.blade.php`
  - `resources/views/admin/pages/teamDetails.blade.php`
  - `resources/views/admin/pages/ticketCategoryDetails.blade.php`
  - `resources/views/admin/pages/activityLogDetails.blade.php`
  - `resources/views/cwd/pages/consumerDetails.blade.php`
  - `resources/views/cwd/pages/endorsementDetails.blade.php`
  - `resources/views/cwd/pages/ticketAccomplishmentDetails.blade.php`
  - `resources/views/shared/pages/ticketDetails.blade.php`
  - `resources/views/components/ticket-history-table.blade.php`
  - `resources/js/history-tabs.js`
  - `resources/js/lightbox.js`
- **Phase Objective:**
  1. Clean up `shared/pages/ticketDetails.blade.php` (588 lines): extract timeline tabs, hierarchy cards, and operational remark threads into dedicated partials.
  2. Convert all key-value inspection lists into accessible `<dl>`, `<dt>`, and `<dd>` definition lists.
  3. Ensure lightbox and modal inspectors use native `<dialog>` or `role="dialog" aria-modal="true"`.
  4. Remove inline `App\Models\...` policy gates in favor of gate strings.

---

### Group 7: Dashboards, Metric Cards & Telemetry Islands (Phase 7)
- **Target Files:**
  - `resources/views/admin/pages/dashboard.blade.php`
  - `resources/views/cwd/pages/dashboard.blade.php`
  - `resources/views/livewire/cwd/dashboard-overview.blade.php`
  - `resources/views/components/dashboard-controls.blade.php`
  - `resources/views/components/dashboard-operations.blade.php`
  - `resources/css/dashboard-cards.css`
  - `resources/css/dashboard-charts.css`
  - `resources/js/dashboard-controls.js`
  - `resources/js/livewire-animations.js`
- **Phase Objective:**
  1. Move raw SVG path definitions out of `@php` controller arrays in `admin/pages/dashboard.blade.php` and `cwd/pages/dashboard.blade.php` into `<x-icons.*>` calls.
  2. Standardize metric cards as `<article class="metric-card">` with `<header>`, `<data value="...">`, and accessible trend indicators.
  3. Ensure pure-CSS column and row charts are wrapped in semantic `<figure>` with `<figcaption>`.

---

### Group 8: Authentication & Error Boundary Pages (Phase 8)
- **Target Files:**
  - `resources/views/auth/login.blade.php`
  - `resources/views/auth/roleSelection.blade.php`
  - `resources/views/errors/layout.blade.php`
  - `resources/views/errors/403.blade.php`
  - `resources/views/errors/404.blade.php`
  - `resources/views/welcome.blade.php`
  - `resources/js/togglePassword.js`
- **Phase Objective:**
  1. Modernize login card into an accessible `<main>` container with clean floating-style inputs.
  2. Remove obsolete `welcome.blade.php`.
  3. Ensure error pages utilize `<main>` and proper semantic headings.

---

## Semantic HTML Transition Matrix

| Legacy Element / Pattern | Target Semantic HTML Tag | ARIA & Accessibility Enhancement |
| :--- | :--- | :--- |
| `<div class="app-sidebar">` | `<aside aria-label="Sidebar Navigation">` | `role="navigation"` on nested `<nav>` |
| `<div class="page-header">` | `<header class="page-header">` | Heading hierarchy `<h1>` with `<p>` subtitle |
| `<div class="filter-toolbar">` | `<search role="search">` or `<nav aria-label="Filters">` | Inputs bound to `<label>` or explicit `aria-label` |
| `<div class="metric-card">` | `<article class="metric-card">` | `<data value="...">` for numeric telemetry |
| `<div class="chart-container">` | `<figure>` with `<figcaption>` | `role="img"` with descriptive summary `aria-label` |
| `<div class="ticket-history-section">` | `<section role="tabpanel">` | `aria-labelledby="{tabId}-heading"` |
| `<div class="details-grid">` | `<dl class="details-list">` | Semantic `<dt>` for labels, `<dd>` for values |
| `<div class="confirmation-modal">` | `<dialog>` | Native focus trap, backdrop blur, `aria-modal="true"` |
| `<div class="table-container">` | `<div tabindex="0" role="region">` | Screen reader notification of horizontal scrolling |
| Raw SVG strings in PHP | `<x-icons.* aria-hidden="true" />` | Omit assistive tree clutter with `aria-hidden` |

---

## Conclusion & Recommended Next Step
This roadmap details every file in `resources/`, resolves architectural divergence between CWD and Admin, provides a clear verdict on controller-passed data vs. inline PHP imports, and outlines an 8-phase execution plan.

**Recommended Immediate Action:** Begin **Phase 1 (General Cleanup & Design Tokens)** to establish the standardized component classes, icon library, and Tailwind design tokens before refactoring individual view groups.

