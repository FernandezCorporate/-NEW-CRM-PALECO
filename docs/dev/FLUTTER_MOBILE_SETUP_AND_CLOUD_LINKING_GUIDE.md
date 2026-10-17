# PALECO OTRS-CRM: Flutter Mobile App Setup & Cloud Linking Guide

> **Audience:** Development Team, QA Testers, and Mobile Developers  
> **Target Environment:** Android Physical Devices & Emulators  
> **Live Cloud Backend:** Render.com (`https://paleco-otrs.onrender.com/api/v1`)  
> **WebSocket Gateway:** Pusher Channels (`cluster: ap1`, port 443)  
> **Media Storage:** Supabase S3 (`paleco-media`)

---

## 1. Overview & Architecture

The PALECO OTRS-CRM Mobile Client is tailored specifically for **Supervisors** (dispatching, team management, ticket review) and **Field Personnel / Linemen** (work execution, accomplishment reporting, photo evidence capture, and offline-async syncing).

```
┌─────────────────────────────────────────────────────────────┐
│                 Flutter Mobile Application                  │
│       (Supervisor Dashboard / Lineman Work Order Flow)      │
└──────────────┬──────────────────────────────┬───────────────┘
               │ HTTPS REST API Calls         │ WSS WebSockets (Port 443)
               ▼                              ▼
┌──────────────────────────────┐ ┌────────────────────────────┐
│   Render.com Web Service     │ │   Pusher Channels Cloud    │
│ (https://paleco-otrs.onrender│ │   (ap1 Singapore Cluster)  │
│  .com/api/v1/*)              │ │   App Key: ccb7b854b7132...│
└──────────────┬───────────────┘ └────────────────────────────┘
               │ Streams Multipart Photos
               ▼
┌──────────────────────────────┐
│       Supabase Storage       │
│  (paleco-media Public Bucket)│
└──────────────────────────────┘
```

---

## 2. Prerequisites & Development Environment

Ensure your development machine has the following tools installed:

