<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'db.php';

$user_id = $_SESSION['user_id'];
$flash = null;
$flash_type = 'success'; // 'success' | 'error'

// Handle manual/cash booking request (existing flow — pay at front desk)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['package_id']) && isset($_POST['request_manual'])) {
    $package_id = (int) $_POST['package_id'];

    $check = mysqli_prepare($conn, "SELECT id FROM bookings WHERE user_id = ? AND package_id = ? AND status = 'Pending'");
    mysqli_stmt_bind_param($check, "ii", $user_id, $package_id);
    mysqli_stmt_execute($check);
    mysqli_stmt_store_result($check);

    if (mysqli_stmt_num_rows($check) > 0) {
        $flash = "You already have a pending request for this package.";
        $flash_type = 'error';
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO bookings (user_id, package_id, status, created_at) VALUES (?, ?, 'Pending', NOW())");
        mysqli_stmt_bind_param($stmt, "ii", $user_id, $package_id);
        if (mysqli_stmt_execute($stmt)) {
            $flash = "Request sent. An admin will review and approve your package shortly.";
            $flash_type = 'success';
        } else {
            $flash = "Something went wrong. Please try again.";
            $flash_type = 'error';
        }
        mysqli_stmt_close($stmt);
    }
    mysqli_stmt_close($check);
}

// Fetch packages
$packages = [];
$result = mysqli_query($conn, "SELECT * FROM packages ORDER BY price ASC");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $packages[] = $row;
    }
}

// Fetch this user's active/pending bookings so we can label buttons correctly
$user_bookings = [];
$ub = mysqli_prepare($conn, "SELECT package_id, status FROM bookings WHERE user_id = ?");
if ($ub) {
    mysqli_stmt_bind_param($ub, "i", $user_id);
    mysqli_stmt_execute($ub);
    $ub_result = mysqli_stmt_get_result($ub);
    while ($row = mysqli_fetch_assoc($ub_result)) {
        $user_bookings[$row['package_id']] = $row['status'];
    }
    mysqli_stmt_close($ub);
}

// Fetch the user's phone to prefill the payment modal
$user_phone = '';
$up = mysqli_prepare($conn, "SELECT phone FROM users WHERE id = ?");
mysqli_stmt_bind_param($up, "i", $user_id);
mysqli_stmt_execute($up);
mysqli_stmt_bind_result($up, $phone_result);
if (mysqli_stmt_fetch($up)) {
    $user_phone = $phone_result ?? '';
}
mysqli_stmt_close($up);

