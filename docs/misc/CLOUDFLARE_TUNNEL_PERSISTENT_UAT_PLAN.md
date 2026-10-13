# PALECO OTRS-CRM: Persistent Cloudflare Tunnel & Mobile UAT Blueprint

**Document Target:** `docs/misc/CLOUDFLARE_TUNNEL_PERSISTENT_UAT_PLAN.md`  
**Document Version:** 1.0  
**Context:** Foolproof architecture and implementation plan to eliminate ephemeral URL churn during client User Acceptance Testing (UAT). Binds local PALECO OTRS-CRM (Web, API, and Reverb WebSockets) to a permanent, static HTTPS domain for $0.00, paired with dynamic base URL discovery for the Flutter mobile field client.  
**Target Codebase:** `PALECO_OTRS-CRM` (Laravel 13.x / Reverb) & Flutter Mobile Client (`docs/dev/FLUTTER_OFFLINE_SYNC_IMPLEMENTATION_SPEC.md`)  

---

## 1. Executive Summary & Root Cause Analysis

### 1.1 The Core Problem: The Ephemeral URL Dilemma
During User Acceptance Testing (UAT), clients and evaluators must test both the **Web CRM Dashboard** and the **Flutter Mobile Lineman Application** on real mobile devices under cellular data. 

Running the default Cloudflare Quick Tunnel command:
```bash
cloudflared tunnel --url http://localhost:8000
```
creates an **ad-hoc, ephemeral tunnel** that assigns a random hostname on each run:
```text
https://brave-platypus-8374.trycloudflare.com  ──>  Killed
https://gentle-mountain-1928.trycloudflare.com ──>  Restarted
https://silver-hawk-9041.trycloudflare.com     ──>  Next Day Reboot
```

### 1.2 The Damaging Ripple Effects on Mobile UAT
1. **Recompilation Friction:** The Flutter mobile application requires `baseUrl` in its Dio HTTP configuration. Every time the laptop sleeps, reboots, or restarts the terminal, developers must reconfigure `baseUrl`, rebuild the Android APK (`flutter build apk`), and redistributing a 30–50MB file to client stakeholders.
2. **Offline Outbox Invalidation:** If mobile testers record offline actions while on an old tunnel hostname, when connection returns, sync requests will fail with DNS resolution errors (`SocketException: Failed host lookup`).
3. **Tester Frustration:** Evaluators cannot reliably bookmark the web portal or test push notifications and WebSockets.

---

## 2. The Foolproof Two-Pillar Architecture

