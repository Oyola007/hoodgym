<?php
session_start();

// If already logged in as a trainer, skip straight to the dashboard
if (isset($_SESSION['trainer_id'])) {
    header("Location: trainer_dashboard.php");
    exit();
}

$error = '';
$MAX_ATTEMPTS = 5;

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($_SESSION['login_attempts'] >= $MAX_ATTEMPTS) {
        $error = 'Too many failed attempts. Please wait a minute and try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Please enter both username and password.';
        } else {
            $conn = new mysqli("localhost", "root", "", "hoodgym");
            if ($conn->connect_error) {
                error_log("HoodGym DB connection failed: " . $conn->connect_error);
                die("We're having trouble connecting right now. Please try again shortly.");
            }
            $conn->set_charset("utf8mb4");

            $stmt = $conn->prepare("SELECT id, username, password, allocated FROM users WHERE username=? AND role='trainer'");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            $trainer = ($result->num_rows === 1) ? $result->fetch_assoc() : null;

            // Verify the password FIRST. Only after a correct password do we reveal
            // whether the account is allocated — otherwise an attacker can learn a
            // trainer account's allocation status without ever knowing the password.
            if ($trainer && password_verify($password, $trainer['password'])) {

                if (!$trainer['allocated']) {
                    $error = 'Your trainer account has not been allocated yet. Contact the admin.';
                    $_SESSION['login_attempts']++;
                } else {
                    // Success — start the trainer session
                    unset($_SESSION['login_attempts']);
                    session_regenerate_id(true);
                    $_SESSION['trainer_id'] = $trainer['id'];
                    $_SESSION['trainer_username'] = $trainer['username'];

                    $stmt->close();
                    $conn->close();
                    header("Location: trainer_dashboard.php");
                    exit();
                }
            } else {
                $error = 'Incorrect username or password.';
                $_SESSION['login_attempts']++;
            }

            $stmt->close();
            $conn->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>HoodGym — Trainer Login</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
<style>
  :root{
    --bg:#0e0e0d;
    --panel:#181816;
    --panel-raised:#212120;
    --line:#2c2c29;
    --hazard:#ffcc00;
    --chalk:#f3f2ec;
    --muted:#8f8d86;
    --danger:#ff4436;
    --success:#35d07f;
  }
  *{box-sizing:border-box;}
  html,body{margin:0;padding:0;}
  body{
    background:var(--bg);
    color:var(--chalk);
    font-family:'Inter',sans-serif;
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:20px;
    -webkit-font-smoothing:antialiased;
  }
  .hazard-rule{
    height:6px;
    width:100%;
    background:repeating-linear-gradient(-45deg,var(--hazard) 0 14px,#111 14px 28px);
  }
  .login-card{
    width:100%;
    max-width:380px;
    background:var(--panel);
    border:1px solid var(--line);
    border-radius:12px;
    overflow:hidden;
  }
  .login-head{
    padding:30px 30px 20px;
    text-align:center;
  }
  .login-head .mark{
    font-family:'Bebas Neue',sans-serif;
    font-size:38px;
    letter-spacing:1px;
    color:var(--chalk);
  }
  .login-head .mark span{color:var(--hazard);}
  .login-head .sub{
    font-family:'JetBrains Mono',monospace;
    font-size:11px;
    letter-spacing:2px;
    color:var(--muted);
    text-transform:uppercase;
    margin-top:6px;
  }
  .login-body{padding:26px 30px 30px;}
  .field{margin-bottom:16px;}
  .field label{
    display:block;
    font-family:'JetBrains Mono',monospace;
    font-size:10.5px;
    letter-spacing:1.2px;
    text-transform:uppercase;
    color:var(--muted);
    margin-bottom:6px;
  }
  .field input{
    width:100%;
    background:var(--panel-raised);
    border:1px solid var(--line);
    color:var(--chalk);
    border-radius:6px;
    padding:11px 12px;
    font-family:'Inter',sans-serif;
    font-size:14px;
  }
  .field input:focus{
    outline:none;
    border-color:var(--hazard);
  }
  .btn-submit{
    width:100%;
    padding:12px;
    border-radius:6px;
    border:1px solid var(--hazard);
    background:var(--hazard);
    color:#0e0e0d;
    font-weight:700;
    font-size:14px;
    letter-spacing:.3px;
    cursor:pointer;
    margin-top:6px;
  }
  .btn-submit:hover{background:#e6b800;}
  .btn-submit:disabled{background:var(--panel-raised);border-color:var(--line);color:var(--muted);cursor:not-allowed;}
  .alert{
    background:rgba(255,68,54,.1);
    border:1px solid rgba(255,68,54,.3);
    color:var(--danger);
    font-size:13px;
    padding:10px 14px;
    border-radius:6px;
    margin-bottom:18px;
  }
  .back-link{
    display:block;
    text-align:center;
    margin-top:18px;
    font-size:12.5px;
    color:var(--muted);
    text-decoration:none;
    font-family:'JetBrains Mono',monospace;
  }
  .back-link:hover{color:var(--chalk);}
</style>
</head>
<body>

  <div class="login-card">
    <div class="hazard-rule"></div>
    <div class="login-head">
      <div class="mark">HOOD<span>GYM</span></div>
      <div class="sub">Trainer Login</div>
    </div>
    <div class="login-body">
      <?php if ($error): ?>
        <div class="alert"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
      <?php $locked = $_SESSION['login_attempts'] >= $MAX_ATTEMPTS; ?>
      <form method="POST" action="trainer_login.php">
        <div class="field">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" required autofocus value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="btn-submit" <?php echo $locked ? 'disabled' : ''; ?>>Log In</button>
      </form>
      <a href="admin_login.php" class="back-link">Admin instead? Log in here</a>
    </div>
  </div>

</body>
</html>
