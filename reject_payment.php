<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "hoodgym");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header("Location: admin_dashboard.php?payment_error=invalid_id#payments");
    exit();
}

$stmt = $conn->prepare("UPDATE payments SET status = 'Rejected' WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    header("Location: admin_dashboard.php?payment_success=rejected#payments");
} else {
    header("Location: admin_dashboard.php?payment_error=update_failed#payments");
}

$stmt->close();
$conn->close();
exit();
