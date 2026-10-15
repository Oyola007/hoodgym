<?php
session_start();

require_once __DIR__ . '/db.php';  // ✅ safest way

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Prepared statement for security
    $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $hashed_password, $role);
        $stmt->fetch();

        // Verify hashed password
        if (password_verify($password, $hashed_password)) {
            if ($role === 'member') {
                $_SESSION['user_id'] = $id;
                $_SESSION['username'] = $username;
                header("Location: dashboard.php");
                exit();
            } elseif ($role === 'admin') {
                $error = "Admins should log in from the admin login page.";
            } elseif ($role === 'trainer') {
                $error = "Trainers should log in from the trainer login page.";
            } else {
                $error = "This account can't sign in here.";
            }
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "No user found.";
    }
    $stmt->close();
}

// Close connection safely
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>HoodGym | User Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: url('risen-wang-20jX9b35r_M-unsplash.jpg') no-repeat center center fixed;
            background-size: cover;
            display:flex; justify-content:center; align-items:center;
            height:100vh; margin:0;
        }
        .container {
            width:350px; background: rgba(0,0,0,0.7);
            padding:30px; border-radius:12px;
            box-shadow:0 0 15px rgba(0,0,0,0.5);
            color:#fff;
        }
        h2 { text-align:center; color:#f1c40f; }
        .error { color:#ff4d4d; margin-bottom:10px; text-align:center; }
        input, button {
            width:100%; padding:12px; margin:10px 0;
            border:none; border-radius:6px;
        }
        input { background:#fff; color:#000; }
        button {
            background:#f1c40f; color:#000; font-weight:bold;
            cursor:pointer;
        }
        button:hover { background:#d4ac0d; color:#fff; }
        .back-btn {
            display:block;
            text-align:center;
            margin-top:15px;
            background:#444; color:#fff;
            padding:10px; border-radius:6px;
            text-decoration:none;
        }
        .back-btn:hover { background:#666; }
    </style>
</head>
<body>
    <div class="container">
        <h2>User Login</h2>
        <?php if (!empty($error)): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <form method="POST" action="">
            <label>Username:</label>
            <input type="text" name="username" required>

            <label>Password:</label>
            <input type="password" name="password" required>

            <button type="submit">Login</button>
        </form>

        <!-- Back Button -->
        <a href="index.php" class="back-btn">⬅ Back to Home</a>
    </div>
</body>
</html>