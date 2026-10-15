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

$trainer_id = (int)$_SESSION['trainer_id'];
$user_id    = (int)($_POST['user_id'] ?? 0);
$note       = trim($_POST['note'] ?? '');

if ($user_id <= 0 || $note === '') {
    header("Location: trainer_dashboard.php?note_error=invalid#Notes");
    exit();
}

// Make sure the target is actually a member
$check = $conn->prepare("SELECT id FROM users WHERE id=? AND role='member'");
$check->bind_param("i", $user_id);
$check->execute();
if ($check->get_result()->num_rows !== 1) {
    $check->close();
    $conn->close();
    header("Location: trainer_dashboard.php?note_error=invalid#Notes");
    exit();
}
$check->close();

$stmt = $conn->prepare("INSERT INTO member_notes (trainer_id, user_id, note) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $trainer_id, $user_id, $note);
$stmt->execute();
$stmt->close();
$conn->close();

header("Location: trainer_dashboard.php?note_success=1#Notes");
exit();
