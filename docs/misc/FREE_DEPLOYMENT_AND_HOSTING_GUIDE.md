# PALECO OTRS-CRM: Free Production & Staging Deployment Guide

**Document Target:** `docs/misc/FREE_DEPLOYMENT_AND_HOSTING_GUIDE.md`  
**Document Version:** 1.0  
**Context:** Comprehensive architectural roadmap, hosting evaluation, and step-by-step deployment blueprint for hosting the entire PALECO OTRS-CRM ecosystem completely free ($0.00/month) with generous database capacity and persistent media storage.  
**Target Codebase:** `PALECO_OTRS-CRM` (Laravel 13.x / PHP 8.4+ / Livewire 4 / Tailwind CSS v4 / Laravel Reverb)  

---

## 1. Project Context: Scanned Tech Stack & Architectural Profile

Before choosing a cloud provider or hosting topology, we must understand the exact technical demands, runtime dependencies, and background services required by the PALECO OTRS-CRM repository.

### 1.1 Complete Technology Inventory

| Layer | Technology & Version | Key Architectural Responsibility | Deployment Implication |
| :--- | :--- | :--- | :--- |
| **Backend Framework** | **Laravel 13.8** (PHP ^8.3, tested on PHP 8.4) | Core MVC routing, FormRequests, Model Policies, Eloquent ORM, Service Layer | Host must provide PHP 8.3+ CLI & FPM with extensions (`pdo_mysql`, `curl`, `mbstring`, `fileinfo`, `gd`/`imagick`, `bcmath`). |
| **Frontend Framework** | **Livewire 4.4** & **Blade Components** | Server-driven reactive UI, dynamic data tables, real-time filtering, 31 centralized SVG icons | Requires HTTP session persistence and low-latency roundtrips to the application server. |
| **Asset Pipeline** | **Vite 8** & **Tailwind CSS v4** (`@tailwindcss/vite`) | CSS compilation, JavaScript bundle optimization, Tom Select styling, Echo client | Requires Node.js (20+) during the build stage to compile production assets into `public/build/`. |
| **Real-Time Broadcasting** | **Laravel Reverb 1.0** & **Laravel Echo 2.5** | Real-time WebSocket broadcasting on `cwd.operations` channel (`TicketCreated`, status updates) | Requires either a persistent WebSocket daemon on port 8080 or fallback to a cloud WebSocket gateway (e.g., Pusher Free). |
| **Authentication & Security** | **Laravel Sanctum 4.0** + Custom Dual-Layer Throttling | Multi-role web sessions (Admin, CWD Officer, Field Supervisor), Mobile Bearer tokens, IP velocity rate limiting, virtual DB lockout | Sessions and cache can run on the database (`SESSION_DRIVER=database`, `CACHE_STORE=database`). |
| **Database Layer** | **MySQL 8.0+** / **MariaDB** (production default), **SQLite** (testing) | 11 relational tables with ULID primary keys, foreign constraints, composite performance indexes, pessimistic locks (`lockForUpdate()`) | Requires transactional database support. SQLite with WAL is viable on single-instance persistent storage; managed MySQL/PostgreSQL is preferred for PaaS. |
| **Media & File Storage** | **Laravel Storage** (`public` / `s3` disk) | Field accomplishment reports with digital canvas signatures (up to 5MB PNG) and multi-photo evidence arrays (up to 10 photos, max 10MB each) | **Stateless containers will delete uploads on restart.** Must use S3-compatible cloud object storage or a persistent host volume. |
| **Offline Mobile Sync** | **Sanctum API + Idempotency Middleware** | Asynchronous batch synchronization from the Flutter mobile field client with duplicate mutation protection | Requires reliable database write availability and fast HTTP API response times. |
| **Background Processes** | **Queue Worker** & **Task Scheduler** | Event broadcasting, activity logging (`spatie/laravel-activitylog`), automated ticket aging, idempotency cleanup | Requires a background supervisor (`php artisan queue:work`) or synchronous fallback (`QUEUE_CONNECTION=sync`). |
| **Health Probes** | **Laravel Health Endpoint** (`/up`) | Lightweight liveness probe (`bootstrap/app.php:20`) | Used by modern cloud PaaS orchestrators to monitor container health and zero-downtime rollouts. |

---

