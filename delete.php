<?php
$conn = new mysqli("localhost", "root", "", "hoodgym");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$id = intval($_GET['id']);
$conn->query("DELETE FROM users WHERE id=$id");

header("Location: admin_dashboard.php");
exit();
?>
