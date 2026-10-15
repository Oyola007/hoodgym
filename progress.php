<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

/* ============================================================
   Confirmed schema (from trainer.php + your DESCRIBE output):

   class_attendance: id, class_id, user_id, session_date (date),
                      status ENUM('present','absent'), marked_by, created_at
   classes: id, class_name, day, time, capacity, trainer (username text)
   member_notes: id, user_id, trainer_id, note, created_at

   Still unconfirmed — queries below are wrapped in try/catch so a
   missing table fails quietly into an empty state instead of
   crashing the page:
   body_metrics: id, user_id, weight_kg, recorded_at
   personal_records: id, user_id, exercise, value, unit, achieved_at
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

// Attendance history (last 12 weeks) — used for streak + total counts.
// Only counts sessions marked "present".
$attendance = safe_query($conn, "
    SELECT ca.session_date, c.class_name
    FROM class_attendance ca
    LEFT JOIN classes c ON c.id = ca.class_id
    WHERE ca.user_id = ? AND ca.status = 'present'
    ORDER BY ca.session_date DESC
    LIMIT 60
", "i", [$user_id]);

$total_sessions = count($attendance);

// Current streak (consecutive days with at least one session, walking backward from today)
$attend_dates = array_unique(array_map(fn($a) => date('Y-m-d', strtotime($a['session_date'])), $attendance));
sort($attend_dates);
$attend_dates = array_reverse($attend_dates);
$streak = 0;
$cursor = new DateTime('today');
foreach ($attend_dates as $d) {
    $dDate = new DateTime($d);
    $diff = (int)$cursor->diff($dDate)->format('%r%a');
    if ($diff === 0 || $diff === -1) {
        $streak++;
        $cursor = $dDate;
    } else {
        break;
    }
}

// Weight trend (last 10 entries)
$weight_log = safe_query($conn, "
    SELECT weight_kg, recorded_at FROM body_metrics
    WHERE user_id = ? ORDER BY recorded_at ASC LIMIT 20
", "i", [$user_id]);

// Personal records
$prs = safe_query($conn, "
    SELECT exercise, value, unit, achieved_at FROM personal_records
    WHERE user_id = ? ORDER BY achieved_at DESC LIMIT 8
", "i", [$user_id]);

// Trainer notes
$notes = safe_query($conn, "
    SELECT n.note, n.created_at, u.username AS trainer_name
    FROM member_notes n
    LEFT JOIN users u ON u.id = n.trainer_id
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC LIMIT 6
", "i", [$user_id]);

// Weekly attendance count for the last 7 days (for the punch-card row)
$today = new DateTime('today');
$week = [];
for ($i = 6; $i >= 0; $i--) {
    $d = (clone $today)->modify("-$i days");
    $key = $d->format('Y-m-d');
    $week[] = ['label' => $d->format('D'), 'date' => $key, 'hit' => in_array($key, $attend_dates)];
}

$chart_labels = array_map(fn($w) => date('M j', strtotime($w['recorded_at'])), $weight_log);
$chart_values = array_map(fn($w) => (float)$w['weight_kg'], $weight_log);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HoodGym — Progress</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

  .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:8px;}
  .card{background:var(--panel);border:1px solid var(--line);border-top:3px solid var(--hazard);padding:22px 24px;}
  .card h3{margin:0 0 12px;font-size:12px;font-weight:600;letter-spacing:1px;text-transform:uppercase;color:var(--muted);}
  .card .value{font-family:var(--font-mono);font-size:36px;font-weight:700;color:var(--text);line-height:1;}
  .card .unit{font-family:var(--font-mono);font-size:14px;color:var(--hazard-dim);margin-left:6px;}

  /* Punch card streak row */
  .punch-row{display:flex;gap:10px;margin-top:16px;}
  .punch{
    flex:1;
    aspect-ratio:1;
    border:1px solid var(--line);
    background:var(--panel);
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    gap:6px;
    font-family:var(--font-mono);
    font-size:11px;
    color:var(--muted);
    text-transform:uppercase;
  }
  .punch.hit{border-color:var(--hazard);background:rgba(255,212,0,0.08);color:var(--hazard);}
  .punch .dot{width:10px;height:10px;border-radius:50%;background:var(--line);}
  .punch.hit .dot{background:var(--hazard);}

  .grid-2{display:grid;grid-template-columns:2fr 1fr;gap:20px;}
  @media (max-width:1000px){.grid-2{grid-template-columns:1fr;}}

  .chart-panel{background:var(--panel);border:1px solid var(--line);padding:28px;}
  canvas{max-height:300px;}

  .list-panel{background:var(--panel);border:1px solid var(--line);padding:24px;}
  .list-panel h3{margin:0 0 16px;font-family:var(--font-display);font-size:20px;letter-spacing:.5px;text-transform:uppercase;}
  .pr-row{display:flex;justify-content:space-between;align-items:baseline;padding:10px 0;border-bottom:1px solid var(--line);font-size:14px;}
  .pr-row:last-child{border-bottom:none;}
  .pr-row .ex{color:var(--text);font-weight:600;}
  .pr-row .val{font-family:var(--font-mono);color:var(--hazard);}
  .pr-row .date{font-family:var(--font-mono);font-size:11px;color:var(--muted);}

  .note-card{background:var(--charcoal);border-left:3px solid var(--hazard);padding:14px 16px;margin-bottom:12px;}
  .note-card:last-child{margin-bottom:0;}
  .note-card p{margin:0 0 8px;font-size:14px;color:var(--text);line-height:1.5;}
  .note-card .meta{font-family:var(--font-mono);font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;}

  .empty-state{background:var(--panel);border:1px dashed var(--line);padding:32px;text-align:center;color:var(--muted);font-family:var(--font-mono);font-size:13px;}

  table.history{width:100%;border-collapse:collapse;}
  table.history th{text-align:left;font-family:var(--font-mono);font-size:11px;letter-spacing:1px;text-transform:uppercase;color:var(--muted);padding:10px 12px;border-bottom:1px solid var(--line);}
  table.history td{padding:12px;border-bottom:1px solid var(--line);font-size:14px;}
  table.history tr:last-child td{border-bottom:none;}

  footer{border-top:1px solid var(--line);text-align:center;padding:26px;color:var(--muted);font-size:13px;}
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
  <a href="dashboard.php">🏠 Dashboard</a>
  <a href="packages.php">📦 Packages</a>
  <a href="progress.php" class="active">📊 Progress</a>
  <a href="community.php">👥 Community</a>
  <a href="settings.php">⚙️ Settings</a>