## 2. Core Free Hosting Challenges & Architectural Solutions

Deploying a modern Laravel 13 enterprise system with real-time WebSockets and rich media uploads for $0.00 introduces three specific architectural hurdles. Below is how each is systematically resolved:

### Challenge 1: The Ephemeral Filesystem Trap (Media Loss)
* **The Problem:** Free container platforms (Render Free, Koyeb, Railway, Vercel) provide stateless, ephemeral filesystems. Every time the container restarts, deploys, or wakes from sleep, files stored locally in `storage/app/public/` are wiped out.
* **The Free Solution:** Decouple media storage from the application server. Connect Laravel's native `s3` filesystem driver to **Cloudflare R2** or **Backblaze B2**, which provide **10 GB of free cloud object storage** forever with **$0 egress (bandwidth) fees**.

### Challenge 2: The WebSocket Daemon on Single-Port PaaS
* **The Problem:** Standard free web services only expose a single external HTTP port (e.g. 80 or 443). Laravel Reverb runs as an independent long-running daemon on port 8080.
* **The Free Solutions:**
  1. *On a Free VPS (Oracle Cloud):* Nginx reverse-proxies both the web app (port 80/443) and Reverb WebSockets (`/app/*` on port 8080) seamlessly over a single domain with free SSL.
  2. *On a Free PaaS (Render / Koyeb):* Leverage the existing `pusher` driver in `config/broadcasting.php`. **Pusher Channels Free Sandbox** provides 200,000 messages/day and 100 concurrent connections for $0/month. The frontend `echo.js` already supports Pusher natively.
  3. *On Local Presentation (Cloudflare Tunnel):* Route both ports 8000 and 8080 through Cloudflare's secure tunnel over HTTPS/WSS for free.

### Challenge 3: Free Relational Database with Real Transactions
* **The Problem:** The PALECO CRM utilizes ULID primary keys, foreign key constraints, `DB::transaction()`, and pessimistic row locks (`lockForUpdate()`). Many free database hosts restrict database sizes to 5MB or lack transactional guarantees.
* **The Free Solution:** Utilize **TiDB Cloud Serverless** (5 GB free MySQL 8.0-compatible storage) or **Aiven for MySQL** (5 GB free), which provide enterprise ACID transactions, row-level locking, and high availability.

---

## 3. Comparison of Free Deployment Architectures

Here is an architectural comparison of the top four zero-cost deployment paths, evaluated for the PALECO OTRS-CRM stack:

```
┌────────────────────────────────────────────────────────────────────────────────────────────────────────┐
│                                 FREE HOSTING ARCHITECTURE SCORECARD                                    │
├───────────────────┬────────────────────┬─────────────────┬───────────────────┬─────────────────────────┤
│ Architecture Path │ Compute & RAM      │ Database Limit  │ Media Storage     │ Recommended Use Case    │
├───────────────────┼────────────────────┼─────────────────┼───────────────────┼─────────────────────────┤
│ Option 1: OCI     │ 4 ARM OCPU / 24 GB │ Up to 200 GB    │ Up to 200 GB      │ Permanent Production,   │
│ Always Free VPS   │ (Never sleeps)     │ (Native MySQL)  │ (Local NVMe Disk) │ Capstone Live Server    │
├───────────────────┼────────────────────┼─────────────────┼───────────────────┼─────────────────────────┤
│ Option 2: Render  │ 512 MB RAM         │ 5 GB Free       │ 10 GB Free        │ Git Push Automated      │
│ + TiDB + R2       │ (Sleeps after 15m) │ (TiDB MySQL)    │ (Cloudflare R2)   │ Staging / Cloud Demo    │
├───────────────────┼────────────────────┼─────────────────┼───────────────────┼─────────────────────────┤
│ Option 3:         │ Your PC Specs      │ Unlimited       │ Unlimited         │ Capstone Oral Defense,  │
│ Cloudflare Tunnel │ (Host machine)     │ (Local MySQL)   │ (Local SSD)       │ Panel Demonstration     │
├───────────────────┼────────────────────┼─────────────────┼───────────────────┼─────────────────────────┤
│ Option 4: Fly.io  │ 256 MB Micro VM    │ 3 GB Volume     │ 3 GB Volume       │ Minimalist Container    │
│ Hobby Tier        │ (Shared CPU)       │ (SQLite WAL)    │ (Persistent Disk) │ Testing                 │
└───────────────────┴────────────────────┴─────────────────┴───────────────────┴─────────────────────────┘
```

