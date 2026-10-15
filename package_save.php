<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: admin_dashboard.php#Packages");
    exit();
}

$conn = new mysqli("localhost", "root", "", "hoodgym");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$id            = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
$name          = trim($_POST['name'] ?? '');
$description   = trim($_POST['description'] ?? '');
$price         = (float)($_POST['price'] ?? 0);
$duration_days = (int)($_POST['duration_days'] ?? 30);

if ($name === '' || $price < 0 || $duration_days <= 0) {
    header("Location: admin_dashboard.php?package_error=1#Packages");
    exit();
}

if ($id) {
    // UPDATE existing package
    $stmt = $conn->prepare("UPDATE packages SET name=?, description=?, price=?, duration_days=? WHERE id=?");
    $stmt->bind_param("ssdii", $name, $description, $price, $duration_days, $id);
} else {
    // INSERT new package
    $stmt = $conn->prepare("INSERT INTO packages (name, description, price, duration_days) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssdi", $name, $description, $price, $duration_days);
}

$stmt->execute();
$stmt->close();
$conn->close();

header("Location: admin_dashboard.php#Packages");
exit();
