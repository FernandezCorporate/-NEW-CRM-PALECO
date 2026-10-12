# PALECO OTRS-CRM: Comprehensive Frontend Architecture & Resources Guide

**Target System:** Palawan Electric Cooperative Online Ticket Resolution System & Customer Relationship Management (PALECO OTRS-CRM)  
**Target Directory:** `PALECO_OTRS-CRM/resources/` (`css/`, `js/`, `views/`)  
**Framework Stack:** Laravel 13.x | Livewire 4 | Tailwind CSS v4 | TomSelect | Vite  
**Audience:** Web Frontend Developers, Full-Stack Engineers, and Capstone Collaborators  
**Document Status:** Complete Architecture & Engineering Reference (Post-Harmonization)  

---

## Table of Contents
1. [Executive Overview & Mental Model](#1-executive-overview--mental-model)
2. [Foundational Concepts & Vocabulary Primer](#2-foundational-concepts--vocabulary-primer)
   - [2.1 The Blade Templating Engine](#21-the-blade-templating-engine)
   - [2.2 Tailwind CSS v4 Engine & Design Token Architecture](#22-tailwind-css-v4-engine--design-token-architecture)
   - [2.3 Livewire 4: Server-Driven Reactive Islands](#23-livewire-4-server-driven-reactive-islands)
   - [2.4 Semantic HTML5 & Landmark Regions](#24-semantic-html5--landmark-regions)
   - [2.5 WAI-ARIA & Accessibility Principles](#25-wai-aria--accessibility-principles)
   - [2.6 TomSelect Combobox System](#26-tomselect-combobox-system)
   - [2.7 Vite Modern Asset Pipeline](#27-vite-modern-asset-pipeline)
3. [Complete Directory & Blueprint Matrix](#3-complete-directory--blueprint-matrix)
4. [Deep Dive: The Styling Engine (`resources/css/`)](#4-deep-dive-the-styling-engine-resourcescss)
   - [4.1 Entrypoint & Loading Hierarchy (`app.css`)](#41-entrypoint--loading-hierarchy-appcss)
   - [4.2 Tokens & Theme System (`base-theme.css`, `layout-shell.css`)](#42-tokens--theme-system-base-themecss-layout-shellcss)
   - [4.3 Component System (`components.css`)](#43-component-system-componentscss)
   - [4.4 TomSelect Design Unification (`custom-tom-select.css`)](#44-tomselect-design-unification-custom-tom-selectcss)
   - [4.5 Dark Mode & Accessibility Adaptations (`appearance.css`, `preferences.css`)](#45-dark-mode--accessibility-adaptations-appearancecss-preferencescss)
   - [4.6 Dashboards, Charts & Motion (`dashboard-cards.css`, `dashboard-charts.css`, `animations.css`, `workspace-refined.css`)](#46-dashboards-charts--motion)
5. [Deep Dive: Client-Side Behaviors (`resources/js/`)](#5-deep-dive-client-side-behaviors-resourcesjs)
   - [5.1 The `app.js` Orchestrator](#51-the-appjs-orchestrator)
   - [5.2 The Data-Attribute Binding Paradigm](#52-the-data-attribute-binding-paradigm)
   - [5.3 Livewire Coexistence & Re-initialization Hooking](#53-livewire-coexistence--re-initialization-hooking)
   - [5.4 Interactive Modules Inventory & Responsibilities](#54-interactive-modules-inventory--responsibilities)
6. [Deep Dive: Views & Templating Hierarchy (`resources/views/`)](#6-deep-dive-views--templating-hierarchy-resourcesviews)
   - [6.1 Layout Shells (`admin.base.base`, `cwd.base.base`, `errors.layout`)](#61-layout-shells)
   - [6.2 Shared Operational Surfaces (`shared/pages/ticketDetails.blade.php`)](#62-shared-operational-surfaces)
   - [6.3 Domain Pages: Admin vs CWD](#63-domain-pages-admin-vs-cwd)
   - [6.4 Livewire Reactive Islands (`livewire/`)](#64-livewire-reactive-islands)
   - [6.5 Component Library (`components/`)](#65-component-library)
   - [6.6 Icon Library (`components/icons/`)](#66-icon-library)
   - [6.7 Auth & Error Boundaries (`auth/`, `errors/`)](#67-auth--error-boundaries)
7. [Developer Structural Preferences & Strict Engineering Rules](#7-developer-structural-preferences--strict-engineering-rules)
8. [Developer Playbook: How to Build, Extend & Maintain](#8-developer-playbook-how-to-build-extend--maintain)
   - [Recipe 1: Creating a Brand-New Page](#recipe-1-creating-a-brand-new-page)
   - [Recipe 2: Creating a Reusable Blade Component](#recipe-2-creating-a-reusable-blade-component)
   - [Recipe 3: Adding an SVG Icon to the System](#recipe-3-adding-an-svg-icon-to-the-system)
   - [Recipe 4: Building a Form with Standard Controls & TomSelect](#recipe-4-building-a-form-with-standard-controls--tomselect)
   - [Recipe 5: Adding a Client-Side Interactive Script](#recipe-5-adding-a-client-side-interactive-script)
   - [Recipe 6: Building a Livewire Table / Reactive Island](#recipe-6-building-a-livewire-table--reactive-island)
   - [Recipe 7: Compiling, Debugging & Automated Testing](#recipe-7-compiling-debugging--automated-testing)
9. [Quick-Reference Cheat Sheet](#9-quick-reference-cheat-sheet)

---

## 1. Executive Overview & Mental Model

In standard web applications, the frontend folder is often treated as a casual collection of HTML snippets and scattered CSS classes. In **PALECO OTRS-CRM**, the frontend folder (`resources/`) is an **integrated, component-driven presentation architecture** built on top of Laravel, Livewire, and Tailwind CSS v4.

### The Request-to-Render Mental Model

```
                    ┌──────────────────────────────────────────────┐
                    │               Incoming Request               │
                    └──────────────────────┬───────────────────────┘
                                           │
                                           ▼
                    ┌──────────────────────────────────────────────┐
                    │       Laravel Controller / Livewire Class    │
                    │   (Fetches Eloquent Models, runs Policies)   │
                    └──────────────────────┬───────────────────────┘
                                           │
                                           ▼
                    ┌──────────────────────────────────────────────┐
                    │              Root Layout Shell               │
                    │    (admin/base/base.blade or cwd/base/base)  │
                    │    - HTML5 Doctype, <head>, @vite asset tags │
                    │    - Skip-to-content accessibility link      │
                    │    - Mobile drawer, Header, Sidebar nav      │
                    │    - Global Flash Messages (<x-alert />)     │
                    └──────────────────────┬───────────────────────┘
                                           │
                                           ▼
                    ┌──────────────────────────────────────────────┐
                    │            Page View / Domain Surface        │
                    │  (e.g., shared.pages.ticketDetails.blade)    │
                    │  - Injects semantic <header>, <main>, <dl>   │
                    │  - Includes modular sub-partials (@include)  │
                    │  - Mounts Livewire islands (<livewire:...>)  │
                    └──────────────────────┬───────────────────────┘
                                           │
                                           ▼
                    ┌──────────────────────────────────────────────┐
                    │     Atomic Blade Components & Primitives     │
                    │  - <x-form.input>, <x-alert>, <x-icons.*>    │
                    │  - Pure CSS design tokens (.btn, .panel)     │
                    └──────────────────────┬───────────────────────┘
                                           │
                                           ▼
                    ┌──────────────────────────────────────────────┐
                    │       Client Browser: DOM Hydration          │
                    │  - Tailwind v4 styles compiled by Vite       │
                    │  - app.js executes data-attribute bindings   │
                    │  - TomSelect enhances designated dropdowns   │
                    │  - Livewire socket/HTTP listeners activate   │
                    └──────────────────────────────────────────────┘
```

### Core Architecture Pillars

1. **Zero Raw Inline SVG Clutter:** SVG path strings are strictly prohibited inside pages. All icons live in dedicated, accessible Blade components (`<x-icons.*>`).
2. **Design Tokens Over Arbitrary Utilities:** Instead of scattering arbitrary hex codes like `bg-[#10a352]`, the app relies on standardized tokens (`.btn-primary`, `--paleco-700`, `.form-input`).
3. **Decoupled JavaScript:** Vanilla JavaScript never binds to presentation classes like `.flex` or `.bg-white`. Scripts bind exclusively to `[data-*]` attributes and element IDs, ensuring styling changes never break functionality.
4. **Accessible by Default (WCAG 2.1 AA):** Every form field is bound to a `<label>` and an `aria-describedby` error element. Tables feature `<caption>` and `<th scope="col">`. Navigation regions use landmarks (`<search>`, `<nav>`, `<aside>`).
5. **Shared Surfaces:** Both the Administrator and Consumer Welfare Desk (CWD) share common high-density interfaces (like Ticket Details and Ticket Queue) with policy-driven permissions (`@can`).

---

## 2. Foundational Concepts & Vocabulary Primer

*(If you are new to Laravel, Blade, Livewire, Tailwind CSS v4, or modern web standards, read this section carefully before touching any file.)*

### 2.1 The Blade Templating Engine
**What it is:** Blade is Laravel’s native templating language. Blade files have the `.blade.php` extension and compile down to plain PHP cached in `storage/framework/views/`.

* **`@extends('layout.name')`**: Declares that this page borrows the structure (HTML skeleton, scripts, sidebar) of a master layout.
* **`@section('name') ... @endsection`**: Fills a named placeholder (`@yield('name')`) defined in the parent layout.
* **`@props(['name' => 'default'])`**: Used inside reusable components to declare variables the component expects from the caller.
* **`<x-component-name />`**: Invokes a Blade component found in `resources/views/components/`. For example, `<x-alert />` loads `resources/views/components/alert.blade.php`.
* **`<x-form.input />`**: Dot notation indicates a subfolder. Loads `resources/views/components/form/input.blade.php`.
* **`$attributes->merge(['class' => '...'])`**: Merges default component classes with any custom classes passed by the caller, without overriding the defaults.
* **`$slot`**: The internal HTML content passed between an opening and closing component tag (`<x-card> My content is the $slot </x-card>`).
* **`@can('ability', $model)`**: Authorization gate. Conditionally renders content only if the authenticated user has permission according to a Laravel Policy.
* **`@csrf`**: Injects a hidden security token input into HTML `<form>` elements to prevent Cross-Site Request Forgery attacks.

### 2.2 Tailwind CSS v4 Engine & Design Token Architecture
**What it is:** Tailwind CSS is a utility-first CSS framework. In version 4, Tailwind underwent a major architectural rewrite:
* **No `tailwind.config.js`:** All configuration is defined in CSS using `@theme` blocks inside `resources/css/app.css`.
* **CSS Custom Properties (Variables):** Theme colors are exposed as native CSS variables (e.g., `--color-paleco-700: #08794e;`), meaning they can be referenced seamlessly in both Tailwind utilities (`text-paleco-700`) and custom stylesheets (`var(--paleco-700)`).
* **`@layer components`:** A CSS directive where reusable classes (like `.btn`, `.form-input`) are declared. Rules defined here can still be customized or overridden by utility classes without requiring `!important`.
* **Design Token:** A named stylistic constant (e.g. `--paleco-700`, `.btn-primary`, `.form-input`). Writing `.btn .btn-primary` ensures that if PALECO rebrands its button color, changing one token updates hundreds of buttons across the entire app instantly.

### 2.3 Livewire 4: Server-Driven Reactive Islands
**What it is:** Livewire allows developers to build reactive, real-time dynamic interfaces (search debouncing, live table filtering, auto-calculating counters) using PHP and Blade without writing custom REST APIs or Vue/React SPAs.
* **`wire:model.live.debounce.300ms="search"`**: Automatically syncs an `<input>` value to a Livewire PHP component property after a 300-millisecond typing pause, re-rendering only the affected table rows.
* **DOM Morphing:** When Livewire receives an update from the server, it compares the new HTML with the existing browser DOM and only changes the elements that actually changed, preserving text input focus.
* **`livewire:navigated`:** A client-side JavaScript event dispatched whenever Livewire finishes updating or navigating. Custom scripts re-attach event listeners upon this event.

### 2.4 Semantic HTML5 & Landmark Regions
**What it is:** Using the appropriate HTML tag for each element's true purpose instead of building everything out of generic `<div>` tags.
* **`<main>`**: The primary, unique content container of the page (excludes sidebars, headers, footers). Assistive technologies let users jump directly here.
* **`<aside>`**: Secondary content related to the page (e.g., sidebar navigation or linked metadata cards).
* **`<search>`**: The modern HTML5 semantic container for search bars and filter forms.
* **`<figure>` & `<figcaption>`**: Used for visual charts, graphs, and illustrations accompanied by a caption.
* **`<article>`**: An independent, self-contained piece of content (e.g., a metric card or an activity log item).
* **`<dl>`, `<dt>`, `<dd>`**: Definition Lists. `<dl>` is the list, `<dt>` is the term/label (e.g., "Account Number"), and `<dd>` is the value/description (e.g., "01-2345-6789"). This is the gold standard for key-value grids.
* **`<dialog>`**: The native HTML5 element for modal dialogs. Features built-in backdrop styling (`::backdrop`), keyboard `Escape` closing, and focus management.
* **`<data value="42">`**: Machine-readable wrapper for numeric statistics.

### 2.5 WAI-ARIA & Accessibility Principles
**What it is:** WAI-ARIA (Web Accessibility Initiative - Accessible Rich Internet Applications) attributes tell screen readers and assistive devices what interactive elements are doing.
* **`aria-label="Description"`**: Provides an invisible, spoken name for buttons or controls that only have an icon.
* **`aria-expanded="true|false"`**: Tells screen readers whether a drawer, accordion, or dropdown menu is open or closed.
* **`aria-current="page"`**: Indicates which tab or navigation link represents the active page.
* **`aria-live="polite"`**: Tells screen readers to announce changes in this container (e.g., "Showing 25 results") without interrupting the user.
* **`role="alert"`**: Announces important notifications (e.g., flash errors) immediately to the user.
* **`role="alertdialog"`**: Designates a confirmation dialog that requires user intervention before proceeding with a destructive action (e.g., deleting a record).

### 2.6 TomSelect Combobox System
**What it is:** TomSelect is a lightweight JavaScript library that turns standard HTML `<select>` dropdowns into searchable, autocomplete-friendly comboboxes.
* **The "Double-Border" Problem:** TomSelect wraps native `<select>` tags in a `.ts-wrapper` containing an internal `.ts-control` box. If both the wrapper and the control declare borders, the user sees an ugly double-border. The PALECO stylesheet (`custom-tom-select.css`) zeros the outer wrapper and moves all border, radius, shadow, and focus-ring states onto `.ts-control`, creating a seamless match with `.form-input`.

### 2.7 Vite Modern Asset Pipeline
**What it is:** Vite is the frontend build tool powering modern Laravel projects.
* **Development (`npm run dev`):** Runs a local dev server that serves JavaScript modules natively and updates CSS in milliseconds via Hot Module Replacement (HMR).
* **Production Build (`npm run build`):** Bundles, minifies, and tree-shakes CSS and JS files into hashed production files placed inside `public/build/`.
* **Blade Directive (`@vite([...])`):** Automatically injects the correct development or production asset tags into the HTML `<head>`.

---

## 3. Complete Directory & Blueprint Matrix

Below is the complete structural map of `PALECO_OTRS-CRM/resources/`:

```
resources/
├── css/                                   # STYLING ENGINE (Tailwind v4 & Custom Tokens)
│   ├── animations.css                     # Keyframes, transitions & reveal animations
│   ├── appearance.css                     # Dark mode & custom theme palette overrides
│   ├── app.css                            # Master entrypoint loaded by Vite & @theme tokens
│   ├── base-theme.css                     # Root CSS custom properties & core color variables
│   ├── components.css                     # Design tokens (.btn, .form-input, .panel, .badge)
│   ├── custom-tom-select.css              # Unified TomSelect border, focus & dark-mode overrides
│   ├── dashboard-cards.css                # Metric cards, telemetry containers, quick-link tiles
│   ├── dashboard-charts.css               # Pure CSS responsive column & bar charts
│   ├── layout-shell.css                   # Sidebar surfaces, mobile drawers, grid layouts
│   ├── preferences.css                    # Compact density mode & typography scaling
│   └── workspace-refined.css              # Operational table styling, history tabs & timelines
│
├── js/                                    # CLIENT-SIDE INTERACTIVITY & MODULES
│   ├── app.js                             # Master script entrypoint loaded by Vite
│   ├── consumerLinkToggle.js              # CRM lookup vs manual walk-in input switcher
│   ├── dashboard-controls.js              # Pinned shortcut selector modal & localStorage cache
│   ├── disableDeptForFieldPerson.js       # Dynamic role-to-department exclusivity toggler
│   ├── echo.js                            # Laravel Echo & Reverb WebSocket listener
│   ├── filter-aria.js                     # Dynamic accessibility announcements for search filters
│   ├── history-tabs.js                    # Accessible tab switcher for ticket activity logs
│   ├── lightbox.js                        # Native <dialog> image modal for field photo proofs
│   ├── livewire-animations.js             # Morph transition hooks for Livewire updates
│   ├── preferences.js                     # Theme, dark mode & compact density manager
│   ├── preventDoubleSubmit.js             # Form debouncing & loading state button locks
│   ├── sidebar-nav.js                     # Mobile responsive drawer & sidebar active states
│   ├── smart-navigation.js                # [data-smart-back] history checker with fallback URL
│   ├── system-fields.js                   # Form focus enhancement & error clearing
│   ├── table-wrapper.js                   # Accessible horizontal scroll hints for data tables
│   ├── teamInlines.js                     # Team member list expansion & roster manipulation
│   ├── ticket-form.js                     # Cascading dropdowns & conditional ticket form logic
│   ├── title-aria.js                      # Live browser tab title announcer for background tabs
│   ├── toggleCardTable.js                 # Directory view switcher (Table rows vs Cards)
│   ├── togglePassword.js                  # Accessible password masking/unmasking toggle
│   ├── tomselect-filters.js               # Event-driven TomSelect binder for dynamic queues
│   └── tomSelect-input_with_autoSuggest.js# Autocomplete combobox initialization with debouncing
│
└── views/                                 # TEMPLATES, BLADE COMPONENTS & INTERFACES
    ├── admin/                             # ADMINISTRATOR DOMAIN
    │   ├── base/
    │   │   └── base.blade.php             # Admin layout shell (Dark emerald branding)
    │   └── pages/                         # Dedicated Admin screens
    │       ├── activityLogDetails.blade.php
    │       ├── departmentManagement.blade.php
    │       ├── systemMonitoring.blade.php
    │       ├── teamManagement.blade.php
    │       ├── ticketCategoryManagement.blade.php
    │       ├── userDetails.blade.php
    │       ├── userManagement.blade.php
    │       └── dashboard.blade.php
    │
    ├── cwd/                               # CONSUMER WELFARE DESK (CWD) DOMAIN
    │   ├── base/
    │   │   └── base.blade.php             # CWD layout shell (Operational branding)
    │   ├── forms/
    │   │   ├── childTicketForm.blade.php  # Dedicated child ticket creation form
    │   │   └── ticketForm.blade.php       # Primary service complaint intake form
    │   ├── pages/                         # Dedicated CWD screens
    │   │   ├── consumerDetails.blade.php
    │   │   ├── consumerManagement.blade.php
    │   │   ├── dashboard.blade.php
    │   │   ├── endorsementDashboard.blade.php
    │   │   ├── endorsementDetails.blade.php
    │   │   ├── ticketAccomplishmentDetails.blade.php
    │   │   ├── ticketDetails.blade.php    # (Delegates to shared.pages.ticketDetails)
    │   │   └── ticketManagement.blade.php # (Delegates to shared.pages.ticketManagement)
    │   └── prompts/
    │       └── alert.blade.php            # Contextual notification prompt
    │
    ├── shared/                            # SHARED DOMAIN SURFACES (Admin & CWD)
    │   ├── settings.blade.php             # Shared user profile & display preferences
    │   └── pages/
    │       ├── ticketManagement.blade.php # High-performance ticket registry view
    │       ├── ticketDetails.blade.php    # High-density ticket inspection master view
    │       └── partials/                  # Modular partials extracted from ticket details
    │           ├── ticket-assigned-personnel.blade.php
    │           ├── ticket-consumer-card.blade.php
    │           ├── ticket-hierarchy.blade.php
    │           └── ticket-remarks-thread.blade.php
    │
    ├── components/                        # REUSABLE BLADE COMPONENT LIBRARY
    │   ├── alert.blade.php                # Dual-mode notification banner (<x-alert />)
    │   ├── confirm-card.blade.php         # Accessible destructive workflow dialog
    │   ├── dashboard-controls.blade.php   # Personalizable shortcut control panel
    │   ├── dashboard-operations.blade.php # Dashboard queue status summary list
    │   ├── settings-nav.blade.php         # Unified sidebar link for user settings
    │   ├── sidebar-user-card.blade.php    # Bottom sidebar authenticated user badge
    │   ├── ticket-history-table.blade.php # Standardized table wrapper for ticket records
    │   │
    │   ├── form/                          # Form field primitives
    │   │   ├── error.blade.php            # Accessible validation error message (<x-form.error />)
    │   │   ├── input.blade.php            # Standardized text input (<x-form.input />)
    │   │   ├── label.blade.php            # Standardized form label with asterisk (<x-form.label />)
    │   │   └── textarea.blade.php         # Standardized multiline input (<x-form.textarea />)
    │   │
    │   └── icons/                         # 31 Standardized SVG Icon Components (<x-icons.* />)
    │       ├── arrow-left.blade.php       ├── clock.blade.php        ├── list-bullet.blade.php
    │       ├── arrow-path.blade.php       ├── dashboard.blade.php    ├── logout.blade.php
    │       ├── bars-3.blade.php           ├── department.blade.php   ├── map-pin.blade.php
    │       ├── category.blade.php         ├── endorsement.blade.php  ├── monitoring.blade.php
    │       ├── check-circle.blade.php     ├── exclamation-triangle...├── pencil.blade.php
    │       ├── chevron-right.blade.php    ├── eye-slash.blade.php    ├── phone.blade.php
    │       ├── eye.blade.php              ├── gear.blade.php         ├── plus.blade.php
    │       ├── search.blade.php           ├── sliders.blade.php      ├── squares-2x2.blade.php
    │       ├── team.blade.php             ├── ticket.blade.php       ├── trash.blade.php
    │       ├── user.blade.php             ├── users.blade.php        ├── x-mark.blade.php
    │       └── information-circle.blade.php
    │
    ├── livewire/                          # REAL-TIME REACTIVE ISLANDS
    │   ├── cwd/
    │   │   ├── dashboard-overview.blade.php # Real-time intake & status telemetry metrics
    │   │   └── ticket-table.blade.php       # Backward-compatible alias
    │   └── shared/
    │       └── ticket-table.blade.php       # Live searchable/filterable ticket queue
    │
    ├── auth/                              # AUTHENTICATION PORTAL
    │   ├── login.blade.php                # Semantic sign-in card with accessible toggle
    │   └── roleSelection.blade.php        # Intermediary multi-role selection screen
    │
    ├── errors/                            # ERROR BOUNDARIES
    │   ├── layout.blade.php               # Accessible error layout shell with smart back button
    │   ├── 403.blade.php                  # Access Forbidden error page
    │   └── 404.blade.php                  # Record / Page Not Found error page
    │
    └── vendor/pagination/                 # ACCESSIBLE PAGINATION TEMPLATES
        ├── simple-tailwind.blade.php      # Previous / Next pagination controls
        └── tailwind.blade.php             # Full numeric pagination styled with .system-pagination
```

---

## 4. Deep Dive: The Styling Engine (`resources/css/`)

The styling architecture uses **Tailwind CSS v4** combined with structured vanilla CSS component layers.

### 4.1 Entrypoint & Loading Hierarchy (`app.css`)
Vite loads only one CSS entrypoint: `resources/css/app.css`. The order of imports inside this file is mission-critical:

```css
/* 1. Core Framework Engine */
@import 'tailwindcss';

/* 2. Third-Party Vendor CSS (Unstyled) */
@import 'tom-select/dist/css/tom-select.default.css';

/* 3. Global Structural Tokens & Layout Variables */
@import './base-theme.css';
@import './layout-shell.css';
@import './workspace-refined.css';
@import './appearance.css';
@import './preferences.css';

/* 4. Modulated Component Layer */
@import './components.css';
@import './dashboard-cards.css';
@import './dashboard-charts.css';
@import './animations.css';

/* 5. Custom Third-Party Overrides (Must load AFTER components to take precedence) */
@import './custom-tom-select.css';

/* 6. Tailwind v4 Scanning Directives */
@source '../views/**/*.blade.php';
@source '../../storage/framework/views/*.php';

/* 7. Design Tokens Declaration */
@theme {
    --font-sans: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
    --color-paleco-950: #062d21;
    --color-paleco-900: #073b2a;
    --color-paleco-700: #08794e;
    --color-paleco-600: #09975d;
    --color-paleco-500: #12ad6b;
}
```

### 4.2 Tokens & Theme System (`base-theme.css`, `layout-shell.css`)
* **`base-theme.css`:** Declares core CSS variables like `--paleco-700` (`#08794e`), `--paleco-900` (`#073b2a`), surface colors, and typography line-heights.
* **`layout-shell.css`:** Declares `.sidebar-surface` (the dark green brand gradient used on navigation sidebars and auth banners) and `.auth-shell` (the subtle radial background on login screens).

### 4.3 Component System (`components.css`)
This is the single source of truth for UI primitives. Never write ad-hoc button or input styles; use these classes:

| Token / Class | Purpose | Modifiers / Sub-classes |
| :--- | :--- | :--- |
| `.btn` | Base button primitive. Sets `inline-flex`, items alignment, font weight, focus ring, transition. | Needs a size modifier and variant modifier. |
| `.btn-primary` | Primary action button. Solid emerald (`var(--paleco-700)`). | Hover darkens to `--paleco-900`. Active scales down slightly. |
| `.btn-secondary` | Neutral action button. White background, slate border (`#cbd5e1`). | Hover lightens to `#f8fafc`. |
| `.btn-danger` | Destructive action button. Solid red (`#e11d48`). | Used for deletions, cancellations, revocations. |
| `.btn-ghost` | Borderless text action button. Transparent background. | Used for "Reset defaults" or secondary text links. |
| `.btn-sm` | Small sizing modifier. `padding: 0.375rem 0.75rem; font-size: 0.75rem; min-height: 2rem;`. | Ideal for table row actions and compact cards. |
| `.btn-md` | Standard sizing modifier. `padding: 0.625rem 1rem; font-size: 0.875rem; min-height: 2.625rem;`. | Standard form submit buttons. |
| `.btn-lg` | Large sizing modifier. `padding: 0.75rem 1.25rem; font-size: 1rem; min-height: 3rem;`. | Hero or portal buttons. |
| `.form-input` | Standardized text, number, and date input. Soft slate border, rounded-lg, emerald focus ring. | Merged automatically by `<x-form.input />`. |
| `.form-label` | Standardized uppercase or sentence-case label. Font weight 600, slate color. | Merged automatically by `<x-form.label />`. |
| `.form-error` | Validation error text. Text-rose-600, font-medium, text-xs. | Merged automatically by `<x-form.error />`. |
| `.panel` | Large operational surface card with rounded-2xl, subtle border, and soft elevation shadow. | Used for dashboards and detailed inspection sections. |
| `.card` | Standard card container with rounded-xl, 1px border, and white fill. | General-purpose wrapper. |

### 4.4 TomSelect Design Unification (`custom-tom-select.css`)
When using TomSelect, native HTML select boxes are wrapped dynamically in `.ts-wrapper`. In older iterations, this created a visual bug with double borders.
* **The Solution in `custom-tom-select.css`:**
  1. `.ts-wrapper` is reset with `border: none !important; padding: 0 !important; background: transparent !important;`.
  2. The inner input container `.ts-control` receives the exact border (`#e2e8f0`), background (`#ffffff`), rounded corners (`0.5rem`), font size, and emerald focus ring of `.form-input`.
  3. The dropdown list `.ts-dropdown` is elevated with a clean shadow, rounded corners, and smooth hover highlighting (`bg-emerald-50 text-emerald-800`).
  4. Full dark mode support is baked in, ensuring dropdown menus transition to dark panels automatically.

### 4.5 Dark Mode & Accessibility Adaptations (`appearance.css`, `preferences.css`)
* **Dark Mode Strategy:** The app uses data-attribute targeting: `html[data-mode="dark"]`. When dark mode is active:
  - Backgrounds switch from `#ffffff` and `#f8fafc` to `var(--night-panel)` and `var(--night-raised)`.
  - Body text switches from `#0f172a` to `var(--night-ink)` (`#e2e8f0`).
  - Muted labels switch from `#64748b` to `var(--night-muted)` (`#94a3b8`).
* **Compact Density Mode:** `html[data-compact="true"]` reduces table row padding and card margins for power users working on lower-resolution monitors.

### 4.6 Dashboards, Charts & Motion
* **`dashboard-cards.css`:** Styles `.metric-card` with flex layouts, data badge slots, and `.metric-icon` containers.
* **`dashboard-charts.css`:** Styles pure CSS column charts and progress tracks for ticket intake without relying on heavy external charting libraries.
* **`animations.css`:** Provides subtle micro-interactions: `.ui-reveal` smoothly fades in elements upon page mount.

---

## 5. Deep Dive: Client-Side Behaviors (`resources/js/`)

All JavaScript in PALECO is modular, vanilla ES6. It does not rely on heavy runtime frameworks (like jQuery, React, or Vue) for page-level scripts.

### 5.1 The `app.js` Orchestrator
`resources/js/app.js` serves as the entrypoint that imports every module. Vite compiles these into a single cached bundle `public/build/assets/app-*.js`.

### 5.2 The Data-Attribute Binding Paradigm
To prevent CSS refactoring from accidentally breaking JavaScript, scripts **never** query styling classes like `document.querySelector('.bg-emerald-50')`. Instead, they query explicit `[data-*]` attributes:

```javascript
// GOOD: Resilient to styling changes
const backButton = document.querySelector('[data-smart-back]');
const controlsDialog = document.querySelector('[data-controls-dialog]');

// FORBIDDEN: Fragile; breaks the moment a designer edits classes
const backButton = document.querySelector('.btn-primary');
```

### 5.3 Livewire Coexistence & Re-initialization Hooking
Because Livewire morphs the DOM dynamically during search debounces or filter changes, event listeners bound only on `DOMContentLoaded` will become detached from newly rendered DOM nodes.

All PALECO scripts follow this double-hooking standard:

```javascript
const initFeature = () => {
    document.querySelectorAll('[data-feature]:not([data-bound])').forEach((el) => {
        el.dataset.bound = 'true'; // Prevent duplicate bindings
        el.addEventListener('click', handleAction);
    });
};

// 1. Initial Page Load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initFeature);
} else {
    initFeature();
}

// 2. Livewire DOM Morph Resumption
document.addEventListener('livewire:navigated', initFeature);
```

### 5.4 Interactive Modules Inventory & Responsibilities

| Module File | Target Data Attribute / Trigger | Functional Responsibility |
| :--- | :--- | :--- |
| `preferences.js` | `[data-theme-choice]`, `[data-mode-toggle]` | Reads and writes user display preferences (theme tint, dark mode, compact mode) to `localStorage` and applies `data-mode` / `data-compact` attributes to `<html>`. |
| `sidebar-nav.js` | `[data-mobile-menu-trigger]`, `[data-mobile-drawer]` | Handles opening, closing, backdrop clicking, and keyboard `Escape` closing for the responsive mobile navigation drawer. |
| `smart-navigation.js` | `[data-smart-back]` | Evaluates `window.history.length`. If the user navigated from within the app, executes `window.history.back()`; otherwise, redirects to `data-fallback-url`. |
| `history-tabs.js` | `[data-tab-target]`, `[data-tab-panel]` | Controls tab switching in [ticketDetails.blade.php](file:///c:/Users/allen/Desktop/Capstone/-NEW-CRM-PALECO/PALECO_OTRS-CRM/resources/views/shared/pages/ticketDetails.blade.php), managing `aria-selected="true|false"` and showing the active panel. |
| `dashboard-controls.js` | `[data-dashboard-controls]`, `[data-controls-dialog]` | Manages user customization of dashboard quick-action shortcuts via native `<dialog>`, persisting chosen shortcuts in `localStorage`. |
| `lightbox.js` | `[data-lightbox-trigger]`, `[data-lightbox-dialog]` | Opens high-resolution field accomplishment photos inside an accessible native `<dialog>` modal with backdrop blur. |
| `preventDoubleSubmit.js`| `form:not([data-allow-double-submit])` | Intercepts form submissions, disables submit buttons, and updates their text to `Processing...` (or `data-loading-text`) to eliminate race conditions. |
| `togglePassword.js` | `[data-password-toggle]` / `#toggle-password-btn` | Toggles password field types between `password` and `text`, updating `aria-pressed`, `aria-label`, and eye icon visibility. |
| `toggleCardTable.js` | `[data-view-toggle]` | Toggles directory views between tabular rows and responsive card grids on user and category management pages. |
| `table-wrapper.js` | `[data-table-scroll]` | Monitors horizontal scroll position of data tables and toggles visual scroll hints for users on smaller screens. |
| `ticket-form.js` | `#category_id`, `#department_id` | Manages cascading category and department selection and auto-fills ticket routing rules on ticket intake forms. |
| `tomselect-filters.js` | `select[data-tomselect-filter]` | Safely initializes TomSelect on Livewire queue filter selects, re-binding cleanly after Livewire updates. |
| `echo.js` | `window.Echo` | Connects to Laravel Reverb WebSockets for real-time ticket desk broadcasting. |

---

## 6. Deep Dive: Views & Templating Hierarchy (`resources/views/`)

### 6.1 Layout Shells
The application features three master layout shells:
1. **`admin/base/base.blade.php`:** Master shell for the Administrator portal. Uses dark emerald brand navigation, admin sidebar routes, and administrative search bars.
2. **`cwd/base/base.blade.php`:** Master shell for the Consumer Welfare Desk portal. Uses operational sidebar routes (Tickets, Endorsements, Consumers).
3. **`errors/layout.blade.php`:** Standalone shell for HTTP error boundary pages (403, 404).

Both `admin/base/base` and `cwd/base/base` share the exact same structural skeleton:
* **Skip-to-Content Link:** `<a href="#main-content" class="skip-link">Skip to main content</a>` (accessible keyboard navigation requirement).
* **Mobile App Bar:** Visible only on screens `< 1024px`, contains hamburger button and PALECO logo.
* **Slide-Over Mobile Drawer:** Accessible modal drawer triggered by hamburger button.
* **Desktop Sidebar:** Sticky `<aside>` navigation bar containing branding, navigation links, and `<x-sidebar-user-card>`.
* **Global Alerts:** Top of `<main>` contains `<x-alert />` to automatically catch all session flash messages.

### 6.2 Shared Operational Surfaces
Certain complex workflows are identical between Admin and CWD officers, with minor differences in permissions. Instead of duplicating hundreds of lines of code, these live in `resources/views/shared/pages/`:

#### `shared/pages/ticketDetails.blade.php` (Master Inspection Surface)
This page is the central hub of the ticketing system. It displays:
* **Ticket Header:** Ticket number, lifecycle status badge, child ticket pill, read-only indicator (for Admins), and category metadata.
* **Modular Partials (`resources/views/shared/pages/partials/`):**
  1. `ticket-consumer-card.blade.php`: Verified CRM account match details rendered in an accessible `<dl>` list.
  2. `ticket-assigned-personnel.blade.php`: Supervisors and field crew assignments with avatar initials.
  3. `ticket-hierarchy.blade.php`: Visual parent-child relationship tree.
  4. `ticket-remarks-thread.blade.php`: Operational communication thread with internal memo toggle.
* **Tabbed History Section:** Accessible `<section>` with tab triggers for Status Changes, Team Assignments, Endorsement Requests, Field Accomplishments, and Remarks.

### 6.3 Domain Pages: Admin vs CWD
* **`admin/pages/`:** Focused on management registries:
  - `dashboard.blade.php`: 6 telemetry metric cards, 7-day trend graph, customizable shortcut controls.
  - `userManagement.blade.php`: Account directory with table/card view toggler and role filters.
  - `departmentManagement.blade.php` & `teamManagement.blade.php`: Organizational structure.
  - `activityLogDetails.blade.php`: Deep inspection of Spatie audit trails.
* **`cwd/pages/`:** Focused on operational dispatch:
  - `dashboard.blade.php`: Live intake overview island, queue status counts, quick action shortcuts.
  - `ticketManagement.blade.php`: Embeds the reactive `<livewire:shared.ticket-table />`.
  - `consumerManagement.blade.php` & `consumerDetails.blade.php`: Billing account linkages.
  - `endorsementDashboard.blade.php`: Inter-departmental rerouting reviews.

### 6.4 Livewire Reactive Islands (`resources/views/livewire/`)
* **`cwd/dashboard-overview.blade.php`:** A real-time telemetry island. Renders intake metric cards with `<data>` tags, a pure CSS 7-day intake column chart, and a lifecycle distribution bar.
* **`shared/ticket-table.blade.php`:** The core operational ticket table. Contains a `<nav class="record-tabs">` status switcher, a `<search>` toolbar with debounced text search, category/department filters, a responsive table container, and custom pagination.

### 6.5 Component Library (`resources/views/components/`)
* **`<x-alert />`:** Dual-mode notification component:
  - Mode 1 (Prop-driven): `<x-alert type="warning" message="Warning text" />`
  - Mode 2 (Automatic flash): Placed as `<x-alert />` in layouts, automatically parses `session('success')`, `session('error')`, `session('warning')`, `session('info')`, and `$errors->all()`.
* **`<x-confirm-card />`:** Renders an accessible destructive confirmation card with `role="alertdialog"`, consequence bullet points, and double-submit protection.
* **`<x-form.input />`:** Renders `<input>` bound to `.form-input`, handles `value="{{ old($name) }}"`, and adds `aria-invalid="true"` and `aria-describedby="error-{$name}"` if validation errors exist.
* **`<x-form.label />`:** Renders `<label class="form-label">`, adding an asterisk `<span class="text-rose-500">*</span>` if `required="true"`.
* **`<x-form.error />`:** Renders `<p class="form-error" role="alert">` displaying `$message` for the designated field name.

### 6.6 Icon Library (`resources/views/components/icons/`)
Every icon is an individual Blade component accepting standard attributes:
```blade
<x-icons.ticket class="w-5 h-5 text-emerald-600" />
<x-icons.arrow-left size="w-4 h-4" stroke="2" />
```
All icons automatically merge `aria-hidden="true"` and `shrink-0`, ensuring screen readers are not cluttered with decorative SVGs.

### 6.7 Auth & Error Boundaries (`resources/views/auth/`, `resources/views/errors/`)
* **`auth/login.blade.php`:** Semantic `<main>` container with brand sidebar (`<aside>`), `.form-input` styling, `.btn-primary` submit button, and accessible password reveal toggle.
* **`auth/roleSelection.blade.php`:** Portal selection view for users choosing between Administrator and CWD Officer portals.
* **`errors/layout.blade.php`:** Clean, branded error page with `<main>` container, contextual badge slot, and intelligent back-navigation button (`data-smart-back`).
* **`errors/403.blade.php` & `errors/404.blade.php`:** 403 Access Forbidden and 404 Not Found implementations extending `errors.layout`.

---

## 7. Developer Structural Preferences & Strict Engineering Rules

When editing or extending files in `resources/`, you **must** adhere to these 7 engineering rules:

### Rule 1: Zero Raw Inline SVGs
* **FORBIDDEN:** Pasting raw `<svg viewBox="...">...</svg>` code directly into page or form views.
* **MANDATORY:** Check `resources/views/components/icons/`. If the icon exists, use `<x-icons.icon-name />`. If it does not exist, create a new icon component following [Recipe 3](#recipe-3-adding-an-svg-icon-to-the-system).

### Rule 2: Single Source of Truth for Styling (Design Tokens)
* **FORBIDDEN:** Arbitrary Tailwind hex classes like `class="bg-[#10a352] text-[#33483e]"`.
* **MANDATORY:** Use design tokens: `.btn .btn-primary`, `.form-input`, `bg-paleco-700`, `text-slate-800`.

### Rule 3: Decouple JavaScript from Styling
* **FORBIDDEN:** Querying DOM elements by visual classes (e.g. `document.querySelector('.btn-primary')`).
* **MANDATORY:** Add a semantic data attribute (e.g. `document.querySelector('[data-smart-back]')` or `document.querySelector('[data-controls-open]')`).

### Rule 4: Mandatory Semantic HTML & Landmarks
* **FORBIDDEN:** Nesting multiple levels of unsemantic `<div>` containers.
* **MANDATORY:**
  - Page wrapper: `<main>`
  - Sidebar / Drawer / Metadata card: `<aside>`
  - Search / Filter bar: `<search role="search">`
  - Key-value metadata: `<dl>`, `<dt>`, `<dd>`
  - Charts / Visual diagrams: `<figure>` and `<figcaption>`
  - Tables: Must include `<caption>` (or `<caption class="sr-only">`) and `<th scope="col">`.

### Rule 5: Strict Form Accessibility
Every form field **must** have:
1. A corresponding `<x-form.label for="...">`
2. An `<x-form.input name="..." id="...">`
3. An `<x-form.error name="..." />`
This guarantees automated `aria-describedby` binding for screen readers.

### Rule 6: Policy-Driven UI Rendering
* **FORBIDDEN:** Checking raw role names like `@if(auth()->user()->role->slug === 'admin')` inside feature buttons.
* **MANDATORY:** Use Laravel Policy checks: `@can('createRemark', $ticket)` or `@can('createChild', $ticket)`.

### Rule 7: Prevent Double-Submission on All Forms
All standard mutation forms automatically inherit double-submit protection via `preventDoubleSubmit.js`. Ensure submit buttons declare `data-loading-text="Saving..."` for immediate user feedback.

---

## 8. Developer Playbook: How to Build, Extend & Maintain

### Recipe 1: Creating a Brand-New Page
To add a new page (e.g. `/cwd/reports`):

1. **Create the Blade View:** `resources/views/cwd/pages/reports.blade.php`
2. **Extend the Base Shell:**
   ```blade
   @extends('cwd.base.base')

   @section('workspace-kind', 'registry')
   @section('title', 'Service Reports')

   @section('content')
       <div class="max-w-7xl mx-auto space-y-6">
           <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
               <div>
                   <p class="eyebrow">Analytics & Telemetry</p>
                   <h1 class="page-title mt-1">Service Reports</h1>
                   <p class="text-sm text-slate-500 mt-1">Monthly breakdown of resolved complaints and field dispatches.</p>
               </div>
               <a href="{{ route('cwd.reports.export') }}" class="btn btn-primary btn-md gap-2">
                   <x-icons.arrow-path class="w-4 h-4" />
                   <span>Export Report</span>
               </a>
           </header>

           <main id="main-content" class="panel p-6">
               {{-- Page Content Here --}}
           </main>
       </div>
   @endsection
   ```
3. **Register the Navigation Link:** In `resources/views/cwd/base/base.blade.php`, add the link to the `<nav>` sidebar list.

---

### Recipe 2: Creating a Reusable Blade Component
To create a reusable status badge component:

1. **Create the File:** `resources/views/components/status-badge.blade.php`
2. **Define Props and Classes:**
   ```blade
   @props([
       'status',
       'size' => 'md',
   ])

   @php
       $badgeClass = match($status) {
           'open' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
           'in_progress' => 'bg-blue-50 text-blue-700 border-blue-200',
           'closed' => 'bg-slate-100 text-slate-700 border-slate-200',
           default => 'bg-slate-50 text-slate-600 border-slate-200',
       };

       $sizeClass = match($size) {
           'sm' => 'px-2 py-0.5 text-[10px]',
           default => 'px-2.5 py-1 text-xs',
       };
   @endphp

   <span {{ $attributes->merge(['class' => "inline-flex items-center font-bold rounded-full border shadow-2xs uppercase tracking-wider $badgeClass $sizeClass"]) }}>
       {{ $slot }}
   </span>
   ```
3. **Usage Anywhere in Views:**
   ```blade
   <x-status-badge status="open">Active</x-status-badge>
   ```

---

### Recipe 3: Adding an SVG Icon to the System
To add a new icon (e.g. `download`):

1. **Create the File:** `resources/views/components/icons/download.blade.php`
2. **Implement the SVG Wrapper:**
   ```blade
   @props(['size' => 'w-5 h-5', 'stroke' => '1.8'])

   <svg {{ $attributes->merge(['class' => "$size shrink-0"]) }} fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
       <path stroke-linecap="round" stroke-linejoin="round" stroke-width="{{ $stroke }}" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
   </svg>
   ```
3. **Usage:** `<x-icons.download class="w-4 h-4 text-emerald-600" />`

---

### Recipe 4: Building a Form with Standard Controls & TomSelect
To build an accessible form with a searchable TomSelect dropdown:

```blade
<form action="{{ route('cwd.tickets.store') }}" method="POST" class="space-y-6">
    @csrf

    <div>
        <x-form.label for="subject" required>Subject</x-form.label>
        <x-form.input name="subject" id="subject" placeholder="Brief summary of concern" required />
        <x-form.error name="subject" />
    </div>

    <div>
        <x-form.label for="category_id" required>Category</x-form.label>
        <select name="category_id" id="category_id" data-tomselect class="form-input w-full" required>
            <option value="">Select a complaint category...</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}">{{ $category->category_name }}</option>
            @endforeach
        </select>
        <x-form.error name="category_id" />
    </div>

    <div>
        <x-form.label for="description">Detailed Description</x-form.label>
        <x-form.textarea name="description" id="description" rows="4" placeholder="Full details..." />
        <x-form.error name="description" />
    </div>

    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
        <a href="{{ route('cwd.tickets') }}" class="btn btn-secondary btn-md">Cancel</a>
        <button type="submit" data-loading-text="Submitting ticket..." class="btn btn-primary btn-md shadow-xs gap-2">
            <x-icons.check-circle class="w-4 h-4" />
            <span>Create Ticket</span>
        </button>
    </div>
</form>
```

---

### Recipe 5: Adding a Client-Side Interactive Script
To add a new interactive feature (e.g., auto-formatting meter serial numbers):

1. **Create the Script:** `resources/js/meter-formatter.js`
   ```javascript
   const initMeterFormatter = () => {
       document.querySelectorAll('input[data-meter-format]:not([data-bound])').forEach((input) => {
           input.dataset.bound = 'true';
           input.addEventListener('input', (e) => {
               let val = e.target.value.replace(/[^0-9]/g, '');
               if (val.length > 8) val = val.substring(0, 8);
               e.target.value = val;
           });
       });
   };

   if (document.readyState === 'loading') {
       document.addEventListener('DOMContentLoaded', initMeterFormatter);
   } else {
       initMeterFormatter();
   }

   document.addEventListener('livewire:navigated', initMeterFormatter);
   ```
2. **Register in `resources/js/app.js`:**
   ```javascript
   import './meter-formatter';
   ```

---

### Recipe 6: Building a Livewire Table / Reactive Island
To build a Livewire table:

1. **Create the Livewire View:** `resources/views/livewire/cwd/consumer-table.blade.php`
2. **Structure with Semantic Search & Table:**
   ```blade
   <div>
       <search class="filter-toolbar mb-6" aria-label="Consumer search">
           <div class="relative w-full">
               <x-icons.search class="absolute left-3 top-3 h-5 w-5 text-slate-400 pointer-events-none" />
               <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by name, account number, or address..." class="form-input w-full pl-10" />
           </div>
       </search>

       <div class="panel overflow-hidden relative">
           <div wire:loading.flex class="hidden absolute inset-0 bg-white/50 z-10 items-center justify-center backdrop-blur-xs">
               <span class="text-xs font-bold text-emerald-700">Loading records...</span>
           </div>

           <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Consumer table">
               <table class="system-table w-full">
                   <caption class="sr-only">Registered consumer accounts</caption>
                   <thead>
                       <tr>
                           <th scope="col">Account Code</th>
                           <th scope="col">Account Name</th>
                           <th scope="col">Status</th>
                           <th scope="col" class="text-right">Actions</th>
                       </tr>
                   </thead>
                   <tbody>
                       @forelse($consumers as $consumer)
                           <tr>
                               <td class="font-mono text-xs font-semibold">{{ $consumer->acct_code }}</td>
                               <td class="font-bold text-slate-800">{{ $consumer->name }}</td>
                               <td>
                                   <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-bold {{ $consumer->status === 'A' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                                       {{ $consumer->status === 'A' ? 'Active' : 'Inactive' }}
                                   </span>
                               </td>
                               <td class="text-right">
                                   <a href="{{ route('cwd.consumers.show', $consumer) }}" class="btn btn-secondary btn-sm">Inspect</a>
                               </td>
                           </tr>
                       @empty
                           <tr>
                               <td colspan="4" class="text-center py-8 text-slate-400">No matching consumer records found.</td>
                           </tr>
                       @endforelse
                   </tbody>
               </table>
           </div>
           
           <div class="p-4 border-t border-slate-100">
               {{ $consumers->links('vendor.pagination.tailwind') }}
           </div>
       </div>
   </div>
   ```

---

### Recipe 7: Compiling, Debugging & Automated Testing

Whenever you modify any CSS, JavaScript, or Blade templates:

```bash
# 1. Navigate to the primary application directory
cd PALECO_OTRS-CRM

# 2. Run the real-time development compiler (leave running in a separate terminal during coding)
npm run dev

# 3. Verify production compilation (ensures zero syntax errors in CSS and JS bundles)
npm run build

# 4. Run the full automated test suite (Pest) to ensure zero regressions
vendor/bin/pest
```

---

## 9. Quick-Reference Cheat Sheet

### Essential Component Snippets

| Component | Example Blade Call | Output |
| :--- | :--- | :--- |
| **Alert (Flash)** | `<x-alert />` | Automatically renders session flash messages (`success`, `error`). |
| **Alert (Explicit)** | `<x-alert type="warning" message="Take caution." />` | Contextual alert box with warning icon and amber fill. |
| **Form Input** | `<x-form.input name="email" type="email" required />` | Standard text input with validation error states and ARIA attributes. |
| **Form Label** | `<x-form.label for="email" required>Email</x-form.label>` | Accessible `<label>` with red asterisk. |
| **Form Error** | `<x-form.error name="email" />` | Accessible `<p class="form-error" role="alert">`. |
| **Action Link** | `<a href="..." class="btn btn-primary btn-md">Click</a>` | Standard emerald action button. |
| **Secondary Button**| `<button class="btn btn-secondary btn-sm">Cancel</button>` | Clean slate outlined button. |
| **Icon Component** | `<x-icons.ticket class="w-5 h-5 text-emerald-600" />` | Accessible vector icon. |
| **Confirm Dialog** | `<x-confirm-card title="Delete" action="..." />` | Destructive modal with consequence bullets. |

### Utility Classes & Tokens Reference

* **Main Headings:** `text-2xl font-bold text-slate-900 tracking-tight font-sans`
* **Section Eyebrow:** `eyebrow text-xs font-bold text-emerald-700 uppercase tracking-wider`
* **Card Container:** `bg-white border border-slate-200 rounded-xl shadow-xs p-6`
* **Status Badges:** `inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider border shadow-2xs`
* **Key-Value Grid:** `<dl class="grid grid-cols-1 md:grid-cols-3 gap-6">`
* **Definition Term (Label):** `<dt class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">`
* **Definition Description (Value):** `<dd class="text-sm font-medium text-slate-800">`

