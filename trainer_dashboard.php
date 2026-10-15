<?php
session_start();
if (!isset($_SESSION['trainer_id'])) {
    header("Location: trainer_login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "hoodgym");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$trainer_id       = (int)$_SESSION['trainer_id'];
$trainer_username = $_SESSION['trainer_username'];

// This trainer's classes
$stmt = $conn->prepare("SELECT id, class_name, day, time, capacity FROM classes WHERE trainer=? ORDER BY id DESC");
$stmt->bind_param("s", $trainer_username);
$stmt->execute();
$classes_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// All members (for attendance rosters + notes dropdown)
$members_result = $conn->query("SELECT id, username FROM users WHERE role='member' ORDER BY username ASC");
$members_rows = $members_result->fetch_all(MYSQLI_ASSOC);

// This trainer's notes, most recent first
$stmt = $conn->prepare("
    SELECT n.id, n.note, n.created_at, u.username
    FROM member_notes n
    JOIN users u ON n.user_id = u.id
    WHERE n.trainer_id = ?
    ORDER BY n.created_at DESC
    LIMIT 100
");
$stmt->bind_param("i", $trainer_id);
$stmt->execute();
$notes_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Recent attendance this trainer has recorded
$stmt = $conn->prepare("
    SELECT a.id, a.session_date, a.status, u.username, c.class_name
    FROM class_attendance a
    JOIN users u ON a.user_id = u.id
    JOIN classes c ON a.class_id = c.id
    WHERE c.trainer = ?
    ORDER BY a.session_date DESC, a.id DESC
    LIMIT 100
");
$stmt->bind_param("s", $trainer_username);
$stmt->execute();
$attendance_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>HoodGym — Trainer</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
<style>
  :root{
    --bg:#0e0e0d; --panel:#181816; --panel-raised:#212120; --line:#2c2c29;
    --hazard:#ffcc00; --chalk:#f3f2ec; --muted:#8f8d86;
    --danger:#ff4436; --success:#35d07f; --warn:#ff9f43; --sidebar-w:230px;
  }
  *{box-sizing:border-box;}
  html,body{margin:0;padding:0;}
  body{background:var(--bg);color:var(--chalk);font-family:'Inter',sans-serif;min-height:100vh;-webkit-font-smoothing:antialiased;}
  ::selection{background:var(--hazard);color:#000;}
  .hazard-rule{height:6px;width:100%;background:repeating-linear-gradient(-45deg,var(--hazard) 0 14px,#111 14px 28px);}
  a{color:inherit;}
  .shell{display:flex;min-height:100vh;}
  .sidebar{width:var(--sidebar-w);flex-shrink:0;background:var(--panel);border-right:1px solid var(--line);display:flex;flex-direction:column;position:fixed;top:0;bottom:0;left:0;transition:transform .25s ease;z-index:50;}
  .brand{padding:26px 22px 18px;}
  .brand .mark{font-family:'Bebas Neue',sans-serif;font-size:34px;letter-spacing:1px;line-height:.9;color:var(--chalk);}
  .brand .mark span{color:var(--hazard);}
  .brand .sub{font-family:'JetBrains Mono',monospace;font-size:10px;letter-spacing:2px;color:var(--muted);text-transform:uppercase;margin-top:6px;}
  nav.primary{display:flex;flex-direction:column;padding:10px 12px;gap:2px;flex:1;}
  nav.primary a{display:flex;align-items:center;gap:12px;padding:12px 12px;border-radius:6px;text-decoration:none;color:var(--muted);font-weight:600;font-size:14px;letter-spacing:.3px;border-left:3px solid transparent;}
  nav.primary a svg{width:17px;height:17px;flex-shrink:0;}
  nav.primary a:hover{background:var(--panel-raised);color:var(--chalk);}
  nav.primary a.active{background:var(--panel-raised);color:var(--hazard);border-left:3px solid var(--hazard);}
  .sidebar-foot{padding:16px 12px 22px;border-top:1px solid var(--line);}
  .admin-chip{display:flex;align-items:center;gap:10px;padding:8px 10px;margin-bottom:8px;}
  .admin-chip .dot{width:8px;height:8px;border-radius:50%;background:var(--success);box-shadow:0 0 0 3px rgba(53,208,127,.15);flex-shrink:0;}
  .admin-chip .name{font-size:13px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
  .logout-link{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:6px;text-decoration:none;color:var(--danger);font-weight:600;font-size:13px;background:rgba(255,68,54,.08);}
  .logout-link:hover{background:rgba(255,68,54,.16);}
  .main{margin-left:var(--sidebar-w);flex:1;min-width:0;}
  .topbar{background:var(--panel);border-bottom:1px solid var(--line);padding:22px 32px;display:flex;align-items:flex-end;justify-content:space-between;gap:20px;}
  .topbar .eyebrow{font-family:'JetBrains Mono',monospace;font-size:11px;letter-spacing:2px;color:var(--muted);text-transform:uppercase;}
  .topbar h1{font-family:'Bebas Neue',sans-serif;font-size:36px;letter-spacing:.5px;margin:2px 0 0;color:var(--chalk);}
  .menu-toggle{display:none;background:var(--panel-raised);border:1px solid var(--line);color:var(--chalk);width:38px;height:38px;border-radius:6px;cursor:pointer;}
  .content{padding:32px;}
  .section{display:none;}
  .section.active{display:block;animation:fade .2s ease;}
  @keyframes fade{from{opacity:0;transform:translateY(4px);}to{opacity:1;transform:translateY(0);}}
  .stat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;margin-bottom:28px;}
  .stat-card{background:var(--panel);border:1px solid var(--line);border-radius:10px;padding:22px 22px 18px;position:relative;overflow:hidden;}
  .stat-card::before{content:"";position:absolute;top:0;left:0;right:0;height:4px;background:repeating-linear-gradient(-45deg,var(--hazard) 0 8px,#111 8px 16px);}
  .stat-card .label{font-family:'JetBrains Mono',monospace;font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted);}
  .stat-card .value{font-family:'JetBrains Mono',monospace;font-weight:700;font-size:48px;line-height:1.1;margin-top:8px;font-variant-numeric:tabular-nums;color:var(--hazard);}
  .panel{background:var(--panel);border:1px solid var(--line);border-radius:10px;overflow:hidden;margin-bottom:24px;}
  .panel-head{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--line);gap:12px;flex-wrap:wrap;}
  .panel-head h3{font-family:'Bebas Neue',sans-serif;font-size:22px;letter-spacing:.5px;margin:0;font-weight:400;}
  .panel-head-right{display:flex;align-items:center;gap:10px;}
  .count-tag{font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--muted);background:var(--panel-raised);padding:4px 10px;border-radius:20px;border:1px solid var(--line);}
  table{width:100%;border-collapse:collapse;}
  thead th{text-align:left;font-family:'JetBrains Mono',monospace;font-size:10.5px;letter-spacing:1.2px;text-transform:uppercase;color:var(--muted);padding:12px 22px;border-bottom:1px solid var(--line);background:var(--panel-raised);}
  tbody td{padding:14px 22px;border-bottom:1px solid var(--line);font-size:14px;}
  tbody tr:last-child td{border-bottom:none;}
  tbody tr:hover{background:rgba(255,255,255,.02);}
  td.mono{font-family:'JetBrains Mono',monospace;color:var(--muted);font-size:13px;}
  td.actions{display:flex;gap:8px;flex-wrap:wrap;}
  .pill{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;letter-spacing:.2px;}
  .pill.status-present{background:rgba(53,208,127,.14);color:var(--success);}
  .pill.status-absent{background:rgba(255,68,54,.14);color:var(--danger);}
  .pill .dot{width:6px;height:6px;border-radius:50%;background:currentColor;}
  .btn{display:inline-block;padding:7px 13px;border-radius:6px;text-decoration:none;font-size:12.5px;font-weight:700;letter-spacing:.2px;border:1px solid transparent;cursor:pointer;font-family:'Inter',sans-serif;}
  .btn-edit{background:rgba(255,204,0,.12);color:var(--hazard);border-color:rgba(255,204,0,.3);}
  .btn-edit:hover{background:var(--hazard);color:#0e0e0d;}
  .btn-primary{background:var(--hazard);color:#0e0e0d;border-color:var(--hazard);}
  .btn-primary:hover{background:#e6b800;}
  .btn-cancel{background:var(--panel-raised);color:var(--muted);border-color:var(--line);}
  .btn-cancel:hover{background:#2a2a27;color:var(--chalk);}
  .empty-row td{text-align:center;padding:34px 20px;color:var(--muted);font-family:'JetBrains Mono',monospace;font-size:12px;letter-spacing:1px;text-transform:uppercase;}
  footer{text-align:center;padding:22px;color:var(--muted);font-size:12px;font-family:'JetBrains Mono',monospace;letter-spacing:.5px;}
  .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:100;align-items:center;justify-content:center;padding:20px;}
  .modal-overlay.open{display:flex;}
  .modal-box{background:var(--panel);border:1px solid var(--line);border-radius:10px;width:100%;max-width:520px;overflow:hidden;max-height:90vh;overflow-y:auto;}
  .modal-box .hazard-rule{height:4px;}
  .modal-head{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--line);}
  .modal-head h3{font-family:'Bebas Neue',sans-serif;font-size:22px;font-weight:400;margin:0;}
  .modal-close{background:none;border:none;color:var(--muted);font-size:20px;cursor:pointer;line-height:1;padding:4px;}
  .modal-close:hover{color:var(--chalk);}
  .modal-body{padding:22px;}
  .field{margin-bottom:16px;}
  .field label{display:block;font-family:'JetBrains Mono',monospace;font-size:10.5px;letter-spacing:1.2px;text-transform:uppercase;color:var(--muted);margin-bottom:6px;}
  .field input,.field textarea,.field select{width:100%;background:var(--panel-raised);border:1px solid var(--line);color:var(--chalk);border-radius:6px;padding:10px 12px;font-family:'Inter',sans-serif;font-size:14px;}
  .field input:focus,.field textarea:focus,.field select:focus{outline:none;border-color:var(--hazard);}
  .field textarea{resize:vertical;min-height:70px;}
  .field-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
  .modal-foot{display:flex;justify-content:flex-end;gap:10px;padding:16px 22px 22px;}
  .roster-row{display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--line);}
  .roster-row:last-child{border-bottom:none;}
  .roster-row .uname{font-size:14px;}
  .toggle-group{display:flex;gap:6px;}
  .toggle-btn{padding:6px 12px;border-radius:6px;font-size:12px;font-weight:700;border:1px solid var(--line);background:var(--panel-raised);color:var(--muted);cursor:pointer;}
  .toggle-btn.present.active{background:rgba(53,208,127,.18);color:var(--success);border-color:rgba(53,208,127,.4);}
  .toggle-btn.absent.active{background:rgba(255,68,54,.18);color:var(--danger);border-color:rgba(255,68,54,.4);}
  .banner{font-size:13px;padding:10px 14px;border-radius:6px;margin-bottom:16px;}
  .banner.success{background:rgba(53,208,127,.1);border:1px solid rgba(53,208,127,.3);color:var(--success);}
  .banner.error{background:rgba(255,68,54,.1);border:1px solid rgba(255,68,54,.3);color:var(--danger);}
  .note-card{padding:14px 22px;border-bottom:1px solid var(--line);}
  .note-card:last-child{border-bottom:none;}
  .note-card .note-head{display:flex;justify-content:space-between;gap:10px;margin-bottom:4px;}
  .note-card .uname{font-weight:600;color:var(--hazard);font-size:13.5px;}
  .note-card .ts{font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--muted);}
  .note-card .txt{font-size:13.5px;color:var(--chalk);line-height:1.5;}
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
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <div class="mark">HOOD<span>GYM</span></div>
      <div class="sub">Trainer Panel</div>
    </div>
    <div class="hazard-rule"></div>
    <nav class="primary" id="primaryNav">
      <a href="#overview" data-target="overview" class="active">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
        Overview
      </a>
      <a href="#MyClasses" data-target="MyClasses">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
        My Classes
      </a>


      <a href="#Attendance" data-target="Attendance">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        Attendance
      </a>
      <a href="#Notes" data-target="Notes">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
        Member Notes
      </a>
    </nav>
    <div class="sidebar-foot">
      <div class="admin-chip">
        <span class="dot"></span>
        <span class="name"><?php echo htmlspecialchars($trainer_username); ?></span>
      </div>
      <a href="trainer_logout.php" class="logout-link">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
        Log out
      </a>
    </div>
  </aside>

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
            <div class="label">My Classes</div>
            <div class="value"><?php echo count($classes_rows); ?></div>
          </div>
          <div class="stat-card">
            <div class="label">Attendance Logged</div>
            <div class="value"><?php echo count($attendance_rows); ?></div>
          </div>
          <div class="stat-card">
            <div class="label">Member Notes</div>
            <div class="value"><?php echo count($notes_rows); ?></div>
          </div>
        </div>
      </div>

      <!-- My Classes -->
      <div id="MyClasses" class="section">
        <?php if (isset($_GET['class_success'])): ?>
          <div class="banner success">Class updated.</div>
        <?php elseif (isset($_GET['class_error'])): ?>
          <div class="banner error">Couldn't update that class — please try again.</div>
        <?php endif; ?>
        <div class="panel">
          <div class="panel-head">
            <h3>My Classes</h3>
            <span class="count-tag"><?php echo count($classes_rows); ?> total</span>
          </div>
          <table>
            <thead><tr><th>Class</th><th>Day</th><th>Time</th><th>Capacity</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (empty($classes_rows)): ?>
                <tr class="empty-row"><td colspan="5">No classes assigned to you yet — ask the admin to assign one.</td></tr>
              <?php else: foreach ($classes_rows as $c): ?>
              <tr>
                <td data-label="Class"><?php echo htmlspecialchars($c['class_name']); ?></td>
                <td data-label="Day"><?php echo htmlspecialchars($c['day']); ?></td>
                <td class="mono" data-label="Time"><?php echo htmlspecialchars($c['time']); ?></td>
                <td class="mono" data-label="Capacity"><?php echo $c['capacity'] !== null ? (int)$c['capacity'] : '—'; ?></td>
                <td class="actions" data-label="Actions">
                  <button type="button" class="btn btn-edit" onclick='openClassModal(<?php echo json_encode($c); ?>)'>Edit</button>
                </td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>


      <!-- Attendance -->
      <div id="Attendance" class="section">
        <?php if (isset($_GET['attendance_success'])): ?>
          <div class="banner success">Attendance saved.</div>
        <?php elseif (isset($_GET['attendance_error'])): ?>
          <div class="banner error">Couldn't save attendance — please try again.</div>
        <?php endif; ?>

        <div class="panel">
          <div class="panel-head"><h3>Mark Attendance</h3></div>
          <div class="modal-body">
            <?php if (empty($classes_rows)): ?>
              <p style="color:var(--muted);font-size:13.5px;">You need at least one assigned class before you can take attendance.</p>
            <?php else: ?>
            <form action="trainer_attendance_save.php" method="POST">
              <div class="field-row">
                <div class="field">
                  <label for="att_class">Class</label>
                  <select id="att_class" name="class_id" required>
                    <?php foreach ($classes_rows as $c): ?>
                      <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['class_name']); ?> — <?php echo htmlspecialchars($c['day']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="field">
                  <label for="att_date">Session date</label>
                  <input type="date" id="att_date" name="session_date" value="<?php echo $today; ?>" required>
                </div>
              </div>
              <div class="field">
                <label>Roster</label>
                <div style="background:var(--panel-raised);border:1px solid var(--line);border-radius:6px;padding:6px 14px;">
                  <?php if (empty($members_rows)): ?>
                    <p style="color:var(--muted);font-size:13px;padding:10px 0;">No members yet.</p>
                  <?php else: foreach ($members_rows as $m): ?>
                    <div class="roster-row">
                      <span class="uname"><?php echo htmlspecialchars($m['username']); ?></span>
                      <div class="toggle-group" data-user="<?php echo $m['id']; ?>">
                        <button type="button" class="toggle-btn present active" onclick="setAttendance(this,'present')">Present</button>
                        <button type="button" class="toggle-btn absent" onclick="setAttendance(this,'absent')">Absent</button>
                      </div>
                      <input type="hidden" name="status[<?php echo $m['id']; ?>]" value="present">
                    </div>
                  <?php endforeach; endif; ?>
                </div>
              </div>
              <div style="text-align:right;margin-top:6px;">
                <button type="submit" class="btn btn-primary">Save Attendance</button>
              </div>
            </form>
            <?php endif; ?>
          </div>
        </div>

        <div class="panel">
          <div class="panel-head">
            <h3>Recent Attendance</h3>
            <span class="count-tag"><?php echo count($attendance_rows); ?> records</span>
          </div>
          <table>
            <thead><tr><th>Date</th><th>Class</th><th>Member</th><th>Status</th></tr></thead>
            <tbody>
              <?php if (empty($attendance_rows)): ?>
                <tr class="empty-row"><td colspan="4">No attendance recorded yet</td></tr>
              <?php else: foreach ($attendance_rows as $a): ?>
              <tr>
                <td class="mono" data-label="Date"><?php echo htmlspecialchars($a['session_date']); ?></td>
                <td data-label="Class"><?php echo htmlspecialchars($a['class_name']); ?></td>
                <td data-label="Member"><?php echo htmlspecialchars($a['username']); ?></td>
                <td data-label="Status"><span class="pill status-<?php echo $a['status']; ?>"><span class="dot"></span><?php echo ucfirst($a['status']); ?></span></td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Member Notes -->
      <div id="Notes" class="section">
        <?php if (isset($_GET['note_success'])): ?>
          <div class="banner success">Note added.</div>
        <?php elseif (isset($_GET['note_error'])): ?>
          <div class="banner error">Couldn't save that note — please try again.</div>
        <?php endif; ?>

        <div class="panel">
          <div class="panel-head"><h3>Add a Note</h3></div>
          <div class="modal-body">
            <form action="trainer_note_save.php" method="POST">
              <div class="field">
                <label for="note_member">Member</label>
                <select id="note_member" name="user_id" required>
                  <option value="">Select a member…</option>
                  <?php foreach ($members_rows as $m): ?>
                    <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['username']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="field">
                <label for="note_text">Note</label>
                <textarea id="note_text" name="note" required placeholder="Progress, injuries to watch, goals discussed…"></textarea>
              </div>
              <div style="text-align:right;">
                <button type="submit" class="btn btn-primary">Save Note</button>
              </div>
            </form>
          </div>
        </div>

        <div class="panel">
          <div class="panel-head">
            <h3>Note History</h3>
            <span class="count-tag"><?php echo count($notes_rows); ?> notes</span>
          </div>
          <div>
            <?php if (empty($notes_rows)): ?>
              <p style="text-align:center;padding:34px 20px;color:var(--muted);font-family:'JetBrains Mono',monospace;font-size:12px;letter-spacing:1px;text-transform:uppercase;">No notes yet</p>
            <?php else: foreach ($notes_rows as $n): ?>
              <div class="note-card">
                <div class="note-head">
                  <span class="uname"><?php echo htmlspecialchars($n['username']); ?></span>
                  <span class="ts"><?php echo htmlspecialchars($n['created_at']); ?></span>
                </div>
                <div class="txt"><?php echo nl2br(htmlspecialchars($n['note'])); ?></div>
              </div>
            <?php endforeach; endif; ?>
          </div>
        </div>
      </div>

    </div>
    <footer>© 2026 HoodGym — Trainer Panel</footer>
  </div>
</div>

<!-- Edit Class Modal -->
<div class="modal-overlay" id="classModalOverlay">
  <div class="modal-box">
    <div class="hazard-rule"></div>
    <div class="modal-head">
      <h3>Edit Class</h3>
      <button type="button" class="modal-close" onclick="closeModal('classModalOverlay')">&times;</button>
    </div>
    <form action="trainer_class_update.php" method="POST">
      <div class="modal-body">
        <input type="hidden" name="id" id="class_id">
        <div class="field">
          <label>Class name</label>
          <input type="text" id="class_name_display" disabled>
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
        <div class="field">
          <label for="class_capacity">Capacity</label>
          <input type="number" id="class_capacity" name="capacity" min="1">
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-cancel" onclick="closeModal('classModalOverlay')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
  const links = document.querySelectorAll('nav.primary a');
  const titles   = { overview: 'Overview', MyClasses: 'My Classes', Attendance: 'Attendance', Notes: 'Member Notes' };
  const eyebrows = { overview: 'Dashboard', MyClasses: 'Schedule', Attendance: 'Roster', Notes: 'Journal' };

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

  function closeModal(overlayId){
    document.getElementById(overlayId).classList.remove('open');
  }
  document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => { if (e.target === overlay) closeModal(overlay.id); });
  });

  function openClassModal(cls){
    document.getElementById('class_id').value = cls.id;
    document.getElementById('class_name_display').value = cls.class_name;
    document.getElementById('class_day').value = cls.day;
    document.getElementById('class_time').value = cls.time;
    document.getElementById('class_capacity').value = cls.capacity !== null ? cls.capacity : '';
    document.getElementById('classModalOverlay').classList.add('open');
  }

  function setAttendance(btn, status){
    const group = btn.closest('.toggle-group');
    group.querySelectorAll('.toggle-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    group.nextElementSibling.value = status;
  }
</script>
</body>
</html>
