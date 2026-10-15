<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "hoodgym");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id) {
    $stmt = $conn->prepare("SELECT photo FROM community_spotlight WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row && $row['photo'] && file_exists(__DIR__ . '/' . $row['photo'])) {
        @unlink(__DIR__ . '/' . $row['photo']);
    }

    $del = $conn->prepare("DELETE FROM community_spotlight WHERE id = ?");
    $del->bind_param("i", $id);
    $del->execute();
    $del->close();
}

$conn->close();
header("Location: admin_dashboard.php?spotlight_success=1#Community");
exit();
