<?php
session_start();
unset($_SESSION['hoodgym_chat_history']);
header('Content-Type: application/json');
echo json_encode(['ok' => true]);
