<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: admin_dashboard.php#Classes");
    exit();
}

$conn = new mysqli("localhost", "root", "", "hoodgym");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$id           = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
$class_name   = trim($_POST['class_name'] ?? '');
$trainer      = trim($_POST['trainer'] ?? '');
$day          = trim($_POST['day'] ?? '');
$time         = trim($_POST['time'] ?? '');
$capacity     = ($_POST['capacity'] ?? '') !== '' ? (int)$_POST['capacity'] : null;

if ($class_name === '' || $trainer === '' || $day === '' || $time === '') {
    header("Location: admin_dashboard.php?class_error=1#Classes");
    exit();
}

// Make sure the chosen name really is an allocated trainer
$check = $conn->prepare("SELECT id FROM users WHERE username=? AND role='trainer' AND allocated=1");
$check->bind_param("s", $trainer);
$check->execute();
if ($check->get_result()->num_rows === 0) {
    $check->close();
    $conn->close();
    header("Location: admin_dashboard.php?class_error=invalid_trainer#Classes");
    exit();
}
$check->close();

if ($id) {
    $stmt = $conn->prepare("UPDATE classes SET class_name=?, trainer=?, day=?, time=?, capacity=? WHERE id=?");
    $stmt->bind_param("ssssii", $class_name, $trainer, $day, $time, $capacity, $id);
} else {
    $stmt = $conn->prepare("INSERT INTO classes (class_name, trainer, day, time, capacity) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $class_name, $trainer, $day, $time, $capacity);
}

$stmt->execute();
$stmt->close();
$conn->close();

header("Location: admin_dashboard.php#Classes");
exit();