---

## 4. Architecture Option 1: Oracle Cloud Always Free (Recommended for Permanent Production)

**Oracle Cloud Infrastructure (OCI) Always Free Tier** is the gold standard for self-hosting Laravel applications at zero cost. Unlike 30-day trials, OCI "Always Free" resources never expire and do not shut down.

### 4.1 Resource Allocation ($0.00 Forever)
* **Compute:** Up to 4 ARM Ampere A1 OCPU cores and **24 GB RAM**. (You can provision one massive VM with 4 cores & 24 GB RAM, or split into two 2-core / 12 GB RAM VMs).
* **Disk Storage:** **200 GB NVMe Block Storage** completely free.
* **Bandwidth:** 10 TB outbound transfer per month.
* **Public IP:** 1 free permanent reserved IPv4 address + free IPv6.

### 4.2 Why This Fits PALECO CRM Perfectly
1. **Unified Stack:** PHP 8.4-FPM, Nginx, MySQL 8.0, Redis, Laravel Reverb (port 8080), and Laravel Queue Worker run side-by-side on a single VM.
2. **Generous Storage:** The 200 GB persistent disk allows storing both the database and tens of thousands of high-resolution accomplishment photos locally in `storage/app/public/` without requiring external S3 accounts.
3. **Always Online:** No cold starts, no sleeping containers. WebSockets and mobile background sync remain active 24/7.

### 4.3 Step-by-Step Deployment Walkthrough

