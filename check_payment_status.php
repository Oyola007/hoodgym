<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not logged in.']);
    exit();
}

require_once __DIR__ . '/db.php';

$checkoutRequestId = $_GET['checkout_request_id'] ?? '';
if (!$checkoutRequestId) {
    echo json_encode(['status' => 'error', 'message' => 'Missing request ID.']);
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = mysqli_prepare($conn, "SELECT status FROM payments WHERE transaction_id = ? AND user_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "si", $checkoutRequestId, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$payment = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$payment) {
    echo json_encode(['status' => 'Pending']);
    exit();
}

// payments.status is 'Pending' | 'Completed' | 'Failed'
echo json_encode(['status' => $payment['status']]);
