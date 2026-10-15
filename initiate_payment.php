<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'You must be logged in to pay.']);
    exit();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mpesa_functions.php';

$user_id = $_SESSION['user_id'];
$package_id = isset($_POST['package_id']) ? (int) $_POST['package_id'] : 0;
$phone_input = trim($_POST['phone'] ?? '');

if (!$package_id || !$phone_input) {
    echo json_encode(['success' => false, 'message' => 'Missing package or phone number.']);
    exit();
}

$phone = mpesa_normalize_phone($phone_input);
if (!preg_match('/^2547\d{8}$/', $phone)) {
    echo json_encode(['success' => false, 'message' => 'Enter a valid Safaricom number, e.g. 07XXXXXXXX.']);
    exit();
}

// Fetch package
$stmt = mysqli_prepare($conn, "SELECT id, name, price FROM packages WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $package_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$package = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$package) {
    echo json_encode(['success' => false, 'message' => 'Package not found.']);
    exit();
}

// Block duplicate payment if the member already has this package active
$check = mysqli_prepare($conn, "SELECT id FROM bookings WHERE user_id = ? AND package_id = ? AND status = 'Approved'");
mysqli_stmt_bind_param($check, "ii", $user_id, $package_id);
mysqli_stmt_execute($check);
mysqli_stmt_store_result($check);
if (mysqli_stmt_num_rows($check) > 0) {
    mysqli_stmt_close($check);
    echo json_encode(['success' => false, 'message' => 'You already have an active package.']);
    exit();
}
mysqli_stmt_close($check);

$accountRef = 'HOODGYM' . $package_id;
$desc = 'Gym Package';

$stk = mpesa_stk_push($phone, (float) $package['price'], $accountRef, $desc);

if (!$stk['success']) {
    echo json_encode(['success' => false, 'message' => $stk['error']]);
    exit();
}

$checkoutRequestId = $stk['data']['CheckoutRequestID'] ?? null;

if (!$checkoutRequestId) {
    echo json_encode(['success' => false, 'message' => 'M-Pesa did not return a request ID. Please try again.']);
    exit();
}

// Record a Pending payment, keyed by CheckoutRequestID so the callback can find it
$insert = mysqli_prepare($conn, "INSERT INTO payments (user_id, package_id, amount, method, status, transaction_id) VALUES (?, ?, ?, 'mpesa', 'Pending', ?)");
mysqli_stmt_bind_param($insert, "iids", $user_id, $package_id, $package['price'], $checkoutRequestId);
mysqli_stmt_execute($insert);
mysqli_stmt_close($insert);

echo json_encode([
    'success' => true,
    'message' => 'Check your phone and enter your M-Pesa PIN to complete payment.',
    'checkout_request_id' => $checkoutRequestId,
]);
