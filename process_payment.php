<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "hoodgym");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: packages.php");
    exit();
}

$user_id    = $_SESSION['user_id'];
$package_id = intval($_POST['package_id'] ?? 0);
$amount     = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
$method     = $_POST['method'] ?? '';

$allowed_methods = ['Mpesa', 'Card', 'PayPal', 'Cash'];

if ($package_id <= 0 || $amount <= 0 || !in_array($method, $allowed_methods, true)) {
    header("Location: packages.php?payment_error=invalid_data");
    exit();
}

$stmt = $conn->prepare("INSERT INTO payments (user_id, package_id, amount, method, status) VALUES (?, ?, ?, ?, 'Pending')");
$stmt->bind_param("iids", $user_id, $package_id, $amount, $method);
$success = $stmt->execute();
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HoodGym — Payment</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --black:#0a0a0a;
    --panel:#1a1a1a;
    --line:#2a2a2a;
    --hazard:#FFD400;
    --text:#EDEDED;
    --muted:#8a8a8a;
    --danger:#E63946;
    --success:#35d07f;
    --font-display:'Bebas Neue',sans-serif;
    --font-body:'Inter',sans-serif;
    --font-mono:'JetBrains Mono',monospace;
  }
  *{box-sizing:border-box;}
  body{
    margin:0;
    min-height:100vh;
    font-family:var(--font-body);
    background:var(--black);
    color:var(--text);
    display:flex;
    justify-content:center;
    align-items:center;
    padding:24px;
    -webkit-font-smoothing:antialiased;
  }
  .hazard-strip{
    position:fixed;
    top:0; left:0;
    height:6px;
    width:100%;
    background:repeating-linear-gradient(45deg,var(--hazard) 0 14px,var(--black) 14px 28px);
  }
  .card{
    background:var(--panel);
    border:1px solid var(--line);
    border-top:3px solid var(--hazard);
    border-radius:4px;
    padding:36px 32px;
    text-align:center;
    width:100%;
    max-width:440px;
    box-shadow:0 12px 30px rgba(0,0,0,0.5);
  }
  h1{
    font-family:var(--font-display);
    font-size:clamp(24px,5vw,32px);
    letter-spacing:.5px;
    text-transform:uppercase;
    margin:0 0 20px;
  }
  h1.success{color:var(--success);}
  h1.error{color:var(--danger);}
  p{
    font-size:15px;
    line-height:1.5;
    margin:12px 0;
    color:var(--text);
  }
  p strong{color:var(--hazard);}
  .btn-link{
    display:block;
    width:100%;
    margin-top:20px;
    padding:14px 20px;
    border-radius:4px;
    background:var(--hazard);
    color:var(--black);
    font-family:var(--font-body);
    font-weight:700;
    font-size:14px;
    letter-spacing:.4px;
    text-transform:uppercase;
    text-decoration:none;
    transition:.2s;
  }
  .btn-link:hover{background:var(--text);}

  @media (max-width:480px){
    .card{padding:28px 20px;}
  }
</style>
</head>
<body>
<div class="hazard-strip"></div>
<div class="card">
<?php if ($success): ?>
  <h1 class="success">Payment Submitted</h1>
  <p>Your <strong><?php echo htmlspecialchars($method); ?></strong> payment of <strong>KES <?php echo htmlspecialchars(number_format($amount, 2)); ?></strong> is now pending confirmation.</p>
  <p style="color:var(--muted);font-size:13px;">An admin will review and approve it shortly.</p>
  <a class="btn-link" href="dashboard.php">Go to Dashboard</a>
<?php else: ?>
  <h1 class="error">Payment Failed</h1>
  <p>We couldn't record your payment. Please try again.</p>
  <a class="btn-link" href="packages.php">Back to Packages</a>
<?php endif; ?>
</div>
</body>
</html>
