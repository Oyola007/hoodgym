<?php
/**
 * M-PESA (Daraja API) Configuration
 * ----------------------------------
 * Fill in your actual sandbox credentials below (from
 * https://developer.safaricom.co.ke/ under "My Apps").
 *
 * IMPORTANT: Update MPESA_CALLBACK_BASE_URL every time your ngrok URL
 * changes (free ngrok URLs change on every restart unless you have a
 * reserved domain).
 */

// Environment: 'sandbox' or 'production'
define('MPESA_ENV', 'sandbox');

// From your Daraja app dashboard
define('MPESA_CONSUMER_KEY', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
define('MPESA_CONSUMER_SECRET', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');

// Sandbox default test shortcode + passkey (Safaricom's public sandbox values).
// Replace both when you move to production with your real paybill/till.
define('MPESA_SHORTCODE', '174379');
define('MPESA_PASSKEY', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');

// Base URL Daraja will call back on. MUST be publicly reachable.
// Run `ngrok http 80` (or your XAMPP port), then paste the https URL it gives you here.
define('MPESA_CALLBACK_BASE_URL', 'https://YOUR-DOMAIN/mpesa_callback.php');

// If HoodGym lives in a subfolder (e.g. http://localhost/hoodgym/), include it here too, e.g. '/hoodgym'
define('MPESA_APP_PATH', '');

// Derived URL — no need to touch this
define('MPESA_CALLBACK_URL', MPESA_CALLBACK_BASE_URL . MPESA_APP_PATH . '/mpesa_callback.php');

// API base URL switches automatically based on environment
define('MPESA_BASE_URL', MPESA_ENV === 'production'
    ? 'https://api.safaricom.co.ke'
    : 'https://sandbox.safaricom.co.ke'
);
