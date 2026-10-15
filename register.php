<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "hoodgym");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $fname = trim($_POST['first_name']);
    $lname = trim($_POST['last_name']);
    $city = trim($_POST['city']);
    $state = trim($_POST['state']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $pass = trim($_POST['password']);
    $confirm_pass = trim($_POST['confirm_password']);

    if ($pass !== $confirm_pass) {
        $error = "Passwords do not match!";
    } else {
        $hashed_password = password_hash($pass, PASSWORD_DEFAULT);
        $role = "member";

        $stmt = $conn->prepare("INSERT INTO users (username, first_name, last_name, city, state, phone, email, password, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssss", $username, $fname, $lname, $city, $state, $phone, $email, $hashed_password, $role);

        if ($stmt->execute()) {
            $_SESSION['username'] = $username;
            header("Location: login.php");
            exit();
        } else {
            $error = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
  <title>HoodGym Registration</title>
  <style>
    body {
      margin: 0;
      font-family: 'Poppins', sans-serif;
      background: url('samuel-girven-fqMu99l8sqo-unsplash.jpg') no-repeat center center fixed;
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: flex-start;
      overflow-y: auto;
      padding: 40px 0;
    }

    .register-box {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(12px);
      padding: 50px;
      border-radius: 15px;
      width: 90%;
      max-width: 600px;
      margin: 20px;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4);
      color: #fff;
      animation: fadeIn 1s ease-in-out;
    }

    .logo {
      text-align: center;
      margin-bottom: 25px;
    }

    .logo img {
      width: 90px;
      border-radius: 50%;
      box-shadow: 0 0 12px #f1c40f;
    }

    h2 {
      text-align: center;
      color: #f1c40f;
      margin-bottom: 30px;
      font-size: 30px;
      letter-spacing: 1px;
    }

    .form-row {
      display: flex;
      gap: 25px;            /* horizontal spacing */
      margin-bottom: 20px;  /* vertical spacing */
    }

    .form-group {
      flex: 1;
    }

    label {
      display: block;
      margin-bottom: 8px;
      font-weight: 500;
    }

    input {
      width: 100%;
      padding: 14px;
      border: none;
      border-radius: 8px;
      outline: none;
      font-size: 15px;
      transition: 0.3s;
    }

    input:focus {
      box-shadow: 0 0 10px #f1c40f;
    }

    button {
      width: 100%;
      padding: 16px;
      background: #f1c40f;
      border: none;
      border-radius: 8px;
      font-weight: bold;
      font-size: 18px;
      cursor: pointer;
      transition: 0.3s;
      margin-top: 25px;
    }

    button:hover {
      background: #d4ac0d;
      transform: scale(1.05);
    }

    .error {
      color: #ff4d4d;
      text-align: center;
      margin-bottom: 15px;
      font-weight: bold;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* Responsive fix for mobile */
    @media (max-width: 600px) {
      .form-row {
        flex-direction: column; /* stack inputs vertically */
        gap: 15px;
      }
    }
  </style>
</head>
<body>
  <div class="register-box">
    <div class="logo">
      <img src="black_on_white.png" alt="HoodGym Logo">
    </div>
    <h2>Register</h2>
    <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
    <form method="POST" action="register.php">
      <div class="form-row">
        <div class="form-group">
          <label>First Name:</label>
          <input type="text" name="first_name" required>
        </div>
        <div class="form-group">
          <label>Last Name:</label>
          <input type="text" name="last_name" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Username:</label>
          <input type="text" name="username" required>
        </div>
        <div class="form-group">
          <label>Email:</label>
          <input type="email" name="email" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>City:</label>
          <input type="text" name="city" required>
        </div>
        <div class="form-group">
          <label>State:</label>
          <input type="text" name="state" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Phone Number:</label>
          <input type="text" name="phone" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Password:</label>
          <input type="password" name="password" required>
        </div>
        <div class="form-group">
          <label>Confirm Password:</label>
          <input type="password" name="confirm_password" required>
        </div>
      </div>

      <button type="submit">Register</button>
    </form>
            <!-- Back Button -->
        <a href="index.php" >⬅ Back to Home</a>
  </div>
</body>
</html>
