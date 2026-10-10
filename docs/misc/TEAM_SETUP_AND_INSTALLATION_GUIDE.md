# PALECO OTRS-CRM: Official Teammate Setup Guide

This guide is for collaborators and teammates working on the **PALECO OTRS-CRM** repository. It covers updating existing branches, manual setup of database migrations and seeders, manual setup and debugging of Laravel Reverb WebSockets, environment configuration, and running the development environment.

---

## 📌 Critical Rule: Working Directory

Always open your terminal inside the **`PALECO_OTRS-CRM`** subfolder before running any commands:

```bash
cd PALECO_OTRS-CRM
```

> **Never run `composer`, `npm`, or `php artisan` from the parent root folder.**

---

## 🚀 Routine A: Updating Existing Setup (`git pull`)

If you already have the project working and just pulled the latest commits from GitHub:

### Step 1: Pull the Latest Code
```bash
git pull origin master
```
*(Replace `master` with your working branch name if different).*

### Step 2: Navigate to Application Directory
```bash
cd PALECO_OTRS-CRM
```

### Step 3: Install Any New PHP & Node Dependencies
```bash
composer install
composer dump-autoload
npm install
```

### Step 4: Verify Your `.env` File (Reverb & PALECO API)
> ⚠️ **Important:** Running `composer install` downloads packages into `vendor/` and **never** creates or modifies your `.env` file. You must ensure the Reverb WebSocket variables and PALECO API key are present in your `.env`.

Open your `.env` file and confirm the following entries exist:

```env
# Broadcast Driver
BROADCAST_CONNECTION=reverb

# PALECO Billing API Key
PALECO_API_KEY=your_paleco_api_key_here

# Laravel Reverb Server Configuration
REVERB_APP_ID=867789
REVERB_APP_KEY=8ygjucohu35acd17ckbm
REVERB_APP_SECRET=vl2uc2v7k9sa6oyiipip
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http

# Vite Client Reverb Configuration
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

*(If you previously had `BROADCAST_CONNECTION=log`, change it to `BROADCAST_CONNECTION=reverb`).*

### Step 5: Run Database Migrations & Clear Cache
```bash
php artisan migrate
php artisan optimize:clear
```

### Step 6: Start the Development Server
```bash
composer run dev
```

---

## 💻 Routine B: First-Time Clone / Fresh Machine Setup

If you are setting up the project on a new laptop or machine for the first time:

### Step 1: Clone the Repository & Enter Folder
```bash
git clone <repository_url>
cd -NEW-CRM-PALECO/PALECO_OTRS-CRM
```

### Step 2: Install Dependencies
```bash
composer install
npm install
```

### Step 3: Create `.env` and Generate Application Key
```bash
# Windows PowerShell:
Copy-Item .env.example .env

# Git Bash / CMD:
cp .env.example .env

php artisan key:generate
```

### Step 4: Configure Database in `.env`
Create a database in your local MySQL (via phpMyAdmin, Laragon, or CLI):
```sql
CREATE DATABASE paleco_otrs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Update your `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=paleco_otrs
DB_USERNAME=root
DB_PASSWORD="your_password"
```

### Step 5: Run Migrations and Seed Initial Accounts
```bash
php artisan migrate:fresh --seed
```

### Step 6: Create Storage Symlink
Allows ticket accomplishment photos and signatures to display properly:
```bash
php artisan storage:link
```

### Step 7: Launch Development Environment
```bash
composer run dev
```

---

## 🗄️ Manual Database Migrations & Seeding Guide

Use these commands when you need granular control over your local database state:

### 1. Checking Current Migration Status
To see which migrations have been executed and which are pending:
```bash
php artisan migrate:status
```

### 2. Running Incremental Migrations (Preserve Data)
When teammates add new tables or columns and you do **not** want to wipe your local test tickets:
```bash
php artisan migrate
```

### 3. Fresh Reset & Full Seeding (Clean Slate)
Wipes all tables, re-runs all migrations from scratch, and populates baseline test data:
```bash
php artisan migrate:fresh --seed
```

### 4. Running Individual Seeders
If tables are migrated but you need to re-seed specific lookup tables or missing test accounts:

```bash
# Run all seeders defined in DatabaseSeeder:
php artisan db:seed

# Or run individual seeders in sequence:
php artisan db:seed --class=RoleSeeder            # Base RBAC Roles (Admin, CWD, Supervisor, Field)
php artisan db:seed --class=TeamRoleSeeder        # Operational Team Roles (Leader, Driver, etc.)
php artisan db:seed --class=DepartmentSeeder      # PALECO Departments (CWD, Technical, etc.)
php artisan db:seed --class=TeamSeeder            # Operational Field Teams
php artisan db:seed --class=TicketCategorySeeder  # Complaint classifications
php artisan db:seed --class=UserSeeder            # Default test user accounts
```

### 5. Migration Rollback & Troubleshooting
* **Roll back the last batch of migrations:**
  ```bash
  php artisan migrate:rollback
  ```
