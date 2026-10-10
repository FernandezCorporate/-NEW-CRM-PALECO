# PALECO OTRS-CRM: Teammate Git Pull Routine & Frontend Troubleshooting Guide

This guide is the official operational standard for all developers and capstone collaborators working on the **PALECO OTRS-CRM** repository. It details the mandatory synchronization sequence when pulling branch updates, explains the multi-process orchestration powered by `composer run dev`, and dissects how to permanently prevent client-side JavaScript and HTML5 validation deadlocks.

---

## 1. Executive Summary: Why a Simple `git pull` is Not Enough

In a modern full-stack Laravel 13 application utilizing **Tailwind CSS v4**, **Vite**, **Laravel Reverb (WebSockets)**, **TomSelect**, and a **Domain-Driven Architecture**, a `git pull` touches multiple system layers:

1. **PHP Classmap & Autoloader:** Recent architectural refactoring organized Form Requests and Services into domain-based directories (`app/Http/Requests/*` and `app/Services/*`). If Composer's PSR-4 classmap is not refreshed on a teammate's machine, PHP will throw fatal `Class "App\Services\..." not found` errors.
2. **Database Schema:** New performance indexes and tables (e.g., composite indexes on tickets) are introduced via migrations.
3. **Frontend Compilation (Vite):** Compiled assets (`public/build`) are deliberately **gitignored** to prevent binary commit bloat and merge collisions. Raw source scripts in `resources/js/` (such as `ticket-form.js`, `teamInlines.js`, and `tomSelect-input_with_autoSuggest.js`) **must be compiled locally in real time**.
4. **Real-Time Daemons (Reverb & Queue):** Desk ticket synchronization relies on active background WebSocket and queue listeners.

Running only `php artisan serve` bypasses the asset compiler and WebSockets, rendering the frontend interactive layers completely inactive.

---

## 2. The Golden Teammate `git pull` Routine

Whenever you pull changes from `origin/master` (or any feature branch), execute this exact 7-step sequence:

```bash
# ==============================================================================
# STEP 1: Pull the latest commits from the remote repository
# ==============================================================================
git pull origin master

# ==============================================================================
# STEP 2: Navigate into the primary Laravel application directory
# (CRITICAL: All Artisan, Composer, and NPM commands MUST run inside this folder!)
# ==============================================================================
cd PALECO_OTRS-CRM

# ==============================================================================
# STEP 3: Refresh PHP Dependencies & PSR-4 Autoload Classmap
# ==============================================================================
composer install
composer dump-autoload

# ==============================================================================
# STEP 4: Install/Update Node Dependencies
# ==============================================================================
npm install

# ==============================================================================
# STEP 5: Apply Any New Database Migrations
# ==============================================================================
php artisan migrate

# ==============================================================================
# STEP 6: Flush Stale Blade Views, Config, and Route Caches
# ==============================================================================
php artisan optimize:clear

# ==============================================================================
# STEP 7: Launch the Full-Stack Multi-Process Orchestrator
# ==============================================================================
composer run dev
```

### Quick One-Liner (PowerShell / Windows Terminal):
For daily development, copy and paste this single chained command:
```powershell
git pull ; cd PALECO_OTRS-CRM ; composer dump-autoload ; php artisan migrate ; php artisan optimize:clear ; composer run dev
```

---

## 3. Mandatory One-Time Reverb WebSockets Setup (If Missing in Your `.env`)

> ⚠️ **COMMON MISCONCEPTION:** `composer install` will **NOT** create Reverb environment variables. `composer install` only downloads PHP package binaries into `vendor/`. It never modifies your local `.env`.

If your `.env` does not contain `REVERB_APP_KEY` or has `BROADCAST_CONNECTION=log`, you have two straightforward ways to configure it:

### Option A: Automated via Laravel Artisan (Recommended)
Run this command inside `PALECO_OTRS-CRM`:
```bash
php artisan reverb:install
```
1. It automatically generates unique keys (`REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`).
2. It appends the Reverb environment block and `VITE_REVERB_*` bindings directly into your `.env`.
3. It switches `BROADCAST_CONNECTION=reverb`.
4. *Prompt Answers:* Choose **`no`** for installing Node dependencies and **`no`** for publishing configuration (both are already bundled in the repo).

