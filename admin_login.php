<?php
session_start();
require_once __DIR__ . '/db.php';  // safer include

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $hashed_password, $role);
        $stmt->fetch();

        if (password_verify($password, $hashed_password)) {
            if ($role === 'admin') {
                // ✅ Only admins allowed
                $_SESSION['admin_id'] = $id;
                $_SESSION['admin_username'] = $username;
                header("Location: admin_dashboard.php");
                exit();
            } else {
                // 🚫 Block non-admins
                $error = "Access denied. Admins only.";
            }
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "No user found.";
    }
    $stmt->close();
}

if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>HoodGym | Admin Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&family=Orbitron:wght@600&display=swap" rel="stylesheet">
    <style>
       body {
            margin:0;
            font-family:'Poppins',sans-serif;
            background: url('imagesadmin.png') no-repeat center center fixed;
            background-size: cover;
            color:#fff;
            display:flex;
            justify-content:center;
            align-items:center;
            height:100vh;
            overflow:hidden;
        }
        .login-box {
            background:rgba(20,20,20,0.9);
            border:2px solid #0ff;
            border-radius:16px;
            padding:40px;
            width:350px;
            box-shadow:0 0 25px #0ff, inset 0 0 15px #0ff;
            animation: glow 3s infinite alternate;
        }
        h2 { text-align:center; color:#0ff; font-family:'Orbitron',sans-serif; }
        .error { color:#ff4d4d; text-align:center; margin-bottom:10px; }
        input, button {
            width:100%; padding:12px; margin:10px 0;
            border:none; border-radius:6px;
        }
        input { background:#fff; color:#000; }
        button {
            background:#0ff; color:#000; font-weight:bold;
            cursor:pointer;
        }
        button:hover { background:#00cccc; color:#fff; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Admin Login</h2>
        <?php if (!empty($error)): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>
        <form method="POST" action="">
            <label>Username:</label>
            <input type="text" name="username" required>
            <label>Password:</label>
            <input type="password" name="password" required>
            <button type="submit">Login</button>
        </form>
        
    </div>
</body>
</html>
