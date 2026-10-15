<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

/* ============================================================
   Confirmed schema:
   class_attendance: id, class_id, user_id, session_date (date),
                      status ENUM('present','absent'), marked_by, created_at
   classes: id, class_name, trainer, day, time, capacity, calories_per_session
   community_spotlight: id, name, tagline, photo, display_order, is_active

   calories_per_session and community_spotlight require the migration
   in migration_crew_and_calories.sql. Until that's run, this page still
   works — it just shows 0 calories and an empty crew section.
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

// Current week window (Monday–Sunday)
$today = new DateTime('today');
$dow = (int)$today->format('N'); // 1 = Monday ... 7 = Sunday
$monday = (clone $today)->modify('-' . ($dow - 1) . ' days');
$sunday = (clone $monday)->modify('+6 days');
$monday_str = $monday->format('Y-m-d');
$sunday_str = $sunday->format('Y-m-d');

// Workouts this week — sessions the trainer has marked "present" for this member
$workouts_row = safe_query($conn, "
    SELECT COUNT(*) AS cnt
    FROM class_attendance
    WHERE user_id = ? AND status = 'present' AND session_date BETWEEN ? AND ?
", "iss", [$user_id, $monday_str, $sunday_str]);
$workouts_this_week = $workouts_row[0]['cnt'] ?? 0;

// Calories this week — sum of each attended class's calories_per_session
$calories_row = safe_query($conn, "
    SELECT COALESCE(SUM(c.calories_per_session), 0) AS total_cal
    FROM class_attendance ca
    JOIN classes c ON c.id = ca.class_id
    WHERE ca.user_id = ? AND ca.status = 'present' AND ca.session_date BETWEEN ? AND ?
", "iss", [$user_id, $monday_str, $sunday_str]);
$calories_this_week = $calories_row[0]['total_cal'] ?? 0;

// Active members sitewide this week — distinct members with at least one
// session marked "present" by any trainer this week
$active_row = safe_query($conn, "
    SELECT COUNT(DISTINCT user_id) AS cnt
    FROM class_attendance
    WHERE status = 'present' AND session_date BETWEEN ? AND ?
", "ss", [$monday_str, $sunday_str]);
$active_members = $active_row[0]['cnt'] ?? 0;

// Daily breakdown for this member's weekly chart
$daily_rows = safe_query($conn, "
    SELECT ca.session_date, COUNT(*) AS sessions, COALESCE(SUM(c.calories_per_session), 0) AS calories
    FROM class_attendance ca
    JOIN classes c ON c.id = ca.class_id
    WHERE ca.user_id = ? AND ca.status = 'present' AND ca.session_date BETWEEN ? AND ?
    GROUP BY ca.session_date
", "iss", [$user_id, $monday_str, $sunday_str]);

$daily_map = [];
foreach ($daily_rows as $d) {
    $daily_map[$d['session_date']] = $d;
}

$chart_labels = [];
$chart_calories = [];
$chart_workouts = [];
for ($i = 0; $i < 7; $i++) {
    $d = (clone $monday)->modify("+$i days");
    $key = $d->format('Y-m-d');
    $chart_labels[] = $d->format('D');
    $chart_calories[] = isset($daily_map[$key]) ? (int)$daily_map[$key]['calories'] : 0;
    $chart_workouts[] = isset($daily_map[$key]) ? (int)$daily_map[$key]['sessions'] : 0;
}

// Crew spotlight, managed by admin
$crew = safe_query($conn, "
    SELECT name, tagline, photo FROM community_spotlight
    WHERE is_active = 1 ORDER BY display_order ASC, id ASC LIMIT 6
");

function initials($name) {
    $parts = preg_split('/\s+/', trim($name));
    $letters = array_map(fn($p) => strtoupper(substr($p, 0, 1)), array_slice($parts, 0, 2));
    return implode('', $letters);
}
$avatar_palette = ['#FFD400', '#E63946', '#8a8a8a', '#B8990A'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HoodGym — Dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
  :root{
    --black:#0a0a0a;
    --charcoal:#141414;
    --panel:#1a1a1a;
    --line:#2a2a2a;
    --hazard:#FFD400;
    --hazard-dim:#B8990A;
    --danger:#E63946;
    --text:#EDEDED;
    --muted:#8a8a8a;
    --font-display:'Bebas Neue',sans-serif;
    --font-body:'Inter',sans-serif;
    --font-mono:'JetBrains Mono',monospace;
  }
  *{box-sizing:border-box;}
  body{
    margin:0;
    font-family:var(--font-body);
    background:var(--black);
    color:var(--text);
    -webkit-font-smoothing:antialiased;
  }
  a{text-decoration:none;}

  .hazard-strip{
    height:6px;
    width:100%;
    background:repeating-linear-gradient(45deg,var(--hazard) 0 14px,var(--black) 14px 28px);
  }

  .sidebar{
    position:fixed;
    top:0;left:0;
    width:220px;
    height:100%;
    background:var(--charcoal);
    border-right:1px solid var(--line);
    padding-top:28px;
    z-index:10;
  }
  .sidebar .brand{
    font-family:var(--font-display);
    font-size:28px;
    letter-spacing:1px;
    color:var(--hazard);
    padding:0 24px 24px;
    border-bottom:1px solid var(--line);
    margin-bottom:12px;
  }
  .sidebar .brand span{color:var(--text);}
  .sidebar a{
    display:flex;
    align-items:center;
    gap:10px;
    color:var(--muted);
    font-size:14px;
    font-weight:600;
    letter-spacing:.3px;
    padding:14px 24px;
    border-left:3px solid transparent;
    transition:.2s;
  }
  .sidebar a:hover,.sidebar a.active{
    background:rgba(255,212,0,0.06);
    color:var(--hazard);
    border-left-color:var(--hazard);
  }

  .main{
    margin-left:220px;
    padding-bottom:60px;
  }

  header.top{
    background:var(--charcoal);
    border-bottom:1px solid var(--line);
    padding:36px 40px 28px;
    display:flex;
    justify-content:space-between;
    align-items:flex-end;
    flex-wrap:wrap;
    gap:20px;
  }
  header.top h1{
    font-family:var(--font-display);
    font-size:42px;
    letter-spacing:1px;
    margin:0 0 6px;
    text-transform:uppercase;
  }
  header.top h1 .hi{color:var(--hazard);}
  header.top p{
    margin:0;
    color:var(--muted);
    font-size:14px;
  }
  .logout-btn{
    background:transparent;
    color:var(--hazard);
    border:1px solid var(--hazard);
    font-family:var(--font-body);
    font-weight:700;
    font-size:13px;
    letter-spacing:.5px;
    text-transform:uppercase;
    padding:11px 22px;
    cursor:pointer;
    transition:.2s;
  }
  .logout-btn:hover{
    background:var(--hazard);
    color:var(--black);
  }

  section{padding:36px 40px;}
  .eyebrow{
    font-family:var(--font-mono);
    font-size:12px;
    letter-spacing:2px;
    color:var(--hazard);
    text-transform:uppercase;
    margin:0 0 6px;
  }
  h2.section-title{
    font-family:var(--font-display);
    font-size:28px;
    letter-spacing:.5px;
    text-transform:uppercase;
    margin:0 0 24px;
  }

  .stats{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:16px;
  }
  .card{
    background:var(--panel);
    border:1px solid var(--line);
    border-top:3px solid var(--hazard);
    padding:22px 24px;
    position:relative;
  }
  .card h3{
    margin:0 0 12px;
    font-size:12px;
    font-weight:600;
    letter-spacing:1px;
    text-transform:uppercase;
    color:var(--muted);
  }
  .card .value{
    font-family:var(--font-mono);
    font-size:36px;
    font-weight:700;
    color:var(--text);
    line-height:1;
  }
  .card .unit{
    font-family:var(--font-mono);
    font-size:14px;
    color:var(--hazard-dim);
    margin-left:6px;
  }

  .chart-panel{
    background:var(--panel);
    border:1px solid var(--line);
    padding:28px;
  }
  canvas{max-height:340px;}

  .gallery{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
    gap:16px;
  }
  .profile{
    background:var(--panel);
    border:1px solid var(--line);
    overflow:hidden;
    text-align:center;
    transition:.25s;
  }
  .profile:hover{
    border-color:var(--hazard);
    transform:translateY(-4px);
  }
  .profile img{
    width:100%;
    height:180px;
    object-fit:cover;
    display:block;
    filter:grayscale(30%);
  }
  .profile:hover img{filter:grayscale(0%);}
  .profile .avatar-fallback{
    width:100%;
    height:180px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-family:var(--font-mono);
    font-weight:700;
    font-size:36px;
    color:var(--black);
  }
  .profile .info{padding:14px;}
  .profile h3{
    margin:0 0 2px;
    font-family:var(--font-display);
    font-size:20px;
    letter-spacing:.5px;
  }
  .profile p{
    margin:0;
    font-size:12px;
    color:var(--muted);
    text-transform:uppercase;
    letter-spacing:.5px;
  }

  .empty-state{
    background:var(--panel);
    border:1px dashed var(--line);
    padding:48px;
    text-align:center;
    color:var(--muted);
    font-family:var(--font-mono);
    font-size:13px;
  }

  footer{
    border-top:1px solid var(--line);
    text-align:center;
    padding:26px;
    color:var(--muted);
    font-size:13px;
  }
  footer a{color:var(--hazard);margin:0 8px;}
  footer a:hover{color:var(--text);}

  @media (max-width:768px){
    .sidebar{width:100%;height:auto;position:relative;}
    .main{margin-left:0;}
    .sidebar a{display:inline-flex;padding:12px 16px;}
    header.top{padding:24px;}
    section{padding:24px;}
  }
</style>
</head>
<body>

<div class="sidebar">
  <div class="brand">HOOD<span>GYM</span></div>
  <a href="dashboard.php" class="active">🏠 Dashboard</a>
  <a href="packages.php">📦 Packages</a>
  <a href="progress.php">📊 Progress</a>
  <a href="community.php">👥 Community</a>
  <a href="settings.php">⚙️ Settings</a>
</div>

<div class="main">
  <div class="hazard-strip"></div>
  <header class="top">
    <div>
      <h1>Welcome back, <span class="hi"><?php echo htmlspecialchars($_SESSION['username']); ?></span></h1>
      <p>Track your progress and connect with the HoodGym community.</p>
    </div>
    <form method="POST" action="logout.php">
      <button type="submit" class="logout-btn">Logout</button>
    </form>
  </header>

  <section>
    <p class="eyebrow">This Week</p>
    <h2 class="section-title">Your Numbers</h2>
    <div class="stats">
      <div class="card">
        <h3>Workouts</h3>
        <div class="value"><?php echo (int)$workouts_this_week; ?><span class="unit">sessions</span></div>
      </div>
      <div class="card">
        <h3>Calories Burned</h3>
        <div class="value"><?php echo number_format((int)$calories_this_week); ?><span class="unit">kcal</span></div>
      </div>
      <div class="card">
        <h3>Active Members</h3>
        <div class="value"><?php echo (int)$active_members; ?><span class="unit">this week</span></div>
      </div>
    </div>
    <p style="font-family:var(--font-mono);font-size:11px;color:var(--muted);margin-top:14px;letter-spacing:.5px;">
      Numbers update automatically whenever your trainer marks a session "present".
    </p>
  </section>

  <section>
    <p class="eyebrow">Trend</p>
    <h2 class="section-title">Weekly Progress</h2>
    <div class="chart-panel">
      <canvas id="progressChart"></canvas>
    </div>
  </section>

  <section>
    <p class="eyebrow">The Crew</p>
    <h2 class="section-title">Meet Our Fit Community</h2>
    <?php if (empty($crew)): ?>
      <div class="empty-state">No crew members spotlighted yet. The admin can add some from the Community tab.</div>
    <?php else: ?>
      <div class="gallery">
        <?php foreach ($crew as $i => $person):
          $color = $avatar_palette[$i % count($avatar_palette)];
        ?>
        <div class="profile">
          <?php if (!empty($person['photo'])): ?>
            <img src="<?php echo htmlspecialchars($person['photo']); ?>" alt="<?php echo htmlspecialchars($person['name']); ?>">
          <?php else: ?>
            <div class="avatar-fallback" style="background:<?php echo $color; ?>;"><?php echo initials($person['name']); ?></div>
          <?php endif; ?>
          <div class="info">
            <h3><?php echo htmlspecialchars($person['name']); ?></h3>
            <p><?php echo htmlspecialchars($person['tagline'] ?: ''); ?></p>
          </div>
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

<script>
const ctx = document.getElementById('progressChart');
new Chart(ctx, {
  type: 'line',
  data: {
    labels: <?php echo json_encode($chart_labels); ?>,
    datasets: [
      {
        label: 'Calories Burned',
        data: <?php echo json_encode($chart_calories); ?>,
        borderColor: '#FFD400',
        backgroundColor: 'rgba(255,212,0,0.12)',
        borderWidth: 2,
        pointBackgroundColor: '#FFD400',
        fill: true,
        tension: 0.35
      },
      {
        label: 'Workouts',
        data: <?php echo json_encode($chart_workouts); ?>,
        borderColor: '#E63946',
        backgroundColor: 'rgba(230,57,70,0.10)',
        borderWidth: 2,
        pointBackgroundColor: '#E63946',
        fill: true,
        tension: 0.35
      }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        labels: { color: '#EDEDED', font: { family: 'Inter', size: 12 } }
      }
    },
    scales: {
      x: {
        ticks: { color: '#8a8a8a', font: { family: 'JetBrains Mono', size: 11 } },
        grid: { color: '#2a2a2a' }
      },
      y: {
        ticks: { color: '#8a8a8a', font: { family: 'JetBrains Mono', size: 11 } },
        grid: { color: '#2a2a2a' }
      }
    }
  }
});
document.getElementById('progressChart').parentElement.style.height = '340px';
</script>

</body>
</html>