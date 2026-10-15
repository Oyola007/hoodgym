<?php
session_start();
$conn = new mysqli("localhost", "root", "", "hoodgym");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HoodGym — Booking & Payment</title>
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
  .amount{
    font-family:var(--font-mono);
    font-weight:700;
    font-size:22px;
    color:var(--hazard);
    margin:16px 0 20px;
  }
  label{
    display:block;
    text-align:left;
    font-family:var(--font-mono);
    font-size:11px;
    letter-spacing:1px;
    text-transform:uppercase;
    color:var(--muted);
    margin:18px 0 6px;
  }
  select{
    width:100%;
    background:#141414;
    color:var(--text);
    border:1px solid var(--line);
    border-radius:4px;
    padding:12px 14px;
    font-family:var(--font-body);
    font-size:15px;
    appearance:none;
    -webkit-appearance:none;
  }
  select:focus{outline:none;border-color:var(--hazard);}
  button, .btn-link{
    display:block;
    width:100%;
    margin-top:20px;
    padding:14px 20px;
    border:none;
    border-radius:4px;
    background:var(--hazard);
    color:var(--black);
    font-family:var(--font-body);
    font-weight:700;
    font-size:14px;
    letter-spacing:.4px;
    text-transform:uppercase;
    text-decoration:none;
    cursor:pointer;
    transition:.2s;
  }
  button:hover, .btn-link:hover{
    background:var(--text);
  }
  .btn-secondary{
    background:transparent;
    color:var(--hazard);
    border:1px solid var(--hazard);
    margin-top:12px;
  }
  .btn-secondary:hover{
    background:var(--hazard);
    color:var(--black);
  }

  @media (max-width:480px){
    .card{padding:28px 20px;}
    button, .btn-link{font-size:13px;padding:13px 16px;}
  }
</style>
</head>
<body>
<div class="hazard-strip"></div>
<div class="card">
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $package_id = intval($_POST['package_id']);
    $user_id = $_SESSION['user_id'] ?? null; // must be set at login

    if (!$user_id) {
        echo "<h1 class='error'>Session Expired</h1>";
        echo "<p>Please log in again to book a package.</p>";
        echo "<a class='btn-link' href='login.php'>Go to Login</a>";
    } else {
        // Step 1: Book the package
        $stmt = $conn->prepare("INSERT INTO bookings (package_id, user_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $package_id, $user_id);

        if ($stmt->execute()) {
            // Step 2: Fetch package price (parameterized to avoid injection)
            $pkgStmt = $conn->prepare("SELECT name, price FROM packages WHERE id = ?");
            $pkgStmt->bind_param("i", $package_id);
            $pkgStmt->execute();
            $pkg = $pkgStmt->get_result()->fetch_assoc();

            $amount = htmlspecialchars($pkg['price']);
            $pkg_name = htmlspecialchars($pkg['name']);
            $safe_package_id = (int)$package_id;

            echo "<h1 class='success'>Package Booked</h1>";
            echo "<p>You booked <strong>{$pkg_name}</strong>.</p>";
            echo "<div class='amount'>$ {$amount}</div>";

            echo "<form method='POST' action='process_payment.php'>
                    <input type='hidden' name='package_id' value='{$safe_package_id}'>
                    <input type='hidden' name='amount' value='{$amount}'>
                    <label for='method'>Payment Method</label>
                    <select name='method' id='method' required>
                        <option value='Mpesa'>M-Pesa</option>
                        <option value='Card'>Credit / Debit Card</option>
                        <option value='PayPal'>PayPal</option>
                        <option value='Cash'>Cash</option>
                    </select>
                    <button type='submit'>Pay Now</button>
                  </form>";

            echo "<a class='btn-link btn-secondary' href='dashboard.php'>Go to Dashboard</a>";

            $pkgStmt->close();
        } else {
            echo "<h1 class='error'>Booking Failed</h1>";
            echo "<p>Something went wrong. Please try again.</p>";
            echo "<a class='btn-link' href='packages.php'>Back to Packages</a>";
        }
        $stmt->close();
    }
} else {
    echo "<h1 class='error'>Invalid Request</h1>";
    echo "<p>This page can't be opened directly.</p>";
    echo "<a class='btn-link' href='packages.php'>Back to Packages</a>";
}
$conn->close();
?>
</div>
</body>
</html>