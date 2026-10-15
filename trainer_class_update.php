<?php
session_start();
if (!isset($_SESSION['trainer_id'])) {
    header("Location: trainer_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: trainer_dashboard.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "hoodgym");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$class_id  = (int)($_POST['id'] ?? 0);
$day       = trim($_POST['day'] ?? '');
$time      = trim($_POST['time'] ?? '');
$capacity  = ($_POST['capacity'] !== '') ? (int)$_POST['capacity'] : null;
$trainer_username = $_SESSION['trainer_username'];

$valid_days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

if ($class_id <= 0 || !in_array($day, $valid_days, true) || $time === '') {
    header("Location: trainer_dashboard.php?class_error=invalid");
    exit();
}

// Ownership check: this class must belong to the logged-in trainer
$check = $conn->prepare("SELECT id FROM classes WHERE id=? AND trainer=?");
$check->bind_param("is", $class_id, $trainer_username);
$check->execute();
if ($check->get_result()->num_rows !== 1) {
    $check->close();
    $conn->close();
    header("Location: trainer_dashboard.php?class_error=not_yours");
    exit();
}
$check->close();

$stmt = $conn->prepare("UPDATE classes SET day=?, time=?, capacity=? WHERE id=? AND trainer=?");
$stmt->bind_param("ssiis", $day, $time, $capacity, $class_id, $trainer_username);
$stmt->execute();
$stmt->close();
$conn->close();

header("Location: trainer_dashboard.php?class_success=updated");
exit();
