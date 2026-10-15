<?php
require_once __DIR__ . '/mpesa_config.php';

/**
 * Get an OAuth access token from Safaricom.
 * Returns the token string, or false on failure.
 */
function mpesa_get_access_token() {
    $credentials = base64_encode(MPESA_CONSUMER_KEY . ':' . MPESA_CONSUMER_SECRET);
    $url = MPESA_BASE_URL . '/oauth/v1/generate?grant_type=client_credentials';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $credentials]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        error_log('M-Pesa token error: ' . $err);
        return false;
    }

    $data = json_decode($response, true);
    return $data['access_token'] ?? false;
}

/**
 * Normalize a Kenyan phone number to the 2547XXXXXXXX format Daraja expects.
 * Accepts 07XXXXXXXX, 7XXXXXXXX, 2547XXXXXXXX, +2547XXXXXXXX.
 */
function mpesa_normalize_phone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phone, 0, 1) === '0') {
        $phone = '254' . substr($phone, 1);
    } elseif (substr($phone, 0, 1) === '7' && strlen($phone) === 9) {
        $phone = '254' . $phone;
    }
    return $phone;
}

/**
 * Initiate an STK Push (Lipa Na M-Pesa Online) request.
 *
 * @param string $phone           Normalized 2547XXXXXXXX phone number
 * @param float  $amount          Amount in KSH
 * @param string $accountRef      Short reference shown to the customer (max ~12 chars)
 * @param string $transactionDesc Short description (max ~13 chars)
 * @return array ['success' => bool, 'data' => array|null, 'error' => string|null]
 */
function mpesa_stk_push($phone, $amount, $accountRef, $transactionDesc) {
    $token = mpesa_get_access_token();
    if (!$token) {
        return ['success' => false, 'data' => null, 'error' => 'Could not authenticate with M-Pesa. Check your Consumer Key/Secret in mpesa_config.php.'];
    }

    $timestamp = date('YmdHis');
    $password = base64_encode(MPESA_SHORTCODE . MPESA_PASSKEY . $timestamp);

    $payload = [
        'BusinessShortCode' => MPESA_SHORTCODE,
        'Password'          => $password,
        'Timestamp'         => $timestamp,
        'TransactionType'   => 'CustomerPayBillOnline',
        'Amount'            => (int) round($amount),
        'PartyA'            => $phone,
        'PartyB'            => MPESA_SHORTCODE,
        'PhoneNumber'       => $phone,
        'CallBackURL'       => MPESA_CALLBACK_URL,
        'AccountReference'  => substr($accountRef, 0, 12),
        'TransactionDesc'   => substr($transactionDesc, 0, 13),
    ];

    $ch = curl_init(MPESA_BASE_URL . '/mpesa/stkpush/v1/processrequest');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return ['success' => false, 'data' => null, 'error' => 'Network error contacting M-Pesa: ' . $err];
    }

    $data = json_decode($response, true);

    if (isset($data['ResponseCode']) && $data['ResponseCode'] === '0') {
        return ['success' => true, 'data' => $data, 'error' => null];
    }

    $errorMsg = $data['errorMessage'] ?? $data['ResponseDescription'] ?? 'Unknown error from M-Pesa. Check that your ngrok URL in mpesa_config.php is current.';
    return ['success' => false, 'data' => $data, 'error' => $errorMsg];
}
