# PALECO OTRS - Backend & API Setup Guide

This repository contains the backend API and web administrative portal for the PALECO Service Ticket ecosystem. This guide is tailored for mobile developers to easily set up the local backend environment and connect the Flutter application to the API, as well as accessing the web-based Blade interface.

## 🌟 System Features Overview

1. **Admin Web Portal:** Complete CRUD management for System Users, Departments, Teams (and their members), and Ticket Categories.
2. **CWD Web Portal:** Primary hub for registering, tracking, and queuing incoming utility Service Tickets.
3. **Mobile API:** Exclusive gateway for **Foremen** and **Field Personnel** to securely authenticate, fetch assigned tickets, and upload completion proofs.

---

## 🛠️ Local Setup Guide

Follow these steps to get the backend and frontend assets running locally on your machine.

### 1. Requirements
Ensure you have the following installed on your machine:
* **PHP** >= 8.1
* **Composer**
* **Node.js & NPM** (Required for compiling Tailwind CSS and Tom Select dependencies)
* **MySQL Server** (via XAMPP, WAMP, MAMP, or standard local install)

### 2. Installation
Clone the repository and install both the backend PHP dependencies and frontend Node dependencies:
```bash
git clone <repository_url>
cd <repository_folder>
composer install
npm install
```

### 3. Environment Configuration
Copy the environment example file and generate the application key:
```bash
cp .env.example .env
php artisan key:generate
```
Open the `.env` file and configure your application URL and database credentials. Update them to match your local MySQL server setup:
```env
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=paleco_otrs
DB_USERNAME=root
DB_PASSWORD="1234"
```

### 4. Database Migration & Seeding
*Note: Make sure your MySQL server is running and you have created a blank database named `paleco_otrs`.*

Run the migrations and seed the database with the required roles and test accounts:
```bash
php artisan migrate:fresh --seed
```

### 5. Serving the Application
Depending on what you are testing, use one of the following methods to start the system:

**Option A: Web Portal & Blade Interface (Full Stack)**
To access the web system and compile the frontend assets (Tailwind CSS, Tom Select), use the following full-stack command. This will start the local server and Vite for hot-module replacement:
```bash
composer run dev
```
*You can now access the web system at `http://localhost:8000`.*

**Option B: Mobile API Testing (CRITICAL FOR PHYSICAL DEVICES)**
If you are testing the mobile app on a physical device or an Android emulator, it **cannot** communicate with `localhost`. You must host the backend on your local network IP:
```bash
php artisan serve --host=0.0.0.0 --port=8000
```
*In your Flutter app's API configuration, replace `http://localhost:8000` with your computer's actual IPv4 address (e.g., `http://192.168.1.15:8000`).*

---

## 🔑 Test Accounts

The database seeder automatically creates the following test accounts. **The password for all accounts is `password`.**

| Username     | System Role       | Portal Access       |
| ------------ | ----------------- | ------------------- |
| `allenglenn` | Admin             | Web Browser Only    |
| `alliah`     | CWD Officer       | Web Browser Only    |
| `mycka`      | Foreman           | **Mobile API Only** |
| `ralph`      | Field Personnel   | **Mobile API Only** |

*(Note: Admin and CWD accounts are strictly blocked from logging into the mobile app, and mobile users are blocked from the web portal).*

---

## 📱 Mobile API Reference

All requests to the backend must include the following header to force Laravel to return JSON validation errors instead of web HTML redirects:
* `Accept: application/json`

### 1. Authentication (Login)
Authenticates a mobile user and issues a Sanctum API access token.

* **Endpoint:** `POST /api/login`
* **Headers Required:**
  * `Accept: application/json`
  * `Content-Type: application/json`

**Expected JSON Payload:**
```json
{
    "username": "mycka",
    "password": "password",
    "device_name": "Android_Emulator" 
}
```
*(Note: `device_name` is required by Laravel Sanctum to label the generated token).*

**Success Response (`200 OK`):**
```json
{
    "message": "Authentication successful.",
    "access_token": "1|abc123def456ghi789jkl012mno",
    "token_type": "Bearer",
    "user": {
        "id": "01H...ULID...",
        "username": "mycka",
        "first_name": "mycka",
        "last_name": "foreman",
        "role": "foreman",
        "department_id": 1
    }
}
```
*⚠️ **Crucial Step:** Save the `access_token` securely on the device (e.g., using `flutter_secure_storage`). It must be passed in the headers of all subsequent API requests.*

### 2. Secure Logout
Revokes the current access token and logs the user out of the device.

* **Endpoint:** `POST /api/logout`
* **Headers Required:**
  * `Accept: application/json`
  * `Authorization: Bearer <your_saved_access_token_here>`

**Expected Payload:** 
*(None required)*

**Success Response (`200 OK`):**
```json
{
    "message": "Successfully logged out and token revoked."
}
```

### 3. Handling Validation & Authentication Errors
If validation fails, credentials are wrong, or the account is locked, the API will return standard HTTP error codes (`422 Unprocessable Entity` or `401 Unauthorized`). 

Extract the user-friendly errors directly from the response and map them to your Flutter UI:

**Example Error Response (`422 Unprocessable Entity`):**
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "username": ["The username field is required."]
    }
}
```

**Example Error Response (`401 Unauthorized`):**
```json
{
    "message": "Invalid credentials or account is deactivated."
}
```