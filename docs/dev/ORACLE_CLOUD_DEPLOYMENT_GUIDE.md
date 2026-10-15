# PALECO OTRS-CRM: Oracle Cloud Infrastructure (OCI) Always Free Deployment Guide

**Target Audience:** DevOps Engineers, Backend Developers, and System Administrators  
**Document Version:** 1.0  
**Target Path:** `docs/dev/ORACLE_CLOUD_DEPLOYMENT_GUIDE.md`  
**Hosting Model:** Oracle Cloud Infrastructure (OCI) Always Free Tier ($0.00 Forever)  
**Target Stack:** Ubuntu 24.04 LTS, PHP 8.4-FPM, Nginx, MySQL 8.0, Laravel Reverb (WebSockets), Node.js 20, Supervisor, Certbot (Let's Encrypt SSL)

---

## Table of Contents
1. [Overview & Always Free Specifications](#1-overview--always-free-specifications)
2. [Phase 1: OCI Account Creation & VM Provisioning](#phase-1-oci-account-creation--vm-provisioning)
3. [Phase 2: Network & Firewall Configuration (VCN & Host iptables)](#phase-2-network--firewall-configuration-vcn--host-iptables)
4. [Phase 3: Server Package Installation (LEMP Stack)](#phase-3-server-package-installation-lemp-stack)
5. [Phase 4: MySQL 8.0 Database Setup](#phase-4-mysql-80-database-setup)
6. [Phase 5: Codebase Deployment & Configuration](#phase-5-codebase-deployment--configuration)
7. [Phase 6: Nginx Reverse Proxy (HTTP & WSS WebSockets)](#phase-6-nginx-reverse-proxy-http--wss-websockets)
8. [Phase 7: Domain Setup & Free Let's Encrypt SSL](#phase-7-domain-setup--free-lets-encrypt-ssl)
9. [Phase 8: Background Daemons with Supervisor (Reverb & Queue)](#phase-8-background-daemons-with-supervisor-reverb--queue)
10. [Phase 9: Production Performance Caching & OPcache](#phase-9-production-performance-caching--opcache)
11. [Phase 10: Verification Checklist & Troubleshooting Matrix](#phase-10-verification-checklist--troubleshooting-matrix)

---

## 1. Overview & Always Free Specifications

Oracle Cloud Infrastructure (OCI) offers an **Always Free Tier** that never expires and does not sleep. It provides enough compute power to host the entire PALECO CRM ecosystem—including the web portal, MySQL database, mobile API, and real-time Reverb WebSocket daemon—on a single, high-performance virtual machine.

### Free Resources Allocated ($0.00 / Month Forever)
* **Compute Architecture:** Ampere Altra ARM (`VM.Standard.A1.Flex`)
* **Compute Power:** Up to **4 OCPU cores** and **24 GB RAM** (e.g. provision 2 to 4 cores with 12 to 24 GB RAM).
* **Storage:** **200 GB NVMe Boot Volume** (Stores OS, database, and tens of thousands of accomplishment photos).
* **Network Bandwidth:** **10 TB** outbound data transfer per month.
* **Public IP:** 1 free permanent reserved IPv4 address + IPv6.

---

## Phase 1: OCI Account Creation & VM Provisioning

### Step 1.1: Sign Up for Oracle Cloud
1. Go to [cloud.oracle.com](https://cloud.oracle.com).
2. Choose a **Home Region** close to the Philippines for lowest latency:
   - **Recommended:** `Singapore (ap-singapore-1)` or `Tokyo (ap-tokyo-1)`.
   > [!IMPORTANT]
   > You cannot change your Home Region after registration. Choose Singapore for optimal latency to Palawan.
3. Complete identity verification. A credit/debit card is required for fraud prevention. A temporary $1 pre-authorization hold is created and immediately released. **$0.00 will be billed.**

### Step 1.2: Create the Compute Instance
1. In the OCI Console dashboard, navigate to **Compute > Instances > Create Instance**.
2. **Name:** `paleco-crm-production`
3. **Placement:** Keep default Availability Domain.
4. **Image and Shape:**
   - Click **Change Image** $\to$ Select **Ubuntu 24.04 LTS Minimal** or **Ubuntu 24.04 LTS**.
   - Click **Change Shape** $\to$ Select **Ampere (ARM-based Processor)** $\to$ `VM.Standard.A1.Flex`.
   - Allocate:
     - **OCPUs:** `2` (or `4`)
     - **Memory (GB):** `12` (or `24`)
5. **Networking:**
   - Primary VNIC: Select or create default **Virtual Cloud Network (VCN)** and **Public Subnet**.
   - Assign public IPv4 address: **Yes**.
6. **Add SSH Keys:**
   - Select **Generate a key pair for me**.
   - Click **Save Private Key** (saves as `ssh-key-xxxx.key`). Keep this file safe on your computer!
7. **Boot Volume:**
   - Check **Specify a custom boot volume size**.
   - Set to `100` GB (or up to `200` GB).
8. Click **Create**. The instance status will turn **RUNNING** within 1–2 minutes. Note your **Public IPv4 Address**.

### Step 1.3: Connect via SSH
From your local terminal (PowerShell or Bash):
```powershell
# Set strict permissions on your private key (Windows PowerShell)
icacls "C:\path\to\ssh-key.key" /inheritance:r
icacls "C:\path\to\ssh-key.key" /grant:r "$($env:USERNAME):(R)"

# Connect to the Ubuntu instance
ssh -i "C:\path\to\ssh-key.key" ubuntu@<YOUR_INSTANCE_PUBLIC_IP>
```

---

## Phase 2: Network & Firewall Configuration (VCN & Host iptables)

> [!CAUTION]
> **The Common Oracle Trap:** Oracle Ubuntu images enforce a strict internal `iptables` firewall by default that drops incoming traffic on ports 80, 443, and 8080 even if you open them in the OCI Web Console! You must configure **both** the OCI Security List and the server's local firewall.

### Step 2.1: Open Ports in OCI Cloud Console (Security List)
1. In the OCI Console, navigate to **Networking > Virtual Cloud Networks**.
2. Click your VCN $\to$ Click your **Public Subnet** $\to$ Click its **Default Security List**.
3. Under **Ingress Rules**, click **Add Ingress Rules**:
   - **Rule 1 (HTTP):**
     - Source CIDR: `0.0.0.0/0`
     - IP Protocol: `TCP`
     - Destination Port Range: `80`
   - **Rule 2 (HTTPS / WSS):**
     - Source CIDR: `0.0.0.0/0`
     - IP Protocol: `TCP`
     - Destination Port Range: `443`
   - **Rule 3 (Reverb Direct / Debugging):**
     - Source CIDR: `0.0.0.0/0`
     - IP Protocol: `TCP`
     - Destination Port Range: `8080`

### Step 2.2: Open Ports in Host Ubuntu Firewall
Run these commands inside your SSH session on the Ubuntu server:
```bash
# Allow essential ports in UFW
sudo ufw allow 22/tcp comment 'SSH'
sudo ufw allow 80/tcp comment 'HTTP'
sudo ufw allow 443/tcp comment 'HTTPS/WSS'
sudo ufw allow 8080/tcp comment 'Reverb'

# Enable UFW
sudo ufw --force enable

# Fix Oracle's default iptables drop rules (Ensures ports 80 and 443 are not rejected)
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 80 -j ACCEPT
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 443 -j ACCEPT
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 8080 -j ACCEPT
sudo netfilter-persistent save 2>/dev/null || sudo iptables-save | sudo tee /etc/iptables/rules.v4
```

---

## Phase 3: Server Package Installation (LEMP Stack)

Run the following commands on the Ubuntu server to install the modern web stack:

```bash
# 1. Update OS packages
sudo apt update && sudo apt upgrade -y
sudo apt install -y software-properties-common curl git unzip zip nginx supervisor ufw

# 2. Add Ondrej PPA for PHP 8.4
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update

# 3. Install PHP 8.4-FPM and required extensions
sudo apt install -y \
    php8.4-fpm \
    php8.4-cli \
    php8.4-common \
    php8.4-mysql \
    php8.4-mbstring \
    php8.4-xml \
    php8.4-bcmath \
    php8.4-curl \
    php8.4-gd \
    php8.4-zip \
    php8.4-intl \
    php8.4-opcache \
    php8.4-readline

# 4. Install Composer (PHP Dependency Manager)
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer

# 5. Install Node.js 20 LTS & NPM (For compiling Tailwind CSS v4 and Vite assets)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# 6. Verify installations
php -v
composer -V
node -v
npm -v
nginx -v
```

---

## Phase 4: MySQL 8.0 Database Setup

Install and secure MySQL server on the VM:

```bash
# 1. Install MySQL Server
sudo apt install -y mysql-server

# 2. Start and enable MySQL service
sudo systemctl enable --now mysql

# 3. Create the PALECO database and dedicated user
sudo mysql
```

Inside the MySQL prompt (`mysql>`), run:
```sql
-- Create database with full utf8mb4 unicode support
CREATE DATABASE paleco_otrs_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create dedicated non-root application user (replace StrongPassword123! with a secure password)
CREATE USER 'paleco_user'@'localhost' IDENTIFIED BY 'StrongPassword123!';

-- Grant complete privileges on the database
GRANT ALL PRIVILEGES ON paleco_otrs_crm.* TO 'paleco_user'@'localhost';

-- Apply changes and exit
FLUSH PRIVILEGES;
EXIT;
```

---

## Phase 5: Codebase Deployment & Configuration

### Step 5.1: Clone the Repository
```bash
# Create web root directory
sudo mkdir -p /var/www
cd /var/www

# Clone repository (replace with your GitHub repository URL)
sudo git clone https://github.com/<YOUR_GITHUB_USER>/<YOUR_REPO_NAME>.git paleco-crm

# Navigate to the Laravel project root
cd /var/www/paleco-crm/PALECO_OTRS-CRM
```

### Step 5.2: Set Linux File Permissions
Ensure the web server (`www-data`) owns storage and cache directories:
```bash
sudo chown -R www-data:ubuntu /var/www/paleco-crm
sudo chmod -R 775 /var/www/paleco-crm/PALECO_OTRS-CRM/storage
sudo chmod -R 775 /var/www/paleco-crm/PALECO_OTRS-CRM/bootstrap/cache
```

### Step 5.3: Install Dependencies & Build Frontend
```bash
cd /var/www/paleco-crm/PALECO_OTRS-CRM

# Install PHP dependencies for production
sudo -u ubuntu composer install --no-dev --optimize-autoloader

# Install NPM packages and compile Vite production assets
npm install
npm run build
```

### Step 5.4: Configure `.env` File
```bash
cp .env.example .env
nano .env
```

Edit the `.env` file to match your production parameters:
```ini
APP_NAME="PALECO OTRS-CRM"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://your-domain.com

LOG_CHANNEL=daily
LOG_LEVEL=error

# Database Connection
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=paleco_otrs_crm
DB_USERNAME=paleco_user
DB_PASSWORD="StrongPassword123!"

# Session & Cache (Can use database for single-server setups)
SESSION_DRIVER=database
SESSION_LIFETIME=120
CACHE_STORE=database
QUEUE_CONNECTION=database

# Real-Time WebSockets (Laravel Reverb)
BROADCAST_CONNECTION=reverb

# Internal Reverb listener (binds locally)
REVERB_SERVER_HOST="127.0.0.1"
REVERB_SERVER_PORT=8080

# External client connection (proxied over standard HTTPS/WSS port 443)
REVERB_HOST="your-domain.com"
REVERB_PORT=443
REVERB_SCHEME=https

VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

### Step 5.5: Generate Key, Run Migrations & Storage Symlink
```bash
# Generate encryption key
php artisan key:generate

# Run database migrations and seed system master data (roles, departments, teams)
php artisan migrate --force --seed

# Create storage symlink for uploaded photos and signatures
php artisan storage:link
```

---

## Phase 6: Nginx Reverse Proxy (HTTP & WSS WebSockets)

Configure Nginx to serve Laravel over PHP-FPM, while simultaneously proxying WebSocket traffic (`/app/*` and `/apps/*`) to Laravel Reverb on port 8080.

Create `/etc/nginx/sites-available/paleco-crm`:
```bash
sudo nano /etc/nginx/sites-available/paleco-crm
```

Paste the following configuration (replace `your-domain.com` with your domain):

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name your-domain.com;
    root /var/www/paleco-crm/PALECO_OTRS-CRM/public;

    # Maximum file upload size for field accomplishment photos
    client_max_body_size 50M;

    index index.php index.html;
    charset utf-8;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    # Standard Web & API Traffic
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Static Assets Caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|woff2|woff|ttf|svg)$ {
        expires 30d;
        add_header Cache-Control "public, no-transform";
    }

    # Media Storage for Photos & Signatures
    location /storage/ {
        alias /var/www/paleco-crm/PALECO_OTRS-CRM/storage/app/public/;
        try_files $uri =404;
    }

    # PHP-FPM FastCGI Handler
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    # Laravel Reverb WebSocket Reverse Proxy (Handles WSS on Port 443)
    location ~ ^/(app|apps)/ {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 600s;
        proxy_send_timeout 600s;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable the site and reload Nginx:
```bash
sudo ln -s /etc/nginx/sites-available/paleco-crm /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
```

---

## Phase 7: Domain Setup & Free Let's Encrypt SSL

### Step 7.1: Point Your Domain to Oracle Public IP
- **Option A (Custom Domain):** Log in to your domain registrar (Namecheap, GoDaddy, Cloudflare) and create an **A Record**:
  - Host / Name: `crm` (or `@`)
  - Target / Value: `<YOUR_ORACLE_PUBLIC_IP>`
- **Option B (Free DuckDNS Domain):** Go to [duckdns.org](https://www.duckdns.org), create a free subdomain (e.g. `paleco-crm.duckdns.org`), and point it to your Oracle Public IP.

### Step 7.2: Obtain Free SSL Certificate
Install Certbot and request an automated Let's Encrypt SSL certificate:
```bash
sudo apt install -y certbot python3-certbot-nginx

# Request certificate (Certbot will automatically configure Nginx for SSL)
sudo certbot --nginx -d your-domain.com
```
Select option to redirect all HTTP traffic to HTTPS. Certbot automatically configures auto-renewal cron jobs.

---

## Phase 8: Background Daemons with Supervisor (Reverb & Queue)

Supervisor ensures that **Laravel Reverb** (WebSockets) and the **Queue Worker** run continuously in the background and auto-restart if they crash or the server reboots.

Create `/etc/supervisor/conf.d/paleco.conf`:
```bash
sudo nano /etc/supervisor/conf.d/paleco.conf
```

Paste the following configuration:
```ini
[program:paleco-reverb]
process_name=%(program_name)s
command=php /var/www/paleco-crm/PALECO_OTRS-CRM/artisan reverb:start --host=127.0.0.1 --port=8080
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/supervisor/paleco-reverb.log

[program:paleco-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/paleco-crm/PALECO_OTRS-CRM/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/paleco-queue.log
```

Start the supervisor processes:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all
sudo supervisorctl status
```
*Output should show:*
```text
paleco-queue:paleco-queue_00   RUNNING   pid 1234, uptime 0:00:10
paleco-queue:paleco-queue_01   RUNNING   pid 1235, uptime 0:00:10
paleco-reverb                  RUNNING   pid 1236, uptime 0:00:10
```

---

## Phase 9: Production Performance Caching & OPcache

Now warm up Laravel’s production caches so API responses run in **~15ms–35ms**:

```bash
cd /var/www/paleco-crm/PALECO_OTRS-CRM

# Warm up framework compilation caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Rebuild frontend assets with the production domain
npm run build
```

### Enable PHP 8.4 OPcache
Edit the PHP-FPM configuration:
```bash
sudo nano /etc/php/8.4/fpm/php.ini
```
Find and set:
```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.revalidate_freq=0
opcache.validate_timestamps=0
```
Restart PHP-FPM:
```bash
sudo systemctl restart php8.4-fpm
```

---

## Phase 10: Verification Checklist & Troubleshooting Matrix

### Verification Checklist
- [ ] **Web CRM Portal:** Visit `https://your-domain.com` $\to$ Login page renders with full Tailwind styling and icons.
- [ ] **Liveness Probe:** Visit `https://your-domain.com/up` $\to$ Returns HTTP 200 OK.
- [ ] **WebSockets in Browser Console:** Open DevTools on the Web CRM $\to$ Console shows `Echo connected to wss://your-domain.com:443`.
- [ ] **Mobile API Endpoint:** Test in Postman: `GET https://your-domain.com/api/tickets` $\to$ Returns HTTP 401 Unauthorized (Sanctum protected).
- [ ] **Media Storage:** Upload a photo via Mobile App $\to$ Image is viewable in Web Desk details without 404 errors.

---

### Troubleshooting Matrix

| Issue / Symptom | Root Cause | Solution |
| :--- | :--- | :--- |
| **Site timed out / unreachable on Port 80/443** | Oracle host `iptables` rules dropping packets. | Run the `iptables -I INPUT 6 ...` commands in **Phase 2.2** and reload `ufw`. |
| **502 Bad Gateway (Nginx)** | PHP-FPM socket not running or permissions mismatched. | Verify status: `sudo systemctl status php8.4-fpm`. Confirm socket file exists at `/var/run/php/php8.4-fpm.sock`. |
| **WebSocket Connection Failed / 403 on Reverb** | Reverb daemon not running or Nginx proxy headers missing. | Check supervisor logs: `sudo cat /var/log/supervisor/paleco-reverb.log`. Ensure `proxy_set_header Upgrade $http_upgrade;` is in Nginx configuration. |
| **Uploaded Photos / Signatures return 404** | Missing public storage symlink. | Run `php artisan storage:link` inside `/var/www/paleco-crm/PALECO_OTRS-CRM`. |
| **Permission Denied writing to logs/sessions** | Ownership mismatch on storage folder. | Run `sudo chown -R www-data:www-data /var/www/paleco-crm/PALECO_OTRS-CRM/storage`. |
| **Code changes not appearing after git pull** | Production caches still holding old state. | Run `php artisan optimize:clear && php artisan config:cache && php artisan route:cache`. |

