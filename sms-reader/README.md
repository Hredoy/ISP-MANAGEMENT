# ISP OS SMS Reader

Android companion app for `[S1-D3] React Native bKash/Nagad/Rocket SMS reader app`.
Runs in the background, detects bKash/Nagad/Rocket "cash in" SMS, parses the
transaction, and POSTs it to the ISP's ISP OS instance for auto-matching
against unpaid invoices (see the companion backend task, Payment SMS
auto-match API).

## What's implemented

- `src/parsers/smsParsers.ts` — provider detection + regex parsing of amount,
  sender phone, transaction ID, and timestamp. Covered by
  `src/parsers/__tests__/smsParsers.test.ts`.
- `src/services/smsListenerService.ts` — wires `react-native-android-sms-listener`
  to the parser, on-device dedup, and the API client.
- `src/storage/dedupStore.ts` — tracks the last 500 forwarded transaction IDs
  in `AsyncStorage` so a re-delivered/re-read SMS isn't posted twice.
- `src/storage/settingsStore.ts` + `src/screens/SettingsScreen.tsx` — on-device
  configuration of the ISP OS API URL and device token (each install belongs
  to one tenant, so this isn't hardcoded).
- `src/screens/StatusScreen.tsx` — live log of forwarded/skipped/ignored SMS,
  useful for on-site troubleshooting.
- `src/permissions/smsPermissions.ts` — requests `RECEIVE_SMS`/`READ_SMS` at
  runtime (required on Android 6+).

## Honesty flag — read before relying on this in production

The regex patterns in `smsParsers.ts` follow each provider's publicly known
"cash in" SMS template, but **no live bKash/Nagad/Rocket merchant account was
available to test against** in this environment. Wording varies by operator,
app version, and account type (personal vs. merchant vs. agent). Same caveat
class as `app/Services/Olt/*` in the main app: verify against real payment
SMS on a real device before trusting auto-matching in production, and expect
to tweak the regexes in `PATTERNS` once you see actual samples.

## Native scaffold

This directory contains the JS/TS application code only. It was not run
through `npx react-native init` in this environment (no Android SDK / network
access here), so the generated `android/` and `ios/` native project folders
are not present yet. To get a runnable app:

```bash
npx react-native init IspOsSmsReader --version 0.74.3 --skip-install
# then copy App.tsx, index.js, src/, package.json, babel.config.js,
# metro.config.js, tsconfig.json from this folder into the generated project
# (or generate into a temp dir and copy its android/ folder into this one).
npm install
```

Add to the generated `android/app/src/main/AndroidManifest.xml`:

```xml
<uses-permission android:name="android.permission.RECEIVE_SMS" />
<uses-permission android:name="android.permission.READ_SMS" />
```

## Backend contract

`POST {apiUrl}/api/mobile/sms-transactions`, `Authorization: Bearer <deviceToken>`:

```json
{
  "provider": "bKash",
  "amount": 500,
  "sender_phone": "01712345678",
  "transaction_id": "8N7A6S5D4F",
  "sms_timestamp": "01/07/2026 14:30",
  "raw_sender": "bKash",
  "raw_body": "You have received Tk 500.00 from 01712345678 ..."
}
```

This endpoint doesn't exist yet — it's the scope of the separate
"Payment SMS auto-match API + MikroTik auto-unblock" task.

## Tests

```bash
npm test
```

Runs the parser unit tests (`smsParsers.test.ts`) — the only part of this app
that's testable without an Android device/emulator.