1. **Flutter SDK:** Version 3.19.0 or higher ([Download Flutter](https://docs.flutter.dev/get-started/install))
   - Verify installation: `flutter doctor -v`
2. **Android Studio & Android SDK:**
   - Android SDK Command-line Tools
   - Android SDK Build-Tools (API 34 or higher)
   - Android SDK Platform-Tools
3. **Java Development Kit (JDK):** Version 17 (OpenJDK or Oracle JDK)
4. **Physical Android Device (Recommended) or Android Emulator:**
   - Physical device: Enable **Developer Options** $\to$ Enable **USB Debugging**.

---

## 3. Cloud Backend Endpoint Configuration

All mobile API calls must target the versioned cloud endpoint `/api/v1/`.

### 3.1 Create or Update `lib/config/api_config.dart`

In your Flutter project under `lib/config/api_config.dart`, configure the endpoints and Pusher credentials:

```dart
class ApiConfig {
  // --- ENVIRONMENT TOGGLE ---
  static const bool isProduction = true;

  // --- BASE URLS ---
  // Production Cloud URL (Render.com)
  static const String _prodBaseUrl = 'https://paleco-otrs.onrender.com/api/v1';

  // Local Development URLs:
  // - 10.0.2.2 for default Android Studio Emulator
  // - 127.0.0.1 for desktop/web testing
  // - Or your workstation's LAN IP (e.g. 192.168.1.100:8000) for physical phone testing
  static const String _devBaseUrl = 'http://10.0.2.2:8000/api/v1';

  static String get baseUrl => isProduction ? _prodBaseUrl : _devBaseUrl;

  // --- AUTHENTICATION & SESSIONS ---
  static String get loginUrl => '$baseUrl/login';
  static String get logoutUrl => '$baseUrl/logout';
  static String get userProfileUrl => '$baseUrl/user/profile';

  // --- TICKETS LIFECYCLE ---
  static String get ticketsUrl => '$baseUrl/tickets';
  static String ticketDetailsUrl(String id) => '$baseUrl/tickets/$id';
  static String startWorkUrl(String id) => '$baseUrl/tickets/$id/start';
  static String accomplishUrl(String id) => '$baseUrl/tickets/$id/accomplish';
  static String assignUrl(String id) => '$baseUrl/tickets/$id/assign';
  static String assignOptionsUrl(String id) => '$baseUrl/tickets/$id/assign-options';
  static String endorseUrl(String id) => '$baseUrl/tickets/$id/endorse';
  static String endorseOptionsUrl(String id) => '$baseUrl/tickets/$id/endorse-options';
  static String ticketHistoryUrl(String id) => '$baseUrl/tickets/$id/history';

  // --- REAL-TIME WEBSOCKETS (PUSHER CHANNELS) ---
  static const String pusherApiKey = 'ccb7b854b71323e17dac';
  static const String pusherCluster = 'ap1';
  
  // Broadcast Authorization Endpoint for Private Channels
  static String get broadcastAuthUrl => isProduction
      ? 'https://paleco-otrs.onrender.com/api/v1/broadcasting/auth'
      : 'http://10.0.2.2:8000/api/v1/broadcasting/auth';
}
```

---

## 4. Dependencies Configuration (`pubspec.yaml`)

Ensure the following packages are included in your `pubspec.yaml`:

```yaml
dependencies:
  flutter:
    sdk: flutter

  # Networking & REST API
  http: ^1.2.0
  dio: ^5.4.1

  # Real-Time Pusher WebSockets
  pusher_channels_flutter: ^2.2.1

  # Local Offline Storage (Store-and-Forward Sync)
  hive: ^2.2.3
  hive_flutter: ^1.1.0

  # Device Hardware (Evidence Photos & Signature)
  image_picker: ^1.0.7
  signature: ^5.4.1
  path_provider: ^2.1.2
  device_info_plus: ^9.1.2
  connectivity_plus: ^5.0.2

  # State Management
  provider: ^6.1.1 # or flutter_bloc / riverpod
```

Run package installation:
```bash
flutter pub get
```

---

## 5. Android Manifest Permissions & Network Security

### 5.1 Android Permissions (`android/app/src/main/AndroidManifest.xml`)

Open `android/app/src/main/AndroidManifest.xml` and add the following permissions inside the `<manifest>` tag:

```xml
<manifest xmlns:android="http://schemas.android.com/apk/res/android">

    <!-- Network Access -->
    <uses-permission android:name="android.permission.INTERNET" />
    <uses-permission android:name="android.permission.ACCESS_NETWORK_STATE" />

    <!-- Camera for Lineman Accomplishment Photo Evidence -->
    <uses-permission android:name="android.permission.CAMERA" />
    <uses-feature android:name="android.hardware.camera" android:required="false" />

    <!-- File Storage Access for Photos -->
    <uses-permission android:name="android.permission.READ_EXTERNAL_STORAGE" android:maxSdkVersion="32" />
    <uses-permission android:name="android.permission.READ_MEDIA_IMAGES" />

    <application
        android:label="PALECO OTRS"
        android:icon="@mipmap/ic_launcher"
        android:usesCleartextTraffic="true"> <!-- Allows local dev http fallback -->
        ...
    </application>
</manifest>
```

---

## 6. Real-Time WebSockets Integration (Pusher)

### 6.1 Initialize Pusher Channels

Initialize the Pusher client upon successful user authentication:

```dart
import 'package:pusher_channels_flutter/pusher_channels_flutter.dart';
import '../config/api_config.dart';

class WebSocketService {
  static final WebSocketService _instance = WebSocketService._internal();
  factory WebSocketService() => _instance;
  WebSocketService._internal();

  final PusherChannelsFlutter _pusher = PusherChannelsFlutter.getInstance();

  Future<void> init(String bearerToken, int departmentId, String? teamId) async {
    try {
      await _pusher.init(
        apiKey: ApiConfig.pusherApiKey,
        cluster: ApiConfig.pusherCluster,
        useTLS: true,
        authEndpoint: ApiConfig.broadcastAuthUrl,
        onAuthorizer: (channelName, socketId, options) async {
          // Send Bearer token to authorize private channels
          final response = await http.post(
            Uri.parse(ApiConfig.broadcastAuthUrl),
            headers: {
              'Authorization': 'Bearer $bearerToken',
              'Accept': 'application/json',
              'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: {
              'socket_id': socketId,
              'channel_name': channelName,
            },
          );
          return jsonDecode(response.body);
        },
      );

      await _pusher.connect();

      // Subscribe to Private Operations Channel
      await _pusher.subscribe(channelName: 'private-cwd.operations');

      // Subscribe to Department Channel (Supervisor)
      await _pusher.subscribe(channelName: 'private-department.$departmentId');

      // Subscribe to Team Channel (Lineman)
      if (teamId != null) {
        await _pusher.subscribe(channelName: 'private-team.$teamId');
      }

      print("[Pusher] Connected and subscribed to real-time channels.");
    } catch (e) {
      print("[Pusher] Connection Error: $e");
    }
  }

  void bindEvent(String eventName, Function(dynamic) onEvent) {
    _pusher.bind(eventName: eventName, onEvent: onEvent);
  }

  Future<void> disconnect() async {
    await _pusher.disconnect();
  }
}
```

---

## 7. Authentication & Live Testing Credentials

When logging in from the mobile application, the backend requires three fields:
1. `username`
2. `password`
3. `device_name` (e.g. `Samsung Galaxy S21`, `Infinix Note 30`, or device model from `device_info_plus`)

### Default Seeded Accounts:

| Role | Username | Password | Intended Screen Flow |
| :--- | :--- | :--- | :--- |
| **Supervisor** | `mycka` | `Password` | Supervisor Dashboard, Crew Assignment, Ticket Review |
| **Field Lineman** | `ralph` | `Password` | Dispatched Tasks, Start Work, Accomplishment + Photos |

---

## 8. Accomplishment Uploads & Supabase S3 Integration

When a lineman completes work, the mobile app sends a `multipart/form-data` POST request to `/api/v1/tickets/{id}/accomplish`.

The backend automatically streams these files directly to **Supabase Storage S3** (`paleco-media`):

```dart
Future<bool> submitAccomplishment({
  required String ticketId,
  required String token,
  required String remarks,
  required String consumerName,
  required File signatureFile,
  required List<File> photoFiles,
}) async {
  final uri = Uri.parse(ApiConfig.accomplishUrl(ticketId));
  final request = http.MultipartRequest('POST', uri);

  request.headers['Authorization'] = 'Bearer $token';
  request.headers['Accept'] = 'application/json';

  request.fields['remarks'] = remarks;
  request.fields['consumer_name'] = consumerName;

  // Attach digital signature
  request.files.add(await http.MultipartFile.fromPath('signature', signatureFile.path));

  // Attach proof-of-work photos
  for (var photo in photoFiles) {
    request.files.add(await http.MultipartFile.fromPath('photos[]', photo.path));
  }

  final streamedResponse = await request.send();
  final response = await http.Response.fromStream(streamedResponse);

  return response.statusCode == 200 || response.statusCode == 201;
}
```

---

## 9. Compiling and Distributing the Production APK

### 9.1 Build Release APK
Run the production build command in your Flutter project directory:

```bash
flutter build apk --release
```

To optimize the APK size for lineman devices, build per-ABI splits:
```bash
flutter build apk --split-per-abi
```

### 9.2 Locate Output File
The compiled installer will be located at:
```
build/app/outputs/flutter-apk/app-release.apk
```

### 9.3 Installing on Test Phones
Connect the phone via USB and install directly:
```bash
flutter install
# or
adb install -r build/app/outputs/flutter-apk/app-release.apk
```

---

## 10. Troubleshooting & Common Pitfalls

| Issue | Root Cause | Solution |
| :--- | :--- | :--- |
| **HTTP 422 Unprocessable Entity on Login** | Missing `device_name` in JSON body | Ensure the login payload sends `{"username": "...", "password": "...", "device_name": "..."}`. |
| **WebSocket Private Channel Auth Fails (403/401)** | Missing or invalid Bearer token in `/broadcasting/auth` header | Confirm that `Authorization: Bearer <token>` is attached in the `onAuthorizer` callback. |
| **Camera crashes on photo capture** | Missing camera hardware feature or permission | Verify that `CAMERA` and `READ_MEDIA_IMAGES` permissions are declared in `AndroidManifest.xml`. |
| **Photos do not appear on web portal after submission** | Uploaded before commit `3ad2522` or wrong disk configuration | Ensure backend is running `master` with Supabase S3 integration. |