// ── Payment method display config (edit these to match your real details) ──
$PAYBILL_BUSINESS_NO = 'XXXXXX';
$BANK_NAME            = 'XXXXXX Bank';
$BANK_ACCOUNT_NAME    = 'HoodGym Ltd';
$BANK_ACCOUNT_NO      = 'XXXXXXXXXX';
$BANK_BRANCH          = 'XXXXXX Branch';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HoodGym — Packages</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --black:#0a0a0a;
    --charcoal:#141414;
    --panel:#1a1a1a;
    --line:#2a2a2a;
    --hazard:#FFD400;
    --hazard-dim:#B8990A;
    --danger:#E63946;
    --success:#3DD68C;
    --text:#EDEDED;
    --muted:#8a8a8a;
    --font-display:'Bebas Neue',sans-serif;
    --font-body:'Inter',sans-serif;
    --font-mono:'JetBrains Mono',monospace;
  }
  *{box-sizing:border-box;}
  body{margin:0;font-family:var(--font-body);background:var(--black);color:var(--text);-webkit-font-smoothing:antialiased;}
  a{text-decoration:none;}
  .hazard-strip{height:6px;width:100%;background:repeating-linear-gradient(45deg,var(--hazard) 0 14px,var(--black) 14px 28px);}

  .sidebar{position:fixed;top:0;left:0;width:220px;height:100%;background:var(--charcoal);border-right:1px solid var(--line);padding-top:28px;z-index:10;}
  .sidebar .brand{font-family:var(--font-display);font-size:28px;letter-spacing:1px;color:var(--hazard);padding:0 24px 24px;border-bottom:1px solid var(--line);margin-bottom:12px;}
  .sidebar .brand span{color:var(--text);}
  .sidebar a{display:flex;align-items:center;gap:10px;color:var(--muted);font-size:14px;font-weight:600;letter-spacing:.3px;padding:14px 24px;border-left:3px solid transparent;transition:.2s;}
  .sidebar a:hover,.sidebar a.active{background:rgba(255,212,0,0.06);color:var(--hazard);border-left-color:var(--hazard);}

  .main{margin-left:220px;padding-bottom:60px;}

  header.top{background:var(--charcoal);border-bottom:1px solid var(--line);padding:36px 40px 28px;display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:20px;}
  header.top h1{font-family:var(--font-display);font-size:42px;letter-spacing:1px;margin:0 0 6px;text-transform:uppercase;}
  header.top h1 .hi{color:var(--hazard);}
  header.top p{margin:0;color:var(--muted);font-size:14px;}
  .logout-btn{background:transparent;color:var(--hazard);border:1px solid var(--hazard);font-family:var(--font-body);font-weight:700;font-size:13px;letter-spacing:.5px;text-transform:uppercase;padding:11px 22px;cursor:pointer;transition:.2s;}
  .logout-btn:hover{background:var(--hazard);color:var(--black);}

  section{padding:36px 40px;}
  .eyebrow{font-family:var(--font-mono);font-size:12px;letter-spacing:2px;color:var(--hazard);text-transform:uppercase;margin:0 0 6px;}
  h2.section-title{font-family:var(--font-display);font-size:28px;letter-spacing:.5px;text-transform:uppercase;margin:0 0 24px;}

  .flash{
    margin:0 40px 0;
    padding:14px 20px;
    font-family:var(--font-mono);
    font-size:13px;
    border-left:3px solid var(--hazard);
    background:var(--panel);
    color:var(--text);
  }
  .flash.error{border-left-color:var(--danger);color:var(--danger);}

  /* Package cards */
  .pkg-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(280px,1fr));
    gap:20px;
  }
  .pkg-card{
    background:var(--panel);
    border:1px solid var(--line);
    border-top:3px solid var(--line);
    padding:28px 26px;
    display:flex;
    flex-direction:column;
    position:relative;
    transition:.2s;
  }
  .pkg-card:hover{border-color:var(--hazard);transform:translateY(-3px);}
  .pkg-card.featured{border-top-color:var(--hazard);}
  .pkg-card .badge{
    position:absolute;
    top:0;right:0;
    background:var(--hazard);
    color:var(--black);
    font-family:var(--font-mono);
    font-weight:700;
    font-size:11px;
    letter-spacing:1px;
    text-transform:uppercase;
    padding:6px 12px;
  }
  .pkg-card h3{
    font-family:var(--font-display);
    font-size:26px;
    letter-spacing:.5px;
    text-transform:uppercase;
    margin:0 0 4px;
  }
  .pkg-card .duration{
    font-family:var(--font-mono);
    font-size:12px;
    color:var(--muted);
    text-transform:uppercase;
    letter-spacing:1px;
    margin-bottom:18px;
  }
  .pkg-card .price{
    font-family:var(--font-mono);
    font-size:40px;
    font-weight:700;
    color:var(--hazard);
    line-height:1;
    margin-bottom:6px;
  }
  .pkg-card .price .cur{font-size:18px;vertical-align:top;margin-right:2px;}
  .pkg-card .desc{
    color:var(--muted);
    font-size:14px;
    line-height:1.6;
    margin-bottom:18px;
  }
  .pkg-card ul{
    list-style:none;
    padding:0;
    margin:0 0 24px;
    flex-grow:1;
  }
  .pkg-card li{
    font-size:13px;
    color:var(--text);
    padding:8px 0;
    border-bottom:1px solid var(--line);
    display:flex;
    align-items:center;
    gap:8px;
  }
  .pkg-card li::before{
    content:"▸";
    color:var(--hazard);
    font-weight:700;
  }
  .pkg-btn{
    width:100%;
    background:var(--hazard);
    color:var(--black);
    border:none;
    font-family:var(--font-body);
    font-weight:700;
    font-size:13px;
    letter-spacing:.5px;
    text-transform:uppercase;
    padding:14px;
    cursor:pointer;
    transition:.2s;
  }
  .pkg-btn:hover{background:#fff;}
  .pkg-btn[disabled]{
    background:var(--line);
    color:var(--muted);
    cursor:not-allowed;
  }
  .pkg-btn.pending{background:transparent;border:1px solid var(--hazard-dim);color:var(--hazard-dim);cursor:default;}
  .pkg-btn.approved{background:transparent;border:1px solid var(--hazard);color:var(--hazard);cursor:default;}

  .pkg-btn-secondary{
    width:100%;
    background:transparent;
    color:var(--muted);
    border:1px solid var(--line);
    font-family:var(--font-body);
    font-weight:600;
    font-size:12px;
    letter-spacing:.3px;
    text-transform:uppercase;
    padding:11px;
    cursor:pointer;
    margin-top:8px;
    transition:.2s;
  }
  .pkg-btn-secondary:hover{border-color:var(--muted);color:var(--text);}

  .empty-state{
    background:var(--panel);
    border:1px dashed var(--line);
    padding:48px;
    text-align:center;
    color:var(--muted);
    font-family:var(--font-mono);
    font-size:13px;
  }

  footer{border-top:1px solid var(--line);text-align:center;padding:26px;color:var(--muted);font-size:13px;}
  footer a{color:var(--hazard);margin:0 8px;}
  footer a:hover{color:var(--text);}

  /* ===== Payment Modal ===== */
  .modal-overlay{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(0,0,0,0.75);
    z-index:100;
    align-items:center;
    justify-content:center;
    padding:20px;
    overflow-y:auto;
  }
  .modal-overlay.open{display:flex;}
  .modal-box{
    background:var(--charcoal);
    border:1px solid var(--line);
    border-top:3px solid var(--hazard);
    width:100%;
    max-width:440px;
    padding:32px;
    position:relative;
    margin:auto;
  }
  .modal-box .modal-close{
    position:absolute;
    top:14px;right:16px;
    background:none;
    border:none;
    color:var(--muted);
    font-size:20px;
    cursor:pointer;
    line-height:1;
  }
  .modal-box .modal-close:hover{color:var(--text);}
  .modal-box h3{
    font-family:var(--font-display);
    font-size:26px;
    letter-spacing:.5px;
    text-transform:uppercase;
    margin:0 0 4px;
  }
  .modal-box .modal-sub{
    color:var(--muted);
    font-size:13px;
    margin:0 0 18px;
  }
  .modal-box label{
    display:block;
    font-family:var(--font-mono);
    font-size:11px;
    letter-spacing:1px;
    text-transform:uppercase;
    color:var(--hazard);
    margin-bottom:8px;
  }
  .modal-box input[type="tel"],
  .modal-box input[type="text"]{
    width:100%;
    background:var(--panel);
    border:1px solid var(--line);
    color:var(--text);
    font-family:var(--font-mono);
    font-size:15px;
    padding:13px 14px;
    margin-bottom:18px;
  }
  .modal-box input[type="tel"]:focus,
  .modal-box input[type="text"]:focus{outline:none;border-color:var(--hazard);}
  .modal-amount{
    font-family:var(--font-mono);
    font-size:13px;
    color:var(--muted);
    margin-bottom:18px;
  }
  .modal-amount span{color:var(--hazard);font-weight:700;font-size:16px;}
  .modal-status{
    font-family:var(--font-mono);
    font-size:12.5px;
    padding:12px 14px;
    margin-bottom:16px;
    display:none;
    border-left:3px solid var(--hazard);
    background:var(--panel);
  }
  .modal-status.show{display:block;}
  .modal-status.error{border-left-color:var(--danger);color:var(--danger);}
  .modal-status.success{border-left-color:var(--success);color:var(--success);}
  .modal-spinner{
    display:inline-block;
    width:14px;height:14px;
    border:2px solid var(--line);
    border-top-color:var(--hazard);
    border-radius:50%;
    margin-right:8px;
    vertical-align:middle;
    animation:spin .7s linear infinite;
  }
  @keyframes spin{to{transform:rotate(360deg);}}

  /* Payment method tabs */
  .pay-tabs{
    display:flex;
    flex-wrap:wrap;
    gap:6px;
    margin-bottom:20px;
    border-bottom:1px solid var(--line);
    padding-bottom:16px;
  }
  .pay-tab{
    flex:1 1 auto;
    background:var(--panel);
    border:1px solid var(--line);
    color:var(--muted);
    font-family:var(--font-mono);
    font-size:11px;
    letter-spacing:.5px;
    text-transform:uppercase;
    padding:10px 8px;
    cursor:pointer;
    transition:.15s;
    white-space:nowrap;
  }
  .pay-tab:hover{color:var(--text);border-color:var(--hazard-dim);}
  .pay-tab.active{background:var(--hazard);border-color:var(--hazard);color:var(--black);font-weight:700;}

  .pay-panel{}
  .pay-instructions{
    background:var(--panel);
    border:1px solid var(--line);
    border-left:3px solid var(--hazard);
    padding:14px 16px;
    margin-bottom:18px;
    font-size:13px;
    line-height:1.7;
    color:var(--text);
  }
  .pay-instructions p{margin:0;}
  .pay-instructions strong{color:var(--hazard);font-family:var(--font-mono);}
  .pay-note{
    font-size:12px;
    color:var(--muted);
    margin:-8px 0 18px;
    line-height:1.5;
  }

  @media (max-width:768px){
    .sidebar{width:100%;height:auto;position:relative;}
    .main{margin-left:0;}
    .sidebar a{display:inline-flex;padding:12px 16px;}
    header.top{padding:24px;}
    section{padding:24px;}
    .flash{margin:0 24px;}
    .pay-tab{font-size:10px;padding:9px 6px;}
  }
</style>
</head>
<body>

<div class="sidebar">
  <div class="brand">HOOD<span>GYM</span></div>
  <a href="dashboard.php">🏠 Dashboard</a>
  <a href="packages.php" class="active">📦 Packages</a>
  <a href="progress.php">📊 Progress</a>
  <a href="community.php">👥 Community</a>
  <a href="settings.php">⚙️ Settings</a>
</div>

<div class="main">
  <div class="hazard-strip"></div>
  <header class="top">
    <div>
      <h1>Choose Your <span class="hi">Package</span></h1>
      <p>Pick a plan that matches your training intensity.</p>
    </div>
    <form method="POST" action="logout.php">
      <button type="submit" class="logout-btn">Logout</button>
    </form>
  </header>

  <?php if ($flash): ?>
    <div class="flash <?php echo $flash_type === 'error' ? 'error' : ''; ?>"><?php echo htmlspecialchars($flash); ?></div>
  <?php endif; ?>

  <section>
    <p class="eyebrow">Membership</p>
    <h2 class="section-title">Available Packages</h2>

    <?php if (empty($packages)): ?>
      <div class="empty-state">No packages are available right now. Check back soon.</div>
    <?php else: ?>
      <div class="pkg-grid">
        <?php foreach ($packages as $pkg):
          $status = $user_bookings[$pkg['id']] ?? null;
          $features = !empty($pkg['features']) ? array_map('trim', explode(',', $pkg['features'])) : [];
        ?>
        <div class="pkg-card <?php echo !empty($pkg['is_featured']) ? 'featured' : ''; ?>">
          <?php if (!empty($pkg['is_featured'])): ?><div class="badge">Most Popular</div><?php endif; ?>
          <h3><?php echo htmlspecialchars($pkg['name']); ?></h3>
          <div class="duration"><?php echo htmlspecialchars($pkg['duration_days'] ?? '30'); ?> Days Access</div>
          <div class="price"><span class="cur">KSH</span><?php echo number_format((float)$pkg['price']); ?></div>
          <p class="desc"><?php echo htmlspecialchars($pkg['description'] ?? ''); ?></p>
          <?php if (!empty($features)): ?>
          <ul>
            <?php foreach ($features as $f): ?>
              <li><?php echo htmlspecialchars($f); ?></li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>

          <?php if ($status === 'Pending'): ?>
            <button class="pkg-btn pending" disabled>Request Pending</button>
          <?php elseif ($status === 'Approved'): ?>
            <button class="pkg-btn approved" disabled>Active Package</button>
          <?php else: ?>
            <button type="button"
                    class="pkg-btn pay-btn"
                    data-id="<?php echo (int)$pkg['id']; ?>"
                    data-name="<?php echo htmlspecialchars($pkg['name']); ?>"
                    data-price="<?php echo (float)$pkg['price']; ?>">
              Pay Now
            </button>
            <form method="POST">
              <input type="hidden" name="package_id" value="<?php echo (int)$pkg['id']; ?>">
              <input type="hidden" name="request_manual" value="1">
              <button type="submit" class="pkg-btn-secondary">Or request without paying now</button>
            </form>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <footer>
    <p>© 2026 HoodGym. All rights reserved.</p>
    <p><a href="#">Facebook</a> · <a href="#">Instagram</a> · <a href="#">YouTube</a></p>
  </footer>
</div>

<!-- ===== Payment Modal ===== -->
<div class="modal-overlay" id="payModal">
  <div class="modal-box">
    <button type="button" class="modal-close" id="modalClose">&times;</button>
    <h3>Complete Payment</h3>
    <p class="modal-sub" id="modalPkgName">Complete your payment to activate this package.</p>
    <div class="modal-amount">Amount: <span id="modalAmount">KSH 0</span></div>

    <div class="pay-tabs" id="payTabs">
      <button type="button" class="pay-tab active" data-method="mpesa_stk">M-Pesa (STK)</button>
      <button type="button" class="pay-tab" data-method="paybill">Paybill / Till</button>
      <button type="button" class="pay-tab" data-method="bank">Bank Transfer</button>
      <button type="button" class="pay-tab" data-method="card">Card</button>
    </div>

    <div id="modalStatus" class="modal-status"></div>

    <!-- M-Pesa STK Push panel -->
    <div class="pay-panel" data-panel="mpesa_stk">
      <label for="modalPhone">Safaricom Number</label>
      <input type="tel" id="modalPhone" placeholder="07XXXXXXXX" value="<?php echo htmlspecialchars($user_phone); ?>">
      <button type="button" class="pkg-btn" id="modalPayBtn">Send Payment Request</button>
    </div>

    <!-- Paybill / Till panel -->
    <div class="pay-panel" data-panel="paybill" style="display:none">
      <div class="pay-instructions">
        <p>Go to M-Pesa → Lipa na M-Pesa → Paybill</p>
        <p>Business No: <strong><?php echo htmlspecialchars($PAYBILL_BUSINESS_NO); ?></strong></p>
        <p>Account No: your phone number</p>
      </div>
      <label for="paybillCode">M-Pesa Transaction Code</label>
      <input type="text" id="paybillCode" placeholder="e.g. QGH7XXXXXX" style="text-transform:uppercase">
      <label for="paybillPhone">Phone Number Used</label>
      <input type="tel" id="paybillPhone" placeholder="07XXXXXXXX" value="<?php echo htmlspecialchars($user_phone); ?>">
      <p class="pay-note">Pay first, then submit the transaction code above. An admin will verify it against the M-Pesa statement before approving.</p>
      <button type="button" class="pkg-btn" id="paybillSubmitBtn">Submit for Verification</button>
    </div>

    <!-- Bank Transfer panel -->
    <div class="pay-panel" data-panel="bank" style="display:none">
      <div class="pay-instructions">
        <p>Bank: <strong><?php echo htmlspecialchars($BANK_NAME); ?></strong></p>
        <p>Account Name: <strong><?php echo htmlspecialchars($BANK_ACCOUNT_NAME); ?></strong></p>
        <p>Account No: <strong><?php echo htmlspecialchars($BANK_ACCOUNT_NO); ?></strong></p>
        <p>Branch: <strong><?php echo htmlspecialchars($BANK_BRANCH); ?></strong></p>
      </div>
      <label for="bankRef">Bank Reference / Slip No.</label>
      <input type="text" id="bankRef" placeholder="Transaction reference">
      <p class="pay-note">Deposit or transfer first, then submit your reference number. An admin will verify it before approving.</p>
      <button type="button" class="pkg-btn" id="bankSubmitBtn">Submit for Verification</button>
    </div>

    <!-- Card panel -->
    <div class="pay-panel" data-panel="card" style="display:none">
      <div class="pay-instructions">
        <p>You'll be redirected to a secure hosted checkout page to enter your card details. HoodGym never stores or handles raw card numbers directly.</p>
      </div>
      <button type="button" class="pkg-btn" id="cardPayBtn">Continue to Card Checkout</button>
    </div>
  </div>
</div>

<script>
(function(){
  var overlay = document.getElementById('payModal');
  var closeBtn = document.getElementById('modalClose');
  var pkgNameEl = document.getElementById('modalPkgName');
  var amountEl = document.getElementById('modalAmount');
  var statusEl = document.getElementById('modalStatus');
  var tabs = document.querySelectorAll('.pay-tab');
  var panels = document.querySelectorAll('.pay-panel');

  var modalPhone = document.getElementById('modalPhone');
  var modalPayBtn = document.getElementById('modalPayBtn');

  var paybillCode = document.getElementById('paybillCode');
  var paybillPhone = document.getElementById('paybillPhone');
  var paybillSubmitBtn = document.getElementById('paybillSubmitBtn');

  var bankRef = document.getElementById('bankRef');
  var bankSubmitBtn = document.getElementById('bankSubmitBtn');

  var cardPayBtn = document.getElementById('cardPayBtn');

  var currentPackageId = null;
  var currentPrice = 0;
  var pollTimer = null;
  var pollAttempts = 0;
  var MAX_POLL_ATTEMPTS = 25; // ~75s at 3s intervals

  function resetPanelsUI(){
    modalPayBtn.disabled = false;
    modalPayBtn.textContent = 'Send Payment Request';
    paybillSubmitBtn.disabled = false;
    paybillSubmitBtn.textContent = 'Submit for Verification';
    bankSubmitBtn.disabled = false;
    bankSubmitBtn.textContent = 'Submit for Verification';
    cardPayBtn.disabled = false;
    cardPayBtn.textContent = 'Continue to Card Checkout';
  }

  function switchTab(method){
    tabs.forEach(function(t){ t.classList.toggle('active', t.dataset.method === method); });
    panels.forEach(function(p){ p.style.display = (p.dataset.panel === method) ? 'block' : 'none'; });
    statusEl.className = 'modal-status';
    statusEl.innerHTML = '';
    if (pollTimer) { clearTimeout(pollTimer); pollTimer = null; }
    resetPanelsUI();
  }

  tabs.forEach(function(tab){
    tab.addEventListener('click', function(){ switchTab(tab.dataset.method); });
  });

  function openModal(pkgId, pkgName, price){
    currentPackageId = pkgId;
    currentPrice = price;
    pkgNameEl.textContent = 'Complete your payment for ' + pkgName + '.';
    amountEl.textContent = 'KSH ' + Number(price).toLocaleString();
    switchTab('mpesa_stk');
    overlay.classList.add('open');
  }

  function closeModal(){
    overlay.classList.remove('open');
    if (pollTimer) { clearTimeout(pollTimer); pollTimer = null; }
  }

  document.querySelectorAll('.pay-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
      openModal(btn.dataset.id, btn.dataset.name, btn.dataset.price);
    });
  });

  closeBtn.addEventListener('click', closeModal);
  overlay.addEventListener('click', function(e){
    if (e.target === overlay) closeModal();
  });

  function showStatus(message, type){
    statusEl.className = 'modal-status show' + (type ? ' ' + type : '');
    statusEl.innerHTML = message;
  }

  // ── M-Pesa STK Push flow (existing) ──────────────────────────────────
  function pollStatus(checkoutRequestId){
    pollAttempts++;
    fetch('check_payment_status.php?checkout_request_id=' + encodeURIComponent(checkoutRequestId))
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (data.status === 'Completed') {
          showStatus('Payment received! Your package is now active.', 'success');
          setTimeout(function(){ window.location.reload(); }, 1800);
        } else if (data.status === 'Failed') {
          showStatus('Payment was not completed (cancelled or timed out). You can try again.', 'error');
          modalPayBtn.disabled = false;
          modalPayBtn.textContent = 'Try Again';
        } else if (pollAttempts >= MAX_POLL_ATTEMPTS) {
          showStatus('Still waiting on confirmation. If you completed the payment, refresh this page shortly.', '');
        } else {
          pollTimer = setTimeout(function(){ pollStatus(checkoutRequestId); }, 3000);
        }
      })
      .catch(function(){
        if (pollAttempts < MAX_POLL_ATTEMPTS) {
          pollTimer = setTimeout(function(){ pollStatus(checkoutRequestId); }, 3000);
        }
      });
  }

  modalPayBtn.addEventListener('click', function(){
    var phone = modalPhone.value.trim();
    if (!phone) {
      showStatus('Please enter your Safaricom phone number.', 'error');
      return;
    }

    modalPayBtn.disabled = true;
    modalPayBtn.textContent = 'Sending Request...';
    showStatus('<span class="modal-spinner"></span>Sending payment request to your phone...', '');

    var body = new URLSearchParams();
    body.append('package_id', currentPackageId);
    body.append('phone', phone);

    fetch('initiate_payment.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: body.toString()
    })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (data.success) {
          showStatus('<span class="modal-spinner"></span>' + data.message, '');
          pollAttempts = 0;
          pollStatus(data.checkout_request_id);
        } else {
          showStatus(data.message || 'Something went wrong. Please try again.', 'error');
          modalPayBtn.disabled = false;
          modalPayBtn.textContent = 'Send Payment Request';
        }
      })
      .catch(function(){
        showStatus('Network error. Please check your connection and try again.', 'error');
        modalPayBtn.disabled = false;
        modalPayBtn.textContent = 'Send Payment Request';
      });
  });

  // ── Paybill / Till manual verification flow ──────────────────────────
  paybillSubmitBtn.addEventListener('click', function(){
    var code = paybillCode.value.trim();
    var phone = paybillPhone.value.trim();
    if (!code || !phone) {
      showStatus('Please enter the transaction code and the phone number used.', 'error');
      return;
    }

    paybillSubmitBtn.disabled = true;
    paybillSubmitBtn.textContent = 'Submitting...';
    showStatus('<span class="modal-spinner"></span>Submitting for verification...', '');

    var body = new URLSearchParams();
    body.append('package_id', currentPackageId);
    body.append('method', 'Paybill');
    body.append('reference', code);
    body.append('phone', phone);

    fetch('submit_manual_payment.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: body.toString()
    })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (data.success) {
          showStatus(data.message, 'success');
          setTimeout(function(){ window.location.reload(); }, 1800);
        } else {
          showStatus(data.message || 'Something went wrong. Please try again.', 'error');
          paybillSubmitBtn.disabled = false;
          paybillSubmitBtn.textContent = 'Submit for Verification';
        }
      })
      .catch(function(){
        showStatus('Network error. Please check your connection and try again.', 'error');
        paybillSubmitBtn.disabled = false;
        paybillSubmitBtn.textContent = 'Submit for Verification';
      });
  });

  // ── Bank transfer manual verification flow ───────────────────────────
  bankSubmitBtn.addEventListener('click', function(){
    var ref = bankRef.value.trim();
    if (!ref) {
      showStatus('Please enter your bank reference or slip number.', 'error');
      return;
    }

    bankSubmitBtn.disabled = true;
    bankSubmitBtn.textContent = 'Submitting...';
    showStatus('<span class="modal-spinner"></span>Submitting for verification...', '');

    var body = new URLSearchParams();
    body.append('package_id', currentPackageId);
    body.append('method', 'Bank Transfer');
    body.append('reference', ref);

    fetch('submit_manual_payment.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: body.toString()
    })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (data.success) {
          showStatus(data.message, 'success');
          setTimeout(function(){ window.location.reload(); }, 1800);
        } else {
          showStatus(data.message || 'Something went wrong. Please try again.', 'error');
          bankSubmitBtn.disabled = false;
          bankSubmitBtn.textContent = 'Submit for Verification';
        }
      })
      .catch(function(){
        showStatus('Network error. Please check your connection and try again.', 'error');
        bankSubmitBtn.disabled = false;
        bankSubmitBtn.textContent = 'Submit for Verification';
      });
  });

  // ── Card checkout (hosted gateway redirect) ───────────────────────────
  cardPayBtn.addEventListener('click', function(){
    cardPayBtn.disabled = true;
    cardPayBtn.textContent = 'Redirecting...';
    showStatus('<span class="modal-spinner"></span>Preparing secure checkout...', '');

    var body = new URLSearchParams();
    body.append('package_id', currentPackageId);

    fetch('initiate_card_payment.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: body.toString()
    })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (data.success && data.redirect_url) {
          window.location.href = data.redirect_url;
        } else {
          showStatus(data.message || 'Card payments are not available right now.', 'error');
          cardPayBtn.disabled = false;
          cardPayBtn.textContent = 'Continue to Card Checkout';
        }
      })
      .catch(function(){
        showStatus('Network error. Please check your connection and try again.', 'error');
        cardPayBtn.disabled = false;
        cardPayBtn.textContent = 'Continue to Card Checkout';
      });
  });
})();
</script>

</body>
</html>