</div>

<div class="main">
  <div class="hazard-strip"></div>
  <header class="top">
    <div>
      <h1>Your <span class="hi">Progress</span></h1>
      <p>Every rep logged. Every streak counted.</p>
    </div>
    <form method="POST" action="logout.php">
      <button type="submit" class="logout-btn">Logout</button>
    </form>
  </header>

  <section>
    <p class="eyebrow">Overview</p>
    <h2 class="section-title">This Week</h2>
    <div class="stats">
      <div class="card">
        <h3>Current Streak</h3>
        <div class="value"><?php echo $streak; ?><span class="unit">days</span></div>
      </div>
      <div class="card">
        <h3>Total Sessions</h3>
        <div class="value"><?php echo $total_sessions; ?><span class="unit">logged</span></div>
      </div>
      <div class="card">
        <h3>Personal Records</h3>
        <div class="value"><?php echo count($prs); ?><span class="unit">set</span></div>
      </div>
    </div>
    <div class="punch-row">
      <?php foreach ($week as $w): ?>
      <div class="punch <?php echo $w['hit'] ? 'hit' : ''; ?>">
        <span class="dot"></span>
        <span><?php echo $w['label']; ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section>
    <p class="eyebrow">Body Metrics</p>
    <h2 class="section-title">Weight Trend</h2>
    <div class="grid-2">
      <div class="chart-panel">
        <?php if (empty($weight_log)): ?>
          <div class="empty-state">No weight entries yet. Log your first check-in to start the trend line.</div>
        <?php else: ?>
          <canvas id="weightChart"></canvas>
        <?php endif; ?>
      </div>
      <div class="list-panel">
        <h3>Personal Records</h3>
        <?php if (empty($prs)): ?>
          <div class="empty-state">No PRs logged yet.</div>
        <?php else: ?>
          <?php foreach ($prs as $pr): ?>
          <div class="pr-row">
            <div>
              <div class="ex"><?php echo htmlspecialchars($pr['exercise']); ?></div>
              <div class="date"><?php echo date('M j, Y', strtotime($pr['achieved_at'])); ?></div>
            </div>
            <div class="val"><?php echo htmlspecialchars($pr['value']); ?> <?php echo htmlspecialchars($pr['unit']); ?></div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section>
    <p class="eyebrow">History</p>
    <h2 class="section-title">Recent Sessions</h2>
    <?php if (empty($attendance)): ?>
      <div class="empty-state">No sessions logged yet. Book a class to start building your history.</div>
    <?php else: ?>
      <div class="list-panel" style="padding:0;">
        <table class="history">
          <thead><tr><th>Date</th><th>Class</th></tr></thead>
          <tbody>
            <?php foreach (array_slice($attendance, 0, 10) as $a): ?>
            <tr>
              <td><?php echo date('D, M j Y', strtotime($a['session_date'])); ?></td>
              <td><?php echo htmlspecialchars($a['class_name'] ?? 'General Session'); ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <section>
    <p class="eyebrow">From Your Trainer</p>
    <h2 class="section-title">Coach Notes</h2>
    <?php if (empty($notes)): ?>
      <div class="empty-state">No notes from your trainer yet.</div>
    <?php else: ?>
      <?php foreach ($notes as $n): ?>
      <div class="note-card">
        <p><?php echo htmlspecialchars($n['note']); ?></p>
        <div class="meta"><?php echo htmlspecialchars($n['trainer_name'] ?? 'Trainer'); ?> · <?php echo date('M j, Y', strtotime($n['created_at'])); ?></div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>

  <footer>
    <p>© 2026 HoodGym. All rights reserved.</p>
    <p><a href="#">Facebook</a> · <a href="#">Instagram</a> · <a href="#">YouTube</a></p>
  </footer>
</div>

<?php if (!empty($weight_log)): ?>
<script>
const ctx = document.getElementById('weightChart');
new Chart(ctx, {
  type: 'line',
  data: {
    labels: <?php echo json_encode($chart_labels); ?>,
    datasets: [{
      label: 'Weight (kg)',
      data: <?php echo json_encode($chart_values); ?>,
      borderColor: '#FFD400',
      backgroundColor: 'rgba(255,212,0,0.12)',
      borderWidth: 2,
      pointBackgroundColor: '#FFD400',
      fill: true,
      tension: 0.35
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { labels: { color: '#EDEDED', font: { family: 'Inter', size: 12 } } } },
    scales: {
      x: { ticks: { color: '#8a8a8a', font: { family: 'JetBrains Mono', size: 11 } }, grid: { color: '#2a2a2a' } },
      y: { ticks: { color: '#8a8a8a', font: { family: 'JetBrains Mono', size: 11 } }, grid: { color: '#2a2a2a' } }
    }
  }
});
document.getElementById('weightChart').parentElement.style.height = '300px';
</script>
<?php endif; ?>

</body>
</html>