* **Roll back all migrations (drop schema without re-running):**
  ```bash
  php artisan migrate:reset
  ```
* **Refresh migrations (rollback all then migrate and seed):**
  ```bash
  php artisan migrate:refresh --seed
  ```

---

## 📡 Manual Laravel Reverb Setup & Diagnostics

Laravel Reverb powers live WebSocket desk updates (instant ticket notifications, status changes, and endorsement alerts) without third-party services like Pusher.

### Method 1: Semi-Automated Setup via Artisan
```bash
php artisan reverb:install
```
> When prompted:
> - **Install Node dependencies?** Type `no` *(already in package.json)*.
> - **Publish configuration?** Type `no` *(config/reverb.php already exists in repo)*.
>
> Artisan will generate your Reverb keys and append them to `.env`.

### Method 2: 100% Manual Configuration (No Artisan Prompts)
Open `PALECO_OTRS-CRM/.env` and ensure this exact block is present:

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

After modifying `.env`, clear the configuration cache:
```bash
php artisan config:clear
```

### Running Reverb Manually (Standalone Terminal)
If you are running services individually rather than using `composer run dev`:

```bash
# Run with live debug logging:
php artisan reverb:start --debug

# Or bind explicitly to localhost on port 8080:
php artisan reverb:start --host=127.0.0.1 --port=8080 --debug
```

### Restarting Reverb
If you make changes to events or broadcast channels, restart the running server:
```bash
php artisan reverb:restart
```

---

## ⚡ How to Run the Application

### Option A: All-in-One Command (Recommended)
`composer run dev` runs all 4 required development services simultaneously in a single terminal:
1. **HTTP Server:** `php artisan serve` (`http://localhost:8000`)
2. **Queue Worker:** `php artisan queue:listen`
3. **WebSocket Server:** `php artisan reverb:start` (`ws://localhost:8080`)
4. **Vite Dev Server:** `npm run dev` (`http://localhost:5173` with instant Hot Module Replacement)

> 💡 **Do you need to run `npm run build`?**  
> **NO.** With `composer run dev`, Vite runs in memory with live hot-reloading. You do **not** need to build static assets.  
> Only run `npm run build` if you are hosting static production assets without the Vite dev server running in the background.

---

### Option B: Multi-Terminal Manual Running
If you prefer dedicated terminal windows to inspect logs individually:

* **Terminal 1 (Backend Web Server):**
  ```bash
  php artisan serve --port=8000
  ```
* **Terminal 2 (Queue Worker):**
  ```bash
  php artisan queue:listen
  ```
* **Terminal 3 (Reverb WebSockets):**
  ```bash
  php artisan reverb:start --debug
  ```
* **Terminal 4 (Frontend Assets):**
  ```bash
  npm run dev
  ```
  *(Or if you prefer not to keep Terminal 4 running, run `npm run build` once).*

---

## 🔑 Default Test Accounts Reference

The initial seeder creates pre-configured test users for all roles.  
**Password for all accounts:** `password`

| Username | Role | Allowed Portal | Capabilities |
| :--- | :--- | :--- | :--- |
| **`allenglenn`** | Admin | **Web Portal Only** | User CRUD, Department CRUD, Team CRUD, Ticket Categories |
| **`alliah`** | CWD Officer | **Web Portal Only** | Ticket creation, Consumer account verification, Child tickets, Endorsements |
| **`mycka`** | Supervisor | **Mobile App Only** | Ticket team assignments, Endorsement approval, Accomplishment review |
| **`ralph`** | Field Personnel | **Mobile App Only** | Ticket progress (`start`), photo uploads, touch signatures |

---

## 🛠️ Common Pitfalls & Solutions

### 1. "Server returned an invalid response format" on Consumer Verification
* **Cause:** Previously occurred when entering an invalid account code because the server was returning an HTML redirect instead of JSON.
* **Status:** **Fixed.** The controller now always returns clean JSON (`HTTP 422`). If an account code is not found in the PALECO billing system, the red preview box will show the exact billing error message.
* **Format:** Account codes must use the standard format: `XX-XXXX-XXXX` (e.g., `02-0504-8538`).

### 2. "WebSocket connection failed" / Notifications Not Updating
* **Check 1:** Is `BROADCAST_CONNECTION=reverb` in your `.env`? (If set to `log`, WebSockets are disabled).
* **Check 2:** Did you start the app with `composer run dev`? If running manually, ensure `php artisan reverb:start` is running in its own terminal.
* **Check 3:** Run `php artisan optimize:clear` to ensure stale configuration is not cached.

### 3. Accomplishment photos / images broken (`404 Not Found`)
* **Fix:** Run `php artisan storage:link` inside `PALECO_OTRS-CRM`.

### 4. `SQLSTATE[HY000] [1049] Unknown database 'paleco_otrs'`
* **Fix:** Ensure MySQL is running in XAMPP/Laragon and the database named in `DB_DATABASE` actually exists in MySQL. Run:
  ```sql
  CREATE DATABASE paleco_otrs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  ```
