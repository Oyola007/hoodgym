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

$trainer_id       = (int)$_SESSION['trainer_id'];
$trainer_username = $_SESSION['trainer_username'];
$class_id     = (int)($_POST['class_id'] ?? 0);
$session_date = trim($_POST['session_date'] ?? '');
$statuses     = $_POST['status'] ?? []; // [user_id => 'present'|'absent']

if ($class_id <= 0 || $session_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $session_date)) {
    header("Location: trainer_dashboard.php?attendance_error=invalid");
    exit();
}

// Ownership check
$check = $conn->prepare("SELECT id FROM classes WHERE id=? AND trainer=?");
$check->bind_param("is", $class_id, $trainer_username);
$check->execute();
if ($check->get_result()->num_rows !== 1) {
    $check->close();
    $conn->close();
    header("Location: trainer_dashboard.php?attendance_error=not_yours");
    exit();
}
$check->close();

$stmt = $conn->prepare("
    INSERT INTO class_attendance (class_id, user_id, session_date, status, marked_by)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by)
");

foreach ($statuses as $user_id => $status) {
    $user_id = (int)$user_id;
    $status = ($status === 'present') ? 'present' : 'absent';
    if ($user_id <= 0) continue;
    $stmt->bind_param("iissi", $class_id, $user_id, $session_date, $status, $trainer_id);
    $stmt->execute();
}

$stmt->close();
$conn->close();

header("Location: trainer_dashboard.php?attendance_success=1#Attendance");
exit();
