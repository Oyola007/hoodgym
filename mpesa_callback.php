<?php
/**
 * Daraja calls this URL once the customer completes (or cancels/fails) the
 * STK Push prompt on their phone. Must be publicly reachable — see
 * mpesa_config.php for the ngrok/production URL setup.
 *
 * This endpoint is hit by Safaricom's servers, not the browser, so there is
 * no session here — everything is looked up via the CheckoutRequestID we
 * stored when the payment was initiated.
 */
require_once __DIR__ . '/db.php';

// Log the raw payload for debugging — safe to remove once things are stable.
$raw = file_get_contents('php://input');
error_log('M-Pesa callback: ' . $raw);

$data = json_decode($raw, true);
$callback = $data['Body']['stkCallback'] ?? null;

if (!$callback) {
    http_response_code(400);
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Invalid payload']);
    exit();
}

$checkoutRequestId = $callback['CheckoutRequestID'] ?? null;
$resultCode = $callback['ResultCode'] ?? null;

if (!$checkoutRequestId) {
    http_response_code(400);
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Missing CheckoutRequestID']);
    exit();
}

// Find the pending payment we created in initiate_payment.php
$stmt = mysqli_prepare($conn, "SELECT id, user_id, package_id FROM payments WHERE transaction_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "s", $checkoutRequestId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$payment = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$payment) {
    // Nothing to update — acknowledge anyway so Safaricom stops retrying
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    exit();
}

if ((int) $resultCode === 0) {
    // Payment succeeded
    $update = mysqli_prepare($conn, "UPDATE payments SET status = 'Completed' WHERE id = ?");
    mysqli_stmt_bind_param($update, "i", $payment['id']);
    mysqli_stmt_execute($update);
    mysqli_stmt_close($update);

    // Auto-activate the membership: update an existing booking or create an Approved one
    $existing = mysqli_prepare($conn, "SELECT id FROM bookings WHERE user_id = ? AND package_id = ? ORDER BY id DESC LIMIT 1");
    mysqli_stmt_bind_param($existing, "ii", $payment['user_id'], $payment['package_id']);
    mysqli_stmt_execute($existing);
    $existingResult = mysqli_stmt_get_result($existing);
    $existingBooking = mysqli_fetch_assoc($existingResult);
    mysqli_stmt_close($existing);

    if ($existingBooking) {
        $upd = mysqli_prepare($conn, "UPDATE bookings SET status = 'Approved' WHERE id = ?");
        mysqli_stmt_bind_param($upd, "i", $existingBooking['id']);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);
    } else {
        $ins = mysqli_prepare($conn, "INSERT INTO bookings (user_id, package_id, status, created_at) VALUES (?, ?, 'Approved', NOW())");
        mysqli_stmt_bind_param($ins, "ii", $payment['user_id'], $payment['package_id']);
        mysqli_stmt_execute($ins);
        mysqli_stmt_close($ins);
    }
} else {
    // Cancelled on the phone, insufficient funds, timed out, etc.
    $update = mysqli_prepare($conn, "UPDATE payments SET status = 'Failed' WHERE id = ?");
    mysqli_stmt_bind_param($update, "i", $payment['id']);
    mysqli_stmt_execute($update);
    mysqli_stmt_close($update);
}

// Always acknowledge receipt so Safaricom doesn't keep retrying
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