To make UAT 100% resilient, zero-friction, and zero-cost, we implement a two-pillar strategy:

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│                                 FOOLPROOF UAT ARCHITECTURE                                       │
│                                                                                                  │
│   PILLAR 1: INFRASTRUCTURE (Cloudflare Named Tunnel)                                             │
│   ┌───────────────────────────┐      ┌─────────────────────────┐      ┌──────────────────────┐   │
│   │ Fixed Domain / Subdomain  │ ───> │ Cloudflare Edge Network │ ───> │ Local Dev Machine    │   │
│   │ uat-crm.paleco.org / .xyz │      │ Static UUID Credentials │      │ Windows Background   │   │
│   │ (NEVER CHANGES)           │      │ Auto-reconnecting WSS   │      │ Service (Auto-Start) │   │
│   └───────────────────────────┘      └─────────────────────────┘      └──────────────────────┘   │
│                                                                                                  │
│   PILLAR 2: MOBILE CLIENT (Dynamic Base URL & Remote Beacon)                                     │
│   ┌───────────────────────────┐      ┌─────────────────────────┐      ┌──────────────────────┐   │
│   │ Mobile App Launch         │ ───> │ Static Remote Beacon    │ ───> │ In-App UAT Switcher  │   │
│   │ Reads SharedPrefs Cache   │      │ (Free GitHub Gist/JSON) │      │ QR Code Scan Option  │   │
│   │ Instant Boot / No Rebuild │      │ Overrides URL Instantly │      │ Fallback to Local LAN│   │
│   └───────────────────────────┘      └─────────────────────────┘      └──────────────────────┘   │
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```

* **Pillar 1 (Infrastructure):** Replace ephemeral *Quick Tunnels* with a **Cloudflare Named Persistent Tunnel** bound to a **static domain/subdomain**. Even if your computer reboots 100 times, changes Wi-Fi networks, or switches to a mobile hotspot, the public URL **never changes**.
* **Pillar 2 (Mobile Client):** Decouple the Flutter application's hardcoded compile-time `baseUrl` using a **Dynamic Discovery Beacon** and an **In-App UAT Environment Switcher**. If the server address ever changes, it updates over-the-air with **zero APK rebuilds**.

---

## 3. Pillar 1: Persistent Cloudflare Named Tunnel Setup ($0.00)

Cloudflare Named Tunnels allow you to map your local ports (`8000` for Laravel, `8080` for Reverb) to a fixed custom domain or subdomain completely free of charge.

### 3.1 Domain Strategy (How to Get a Static Hostname for Free)
A persistent tunnel requires a domain managed on Cloudflare DNS (Cloudflare DNS is 100% free). You have three options:
1. **Option A (Existing Domain / Subdomain - Recommended):** If anyone on the team owns any domain (e.g. `paleco-project.com`, `mycapstone.net`, or a personal portfolio domain like `johndoe.dev`), you can create a dedicated subdomain for free:
   * Web & API: `uat-crm.yourdomain.com`
   * Reverb WebSockets: `uat-ws.yourdomain.com`
2. **Option B (Ultra-Budget $1–$2 TLD):** Buy a cheap top-level domain (`.xyz`, `.top`, or `.lat`) on Namecheap, Cloudflare Registrar, or Porkbun for ~\$1.00 for an entire year.
3. **Option C (Free Managed Subdomain via DuckDNS / FreeDNS):** Point a free dynamic DNS subdomain to Cloudflare if a paid domain is completely inaccessible.

---

### 3.2 Step-by-Step Persistent Tunnel Installation (Windows)

#### Step 1: Install Cloudflare CLI (`cloudflared`)
Run PowerShell as Administrator:
```powershell
winget install --id Cloudflare.cloudflared
```
Verify the installation:
```powershell
cloudflared --version
```

#### Step 2: Authenticate with Cloudflare
Run:
```powershell
cloudflared tunnel login
```
* A browser window will open automatically.
* Select your Cloudflare zone/domain to authorize `cloudflared`.
* This downloads a certificate file to `C:\Users\<username>\.cloudflared\cert.pem`.

#### Step 3: Create the Persistent Named Tunnel
Create a named tunnel called `paleco-uat`:
```powershell
cloudflared tunnel create paleco-uat
```
Output:
```text
Tunnel credentials written to C:\Users\allen\.cloudflared\a1b2c3d4-e5f6-7890-abcd-1234567890ab.json.
Created tunnel paleco-uat with id a1b2c3d4-e5f6-7890-abcd-1234567890ab
```
> **Note:** Copy the generated **Tunnel ID** (UUID). This ID is permanent.

#### Step 4: Route the DNS Hostnames
Link your permanent subdomains directly to this Tunnel ID:
```powershell
# Route 1: Main Web CRM & Mobile API
cloudflared tunnel route dns paleco-uat uat-crm.yourdomain.com

# Route 2: Real-time Reverb WebSockets (Optional dedicated endpoint)
cloudflared tunnel route dns paleco-uat uat-ws.yourdomain.com
```
Cloudflare automatically inserts `CNAME` records in your DNS dashboard pointing to `<TUNNEL-ID>.cfargotunnel.com`.

#### Step 5: Configure Multi-Service Ingress Rules
Create a configuration file at `C:\Users\<username>\.cloudflared\config.yml`:

```yaml
tunnel: a1b2c3d4-e5f6-7890-abcd-1234567890ab
credentials-file: C:\Users\allen\.cloudflared\a1b2c3d4-e5f6-7890-abcd-1234567890ab.json

ingress:
  # 1. Real-Time Reverb WebSocket Ingress
  # Route /app/* on the main domain or via dedicated ws subdomain to port 8080
  - hostname: uat-ws.yourdomain.com
    service: http://localhost:8080
    originRequest:
      noTLSVerify: true
      connectTimeout: 30s

  # 2. Web CRM Portal & Mobile REST API (/api/*)
  - hostname: uat-crm.yourdomain.com
    service: http://localhost:8000
    originRequest:
      noTLSVerify: true
      connectTimeout: 30s

  # 3. Catch-all fallback rule (required by Cloudflare)
  - service: http_status:404
```

#### Step 6: Test the Persistent Tunnel
Start the tunnel manually to verify:
```powershell
cloudflared tunnel run paleco-uat
```
Now navigate to `https://uat-crm.yourdomain.com`:
* **Result:** Your local Laravel application loads over a verified, globally valid Cloudflare SSL certificate!
* **Persistence Test:** Press `Ctrl+C` to stop the tunnel. Wait 10 seconds. Re-run `cloudflared tunnel run paleco-uat`. The URL remains **100% identical**.

---

### 3.3 Installing as an Auto-Starting Windows Service

