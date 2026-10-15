<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "hoodgym");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Fetch trainers only (for the Trainers tab)
$result_trainers_only = $conn->query("SELECT id, username, role, allocated FROM users WHERE role='trainer' ORDER BY username ASC");

// Fetch all users
$result_users = $conn->query("SELECT id, username, role, allocated FROM users");

// Fetch existing members eligible to be promoted to trainer
$result_members_list = $conn->query("SELECT id, username FROM users WHERE role='member' ORDER BY username ASC");
$members_for_dropdown = [];
while ($m = $result_members_list->fetch_assoc()) {
    $members_for_dropdown[] = $m;
}

// Count stats
$totalUsers = $conn->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc()['total'];
$totalTrainers = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role='trainer' AND allocated=1")->fetch_assoc()['total'];
$totalMembers = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role='member' OR allocated=0")->fetch_assoc()['total'];

// Fetch bookings (now includes status)
$result_bookings = $conn->query("
    SELECT b.id, u.username, p.name AS package_name, b.status
    FROM bookings b 
    JOIN users u ON b.user_id=u.id 
    JOIN packages p ON b.package_id=p.id 
    ORDER BY b.id DESC
");

// Fetch payments
$result_payments = $conn->query("
    SELECT pay.id, u.username, p.name AS package_name, pay.amount, pay.method, pay.status 
    FROM payments pay 
    JOIN users u ON pay.user_id=u.id 
    JOIN packages p ON pay.package_id=p.id 
    ORDER BY pay.id DESC
");

// Fetch packages
$result_packages = $conn->query("SELECT id, name, description, price, duration_days FROM packages ORDER BY id DESC");

// Fetch allocated trainers (for the "assign class" dropdown)
$result_trainers_list = $conn->query("SELECT id, username FROM users WHERE role='trainer' AND allocated=1 ORDER BY username ASC");
$trainers_for_dropdown = [];
while ($t = $result_trainers_list->fetch_assoc()) {
    $trainers_for_dropdown[] = $t;
}

// Fetch classes (trainer assignments)
$result_classes = $conn->query("
    SELECT id, class_name, trainer, day, time, capacity, calories_per_session
    FROM classes
    ORDER BY id DESC
");
$classes_rows = [];
while ($c = $result_classes->fetch_assoc()) {
    $classes_rows[] = $c;
}

// Fetch community spotlight ("The Crew" on the member dashboard).
// Wrapped in try/catch — if the migration hasn't been run yet, this
// section just shows empty instead of crashing the whole admin panel.
$spotlight_rows = [];
try {
    $result_spotlight = $conn->query("SELECT id, name, tagline, photo, display_order FROM community_spotlight ORDER BY display_order ASC, id ASC");
    while ($s = $result_spotlight->fetch_assoc()) {
        $spotlight_rows[] = $s;
    }
} catch (\mysqli_sql_exception $e) {
    $spotlight_rows = [];
}

// Encode packages for JS (used to pre-fill the edit modal)
$packages_rows = [];
$result_packages->data_seek(0);
while ($pkg = $result_packages->fetch_assoc()) {
    $packages_rows[] = $pkg;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>HoodGym — Admin</title>
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
    --hazard-dim:#5c4d05;
    --chalk:#f3f2ec;
    --muted:#8f8d86;
    --danger:#ff4436;
    --success:#35d07f;
    --warn:#ff9f43;
    --sidebar-w:230px;
  }

  *{box-sizing:border-box;}
  html,body{margin:0;padding:0;}
  body{
    background:var(--bg);
    color:var(--chalk);
    font-family:'Inter',sans-serif;
    min-height:100vh;
    -webkit-font-smoothing:antialiased;
  }

  ::selection{background:var(--hazard);color:#000;}

  /* ---------- hazard stripe (signature motif) ---------- */
  .hazard-rule{
    height:6px;
    width:100%;
    background:repeating-linear-gradient(
      -45deg,
      var(--hazard) 0 14px,
      #111 14px 28px
    );
  }

  a{color:inherit;}

  /* ---------- layout shell ---------- */
  .shell{
    display:flex;
    min-height:100vh;
  }

  /* ---------- sidebar ---------- */
  .sidebar{
    width:var(--sidebar-w);
    flex-shrink:0;
    background:var(--panel);
    border-right:1px solid var(--line);
    display:flex;
    flex-direction:column;
    position:fixed;
    top:0; bottom:0; left:0;
    transition:transform .25s ease;
    z-index:50;
  }
  .brand{
    padding:26px 22px 18px;
  }
  .brand .mark{
    font-family:'Bebas Neue',sans-serif;
    font-size:34px;
    letter-spacing:1px;
    line-height:.9;
    color:var(--chalk);
  }
  .brand .mark span{color:var(--hazard);}
  .brand .sub{
    font-family:'JetBrains Mono',monospace;
    font-size:10px;
    letter-spacing:2px;
    color:var(--muted);
    text-transform:uppercase;
    margin-top:6px;
  }

  nav.primary{
    display:flex;
    flex-direction:column;
    padding:10px 12px;
    gap:2px;
    flex:1;
  }
  nav.primary a{
    display:flex;
    align-items:center;
    gap:12px;
    padding:12px 12px;
    border-radius:6px;
    text-decoration:none;
    color:var(--muted);
    font-weight:600;
    font-size:14px;
    letter-spacing:.3px;
    border-left:3px solid transparent;
  }
  nav.primary a svg{width:17px;height:17px;flex-shrink:0;}
  nav.primary a:hover{background:var(--panel-raised);color:var(--chalk);}
  nav.primary a.active{
    background:var(--panel-raised);
    color:var(--hazard);
    border-left:3px solid var(--hazard);
  }

  .sidebar-foot{
    padding:16px 12px 22px;
    border-top:1px solid var(--line);
  }
  .admin-chip{
    display:flex;
    align-items:center;
    gap:10px;
    padding:8px 10px;
    margin-bottom:8px;
  }
  .admin-chip .dot{
    width:8px;height:8px;border-radius:50%;
    background:var(--success);
    box-shadow:0 0 0 3px rgba(53,208,127,.15);
    flex-shrink:0;
  }
  .admin-chip .name{
    font-size:13px;font-weight:600;
    overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
  }
  .logout-link{
    display:flex;align-items:center;gap:10px;
    padding:10px 12px;
    border-radius:6px;
    text-decoration:none;
    color:var(--danger);
    font-weight:600;font-size:13px;
    background:rgba(255,68,54,.08);
  }
  .logout-link:hover{background:rgba(255,68,54,.16);}

  /* ---------- main ---------- */
  .main{
    margin-left:var(--sidebar-w);
    flex:1;
    min-width:0;
  }

  .topbar{
    background:var(--panel);
    border-bottom:1px solid var(--line);
    padding:22px 32px;
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:20px;
  }
  .topbar .eyebrow{
    font-family:'JetBrains Mono',monospace;
    font-size:11px;
    letter-spacing:2px;
    color:var(--muted);
    text-transform:uppercase;
  }
  .topbar h1{
    font-family:'Bebas Neue',sans-serif;
    font-size:36px;
    letter-spacing:.5px;
    margin:2px 0 0;
    color:var(--chalk);
  }
  .menu-toggle{
    display:none;
    background:var(--panel-raised);
    border:1px solid var(--line);
    color:var(--chalk);
    width:38px;height:38px;
    border-radius:6px;
    cursor:pointer;
  }

  .content{padding:32px;}
  .section{display:none;}
  .section.active{display:block;animation:fade .2s ease;}
  @keyframes fade{from{opacity:0;transform:translateY(4px);}to{opacity:1;transform:translateY(0);}}

  /* ---------- scoreboard stat cards ---------- */
  .stat-grid{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:18px;
    margin-bottom:28px;
  }
  .stat-card{
    background:var(--panel);
    border:1px solid var(--line);
    border-radius:10px;
    padding:22px 22px 18px;
    position:relative;
    overflow:hidden;
  }
  .stat-card::before{
    content:"";
    position:absolute;
    top:0;left:0;right:0;height:4px;
    background:repeating-linear-gradient(-45deg,var(--hazard) 0 8px,#111 8px 16px);
  }
  .stat-card .label{
    font-family:'JetBrains Mono',monospace;
    font-size:11px;
    letter-spacing:1.5px;
    text-transform:uppercase;
    color:var(--muted);
  }
  .stat-card .value{
    font-family:'JetBrains Mono',monospace;
    font-weight:700;
    font-size:48px;
    line-height:1.1;
    margin-top:8px;
    font-variant-numeric:tabular-nums;
  }
  .stat-card.members .value{color:var(--hazard);}
  .stat-card.trainers .value{color:var(--success);}

  /* ---------- cards / panels ---------- */
  .panel{
    background:var(--panel);
    border:1px solid var(--line);
    border-radius:10px;
    overflow:hidden;
  }
  .panel-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:18px 22px;
    border-bottom:1px solid var(--line);
    gap:12px;
    flex-wrap:wrap;
  }
  .panel-head h3{
    font-family:'Bebas Neue',sans-serif;
    font-size:22px;
    letter-spacing:.5px;
    margin:0;
    font-weight:400;
  }
  .panel-head-right{
    display:flex;
    align-items:center;
    gap:10px;
  }
  .count-tag{
    font-family:'JetBrains Mono',monospace;
    font-size:11px;
    color:var(--muted);
    background:var(--panel-raised);
    padding:4px 10px;
    border-radius:20px;
    border:1px solid var(--line);
  }

  /* ---------- tables ---------- */
  table{width:100%;border-collapse:collapse;}
  thead th{
    text-align:left;
    font-family:'JetBrains Mono',monospace;
    font-size:10.5px;
    letter-spacing:1.2px;
    text-transform:uppercase;
    color:var(--muted);
    padding:12px 22px;
    border-bottom:1px solid var(--line);
    background:var(--panel-raised);
  }
  tbody td{
    padding:14px 22px;
    border-bottom:1px solid var(--line);
    font-size:14px;
  }
  tbody tr:last-child td{border-bottom:none;}
  tbody tr:hover{background:rgba(255,255,255,.02);}
  td.mono{font-family:'JetBrains Mono',monospace;color:var(--muted);font-size:13px;}
  td.actions{display:flex;gap:8px;flex-wrap:wrap;}
  td.desc{color:var(--muted);font-size:13px;max-width:260px;}

  /* ---------- pills ---------- */
  .pill{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:4px 10px;
    border-radius:20px;
    font-size:12px;
    font-weight:600;
    letter-spacing:.2px;
  }
  .pill.role-admin{background:rgba(255,204,0,.14);color:var(--hazard);}
  .pill.role-trainer{background:rgba(53,208,127,.14);color:var(--success);}
  .pill.role-member{background:rgba(143,141,134,.16);color:var(--chalk);}
  .pill.status-completed,.pill.status-approved{background:rgba(53,208,127,.14);color:var(--success);}
  .pill.status-pending{background:rgba(255,159,67,.14);color:var(--warn);}
  .pill.status-rejected,.pill.status-failed{background:rgba(255,68,54,.14);color:var(--danger);}
  .pill.status-default{background:rgba(143,141,134,.16);color:var(--muted);}
  .pill .dot{width:6px;height:6px;border-radius:50%;background:currentColor;}

  /* ---------- buttons ---------- */
  .btn{
    display:inline-block;
    padding:7px 13px;
    border-radius:6px;
    text-decoration:none;
    font-size:12.5px;
    font-weight:700;
    letter-spacing:.2px;
    border:1px solid transparent;
    cursor:pointer;
    font-family:'Inter',sans-serif;
  }
  .btn-approve{background:rgba(53,208,127,.12);color:var(--success);border-color:rgba(53,208,127,.3);}
  .btn-approve:hover{background:var(--success);color:#0e0e0d;}
  .btn-reject{background:rgba(255,159,67,.12);color:var(--warn);border-color:rgba(255,159,67,.3);}
  .btn-reject:hover{background:var(--warn);color:#0e0e0d;}
  .btn-danger{background:rgba(255,68,54,.12);color:var(--danger);border-color:rgba(255,68,54,.3);}
  .btn-danger:hover{background:var(--danger);color:#fff;}
  .btn-edit{background:rgba(255,204,0,.12);color:var(--hazard);border-color:rgba(255,204,0,.3);}
  .btn-edit:hover{background:var(--hazard);color:#0e0e0d;}
  .btn-primary{background:var(--hazard);color:#0e0e0d;border-color:var(--hazard);}
  .btn-primary:hover{background:#e6b800;}

  .empty-row td{
    text-align:center;
    padding:34px 20px;
    color:var(--muted);
    font-family:'JetBrains Mono',monospace;
    font-size:12px;
    letter-spacing:1px;
    text-transform:uppercase;
  }

  footer{
    text-align:center;
    padding:22px;
    color:var(--muted);
    font-size:12px;
    font-family:'JetBrains Mono',monospace;
    letter-spacing:.5px;
  }

  /* ---------- modal ---------- */
  .modal-overlay{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.65);
    z-index:100;
    align-items:center;
    justify-content:center;
    padding:20px;
  }
  .modal-overlay.open{display:flex;}
  .modal-box{
    background:var(--panel);
    border:1px solid var(--line);
    border-radius:10px;
    width:100%;
    max-width:460px;
    overflow:hidden;
    max-height:90vh;
    overflow-y:auto;
  }
  .modal-box .hazard-rule{height:4px;}
  .modal-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:18px 22px;
    border-bottom:1px solid var(--line);
  }
  .modal-head h3{
    font-family:'Bebas Neue',sans-serif;
    font-size:22px;
    font-weight:400;
    margin:0;
  }
  .modal-close{
    background:none;border:none;color:var(--muted);
    font-size:20px;cursor:pointer;line-height:1;
    padding:4px;
  }
  .modal-close:hover{color:var(--chalk);}
  .modal-body{padding:22px;}
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
  .field input,.field textarea,.field select{
    width:100%;
    background:var(--panel-raised);
    border:1px solid var(--line);
    color:var(--chalk);
    border-radius:6px;
    padding:10px 12px;
    font-family:'Inter',sans-serif;
    font-size:14px;
  }
  .field input:focus,.field textarea:focus,.field select:focus{
    outline:none;
    border-color:var(--hazard);
  }
  .field textarea{resize:vertical;min-height:70px;}
  .field-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
  .modal-foot{
    display:flex;
    justify-content:flex-end;
    gap:10px;
    padding:16px 22px 22px;
  }
  .btn-cancel{background:var(--panel-raised);color:var(--muted);border-color:var(--line);}
  .btn-cancel:hover{background:#2a2a27;color:var(--chalk);}

  /* ---------- responsive ---------- */
  @media (max-width:900px){
    .stat-grid{grid-template-columns:1fr;}
    .sidebar{transform:translateX(-100%);}
    .sidebar.open{transform:translateX(0);}
    .main{margin-left:0;}
    .menu-toggle{display:block;}
    thead{display:none;}
    table,tbody,tr,td{display:block;width:100%;}
    tbody tr{border-bottom:1px solid var(--line);padding:12px 0;}
    tbody td{border-bottom:none;padding:6px 22px;display:flex;justify-content:space-between;gap:10px;text-align:right;}
    tbody td::before{content:attr(data-label);font-family:'JetBrains Mono',monospace;font-size:10px;letter-spacing:1px;text-transform:uppercase;color:var(--muted);text-align:left;}
    td.actions{justify-content:flex-end;}
    .field-row{grid-template-columns:1fr;}
  }
</style>
</head>
<body>

<div class="shell">

  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <div class="mark">HOOD<span>GYM</span></div>
      <div class="sub">Admin Control</div>
    </div>
    <div class="hazard-rule"></div>

    <nav class="primary" id="primaryNav">
      <a href="#overview" data-target="overview" class="active">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
        Overview
      </a>
      <a href="#Trainers" data-target="Trainers">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Trainers
      </a>
      <a href="#Users" data-target="Users">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Users
      </a>
      <a href="#Packages" data-target="Packages">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41 11 3.83A2 2 0 0 0 9.59 3.24L4 3a1 1 0 0 0-1 1l.24 5.59a2 2 0 0 0 .59 1.41l9.58 9.59a2 2 0 0 0 2.83 0l4.35-4.35a2 2 0 0 0 0-2.83Z"/><circle cx="7.5" cy="7.5" r="1.5"/></svg>
        Packages
      </a>
      <a href="#Classes" data-target="Classes">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
        Classes
      </a>
      <a href="#bookings" data-target="bookings">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        Bookings
      </a>
      <a href="#payments" data-target="payments">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
        Payments
      </a>
      <a href="#Community" data-target="Community">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="14" rx="2"/><path d="M3 15l4.5-4.5a2 2 0 0 1 2.8 0L15 15"/><path d="M13 13l1.5-1.5a2 2 0 0 1 2.8 0L21 15"/><circle cx="8" cy="8" r="1.5"/></svg>
        Community
      </a>
    </nav>

    <div class="sidebar-foot">
      <div class="admin-chip">
        <span class="dot"></span>
        <span class="name"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
      </div>
      <a href="admin_logout.php" class="logout-link">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
        Log out
      </a>
    </div>
  </aside>

  <!-- Main -->
  <div class="main">
    <div class="topbar">
      <div style="display:flex;align-items:center;gap:14px;">
        <button class="menu-toggle" id="menuToggle" aria-label="Toggle menu">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
        </button>
        <div>
          <div class="eyebrow" id="pageEyebrow">Dashboard</div>
          <h1 id="pageTitle">Overview</h1>
        </div>
      </div>
    </div>
    <div class="hazard-rule"></div>

    <div class="content">

      <!-- Overview -->
      <div id="overview" class="section active">
        <div class="stat-grid">
          <div class="stat-card">
            <div class="label">Total Users</div>
            <div class="value"><?php echo $totalUsers; ?></div>
          </div>
          <div class="stat-card members">
            <div class="label">Members</div>
            <div class="value"><?php echo $totalMembers; ?></div>
          </div>
          <div class="stat-card trainers">
            <div class="label">Allocated Trainers</div>
            <div class="value"><?php echo $totalTrainers; ?></div>
          </div>
        </div>
      </div>

      <!-- Trainers -->
      <div id="Trainers" class="section">
        <?php if (isset($_GET['trainer_success'])): ?>
          <div style="background:rgba(53,208,127,.1);border:1px solid rgba(53,208,127,.3);color:var(--success);font-size:13px;padding:10px 14px;border-radius:6px;margin-bottom:16px;">
            <?php
              $tsuccess = $_GET['trainer_success'];
              if ($tsuccess === 'created') echo 'Trainer account created — they can now log in at the trainer login page.';
              elseif ($tsuccess === 'promoted') echo 'Member promoted to trainer — they can now log in at the trainer login page.';
              else echo 'Done.';
            ?>
          </div>
        <?php endif; ?>
        <?php if (isset($_GET['trainer_error'])): ?>
          <div style="background:rgba(255,68,54,.1);border:1px solid rgba(255,68,54,.3);color:var(--danger);font-size:13px;padding:10px 14px;border-radius:6px;margin-bottom:16px;">
            <?php
              $terr = $_GET['trainer_error'];
              if ($terr === 'username_taken') echo 'That username is already taken.';
              elseif ($terr === 'bad_username') echo 'Username must be 3-50 characters (letters, numbers, underscore, period only).';
              elseif ($terr === 'promote_failed') echo 'Could not promote that account — it may no longer be a member.';
              else echo 'Please check the trainer details and try again.';
            ?>
          </div>
        <?php endif; ?>
        <div class="panel">
          <div class="panel-head">
            <h3>Trainer Management</h3>
            <div class="panel-head-right">
  <span class="count-tag"><?php echo $result_trainers_only->num_rows; ?> total</span>
  <button type="button" class="btn btn-primary" onclick="openTrainerModal()">+ Add Trainer</button>
</div>
          </div>
          <table>
            <thead>
              <tr><th>ID</th><th>Username</th><th>Role</th><th>Allocation</th><th>Actions</th></tr>
            </thead>
            <tbody>
  <?php if ($result_trainers_only->num_rows === 0): ?>
    <tr class="empty-row"><td colspan="5">No trainers found</td></tr>
  <?php else: $result_trainers_only->data_seek(0); while ($user = $result_trainers_only->fetch_assoc()): ?>
  <tr>
    <td class="mono" data-label="ID">#<?php echo $user['id']; ?></td>
    <td data-label="Username"><?php echo htmlspecialchars($user['username']); ?></td>
    <td data-label="Role">
      <span class="pill role-<?php echo htmlspecialchars($user['role']); ?>"><span class="dot"></span><?php echo htmlspecialchars(ucfirst($user['role'])); ?></span>
    </td>
    <td data-label="Allocation">
      <?php if ($user['allocated']): ?>
        <span class="pill status-approved">✅ Allocated</span>
      <?php else: ?>
        <span class="pill status-rejected">❌ Not allocated</span>
      <?php endif; ?>
    </td>
    <td class="actions" data-label="Actions">
      <?php if (!$user['allocated']): ?>
        <a class="btn btn-approve" href="allocate_trainer.php?id=<?php echo $user['id']; ?>">Allocate</a>
      <?php else: ?>
        <a class="btn btn-reject" href="deallocate_trainer.php?id=<?php echo $user['id']; ?>">Remove</a>
      <?php endif; ?>
      <a class="btn btn-danger" href="delete.php?id=<?php echo $user['id']; ?>" onclick="return confirm('Delete this user?');">Delete</a>
    </td>
  </tr>
  <?php endwhile; endif; ?>
</tbody>
          </table>
        </div>
      </div>

      <!-- Users -->
      <div id="Users" class="section">
        <div class="panel">
          <div class="panel-head">
            <h3>User's Management</h3>
            <span class="count-tag"><?php echo $totalUsers; ?> total</span>
          </div>
          <table>
            <thead>
              <tr><th>ID</th><th>Username</th><th>Role</th><th>Allocation</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <?php if ($result_users->num_rows === 0): ?>
                <tr class="empty-row"><td colspan="5">No users found</td></tr>
              <?php else: $result_users->data_seek(0); while ($user = $result_users->fetch_assoc()): ?>
              <tr>
                <td class="mono" data-label="ID">#<?php echo $user['id']; ?></td>
                <td data-label="Username"><?php echo htmlspecialchars($user['username']); ?></td>
                <td data-label="Role">
                  <span class="pill role-<?php echo htmlspecialchars($user['role']); ?>"><span class="dot"></span><?php echo htmlspecialchars(ucfirst($user['role'])); ?></span>
                </td>
                <td data-label="Allocation">
                  <?php if ($user['role']==='admin'): ?>
                    <span class="pill role-admin">👑 Default</span>
                  <?php elseif ($user['allocated']): ?>
                    <span class="pill status-approved">✅ Allocated</span>
                  <?php else: ?>
                    <span class="pill status-rejected">❌ Not allocated</span>
                  <?php endif; ?>
                </td>
                <td class="actions" data-label="Actions">
                  <?php if ($user['role'] === 'trainer' && !$user['allocated']): ?>
                    <a class="btn btn-approve" href="allocate_trainer.php?id=<?php echo $user['id']; ?>">Allocate</a>
                  <?php elseif ($user['role'] === 'trainer' && $user['allocated']): ?>
                    <a class="btn btn-reject" href="deallocate_trainer.php?id=<?php echo $user['id']; ?>">Remove</a>
                  <?php endif; ?>
                  <?php if ($user['role'] !== 'admin'): ?>
                    <a class="btn btn-danger" href="delete.php?id=<?php echo $user['id']; ?>" onclick="return confirm('Delete this user?');">Delete</a>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endwhile; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Packages -->
      <div id="Packages" class="section">
        <div class="panel">
          <div class="panel-head">
            <h3>Packages</h3>
            <div class="panel-head-right">
              <span class="count-tag"><?php echo $result_packages->num_rows; ?> total</span>
              <button type="button" class="btn btn-primary" onclick="openPackageModal()">+ Add Package</button>
            </div>
          </div>
          <table>
            <thead>
              <tr><th>ID</th><th>Name</th><th>Description</th><th>Price</th><th>Duration</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <?php if ($result_packages->num_rows === 0): ?>
                <tr class="empty-row"><td colspan="6">No packages yet — add your first one</td></tr>
              <?php else: foreach ($packages_rows as $pkg): ?>
              <tr>
                <td class="mono" data-label="ID">#<?php echo $pkg['id']; ?></td>
                <td data-label="Name"><?php echo htmlspecialchars($pkg['name']); ?></td>
                <td class="desc" data-label="Description"><?php echo htmlspecialchars($pkg['description'] ?: '—'); ?></td>
                <td class="mono" data-label="Price">$<?php echo number_format((float)$pkg['price'], 2); ?></td>
                <td data-label="Duration"><?php echo (int)$pkg['duration_days']; ?> days</td>
                <td class="actions" data-label="Actions">
                  <button type="button" class="btn btn-edit"
                    onclick='openPackageModal(<?php echo json_encode($pkg); ?>)'>Edit</button>
                  <a class="btn btn-danger" href="package_delete.php?id=<?php echo $pkg['id']; ?>" onclick="return confirm('Delete this package?');">Delete</a>
                </td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Classes -->
      <div id="Classes" class="section">
        <div class="panel">
          <div class="panel-head">
            <h3>Class Assignments</h3>
            <div class="panel-head-right">
              <span class="count-tag"><?php echo count($classes_rows); ?> total</span>
              <button type="button" class="btn btn-primary" onclick="openClassModal()"
                <?php echo empty($trainers_for_dropdown) ? 'disabled title="Allocate a trainer first"' : ''; ?>>+ Assign Class</button>
            </div>
          </div>
          <table>
            <thead>
              <tr><th>ID</th><th>Class</th><th>Trainer</th><th>Day</th><th>Time</th><th>Capacity</th><th>Calories</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <?php if (empty($classes_rows)): ?>
                <tr class="empty-row"><td colspan="8">No classes assigned yet</td></tr>
              <?php else: foreach ($classes_rows as $c): ?>
              <tr>
                <td class="mono" data-label="ID">#<?php echo $c['id']; ?></td>
                <td data-label="Class"><?php echo htmlspecialchars($c['class_name']); ?></td>
                <td data-label="Trainer">
                  <span class="pill role-trainer"><span class="dot"></span><?php echo htmlspecialchars($c['trainer']); ?></span>
                </td>
                <td data-label="Day"><?php echo htmlspecialchars($c['day']); ?></td>
                <td class="mono" data-label="Time"><?php echo htmlspecialchars($c['time']); ?></td>
                <td class="mono" data-label="Capacity"><?php echo $c['capacity'] !== null ? (int)$c['capacity'] : '—'; ?></td>
                <td class="mono" data-label="Calories"><?php echo (int)($c['calories_per_session'] ?? 300); ?> kcal</td>
                <td class="actions" data-label="Actions">
                  <button type="button" class="btn btn-edit"
                    onclick='openClassModal(<?php echo json_encode($c); ?>)'>Edit</button>
                  <a class="btn btn-danger" href="class_delete.php?id=<?php echo $c['id']; ?>" onclick="return confirm('Remove this class assignment?');">Delete</a>
                </td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Bookings -->
      <div id="bookings" class="section">
        <?php if (isset($_GET['booking_success'])): ?>
          <div style="background:rgba(53,208,127,.1);border:1px solid rgba(53,208,127,.3);color:var(--success);font-size:13px;padding:10px 14px;border-radius:6px;margin-bottom:16px;">
            Booking <?php echo htmlspecialchars($_GET['booking_success']); ?> successfully.
          </div>
        <?php endif; ?>
        <?php if (isset($_GET['booking_error'])): ?>
          <div style="background:rgba(255,68,54,.1);border:1px solid rgba(255,68,54,.3);color:var(--danger);font-size:13px;padding:10px 14px;border-radius:6px;margin-bottom:16px;">
            Something went wrong updating that booking. Please try again.
          </div>
        <?php endif; ?>
        <div class="panel">
          <div class="panel-head">
            <h3>Bookings</h3>
            <span class="count-tag"><?php echo $result_bookings->num_rows; ?> total</span>
          </div>
          <table>
            <thead>
              <tr><th>ID</th><th>User</th><th>Package</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <?php if ($result_bookings->num_rows === 0): ?>
                <tr class="empty-row"><td colspan="5">No bookings yet</td></tr>
              <?php else: while ($b = $result_bookings->fetch_assoc()):
                $bStatusRaw = $b['status'] ?: 'Pending';
                $bStatus = strtolower($bStatusRaw);
                $bClass = 'status-default';
                if ($bStatus === 'approved') $bClass = 'status-completed';
                elseif ($bStatus === 'pending') $bClass = 'status-pending';
                elseif ($bStatus === 'rejected') $bClass = 'status-rejected';
              ?>
              <tr>
                <td class="mono" data-label="ID">#<?php echo $b['id']; ?></td>
                <td data-label="User"><?php echo htmlspecialchars($b['username']); ?></td>
                <td data-label="Package"><?php echo htmlspecialchars($b['package_name']); ?></td>
                <td data-label="Status"><span class="pill <?php echo $bClass; ?>"><span class="dot"></span><?php echo htmlspecialchars(ucfirst($bStatus)); ?></span></td>
                <td class="actions" data-label="Actions">
                  <?php if ($bStatus !== 'approved'): ?>
                    <a class="btn btn-approve" href="approve_booking.php?id=<?php echo $b['id']; ?>">Approve</a>
                  <?php endif; ?>
                  <?php if ($bStatus !== 'rejected'): ?>
                    <a class="btn btn-reject" href="reject_booking.php?id=<?php echo $b['id']; ?>">Reject</a>
                  <?php endif; ?>
                  <a class="btn btn-danger" href="delete_booking.php?id=<?php echo $b['id']; ?>" onclick="return confirm('Delete this booking?');">Delete</a>
                </td>
              </tr>
              <?php endwhile; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Payments -->
      <div id="payments" class="section">
        <?php if (isset($_GET['payment_success'])): ?>
          <div style="background:rgba(53,208,127,.1);border:1px solid rgba(53,208,127,.3);color:var(--success);font-size:13px;padding:10px 14px;border-radius:6px;margin-bottom:16px;">
            Payment <?php echo htmlspecialchars($_GET['payment_success']); ?> successfully.
          </div>
        <?php endif; ?>
        <?php if (isset($_GET['payment_error'])): ?>
          <div style="background:rgba(255,68,54,.1);border:1px solid rgba(255,68,54,.3);color:var(--danger);font-size:13px;padding:10px 14px;border-radius:6px;margin-bottom:16px;">
            Something went wrong updating that payment. Please try again.
          </div>
        <?php endif; ?>
        <div class="panel">
          <div class="panel-head">
            <h3>Payments</h3>
            <span class="count-tag"><?php echo $result_payments->num_rows; ?> total</span>
          </div>
          <table>
            <thead>
              <tr><th>ID</th><th>User</th><th>Package</th><th>Amount</th><th>Method</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <?php if ($result_payments->num_rows === 0): ?>
                <tr class="empty-row"><td colspan="7">No payments yet</td></tr>
              <?php else: while ($p = $result_payments->fetch_assoc()):
                $statusClass = 'status-default';
                $statusLower = strtolower($p['status']);
                if (in_array($statusLower, ['completed','approved'])) $statusClass = 'status-completed';
                elseif ($statusLower === 'pending') $statusClass = 'status-pending';
                elseif (in_array($statusLower, ['rejected','failed'])) $statusClass = 'status-rejected';
              ?>
              <tr>
                <td class="mono" data-label="ID">#<?php echo $p['id']; ?></td>
                <td data-label="User"><?php echo htmlspecialchars($p['username']); ?></td>
                <td data-label="Package"><?php echo htmlspecialchars($p['package_name']); ?></td>
                <td class="mono" data-label="Amount"><?php echo htmlspecialchars($p['amount']); ?></td>
                <td data-label="Method"><?php echo htmlspecialchars($p['method']); ?></td>
                <td data-label="Status"><span class="pill <?php echo $statusClass; ?>"><span class="dot"></span><?php echo htmlspecialchars(ucfirst($p['status'])); ?></span></td>
                <td class="actions" data-label="Actions">
                  <?php if ($statusLower !== 'completed' && $statusLower !== 'approved'): ?>
                    <a class="btn btn-approve" href="approve_payment.php?id=<?php echo $p['id']; ?>">Approve</a>
                  <?php endif; ?>
                  <?php if ($statusLower !== 'rejected'): ?>
                    <a class="btn btn-reject" href="reject_payment.php?id=<?php echo $p['id']; ?>">Reject</a>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endwhile; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Community -->
      <div id="Community" class="section">
        <?php if (isset($_GET['spotlight_success'])): ?>
          <div style="background:rgba(53,208,127,.1);border:1px solid rgba(53,208,127,.3);color:var(--success);font-size:13px;padding:10px 14px;border-radius:6px;margin-bottom:16px;">
            Crew spotlight updated.
          </div>
        <?php endif; ?>
        <?php if (isset($_GET['spotlight_error'])): ?>
          <div style="background:rgba(255,68,54,.1);border:1px solid rgba(255,68,54,.3);color:var(--danger);font-size:13px;padding:10px 14px;border-radius:6px;margin-bottom:16px;">
            <?php
              $serr = $_GET['spotlight_error'];
              if ($serr === 'missing_name') echo 'Name is required.';
              elseif ($serr === 'bad_file_type') echo 'Photo must be a JPG, PNG, or WEBP file.';
              elseif ($serr === 'file_too_large') echo 'Photo must be under 4MB.';
              elseif ($serr === 'upload_failed') echo 'Photo upload failed — please try again.';
              else echo 'Something went wrong saving that entry.';
            ?>
          </div>
        <?php endif; ?>
        <div class="panel">
          <div class="panel-head">
            <h3>The Crew — Dashboard Spotlight</h3>
            <div class="panel-head-right">
              <span class="count-tag"><?php echo count($spotlight_rows); ?> total</span>
              <button type="button" class="btn btn-primary" onclick="openSpotlightModal()">+ Add to Crew</button>
            </div>
          </div>
          <table>
            <thead>
              <tr><th>Photo</th><th>Name</th><th>Tagline</th><th>Order</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <?php if (empty($spotlight_rows)): ?>
                <tr class="empty-row"><td colspan="5">No crew members spotlighted yet — this is what shows in "Meet Our Fit Community" on the member dashboard.</td></tr>
              <?php else: foreach ($spotlight_rows as $s): ?>
              <tr>
                <td data-label="Photo">
                  <?php if (!empty($s['photo'])): ?>
                    <img src="<?php echo htmlspecialchars($s['photo']); ?>" alt="" style="width:44px;height:44px;border-radius:50%;object-fit:cover;">
                  <?php else: ?>
                    <span class="pill status-default">No photo</span>
                  <?php endif; ?>
                </td>
                <td data-label="Name"><?php echo htmlspecialchars($s['name']); ?></td>
                <td class="desc" data-label="Tagline"><?php echo htmlspecialchars($s['tagline'] ?: '—'); ?></td>
                <td class="mono" data-label="Order"><?php echo (int)$s['display_order']; ?></td>
                <td class="actions" data-label="Actions">
                  <button type="button" class="btn btn-edit"
                    onclick='openSpotlightModal(<?php echo json_encode($s); ?>)'>Edit</button>
                  <a class="btn btn-danger" href="community_spotlight_delete.php?id=<?php echo $s['id']; ?>" onclick="return confirm('Remove this person from the crew spotlight?');">Delete</a>
                </td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>

    <footer>© 2026 HoodGym — Admin Control Panel</footer>
  </div>
</div>

<!-- ================= Spotlight (Crew) Modal ================= -->
<div class="modal-overlay" id="spotlightModalOverlay">
  <div class="modal-box">
    <div class="hazard-rule"></div>
    <div class="modal-head">
      <h3 id="spotlightModalTitle">Add to Crew</h3>
      <button type="button" class="modal-close" onclick="closeModal('spotlightModalOverlay')">&times;</button>
    </div>
    <form action="community_spotlight_save.php" method="POST" enctype="multipart/form-data">
      <div class="modal-body">
        <input type="hidden" name="id" id="spotlight_id">
        <div class="field">
          <label for="spotlight_name">Name</label>
          <input type="text" id="spotlight_name" name="name" required maxlength="100" placeholder="e.g. Sarah">
        </div>
        <div class="field">
          <label for="spotlight_tagline">Tagline</label>
          <input type="text" id="spotlight_tagline" name="tagline" maxlength="150" placeholder="e.g. Fitness Coach">
        </div>
        <div class="field">
          <label for="spotlight_photo">Photo</label>
          <input type="file" id="spotlight_photo" name="photo" accept="image/jpeg,image/png,image/webp">
          <p id="spotlight_current_photo_note" style="color:var(--muted);font-size:12px;margin-top:6px;display:none;">Leave blank to keep the current photo.</p>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-cancel" onclick="closeModal('spotlightModalOverlay')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- ================= Package Modal ================= -->
<div class="modal-overlay" id="packageModalOverlay">
  <div class="modal-box">
    <div class="hazard-rule"></div>
    <div class="modal-head">
      <h3 id="packageModalTitle">Add Package</h3>
      <button type="button" class="modal-close" onclick="closeModal('packageModalOverlay')">&times;</button>
    </div>
    <form action="package_save.php" method="POST">
      <div class="modal-body">
        <input type="hidden" name="id" id="package_id">
        <div class="field">
          <label for="package_name">Package name</label>
          <input type="text" id="package_name" name="name" required maxlength="100" placeholder="e.g. Gold Membership">
        </div>
        <div class="field">
          <label for="package_description">Description</label>
          <textarea id="package_description" name="description" placeholder="What's included in this package"></textarea>
        </div>
        <div class="field-row">
          <div class="field">
            <label for="package_price">Price ($)</label>
            <input type="number" id="package_price" name="price" step="0.01" min="0" required>
          </div>
          <div class="field">
            <label for="package_duration">Duration (days)</label>
            <input type="number" id="package_duration" name="duration_days" min="1" required value="30">
          </div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-cancel" onclick="closeModal('packageModalOverlay')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Package</button>
      </div>
    </form>
  </div>
</div>

<!-- ================= Add Trainer Modal ================= -->
<div class="modal-overlay" id="trainerModalOverlay">
  <div class="modal-box">
    <div class="hazard-rule"></div>
    <div class="modal-head">
      <h3>Add Trainer</h3>
      <button type="button" class="modal-close" onclick="closeModal('trainerModalOverlay')">&times;</button>
    </div>

    <div style="display:flex;gap:8px;padding:0 22px;margin-top:18px;">
      <button type="button" class="btn btn-primary" id="tabCreateBtn" onclick="switchTrainerTab('create')" style="flex:1;">Create New</button>
      <button type="button" class="btn btn-cancel" id="tabPromoteBtn" onclick="switchTrainerTab('promote')" style="flex:1;">Promote Member</button>
    </div>

    <!-- Create new trainer -->
    <form action="trainer_add.php" method="POST" id="createTrainerForm">
      <input type="hidden" name="mode" value="create">
      <div class="modal-body">
        <div class="field">
          <label for="trainer_username">Username</label>
          <input type="text" id="trainer_username" name="username" required maxlength="50" placeholder="e.g. coach_dave">
        </div>
        <div class="field">
          <label for="trainer_password">Password</label>
          <input type="password" id="trainer_password" name="password" required minlength="6" placeholder="At least 6 characters">
        </div>
        <div class="field">
          <label for="trainer_confirm_password">Confirm password</label>
          <input type="password" id="trainer_confirm_password" name="confirm_password" required minlength="6">
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-cancel" onclick="closeModal('trainerModalOverlay')">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Trainer</button>
      </div>
    </form>

    <!-- Promote existing member -->
    <form action="trainer_add.php" method="POST" id="promoteTrainerForm" style="display:none;">
      <input type="hidden" name="mode" value="promote">
      <div class="modal-body">
        <div class="field">
          <label for="promote_user_id">Member</label>
          <select id="promote_user_id" name="user_id" required>
            <option value="">Select a member…</option>
            <?php foreach ($members_for_dropdown as $m): ?>
              <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['username']); ?></option>
            <?php endforeach; ?>
          </select>
          <?php if (empty($members_for_dropdown)): ?>
            <p style="color:var(--muted);font-size:12.5px;margin-top:8px;">No members available to promote.</p>
          <?php endif; ?>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-cancel" onclick="closeModal('trainerModalOverlay')">Cancel</button>
        <button type="submit" class="btn btn-primary" <?php echo empty($members_for_dropdown) ? 'disabled' : ''; ?>>Promote to Trainer</button>
      </div>
    </form>
  </div>
</div>

<!-- ================= Class Assignment Modal ================= -->
<div class="modal-overlay" id="classModalOverlay">
  <div class="modal-box">
    <div class="hazard-rule"></div>
    <div class="modal-head">
      <h3 id="classModalTitle">Assign Class</h3>
      <button type="button" class="modal-close" onclick="closeModal('classModalOverlay')">&times;</button>
    </div>
    <form action="class_save.php" method="POST">
      <div class="modal-body">
        <input type="hidden" name="id" id="class_id">
        <div class="field">
          <label for="class_name">Class name</label>
          <input type="text" id="class_name" name="class_name" required maxlength="100" placeholder="e.g. Morning HIIT">
        </div>
        <div class="field">
          <label for="class_trainer">Trainer</label>
          <select id="class_trainer" name="trainer" required>
            <option value="">Select a trainer…</option>
            <?php foreach ($trainers_for_dropdown as $t): ?>
              <option value="<?php echo htmlspecialchars($t['username']); ?>"><?php echo htmlspecialchars($t['username']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field-row">
          <div class="field">
            <label for="class_day">Day</label>
            <select id="class_day" name="day" required>
              <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $day): ?>
                <option value="<?php echo $day; ?>"><?php echo $day; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="class_time">Time</label>
            <input type="time" id="class_time" name="time" required>
          </div>
        </div>
        <div class="field-row">
          <div class="field">
            <label for="class_capacity">Capacity</label>
            <input type="number" id="class_capacity" name="capacity" min="1" value="20">
          </div>
          <div class="field">
            <label for="class_calories">Calories / session</label>
            <input type="number" id="class_calories" name="calories_per_session" min="0" value="300">
          </div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-cancel" onclick="closeModal('classModalOverlay')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Class</button>
      </div>
    </form>
  </div>
</div>

<script>
  const links = document.querySelectorAll('nav.primary a');
  const titles   = { overview: 'Overview', Trainers: 'Trainers', Users: 'Users', Packages: 'Packages', Classes: 'Classes', bookings: 'Bookings', payments: 'Payments', Community: 'Community' };
  const eyebrows = { overview: 'Dashboard', Trainers: 'Roster', Users: 'Roster', Packages: 'Catalog', Classes: 'Schedule', bookings: 'Schedule', payments: 'Ledger', Community: 'Spotlight' };

  function showSection(id){
    document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
    const target = document.getElementById(id);
    if (!target) return;
    target.classList.add('active');
    links.forEach(l => l.classList.toggle('active', l.dataset.target === id));
    document.getElementById('pageTitle').textContent = titles[id] || id;
    document.getElementById('pageEyebrow').textContent = eyebrows[id] || '';
    document.getElementById('sidebar').classList.remove('open');
  }

  links.forEach(link => {
    link.addEventListener('click', e => {
      e.preventDefault();
      showSection(link.dataset.target);
      history.replaceState(null, '', '#' + link.dataset.target);
    });
  });

  document.getElementById('menuToggle').addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('open');
  });

  window.addEventListener('load', () => {
    const hash = location.hash.replace('#','');
    showSection(titles[hash] ? hash : 'overview');
  });

  /* ---------- modal helpers ---------- */
  function closeModal(overlayId){
    document.getElementById(overlayId).classList.remove('open');
  }
  document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => {
      if (e.target === overlay) closeModal(overlay.id);
    });
  });

  /* ---------- package modal ---------- */
  function openPackageModal(pkg){
    document.getElementById('packageModalTitle').textContent = pkg ? 'Edit Package' : 'Add Package';
    document.getElementById('package_id').value = pkg ? pkg.id : '';
    document.getElementById('package_name').value = pkg ? pkg.name : '';
    document.getElementById('package_description').value = pkg ? (pkg.description || '') : '';
    document.getElementById('package_price').value = pkg ? pkg.price : '';
    document.getElementById('package_duration').value = pkg ? pkg.duration_days : 30;
    document.getElementById('packageModalOverlay').classList.add('open');
  }

  /* ---------- class modal ---------- */
  function openClassModal(cls){
    document.getElementById('classModalTitle').textContent = cls ? 'Edit Class' : 'Assign Class';
    document.getElementById('class_id').value = cls ? cls.id : '';
    document.getElementById('class_name').value = cls ? cls.class_name : '';
    document.getElementById('class_trainer').value = cls ? cls.trainer : '';
    document.getElementById('class_day').value = cls ? cls.day : 'Monday';
    document.getElementById('class_time').value = cls ? cls.time : '';
    document.getElementById('class_capacity').value = cls && cls.capacity !== null ? cls.capacity : 20;
    document.getElementById('class_calories').value = cls && cls.calories_per_session !== undefined ? cls.calories_per_session : 300;
    document.getElementById('classModalOverlay').classList.add('open');
  }
  /* ---------- trainer modal ---------- */
  function openTrainerModal(){
    switchTrainerTab('create');
    document.getElementById('trainerModalOverlay').classList.add('open');
  }

  function switchTrainerTab(tab){
    const createForm = document.getElementById('createTrainerForm');
    const promoteForm = document.getElementById('promoteTrainerForm');
    const createBtn = document.getElementById('tabCreateBtn');
    const promoteBtn = document.getElementById('tabPromoteBtn');

    if (tab === 'create'){
      createForm.style.display = '';
      promoteForm.style.display = 'none';
      createBtn.classList.add('btn-primary');
      createBtn.classList.remove('btn-cancel');
      promoteBtn.classList.add('btn-cancel');
      promoteBtn.classList.remove('btn-primary');
    } else {
      createForm.style.display = 'none';
      promoteForm.style.display = '';
      promoteBtn.classList.add('btn-primary');
      promoteBtn.classList.remove('btn-cancel');
      createBtn.classList.add('btn-cancel');
      createBtn.classList.remove('btn-primary');
    }
  }

  /* ---------- spotlight (crew) modal ---------- */
  function openSpotlightModal(s){
    document.getElementById('spotlightModalTitle').textContent = s ? 'Edit Crew Member' : 'Add to Crew';
    document.getElementById('spotlight_id').value = s ? s.id : '';
    document.getElementById('spotlight_name').value = s ? s.name : '';
    document.getElementById('spotlight_tagline').value = s ? (s.tagline || '') : '';
    document.getElementById('spotlight_photo').value = '';
    document.getElementById('spotlight_current_photo_note').style.display = s ? 'block' : 'none';
    document.getElementById('spotlightModalOverlay').classList.add('open');
  }
</script>
</body>
</html>