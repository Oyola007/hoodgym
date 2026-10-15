# M-Pesa Payment Module — Setup Guide

## Files added
- `mpesa_config.php` — your Daraja credentials + callback URL
- `mpesa_functions.php` — access token, phone normalization, STK push logic
- `initiate_payment.php` — AJAX endpoint the modal calls to start a payment
- `mpesa_callback.php` — Daraja calls this when the payment finishes
- `check_payment_status.php` — AJAX endpoint the browser polls for status
- `packages.php` — replaces your existing file; adds the "Pay with M-Pesa" modal
  (the old "request without paying" flow is kept as a secondary option for
  front-desk cash payments)

## 1. Drop the files in
Copy all files into your HoodGym root folder (same level as `db.php`),
overwriting the existing `packages.php`.

## 2. Fill in your credentials
Open `mpesa_config.php` and set:
```php
define('MPESA_CONSUMER_KEY', '...');
define('MPESA_CONSUMER_SECRET', '...');
```
Leave `MPESA_SHORTCODE` and `MPESA_PASSKEY` as-is if you're using Safaricom's
standard sandbox test shortcode (174379) — only change them if your app was
issued different sandbox values.

## 3. Expose localhost with ngrok
Daraja must be able to reach your callback URL over the public internet, so
`localhost` alone won't work.

1. Download ngrok: https://ngrok.com/download
2. Start XAMPP (Apache + MySQL running)
3. Run:
   ```
   ngrok http 80
   ```
   (use whatever port Apache listens on — 80 by default, or 8080 if you changed it)
4. Copy the `https://xxxx.ngrok-free.app` URL it gives you
5. Paste it into `mpesa_config.php`:
   ```php
   define('MPESA_CALLBACK_BASE_URL', 'https://xxxx.ngrok-free.app');
   ```
   If HoodGym sits in a subfolder like `http://localhost/hoodgym/`, also set:
   ```php
   define('MPESA_APP_PATH', '/hoodgym');
   ```

**Note:** free ngrok URLs change every time you restart it — update
`mpesa_config.php` again each time, or use `check_payment_status.php` as a
diagnostic if payments seem to "hang" (usually means the callback URL is stale).

## 4. Test it
Use Safaricom's official sandbox test number when trying the STK Push:
- Phone: `254708374149`
- Any PIN works in sandbox — you'll get a simulated prompt, not a real one
  (sandbox doesn't send an actual phone popup; some setups require you to
  simulate the callback manually via Postman if the push isn't delivered —
  Safaricom's sandbox delivery can be inconsistent)

Watch your Apache error log (`xampp/apache/logs/error.log`) — both
`mpesa_functions.php` and `mpesa_callback.php` log errors there, which is the
fastest way to debug auth or callback issues.

## 5. Going to production later
When you're ready to accept real payments:
1. Apply for a production shortcode (paybill/till) via the Daraja portal
2. Set `MPESA_ENV` to `'production'`
3. Replace `MPESA_SHORTCODE` and `MPESA_PASSKEY` with your real ones
4. Point `MPESA_CALLBACK_BASE_URL` at your live domain (no more ngrok needed)

## How the flow works
1. Member clicks **Pay with M-Pesa** on a package → modal opens, pre-filled
   with their phone number from the `users` table
2. Browser calls `initiate_payment.php` → server calls Daraja's STK Push API
   → a `Pending` row is inserted into `payments`
3. Member enters their M-Pesa PIN on their phone
4. Safaricom calls `mpesa_callback.php` → payment marked `Completed` or
   `Failed`, and on success a matching `bookings` row is auto-set to
   `Approved` (no admin review needed, since it's already paid)
5. Meanwhile the browser polls `check_payment_status.php` every 3 seconds and
   reloads the page once it sees `Completed`