To ensure UAT testers never experience downtime when your terminal window is accidentally closed or your PC reboots, install `cloudflared` as a native Windows Service.

Run PowerShell as Administrator:
```powershell
# Install the tunnel as a Windows Service
cloudflared service install

# Copy config.yml and credential JSON to the system directory
New-Item -ItemType Directory -Force -Path "C:\Windows\System32\config\systemprofile\.cloudflared"
Copy-Item "C:\Users\allen\.cloudflared\*" "C:\Windows\System32\config\systemprofile\.cloudflared\" -Recurse

# Start the service
Start-Service cloudflared
```
* **Status:** `cloudflared` now runs quietly in the background as a Windows service.
* **Auto-Recovery:** If Windows restarts, the tunnel automatically reconnects to `https://uat-crm.yourdomain.com` within 15 seconds of boot.

---

## 4. Laravel Backend Configuration for Static Tunnel

Update `PALECO_OTRS-CRM/.env` to reflect the static domain:

```ini
# Application URL
APP_URL=https://uat-crm.yourdomain.com

# Reverb WebSockets Configuration
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=867789
REVERB_APP_KEY=8ygjucohu35acd17ckbm
REVERB_APP_SECRET=vl2uc2v7k9sa6oyiipip
REVERB_HOST="uat-ws.yourdomain.com"
REVERB_PORT=443
REVERB_SCHEME=https

# Frontend Echo Asset Injection
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME="https"
```

Recompile production assets and clear config cache:
```powershell
npm run build
php artisan config:clear
php artisan route:clear
```

---

## 5. Pillar 2: Flutter Mobile Dynamic Base URL Architecture

Even with a static domain, professional mobile development dictates that client APKs should **never** have an immutable, hardcoded `baseUrl`. If the dev team ever needs to switch from the Cloudflare Tunnel to an office LAN IP (`192.168.1.50:8000`) or a backup server, clients should not be forced to install a new APK.

### 5.1 Architecture: The 3-Tier Resolution Strategy
When the Flutter app launches, it resolves its active `baseUrl` using this priority cascade:

```
┌────────────────────────────────────────────────────────┐
│             BASE URL RESOLUTION PRIORITY               │
│                                                        │
│   1. Local Override (SharedPreferences)               │
│      (User manually entered/scanned a custom host)    │
│                        │ (if null)                     │
│                        ▼                               │
│   2. Remote Discovery Beacon (GitHub Gist / JSON)      │
│      (Over-the-air update without APK rebuild)         │
│                        │ (if network fails)            │
│                        ▼                               │
│   3. Compile-Time Fallback Constant                   │
│      (`https://uat-crm.yourdomain.com`)                │
└────────────────────────────────────────────────────────┘
```

---

### 5.2 Implementation Step 1: Environment & Discovery Service

Create `lib/core/network/environment_config.dart` in your Flutter project:

```dart
// lib/core/network/environment_config.dart
import 'dart:convert';
import 'package:dio/dio.dart';
import 'package:shared_preferences/shared_preferences.dart';

class EnvironmentConfig {
  static const String _prefKeyBaseUrl = 'uat_custom_base_url';
  
  // 1. Default Compile-Time Static Hostname (From Cloudflare Named Tunnel)
  static const String defaultBaseUrl = 'https://uat-crm.yourdomain.com';
  
  // 2. Free Remote Beacon URL (Public Raw GitHub Gist or Cloudflare Pages JSON)
  // Example format: {"base_url": "https://uat-crm.yourdomain.com", "reverb_host": "uat-ws.yourdomain.com"}
  static const String remoteBeaconUrl = 'https://gist.githubusercontent.com/<your-username>/<gist-id>/raw/paleco_uat_config.json';

  static String _activeBaseUrl = defaultBaseUrl;

  static String get activeBaseUrl => _activeBaseUrl;

  /// Initializes the active base URL on application startup
  static Future<void> initialize() async {
    final prefs = await SharedPreferences.getInstance();
    
    // Priority 1: Check manual local override
    final manualOverride = prefs.getString(_prefKeyBaseUrl);
    if (manualOverride != null && manualOverride.trim().isNotEmpty) {
      _activeBaseUrl = _sanitizeUrl(manualOverride);
      return;
    }

    // Priority 2: Fetch Remote Beacon (Non-blocking with 3-second timeout)
    try {
      final beaconDio = Dio(BaseOptions(connectTimeout: const Duration(seconds: 3)));
      final response = await beaconDio.get(remoteBeaconUrl);
      if (response.statusCode == 200) {
        final data = response.data is Map ? response.data : jsonDecode(response.data.toString());
        if (data['base_url'] != null && data['base_url'].toString().startsWith('http')) {
          _activeBaseUrl = _sanitizeUrl(data['base_url'].toString());
          return;
        }
      }
    } catch (_) {
      // Beacon unreachable (e.g. initial offline state) - fall back safely to default
    }

    // Priority 3: Fall back to default compile-time static hostname
    _activeBaseUrl = defaultBaseUrl;
  }

