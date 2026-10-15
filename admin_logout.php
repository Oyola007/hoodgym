<?php
session_start();
unset($_SESSION['trainer_id'], $_SESSION['trainer_username']);
session_regenerate_id(true);
header("Location: admin_login.php");
exit();
