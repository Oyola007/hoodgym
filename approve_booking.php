<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "hoodgym");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: admin_dashboard.php?booking_error=invalid_id#bookings");
    exit();
}

$stmt = $conn->prepare("UPDATE bookings SET status = 'Approved' WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        header("Location: admin_dashboard.php?booking_success=approved#bookings");
    } else {
        // Query succeeded but no row matched this id
        header("Location: admin_dashboard.php?booking_error=not_found#bookings");
    }
} else {
    header("Location: admin_dashboard.php?booking_error=update_failed#bookings");
}

$stmt->close();
$conn->close();
exit();
