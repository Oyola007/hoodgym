<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "hoodgym");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$id      = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
$name    = trim($_POST['name'] ?? '');
$tagline = trim($_POST['tagline'] ?? '');

if ($name === '') {
    header("Location: admin_dashboard.php?spotlight_error=missing_name#Community");
    exit();
}

// Handle photo upload (optional — keeps existing photo on edit if none provided)
$photo_path = null;
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $_FILES['photo']['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowed[$mime])) {
        header("Location: admin_dashboard.php?spotlight_error=bad_file_type#Community");
        exit();
    }
    if ($_FILES['photo']['size'] > 4 * 1024 * 1024) { // 4MB cap
        header("Location: admin_dashboard.php?spotlight_error=file_too_large#Community");
        exit();
    }

    $upload_dir = __DIR__ . '/uploads/community/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $ext = $allowed[$mime];
    $filename = 'spotlight_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = $upload_dir . $filename;

    if (!move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
        header("Location: admin_dashboard.php?spotlight_error=upload_failed#Community");
        exit();
    }
    $photo_path = 'uploads/community/' . $filename;
}

if ($id) {
    // Update existing entry
    if ($photo_path) {
        // Delete old photo file if present
        $old = $conn->prepare("SELECT photo FROM community_spotlight WHERE id = ?");
        $old->bind_param("i", $id);
        $old->execute();
        $oldPhoto = $old->get_result()->fetch_assoc()['photo'] ?? null;
        $old->close();
        if ($oldPhoto && file_exists(__DIR__ . '/' . $oldPhoto)) {
            @unlink(__DIR__ . '/' . $oldPhoto);
        }

        $stmt = $conn->prepare("UPDATE community_spotlight SET name=?, tagline=?, photo=? WHERE id=?");
        $stmt->bind_param("sssi", $name, $tagline, $photo_path, $id);
    } else {
        $stmt = $conn->prepare("UPDATE community_spotlight SET name=?, tagline=? WHERE id=?");
        $stmt->bind_param("ssi", $name, $tagline, $id);
    }
    $ok = $stmt->execute();
    $stmt->close();
} else {
    // Create new entry — push to the end of the display order
    $orderResult = $conn->query("SELECT COALESCE(MAX(display_order), 0) + 1 AS next_order FROM community_spotlight");
    $nextOrder = $orderResult->fetch_assoc()['next_order'];

    $stmt = $conn->prepare("INSERT INTO community_spotlight (name, tagline, photo, display_order) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $name, $tagline, $photo_path, $nextOrder);
    $ok = $stmt->execute();
    $stmt->close();
}

$conn->close();
header("Location: admin_dashboard.php?" . ($ok ? "spotlight_success=1" : "spotlight_error=save_failed") . "#Community");
exit();
