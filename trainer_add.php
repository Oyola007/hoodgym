<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: admin_dashboard.php#Trainers");
    exit();
}

$conn = new mysqli("localhost", "root", "", "hoodgym");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$mode = $_POST['mode'] ?? '';

if ($mode === 'create') {
    // ---- Create a brand-new trainer account ----
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($username === '' || strlen($password) < 6 || $password !== $confirm) {
        header("Location: admin_dashboard.php?trainer_error=1#Trainers");
        exit();
    }

    // Make sure the username isn't already taken
    $check = $conn->prepare("SELECT id FROM users WHERE username=?");
    $check->bind_param("s", $username);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $check->close();
        $conn->close();
        header("Location: admin_dashboard.php?trainer_error=username_taken#Trainers");
        exit();
    }
    $check->close();

    $hashed = password_hash($password, PASSWORD_DEFAULT);

    // New trainers created directly by the admin are allocated immediately
    $stmt = $conn->prepare("INSERT INTO users (username, password, role, allocated) VALUES (?, ?, 'trainer', 1)");
    $stmt->bind_param("ss", $username, $hashed);
    $stmt->execute();
    $stmt->close();

} elseif ($mode === 'promote') {
    // ---- Promote an existing member to trainer ----
    $user_id = (int)($_POST['user_id'] ?? 0);

    if ($user_id <= 0) {
        header("Location: admin_dashboard.php?trainer_error=1#Trainers");
        exit();
    }

    // Only allow promoting accounts that are currently members
    $stmt = $conn->prepare("UPDATE users SET role='trainer', allocated=1 WHERE id=? AND role='member'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

} else {
    header("Location: admin_dashboard.php?trainer_error=1#Trainers");
    exit();
}

$conn->close();
header("Location: admin_dashboard.php#Trainers");
exit();
