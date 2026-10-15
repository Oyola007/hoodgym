<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

/* ============================================================
   Confirmed schema (from trainer.php + your DESCRIBE output):

   users: id, username, role ENUM('member','trainer','admin', ...)
   class_attendance: id, class_id, user_id, session_date (date),
                      status ENUM('present','absent'), marked_by, created_at
   classes: id, class_name, day, time, capacity, trainer (username text)

   Still unconfirmed (not used below until you confirm): users.bio,
   users.joined_at/created_at. Queries are wrapped in try/catch so an
   unconfirmed column or table fails quietly into an empty state
   instead of crashing the page.
   ============================================================ */

require_once 'db.php';
$user_id = $_SESSION['user_id'];

function safe_query($conn, $sql, $types = '', $params = []) {
    try {
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) return [];
        if ($types) mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = [];
        if ($result) { while ($r = mysqli_fetch_assoc($result)) $rows[] = $r; }
        mysqli_stmt_close($stmt);
        return $rows;
    } catch (\mysqli_sql_exception $e) {
        return [];
    }
}

// Leaderboard: top members by sessions marked "present" this calendar month
$leaderboard = safe_query($conn, "
    SELECT u.id, u.username, COUNT(ca.id) AS session_count
    FROM users u
    JOIN class_attendance ca ON ca.user_id = u.id
    WHERE u.role = 'member'
      AND ca.status = 'present'
      AND MONTH(ca.session_date) = MONTH(CURRENT_DATE())
      AND YEAR(ca.session_date) = YEAR(CURRENT_DATE())
    GROUP BY u.id, u.username
    ORDER BY session_count DESC
    LIMIT 10
");

// Trainers directory
$trainers = safe_query($conn, "
    SELECT id, username FROM users WHERE role = 'trainer' ORDER BY username ASC
");

// Member directory (everyone else, excluding self)
// Note: joined_at/created_at not yet confirmed to exist on users, so
// ordering is by id DESC (newest accounts have higher ids) for now.
$members = safe_query($conn, "
    SELECT id, username FROM users
    WHERE role = 'member' AND id != ?
    ORDER BY id DESC LIMIT 24
", "i", [$user_id]);

$avatar_palette = ['#FFD400', '#E63946', '#8a8a8a', '#B8990A'];
function initials($name) {
    $parts = preg_split('/\s+/', trim($name));
    $letters = array_map(fn($p) => strtoupper(substr($p, 0, 1)), array_slice($parts, 0, 2));
    return implode('', $letters);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HoodGym — Community</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --black:#0a0a0a; --charcoal:#141414; --panel:#1a1a1a; --line:#2a2a2a;
    --hazard:#FFD400; --hazard-dim:#B8990A; --danger:#E63946;
    --text:#EDEDED; --muted:#8a8a8a;
    --font-display:'Bebas Neue',sans-serif; --font-body:'Inter',sans-serif; --font-mono:'JetBrains Mono',monospace;
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

  /* Leaderboard */
  .leaderboard{background:var(--panel);border:1px solid var(--line);}
  .lb-row{
    display:grid;
    grid-template-columns:60px 1fr 120px;
    align-items:center;
    padding:16px 24px;
    border-bottom:1px solid var(--line);
  }
  .lb-row:last-child{border-bottom:none;}
  .lb-row .rank{
    font-family:var(--font-mono);
    font-size:20px;
    font-weight:700;
    color:var(--muted);
  }
  .lb-row:nth-child(1) .rank{color:var(--hazard);}
  .lb-row:nth-child(2) .rank{color:#d8d8d8;}
  .lb-row:nth-child(3) .rank{color:var(--hazard-dim);}
  .lb-row .name{font-weight:600;font-size:15px;}
  .lb-row .count{
    font-family:var(--font-mono);
    text-align:right;
    color:var(--hazard);
    font-weight:700;
  }
  .lb-row .count .lbl{color:var(--muted);font-weight:400;font-size:11px;margin-left:4px;text-transform:uppercase;}

  /* Avatars */
  .avatar{
    width:40px;height:40px;
    border-radius:50%;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    font-family:var(--font-mono);
    font-weight:700;
    font-size:14px;
    color:var(--black);
    margin-right:14px;
  }
  .lb-row{display:flex;}
  .lb-row .rank{width:44px;flex-shrink:0;}
  .lb-row .who{display:flex;align-items:center;flex-grow:1;}
  .lb-row .count{margin-left:auto;}

  /* Directory grids */
  .gallery{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;}
  .profile{background:var(--panel);border:1px solid var(--line);overflow:hidden;text-align:center;transition:.25s;padding:24px 14px;}
  .profile:hover{border-color:var(--hazard);transform:translateY(-4px);}
  .profile .avatar-lg{
    width:64px;height:64px;border-radius:50%;
    display:flex;align-items:center;justify-content:center;
    margin:0 auto 14px;
    font-family:var(--font-mono);font-weight:700;font-size:20px;color:var(--black);
  }
  .profile h3{margin:0 0 2px;font-family:var(--font-display);font-size:20px;letter-spacing:.5px;}
  .profile p{margin:0;font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;}
  .profile .joined{font-family:var(--font-mono);font-size:11px;color:var(--hazard-dim);margin-top:8px;}

  .empty-state{background:var(--panel);border:1px dashed var(--line);padding:48px;text-align:center;color:var(--muted);font-family:var(--font-mono);font-size:13px;}

  footer{border-top:1px solid var(--line);text-align:center;padding:26px;color:var(--muted);font-size:13px;}
  footer a{color:var(--hazard);margin:0 8px;}
  footer a:hover{color:var(--text);}

  @media (max-width:768px){
    .sidebar{width:100%;height:auto;position:relative;}
    .main{margin-left:0;}
    .sidebar a{display:inline-flex;padding:12px 16px;}
    header.top{padding:24px;}
    section{padding:24px;}
    .lb-row{padding:14px 16px;}
  }
</style>
</head>
<body>

<div class="sidebar">
  <div class="brand">HOOD<span>GYM</span></div>
  <a href="dashboard.php">🏠 Dashboard</a>
  <a href="packages.php">📦 Packages</a>
  <a href="progress.php">📊 Progress</a>
  <a href="community.php" class="active">👥 Community</a>
  <a href="settings.php">⚙️ Settings</a>
</div>

<div class="main">
  <div class="hazard-strip"></div>
  <header class="top">
    <div>
      <h1>The <span class="hi">Crew</span></h1>
      <p>Who's showing up, who's coaching, who's grinding this month.</p>
    </div>
    <form method="POST" action="logout.php">
      <button type="submit" class="logout-btn">Logout</button>
    </form>
  </header>

  <section>
    <p class="eyebrow">This Month</p>
    <h2 class="section-title">Attendance Leaderboard</h2>
    <?php if (empty($leaderboard)): ?>
      <div class="empty-state">No sessions logged this month yet. Be the first on the board.</div>
    <?php else: ?>
      <div class="leaderboard">
        <?php foreach ($leaderboard as $i => $row):
          $color = $avatar_palette[$i % count($avatar_palette)];
        ?>
        <div class="lb-row">
          <div class="rank">#<?php echo $i + 1; ?></div>
          <div class="who">
            <span class="avatar" style="background:<?php echo $color; ?>;"><?php echo initials($row['username']); ?></span>
            <span class="name"><?php echo htmlspecialchars($row['username']); ?></span>
          </div>
          <div class="count"><?php echo (int)$row['session_count']; ?><span class="lbl">sessions</span></div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section>
    <p class="eyebrow">Coaching Staff</p>
    <h2 class="section-title">Trainers</h2>
    <?php if (empty($trainers)): ?>
      <div class="empty-state">No trainer profiles found.</div>
    <?php else: ?>
      <div class="gallery">
        <?php foreach ($trainers as $i => $t):
          $color = $avatar_palette[$i % count($avatar_palette)];
        ?>
        <div class="profile">
          <div class="avatar-lg" style="background:<?php echo $color; ?>;"><?php echo initials($t['username']); ?></div>
          <h3><?php echo htmlspecialchars($t['username']); ?></h3>
          <p><?php echo htmlspecialchars($t['bio'] ?? 'Trainer'); ?></p>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section>
    <p class="eyebrow">The Community</p>
    <h2 class="section-title">Members</h2>
    <?php if (empty($members)): ?>
      <div class="empty-state">No other members yet. Invite your crew.</div>
    <?php else: ?>
      <div class="gallery">
        <?php foreach ($members as $i => $m):
          $color = $avatar_palette[$i % count($avatar_palette)];
        ?>
        <div class="profile">
          <div class="avatar-lg" style="background:<?php echo $color; ?>;"><?php echo initials($m['username']); ?></div>
          <h3><?php echo htmlspecialchars($m['username']); ?></h3>
          <p>Member</p>
          <?php if (!empty($m['joined_at'])): ?>
            <div class="joined">Since <?php echo date('M Y', strtotime($m['joined_at'])); ?></div>
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

</body>
</html>