### Option B: Copy From `.env.example`
Open `.env.example` and copy the Reverb block directly to the bottom of your `.env`:
```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=867789
REVERB_APP_KEY=8ygjucohu35acd17ckbm
REVERB_APP_SECRET=vl2uc2v7k9sa6oyiipip
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

> 🔄 **After adding Reverb variables:** Always run `php artisan optimize:clear` and restart `composer run dev` so Vite recompiles the new `VITE_REVERB_*` bindings into your browser scripts.

---

## 4. Deep Dive: What `composer run dev` Actually Does

In `PALECO_OTRS-CRM/composer.json`, our custom `dev` script is defined as:

```json
"scripts": {
    "dev": [
        "Composer\\Config::disableProcessTimeout",
        "npx concurrently -c \"#93c5fd,#c4b5fd,#fdba74,#f472b6\" \"php artisan serve\" \"php artisan queue:listen --tries=1\" \"npm run dev\" \"php artisan reverb:start\" --names='server,queue,vite,reverb' --kill-others"
    ]
}
```

This single command utilizes `npx concurrently` to spawn and monitor **4 distinct, color-coded processes** in a unified terminal window:

```
┌──────────────────────────────────────────────────────────────────────────────────┐
│                             COMPOSER RUN DEV RUNNER                              │
├───────────┬─────────────┬─────────────────────────────────┬──────────────────────┤
│ Name      │ Color       │ Command Executed                │ Operational Role     │
├───────────┼─────────────┼─────────────────────────────────┼──────────────────────┤
│ [server]  │ 🟦 Light Blue│ php artisan serve               │ HTTP Web Server      │
│           │             │                                 │ (http://127.0.0.1:8000)│
├───────────┼─────────────┼─────────────────────────────────┼──────────────────────┤
│ [queue]   │ 🟪 Lavender │ php artisan queue:listen --tries=1│ Asynchronous worker│
│           │             │                                 │ for jobs & events    │
├───────────┼─────────────┼─────────────────────────────────┼──────────────────────┤
│ [vite]    │ 🟧 Orange   │ npm run dev                     │ Vite Live HMR Server │
│           │             │                                 │ (http://127.0.0.1:5173)│
├───────────┼─────────────┼─────────────────────────────────┼──────────────────────┤
│ [reverb]  │ 🟥 Pink     │ php artisan reverb:start        │ WebSocket Server     │
│           │             │                                 │ (ws://127.0.0.1:8080) │
└───────────┴─────────────┴─────────────────────────────────┴──────────────────────┘
```

> 💡 **Graceful Shutdown:** Pressing `Ctrl + C` once in the terminal triggers `--kill-others`, terminating all 4 processes cleanly without leaving orphaned PHP or Node background tasks.

---

## 4. Why Running Only `php artisan serve` Breaks the System

If a developer runs **only** `php artisan serve`:

```
❌ php artisan serve (ALONE)
    ├── ❌ NO Vite Server        -> Zero JavaScript & Tailwind bundled!
    ├── ❌ NO Reverb WebSockets   -> Real-time ticket dispatching disconnected!
    └── ❌ NO Queue Worker       -> Queued jobs stuck in database!
```

Our application bundle in `resources/js/app.js` aggregates **19 interactive JavaScript modules**:
1. `echo.js` — Laravel Echo WebSocket connection for real-time ticket alerts
2. `ticket-form.js` — Unlisted/custom category toggle logic
3. `tomSelect-input_with_autoSuggest.js` — Searchable dropdown instantiation for `.tom-select-sync`
4. `teamInlines.js` — Dynamic crew roster member addition & row cloning
5. `consumerLinkToggle.js` — Billing account lookup toggle
6. `disableDeptForFieldPerson.js` — Contextual department lockdown
7. `preventDoubleSubmit.js` — Anti-double click protection
8. `togglePassword.js` — Form password reveal/hide toggles
9. `lightbox.js` — Fullscreen modal for accomplishment photo evidence
10. `toggleCardTable.js`, `preferences.js`, `sidebar-nav.js`, `history-tabs.js`, `dashboard-controls.js`, `table-wrapper.js`, `filter-aria.js`, `title-aria.js`, `system-fields.js`, `livewire-animations.js`, `tomselect-filters.js`.

**Without Vite running, every single one of these 19 modules fails to load.**

---

## 5. Case Studies: The Frontend Bugs & How They Are Resolved

### Case Study 1: The "Custom Category Disabled" Bug
* **Observed Symptom:** On the ticket creation form (`ticketForm.blade.php`), clicking the checkbox *"Request Unlisted / Custom Category"* did nothing; the `other_category_name` input remained grayed out and disabled.
* **Root Cause:** In `resources/views/cwd/forms/ticketForm.blade.php`, the text input starts with the native HTML `disabled` attribute:
  ```html
  <input type="text" name="other_category_name" id="other_category_name" disabled ...>
  ```
  Un-disabling this input is 100% managed by `resources/js/ticket-form.js`:
  ```javascript
  otherCheckbox.addEventListener('change', () => {
      if (otherCheckbox.checked) {
          customInput.disabled = false;
          // ...
      }
  });
  ```
  Because the groupmate only ran `php artisan serve` without `npm run dev` (or `npm run build`), `ticket-form.js` never executed. The field was stuck in its initial disabled state.
* **Permanent Prevention:** Always run `composer run dev`. If working in an environment where running Vite dev server is inconvenient, run `npm run build` once to create static production assets in `public/build`.

---

### Case Study 2: The "Save Team Button Not Clickable" Deadlock
* **Observed Symptom:** On the team creation form (`teamForm.blade.php`), filling in details and clicking the *"Save Team"* submit button yielded no response. The button seemed dead/unclickable.
* **Root Cause (The HTML5 Validation Trap):**
  In `teamForm.blade.php`, the department select element had:
  ```html
  <select name="department_id" class="tom-select-sync hidden" ... required>
  ```
  1. The select has `required` (mandatory HTML5 validation).
  2. The select also had the Tailwind utility class `hidden` (`display: none;`).
  3. When the user clicks `<button type="submit">`, the browser intercepts the form submit to perform native validation.
  4. Finding the empty select, the browser attempts to display a validation bubble on it.
  5. Because an element with `display: none` **cannot receive focus**, modern browsers (Chrome, Edge, Firefox) halt form submission and emit this error to the F12 console:
     ```text
     An invalid form control with name='department_id' is not focusable.
     ```
  6. The browser cancels the form submission entirely without showing any user alert.
* **The Permanent Code Fix:**  
  The hardcoded `hidden` class was removed from `teamForm.blade.php`. The select is now visible and focusable by default, and styled appropriately:
  ```html
  <select name="department_id" class="tom-select-sync w-full border rounded-lg text-sm ..." required>
  ```
  TomSelect handles hiding the native select dynamically when JavaScript mounts; if JavaScript is slow or absent, the native element remains focusable and does not deadlock the browser.
* **Design Rule for Developers:**  
  > ⚠️ **NEVER pair `hidden` with `required` on any form input.** If an element is `hidden`, the browser cannot focus it to show HTML5 validation warnings, which permanently breaks the submit button.

---

## 6. Alternative Development Modes

### Mode A: Production-Style Static Build (No Vite Dev Server)
If a teammate prefers running only the backend server and does not want the Vite live-reloader running in the background:

```bash
cd PALECO_OTRS-CRM

# 1. Compile assets statically once
npm run build

# 2. Run the application
php artisan serve
```
*(Note: If you edit any `.js`, `.css`, or Blade files with new Tailwind classes, you must re-run `npm run build` to see the changes).*

---

### Mode B: Mobile API Testing (Physical Phones & LAN)
If a teammate is testing the mobile application from a physical Android/iOS phone or a separate machine on the local Wi-Fi:

1. Identify your local IPv4 address:
   ```powershell
   ipconfig
   # Example: 192.168.1.25
   ```
2. Bind the server to your network adapter:
   ```bash
   php artisan serve --host=0.0.0.0 --port=8000
   ```
3. Update the mobile app base URL to:
   ```text
   http://192.168.1.25:8000/api
   ```

---

## 7. Developer Diagnostic Matrix (Quick Troubleshooting)

| Symptom / Error Message | Root Cause | Immediate Fix |
| :--- | :--- | :--- |
| **`Class "App\Services\..." not found`** | Local Composer classmap is out of date after file reorganization. | Run `composer dump-autoload` inside `PALECO_OTRS-CRM`. |
| **`Vite manifest not found at: public/build/manifest.json`** | Neither `npm run dev` is active nor `npm run build` was run. | Run `composer run dev` or execute `npm run build`. |
| **`An invalid form control with name='...' is not focusable.`** | A form input marked `required` is hidden via `display: none` / `hidden`. | Remove the `hidden` class from the `<select>` or `<input>`. |
| **Custom category / consumer link checkbox doesn't toggle** | Vite client bundle is missing; `ticket-form.js` did not execute. | Ensure `composer run dev` is running (orange `[vite]` thread active). |
| **TomSelect dropdowns display as standard HTML selects** | TomSelect script failed to initialize or assets uncompiled. | Run `npm install` and start `composer run dev`. |
| **Save / Submit button does nothing when clicked** | Native HTML5 validation failed on an invisible field or form has JS syntax error. | Press `F12` -> Console. Look for "not focusable" or form validation errors. |
| **Real-time table does not update on new tickets** | Laravel Reverb WebSocket server is offline. | Run `composer run dev` (verify pink `[reverb]` thread is running on port 8080). |
| **Old Blade styling or outdated routes persist** | Cached views and route tables. | Run `php artisan optimize:clear`. |

---

*Authored for the PALECO Capstone Engineering Team. Always consult this document before reporting frontend interface regressions after a pull.*