  /// Sets a new manual base URL (e.g. from QR scanner or in-app developer settings)
  static Future<void> setManualOverride(String newUrl) async {
    final prefs = await SharedPreferences.getInstance();
    final sanitized = _sanitizeUrl(newUrl);
    await prefs.setString(_prefKeyBaseUrl, sanitized);
    _activeBaseUrl = sanitized;
  }

  /// Clears manual override and resets to remote beacon/default
  static Future<void> resetToDefault() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_prefKeyBaseUrl);
    _activeBaseUrl = defaultBaseUrl;
  }

  static String _sanitizeUrl(String url) {
    var trimmed = url.trim();
    if (trimmed.endsWith('/')) {
      trimmed = trimmed.substring(0, trimmed.length - 1);
    }
    return trimmed;
  }
}
```

---

### 5.3 Implementation Step 2: Dynamic Dio Client Factory

Update `lib/core/network/api_client.dart` so Dio reacts dynamically to base URL updates:

```dart
// lib/core/network/api_client.dart
import 'package:dio/dio.dart';
import 'environment_config.dart';

Dio createApiClient({required String Function() getToken}) {
  final dio = Dio(
    BaseOptions(
      baseUrl: EnvironmentConfig.activeBaseUrl,
      connectTimeout: const Duration(seconds: 15),
      receiveTimeout: const Duration(seconds: 30),
      headers: {
        'Accept': 'application/json',
      },
    ),
  );

  // Dynamic Base URL Interceptor: Ensures requests always read the latest URL
  dio.interceptors.add(
    InterceptorsWrapper(
      onRequest: (options, handler) {
        // Enforce the globally active base URL
        options.baseUrl = EnvironmentConfig.activeBaseUrl;
        
        final token = getToken();
        if (token.isNotEmpty) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        return handler.next(options);
      },
    ),
  );

  return dio;
}
```

---

### 5.4 Implementation Step 3: In-App Developer & UAT Switcher Dialog

Add a hidden gesture on the login screen (e.g., **triple-tap the PALECO logo**) to open the UAT Server Switcher dialog. This allows mobile testers or developers to type a new tunnel URL or reset on the fly:

```dart
// lib/features/auth/presentation/widgets/uat_server_switcher_dialog.dart
import 'package:flutter/material.dart';
import '../../../../core/network/environment_config.dart';

class UatServerSwitcherDialog extends StatefulWidget {
  const UatServerSwitcherDialog({super.key});

  static void show(BuildContext context) {
    showDialog(
      context: context,
      builder: (context) => const UatServerSwitcherDialog(),
    );
  }

  @override
  State<UatServerSwitcherDialog> createState() => _UatServerSwitcherDialogState();
}

class _UatServerSwitcherDialogState extends State<UatServerSwitcherDialog> {
  late final TextEditingController _controller;