#### Step 1: Provision the VM
1. Sign up at [cloud.oracle.com](https://cloud.oracle.com) (requires a credit card for anti-fraud verification; $0 is billed).
2. Create an **Instance**:
   * Image: **Ubuntu 24.04 LTS**
   * Shape: **Ampere ARM** (`VM.Standard.A1.Flex`)
   * OCPUs: **2 to 4**
   * Memory: **12 to 24 GB**
   * Boot Volume: **100 GB to 200 GB**
3. Save your SSH private key and note the public IPv4 address.

#### Step 2: Configure OCI Firewall & Security List
In the OCI Console under **Virtual Cloud Network > Security Lists > Default Security List**, add Ingress Rules:
* Port `80` (HTTP) — Source: `0.0.0.0/0`
* Port `443` (HTTPS & WSS) — Source: `0.0.0.0/0`
* Port `8080` (Reverb WebSocket, optional if reverse-proxied via 443) — Source: `0.0.0.0/0`

On the Ubuntu VM, open the local `iptables`/`ufw`:
```bash
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 8080/tcp
sudo ufw enable
```

#### Step 3: Install PHP 8.4, Nginx & MySQL 8
```bash
# Update and add Ondrej PHP PPA for PHP 8.4
sudo apt update && sudo apt upgrade -y
sudo apt install -y software-properties-common curl git unzip nginx supervisor
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update

# Install PHP 8.4 and required extensions
sudo apt install -y php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring \
    php8.4-xml php8.4-bcmath php8.4-curl php8.4-gd php8.4-zip php8.4-intl \
    php8.4-redis mysql-server

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Node.js 20 & NPM
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

#### Step 4: Clone Codebase & Install Dependencies
```bash
cd /var/www
sudo git clone https://github.com/<your-username>/<your-repo>.git paleco-crm
cd paleco-crm/PALECO_OTRS-CRM

# Permissions
sudo chown -R www-data:www-data /var/www/paleco-crm
sudo chmod -R 775 storage bootstrap/cache

# Install PHP and Node dependencies
sudo -u www-data composer install --no-dev --optimize-autoloader
npm install
npm run build
```

#### Step 5: Configure Environment & Database
```bash
# Setup Database in MySQL
sudo mysql -e "CREATE DATABASE paleco_otrs_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'paleco_user'@'localhost' IDENTIFIED BY 'StrongPassword123!';"
sudo mysql -e "GRANT ALL PRIVILEGES ON paleco_otrs_crm.* TO 'paleco_user'@'localhost'; FLUSH PRIVILEGES;"

# Create .env
cp .env.example .env
php artisan key:generate
```

Update `/var/www/paleco-crm/PALECO_OTRS-CRM/.env`:
```ini
APP_NAME="PALECO OTRS-CRM"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=paleco_otrs_crm
DB_USERNAME=paleco_user
DB_PASSWORD=StrongPassword123!

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=public

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=867789
REVERB_APP_KEY=paleco_prod_key_xyz
REVERB_APP_SECRET=paleco_prod_secret_abc
REVERB_HOST="your-domain.com"
REVERB_PORT=443
REVERB_SCHEME=https

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

Run database setup:
```bash
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

#### Step 6: Configure Nginx with WebSocket Reverse Proxy
Create `/etc/nginx/sites-available/paleco-crm`:
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/paleco-crm/PALECO_OTRS-CRM/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    # Max upload size for accomplishment photos and signatures (up to 20MB per request)
    client_max_body_size 25M;

    # Standard Laravel Routing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    # Laravel Reverb WebSocket Reverse Proxy (Proxies wss://your-domain.com/app/* to Reverb port 8080)
    location /app/ {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_read_timeout 60;
        proxy_connect_timeout 60;
    }

    # PHP-FPM Handler
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```
Enable site and acquire free SSL:
```bash
sudo ln -s /etc/nginx/sites-available/paleco-crm /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx

# Install free Let's Encrypt SSL
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com
```

#### Step 7: Supervisor for Queue Workers & Reverb Daemon
Create `/etc/supervisor/conf.d/paleco-daemons.conf`:
```ini
[program:paleco-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/paleco-crm/PALECO_OTRS-CRM/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/paleco-queue.log

[program:paleco-reverb]
command=php /var/www/paleco-crm/PALECO_OTRS-CRM/artisan reverb:start --host=127.0.0.1 --port=8080
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/supervisor/paleco-reverb.log
```
Start supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all
```

---

## 5. Architecture Option 2: Render + TiDB Serverless + Cloudflare R2 (Zero-DevOps PaaS)

If you prefer continuous deployment directly from GitHub without managing a Linux server, this decoupled cloud stack provides generous free tiers across compute, database, and storage.

```
┌────────────────────────────────────────────────────────────────────────┐
│                    DECOUPLED PAAS CLOUD ARCHITECTURE                   │
│                                                                        │
│   ┌─────────────────────┐       ┌──────────────────────────────────┐   │
│   │     Render Web      │       │     TiDB Cloud Serverless        │   │
│   │    (Free 512MB)     │──────>│     5 GB Free MySQL Database     │   │
│   │  PHP 8.4 + Livewire │       │   (ACID, ULID, lockForUpdate)    │   │
│   └──────────┬──────────┘       └──────────────────────────────────┘   │
│              │                                                         │
│              │ (Uploads)        ┌──────────────────────────────────┐   │
│              └─────────────────>│      Cloudflare R2 Storage       │   │
│                                 │   10 GB Free S3 Object Storage   │   │
│                                 │     $0 Bandwidth / Egress Fees   │   │
│                                 └──────────────────────────────────┘   │
└────────────────────────────────────────────────────────────────────────┘
```

### 5.1 Component Breakdown & Free Allowances
1. **Application Hosting:** **Render Free Web Service**
   * 512 MB RAM, free SSL certificate, automatic Git deployments on push.
   * *Limitation:* Spins down after 15 minutes of inactivity (takes 30-50s to wake up on next hit).
2. **Database:** **TiDB Cloud Serverless**
   * **5 GB free storage forever** (supports 100,000+ tickets and millions of log rows).
   * 100% MySQL 8.0 wire-compatible (supports ULID strings, JSON columns, transactions, and pessimistic locking).
3. **Media Storage:** **Cloudflare R2**
   * **10 GB free storage per month**.
   * 10,000,000 Class B read requests / month.
   * 1,000,000 Class A write requests / month.
   * **$0 Egress Fees** (completely free bandwidth, unlike AWS S3 which charges for downloads).
4. **WebSockets (Alternative):** **Pusher Channels Sandbox**
   * 200,000 messages / day free.
   * 100 concurrent connections free.
   * Replaces Reverb on single-port PaaS without code changes.

### 5.2 Step-by-Step Configuration

#### Step 1: Set Up Cloudflare R2 (10 GB Free Storage)
1. In your Cloudflare dashboard, go to **R2 Object Storage > Create Bucket**.
2. Name the bucket `paleco-crm-storage`.
3. In **R2 > Manage R2 API Tokens**, create a token with `Object Read & Write` permissions.
4. Note your:
   * **Access Key ID**
   * **Secret Access Key**
   * **Endpoint URL** (e.g., `https://<account_id>.r2.cloudflarestorage.com`)
   * **Public Bucket URL** (enable "Public Development URL" or bind a custom domain like `cdn.yourdomain.com`).

#### Step 2: Set Up TiDB Cloud Serverless (5 GB Free MySQL)
1. Sign up at [tidbcloud.com](https://tidbcloud.com).
2. Create a new **Serverless Cluster** (free, no credit card required).
3. Under cluster security, allow traffic from `0.0.0.0/0` (Render's dynamic IPs).
4. Click **Connect** and select **General / MySQL CLI**. Note the:
   * Host (`gateway01.ap-southeast-1.prod.aws.tidbcloud.com`)
   * Port (`4000`)
   * Username (`<cluster-prefix>.root`)
   * Password
   * Database (`paleco_otrs_crm`)

#### Step 3: Configure Laravel for Cloudflare R2 in `config/filesystems.php`
The project's [`config/filesystems.php`](file:///c:/Users/allen/Desktop/Capstone/-NEW-CRM-PALECO/PALECO_OTRS-CRM/config/filesystems.php#L50-L60) is already structured for S3:
```php
's3' => [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION', 'auto'),
    'bucket' => env('AWS_BUCKET'),
    'url' => env('AWS_URL'),
    'endpoint' => env('AWS_ENDPOINT'),
    'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
],
```

To route all accomplishment uploads to Cloudflare R2 on production, simply set these environment variables in Render:
```ini
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=<your-r2-access-key-id>
AWS_SECRET_ACCESS_KEY=<your-r2-secret-access-key>
AWS_DEFAULT_REGION=auto
AWS_BUCKET=paleco-crm-storage
AWS_ENDPOINT=https://<account_id>.r2.cloudflarestorage.com
AWS_URL=https://pub-<hash>.r2.dev
AWS_USE_PATH_STYLE_ENDPOINT=false
```

#### Step 4: Create a Production `Dockerfile` for Render
Create `PALECO_OTRS-CRM/Dockerfile`:
```dockerfile
# Multi-stage build: 1. Asset compilation
FROM node:20-alpine AS assets
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

# Stage 2: PHP 8.4 Production Environment
FROM php:8.4-fpm-alpine AS runner

# Install system dependencies and PHP extensions
RUN apk add --no-cache \
    nginx \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    zip \
    unzip \
    oniguruma-dev

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring gd zip bcmath opcache

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application source
COPY . .
COPY --from=assets /app/public/build /var/www/html/public/build

# Install production PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Copy Nginx config
COPY ./docker/nginx.conf /etc/nginx/http.d/default.conf

# Start script
COPY ./docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
```

Create `PALECO_OTRS-CRM/docker/entrypoint.sh`:
```bash
#!/bin/sh
set -e

# Run migrations and cache config on container boot
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start PHP-FPM in background and Nginx in foreground
php-fpm -D
nginx -g "daemon off;"
```

#### Step 5: Deploy on Render
1. In Render, select **New > Web Service**.
2. Connect your GitHub repository and set the Root Directory to `PALECO_OTRS-CRM`.
3. Select **Docker** environment.
4. In **Environment Variables**, add the TiDB credentials, R2 credentials, `APP_KEY`, and set `APP_ENV=production`.
5. Under **Health Check Path**, set `/up` (Laravel 13's native probe).
6. Click **Create Web Service**.

---

## 6. Architecture Option 3: Cloudflare Tunnel (Recommended for Capstone Oral Defense & Live Demonstrations)

If you are preparing for a Capstone oral defense, panel demonstration, or evaluator review, deploying to free cloud tiers presents two severe risks:
1. **Cold Boots:** Panelists accessing the link encounter a 40-second delay because the free container went to sleep.
2. **Resource Throttling:** Rapid mobile uploads during a live presentation can exceed free RAM or connection quotas.

**Cloudflare Tunnel (Zero Trust)** bypasses all cloud limits by securely routing internet traffic directly to your local development machine for free.

```
┌────────────────────────────────────────────────────────────────────────┐
│                   CLOUDFLARE TUNNEL LIVE DEFENSE SETUP                 │
│                                                                        │
│   Panelists / Mobile App           Cloudflare Global Edge              │
│   (Anywhere on Internet)  ───────> (Free SSL & DDoS Shield)            │
│                                              │                         │
│                                              ▼ (Encrypted Tunnel)      │
│                                   ┌──────────────────────────────┐     │
│                                   │     Local Dev Computer       │     │
│                                   │   - PHP 8.4 (`serve` / FPM)  │     │
│                                   │   - Local MySQL 8.0          │     │
│                                   │   - Reverb WebSocket (8080)  │     │
│                                   │   - Full SSD Storage         │     │
│                                   └──────────────────────────────┘     │
└────────────────────────────────────────────────────────────────────────┘
```

### 6.1 Key Advantages for Defense
* **Cost:** 100% Free Forever. Zero credit card required.
* **Storage:** **Unlimited** (uses your computer's local drive for both database and photos).
* **Speed:** Instant responsiveness with zero cold starts.
* **Full Stack Support:** Runs Web HTTP, Queue worker, and Laravel Reverb WebSockets without complex server setups.
* **Official HTTPS:** Provides an authentic `https://your-capstone.trycloudflare.com` or custom domain URL with valid SSL for mobile apps.

### 6.2 3-Minute Setup Instructions

#### Step 1: Install Cloudflare Tunnel CLI (`cloudflared`)
On Windows (PowerShell as Administrator):
```powershell
winget install --id Cloudflare.cloudflared
```
Or download the binary directly from [developers.cloudflare.com/cloudflare-one/connections/connect-networks/downloads/](https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/downloads/).

#### Step 2: Start Your Local PALECO CRM
In PowerShell inside `PALECO_OTRS-CRM`:
```powershell
composer dev
```
*(This launches `php artisan serve` on port 8000, `queue:listen`, `npm run dev`, and `reverb:start` on port 8080).*

#### Step 3: Launch the Cloudflare Tunnel
In a second terminal window:
```powershell
cloudflared tunnel --url http://localhost:8000
```

Cloudflare will output a public HTTPS URL:
```text
+--------------------------------------------------------------------------------------------+
| Your quick Tunnel has been created! Visit it at:                                           |
| https://paleco-preview-1234.trycloudflare.com                                              |
+--------------------------------------------------------------------------------------------+
```

#### Step 4: Update `.env` for the Session
Set in `PALECO_OTRS-CRM/.env`:
```ini
APP_URL=https://paleco-preview-1234.trycloudflare.com
REVERB_HOST="paleco-preview-1234.trycloudflare.com"
REVERB_PORT=443
REVERB_SCHEME=https
```
Any panelist, evaluator, or mobile field personnel can now access the system, submit tickets, view live dashboard cards, and upload high-resolution accomplishment photos from any device on cellular data or campus Wi-Fi.

---

## 7. WebSockets Strategy: Reverb vs. Pusher Free Sandbox

The project utilizes real-time broadcasting on the private `cwd.operations` channel (`TicketCreated` events). Here is how to handle WebSockets depending on your hosting path:

### Scenario A: Running on Oracle Cloud VPS or Cloudflare Tunnel (Use Reverb)
Keep the default `BROADCAST_CONNECTION=reverb`. Reverb runs natively as a local process on port 8080. It provides unlimited messages, zero subscription constraints, and sub-millisecond local latency.

### Scenario B: Running on Free PaaS (Render / Koyeb) (Use Pusher Free)
Free single-port containers cannot host an independent WebSocket server. You can swap Reverb for Pusher Sandbox without changing a single line of backend or frontend logic:

1. Create a free account at [pusher.com](https://pusher.com) and create a **Channels** app.
2. In `.env`, switch:
   ```ini
   BROADCAST_CONNECTION=pusher
   PUSHER_APP_ID=your_pusher_id
   PUSHER_APP_KEY=your_pusher_key
   PUSHER_APP_SECRET=your_pusher_secret
   PUSHER_APP_CLUSTER=ap1
   ```
3. Update `resources/js/echo.js` to initialize Pusher if `VITE_PUSHER_APP_KEY` is present.
4. **Pusher Free Limits:** 200,000 messages per day and 100 concurrent connections—more than enough for capstone grading and pilot deployment.

---

## 8. Production Environment Configuration Recipe (`.env.production`)

Below is the hardened, optimized `.env` template for production deployment:

```ini
# ==============================================================================
# PALECO OTRS-CRM: PRODUCTION CONFIGURATION TEMPLATE
# ==============================================================================

APP_NAME="PALECO OTRS-CRM"
APP_ENV=production
APP_KEY=base64:GENERATE_NEW_KEY_WITH_PHP_ARTISAN_KEY_GENERATE
APP_DEBUG=false
APP_URL=https://your-domain.com

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=warning

# ------------------------------------------------------------------------------
# DATABASE CONFIGURATION (TiDB Serverless / Oracle MySQL / Aiven)
# ------------------------------------------------------------------------------
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=paleco_otrs_crm
DB_USERNAME=paleco_user
DB_PASSWORD=SecureProductionPasswordHere
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci

# ------------------------------------------------------------------------------
# SESSIONS, CACHE & QUEUES (Database-Backed = Zero External Redis Cost)
# ------------------------------------------------------------------------------
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true

CACHE_STORE=database
QUEUE_CONNECTION=database

# ------------------------------------------------------------------------------
# MEDIA STORAGE (Option A: Local Disk on VPS / Option B: Cloudflare R2 on PaaS)
# ------------------------------------------------------------------------------
# For Oracle VPS (Local 200GB NVMe Disk):
FILESYSTEM_DISK=public

# For Render / PaaS (Uncomment for Cloudflare R2 - 10GB Free Forever):
# FILESYSTEM_DISK=s3
# AWS_ACCESS_KEY_ID=your_r2_access_key
# AWS_SECRET_ACCESS_KEY=your_r2_secret_key
# AWS_DEFAULT_REGION=auto
# AWS_BUCKET=paleco-crm-storage
# AWS_ENDPOINT=https://your_account_id.r2.cloudflarestorage.com
# AWS_URL=https://pub-your_hash.r2.dev
# AWS_USE_PATH_STYLE_ENDPOINT=false

# ------------------------------------------------------------------------------
# REAL-TIME BROADCASTING (Reverb on VPS / Pusher on PaaS)
# ------------------------------------------------------------------------------
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=867789
REVERB_APP_KEY=reverb_key_production_token
REVERB_APP_SECRET=reverb_secret_production_token
REVERB_HOST="your-domain.com"
REVERB_PORT=443
REVERB_SCHEME=https

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

# ------------------------------------------------------------------------------
# EXTERNAL INTEGRATIONS
# ------------------------------------------------------------------------------
PALECO_API_KEY=your_paleco_external_api_key
```

---

## 9. Production Pre-Flight Checklist

Execute these performance and security commands before opening the system to evaluators or users:

```bash
# 1. Clear development caches
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 2. Compile and optimize for production
composer install --no-dev --optimize-autoloader
npm run build

# 3. Build framework speed caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 4. Ensure public storage symlink exists
php artisan storage:link

# 5. Execute migrations safely
php artisan migrate --force

# 6. Verify health probe
curl -I http://localhost/up
# Expect: HTTP/1.1 200 OK
```

---

## 10. Summary & Recommended Action Plan

| Project Milestone | Recommended Hosting Strategy | Cost | Primary Justification |
| :--- | :--- | :---: | :--- |
| **Capstone Defense & Panel Presentation** | **Option 3: Cloudflare Tunnel** | **$0.00** | Zero latency, zero cold starts, unlimited local SSD storage, 100% reliable for live mobile demonstrations. |
| **Permanent Production / Capstone Deployment** | **Option 1: Oracle Cloud Always Free (OCI)** | **$0.00** | 4 OCPU, 24 GB RAM, 200 GB persistent storage, native MySQL and Reverb support, never sleeps. |
| **Fast Cloud Staging / Automated GitHub CI** | **Option 2: Render + TiDB + Cloudflare R2** | **$0.00** | Zero server management, 5 GB managed MySQL, 10 GB free S3 storage, automated git-push builds. |

