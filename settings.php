<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "hoodgym";
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$user_id = $_SESSION['user_id'];
$message = "";

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_username = trim($_POST['username']);
    $new_email = trim($_POST['email']);
    $new_password = trim($_POST['password']);

    if (!empty($new_password)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET username=?, email=?, password=? WHERE id=?");
        $stmt->bind_param("sssi", $new_username, $new_email, $hashed_password, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET username=?, email=? WHERE id=?");
        $stmt->bind_param("ssi", $new_username, $new_email, $user_id);
    }

    if ($stmt->execute()) {
        $message = "✅ Settings updated successfully!";
        $_SESSION['username'] = $new_username;
    } else {
        $message = "❌ Error updating settings.";
    }
    $stmt->close();
}

// Fetch current user info
$stmt = $conn->prepare("SELECT username, email FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($username, $email);
$stmt->fetch();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>HoodGym | Settings</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { margin:0; font-family:'Poppins',sans-serif; background:#0d0d0d; color:#fff; }
        header { background:linear-gradient(135deg,#111,#222); padding:20px; text-align:center; }
        header h1 { color:#f1c40f; font-size:40px; }
        .container { max-width:600px; margin:40px auto; background:rgba(255,255,255,0.05);
            padding:30px; border-radius:16px; box-shadow:0 8px 20px rgba(0,0,0,0.6); }
        h2 { color:#f1c40f; margin-bottom:20px; }
        form { display:flex; flex-direction:column; }
        label { margin-top:10px; font-weight:600; }
        input { padding:12px; margin-top:5px; border:none; border-radius:8px; background:#222; color:#fff; }
        input:focus { outline:none; box-shadow:0 0 8px #f1c40f; }
        button { margin-top:20px; padding:14px; background:#f1c40f; border:none; border-radius:8px;
            font-weight:bold; cursor:pointer; transition:0.3s; }
        button:hover { background:#d4ac0d; transform:scale(1.05); }
        .message { margin-top:15px; text-align:center; font-weight:bold; }
        nav { text-align:center; margin-top:20px; }
        nav a { color:#f1c40f; margin:0 15px; text-decoration:none; font-weight:600; }
        nav a:hover { color:#fff; }
    </style>
</head>
<body>
    <header>
        <h1>⚙️ Settings Dashboard</h1>
        <p>Customize your HoodGym experience</p>
    </header>

    <div class="container">
        <h2>Update Profile</h2>
        <?php if (!empty($message)) echo "<p class='message'>$message</p>"; ?>
        <form method="POST" action="settings.php">
            <label>Username:</label>
            <input type="text" name="username" value="<?php echo htmlspecialchars($username); ?>" required>

            <label>Email:</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>

            <label>New Password (leave blank to keep current):</label>
            <input type="password" name="password">

            <button type="submit">Save Changes</button>
        </form>
    </div>

    <nav>
        <a href="index.php">🏠 Home</a>
        <a href="dashboard.php">📊 Dashboard</a>
        <a href="login.php">🚪 Logout</a>
    </nav>
</body>
</html>