  @override
  void initState() {
    super.initState();
    _controller = TextEditingController(text: EnvironmentConfig.activeBaseUrl);
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Row(
        children: [
          Icon(Icons.tune, color: Color(0xFF008F5D)),
          SizedBox(width: 8),
          Text('UAT Server Config', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
        ],
      ),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Active Base URL for API & Sync:',
            style: TextStyle(fontSize: 12, color: Colors.grey),
          ),
          const SizedBox(height: 8),
          TextField(
            controller: _controller,
            decoration: const InputDecoration(
              border: OutlineInputBorder(),
              hintText: 'https://uat-crm.yourdomain.com',
              isDense: true,
            ),
          ),
          const SizedBox(height: 12),
          Text(
            'Default: ${EnvironmentConfig.defaultBaseUrl}',
            style: const TextStyle(fontSize: 11, color: Colors.blueGrey),
          ),
        ],
      ),
      actions: [
        TextButton(
          onPressed: () async {
            await EnvironmentConfig.resetToDefault();
            if (mounted) {
              Navigator.pop(context);
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('Reset to Default Static Hostname!')),
              );
            }
          },
          child: const Text('Reset', style: TextStyle(color: Colors.red)),
        ),
        ElevatedButton(
          style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF008F5D)),
          onPressed: () async {
            final text = _controller.text.trim();
            if (text.startsWith('http')) {
              await EnvironmentConfig.setManualOverride(text);
              if (mounted) {
                Navigator.pop(context);
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(content: Text('Server updated to: ${EnvironmentConfig.activeBaseUrl}')),
                );
              }
            }
          },
          child: const Text('Save & Apply', style: TextStyle(color: Colors.white)),
        ),
      ],
    );
  }
}
```

#### How to Wire into the Login Screen:
Wrap the PALECO logo in `GestureDetector`:
```dart
GestureDetector(
  onDoubleTap: () => UatServerSwitcherDialog.show(context),
  child: Image.asset('assets/images/paleco_logo.png', height: 72),
)
```

---

## 6. Host Machine Reliability & Keepalive Protocols

Because the UAT server is hosted on your local workstation, three operational risks must be mitigated:

### 6.1 Prevent Windows Sleep & Suspension
If your host laptop enters sleep mode, the tunnel drops and incoming mobile sync requests will time out.
1. In Windows Settings: **System > Power & battery**.
2. Under **Screen and sleep**:
   * *When plugged in, put my device to sleep after:* Set to **Never**.
3. Under **Lid close action** (Control Panel > Power Options):
   * *When I close the lid (Plugged in):* Set to **Do nothing**.

### 6.2 Keepalive & Auto-Restart Script for Laravel Daemons
Create a PowerShell watchdog script at `c:\Users\allen\Desktop\Capstone\-NEW-CRM-PALECO\start-uat-daemons.ps1`:

```powershell
# start-uat-daemons.ps1
Write-Host "==========================================================" -ForegroundColor Green
Write-Host " STARTING PALECO OTRS-CRM PERSISTENT UAT ENVIRONMENT      " -ForegroundColor Green
Write-Host "==========================================================" -ForegroundColor Green

Set-Location "c:\Users\allen\Desktop\Capstone\-NEW-CRM-PALECO\PALECO_OTRS-CRM"

# Ensure cloudflared service is running
$cfService = Get-Service -Name "cloudflared" -ErrorAction SilentlyContinue
if ($cfService -and $cfService.Status -ne "Running") {
    Write-Host "Starting Cloudflare Tunnel Windows Service..." -ForegroundColor Yellow
    Start-Service cloudflared
}

# Run the 4 core processes via Concurrently
Write-Host "Launching Web Server, Queue Worker, Vite Assets, and Reverb..." -ForegroundColor Cyan
composer dev
```

Whenever you want to host UAT, right-click `start-uat-daemons.ps1` and select **Run with PowerShell**.

---

## 7. Pre-UAT Verification Runbook & Health Checklist

Before distributing the mobile APK to client evaluators, perform this 5-minute pre-flight checklist:

| Verification Step | Verification Command / Action | Expected Result | Pass/Fail |
| :--- | :--- | :--- | :---: |
| **1. Web Portal Reachability** | Open browser on phone cellular data: `https://uat-crm.yourdomain.com/login` | Login page loads cleanly with green PALECO theme; SSL padlock verified. | [ ] |
| **2. API Health Check** | `curl -I https://uat-crm.yourdomain.com/up` | `HTTP/1.1 200 OK` | [ ] |
| **3. Reverb WebSocket Ping** | Open Web CRM Developer Console > Network > WS | Connection to `wss://uat-ws.yourdomain.com` or `/app/*` establishes with status `101 Switching Protocols`. | [ ] |
| **4. Mobile Login** | Enter Lineman credentials on Flutter APK via cellular network | Returns valid Sanctum Bearer token; lands on Assigned Tickets view. | [ ] |
| **5. Mobile Photo Upload** | Accomplish ticket with 3 photos + digital signature | Photos upload successfully; appear immediately in Web CRM ticket details. | [ ] |
| **6. Machine Reboot Test** | Restart host laptop completely. Wait 1 minute. Refresh phone browser. | Site comes back online automatically without manual intervention. | [ ] |

---

## 8. Summary of Benefits for PALECO Capstone UAT

1. **Static Hostname Forever:** `https://uat-crm.yourdomain.com` remains permanently fixed across laptop reboots, Wi-Fi changes, and network switches.
2. **Zero Mobile Recompilation:** The Flutter APK is built and signed **once**. Clients never need to receive updated APK builds due to tunnel restarts.
3. **Over-The-Air Control:** If you ever need to change the tunnel destination or switch to a backup server, you update the Remote Beacon JSON or have the tester double-tap the logo.
4. **$0.00 Total Expense:** Utilizes Cloudflare's free named tunnel infrastructure, free SSL termination, and free unlimited bandwidth.

