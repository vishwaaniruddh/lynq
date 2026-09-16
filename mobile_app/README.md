# LYNQ Engineer Mobile App (Expo / React Native)

A dedicated mobile app built for the **Engineer Role** in the LYNQ platform.

## Features
- **JWT Authentication**: Full login integration with automatic token refresh (`/api/auth/login.php`, `/api/auth/refresh.php`).
- **Dashboard Overview**: Status counters (Assigned, In Progress, Completed, Feasibility Pending) and quick actions.
- **Assigned Sites & Search**: Filter by status and search by ATM ID, Bank, City, or State.
- **Site Details & Navigation**:
  - One-tap phone dialer for site contact person
  - Google Maps directions / GPS navigation
  - Status updates (Start Visit / Complete)
  - ETA scheduler
- **Feasibility Inspection Form**:
  - General Info, Power & UPS, Civil & Space, Network & Signal strength
  - Site photo capture via Camera or Gallery selection (`expo-image-picker`)
  - Offline Draft saving via `AsyncStorage` + API submission (`/api/engineer/feasibility.php`)
- **Server URL Switcher**: Seamlessly switch between Localhost, Production (`https://lynq.advantagesb.com`), and Local Wi-Fi IP for physical device testing.

---

## Engineer Test Credentials
- **Email/Username**: `vikaspal@gmail.com`
- **Password**: `rootroot`

---

## How to Run with Expo Go

1. Navigate to the `mobile_app` folder:
   ```bash
   cd mobile_app
   ```

2. Start the Expo development server:
   ```bash
   npx expo start
   ```

3. **To test on Android / iOS (Physical Phone via Expo Go)**:
   - Install **Expo Go** from Google Play Store or Apple App Store.
   - Ensure your phone is on the **same Wi-Fi network** as your computer.
   - Scan the QR code displayed in your terminal using Expo Go.
   - In the login screen of the app, tap the **Server URL** link at the bottom and enter your computer's Wi-Fi IP address (e.g. `http://192.168.1.50/lynq` or `https://lynq.advantagesb.com`).

4. **To test on Android Emulator / Web**:
   - Press `a` in the terminal to open on connected Android emulator.
   - Press `w` to run in web browser.

---

## Building an Android APK (Standalone)

To generate a standalone `.apk` using EAS Build:
```bash
npx eas-cli login
npx eas-cli build -p android --profile preview
```
