<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "hoodgym";
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Fetch upcoming classes from DB
$schedule = [];
$class_result = $conn->query("SELECT day, class_name, time FROM classes ORDER BY id ASC");
if ($class_result && $class_result->num_rows > 0) {
    while ($row = $class_result->fetch_assoc()) {
        $schedule[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>HoodGym | Train Hard. Stay Strong.</title>
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
    --muted:#a9a79f;
    --danger:#ff4436;
    --success:#35d07f;
    --warn:#ff9f43;
  }

  *{box-sizing:border-box;}
  html{scroll-behavior:smooth;}
  body{
    margin:0;
    font-family:'Inter',sans-serif;
    background:var(--bg);
    color:var(--chalk);
    -webkit-font-smoothing:antialiased;
  }
  a{color:inherit;}
  ::selection{background:var(--hazard);color:#000;}

  /* signature hazard motif */
  .hazard-rule{
    height:6px;width:100%;
    background:repeating-linear-gradient(-45deg,var(--hazard) 0 14px,#111 14px 28px);
  }

  .eyebrow{
    font-family:'JetBrains Mono',monospace;
    font-size:11px;
    letter-spacing:2.5px;
    text-transform:uppercase;
    color:var(--hazard);
  }
  h1,h2,h3{font-family:'Bebas Neue',sans-serif;font-weight:400;letter-spacing:.5px;margin:0;}

  /* ---------- header / nav ---------- */
  header.site{
    position:sticky; top:0; z-index:40;
    background:rgba(14,14,13,.9);
    backdrop-filter:blur(6px);
    border-bottom:1px solid var(--line);
  }
  .nav-inner{
    max-width:1180px; margin:0 auto;
    padding:16px 28px;
    display:flex; align-items:center; justify-content:space-between;
  }
  .logo{
    font-family:'Bebas Neue',sans-serif;
    font-size:26px; letter-spacing:1px;
  }
  .logo span{color:var(--hazard);}
  .nav-buttons{display:flex; gap:10px;}
  .btn{
    display:inline-block;
    padding:10px 20px;
    border-radius:6px;
    text-decoration:none;
    font-weight:700;
    font-size:14px;
    letter-spacing:.2px;
    border:1px solid transparent;
    transition:.2s ease;
  }
  .btn-primary{background:var(--hazard); color:#0e0e0d;}
  .btn-primary:hover{background:#e6b800;}
  .btn-ghost{background:transparent; color:var(--chalk); border-color:var(--line);}
  .btn-ghost:hover{border-color:var(--hazard); color:var(--hazard);}

  /* ---------- hero ---------- */
  .hero{
    position:relative;
    min-height:74vh;
    display:flex; align-items:center;
    background:
      linear-gradient(180deg, rgba(10,10,9,.55) 0%, rgba(10,10,9,.85) 100%),
      url('victor-freitas-WvDYdXDzkhs-unsplash.jpg') center center / cover no-repeat;
  }
  .hero-inner{
    max-width:1180px; margin:0 auto;
    padding:60px 28px;
    width:100%;
  }
  .hero .eyebrow{margin-bottom:14px;}
  .hero h1{
    font-size:clamp(48px, 9vw, 96px);
    line-height:.92;
    color:var(--chalk);
  }
  .hero h1 span{color:var(--hazard);}
  .hero p.tag{
    font-size:18px;
    color:var(--muted);
    margin:18px 0 30px;
    max-width:480px;
  }
  .hero-cta{display:flex; gap:14px; flex-wrap:wrap;}

  /* ---------- sections ---------- */
  .wrap{max-width:1180px; margin:0 auto; padding:0 28px;}
  section{padding:64px 0;}
  section + section{border-top:1px solid var(--line);}

  .section-head{
    display:flex; align-items:baseline; justify-content:space-between;
    margin-bottom:28px; gap:20px; flex-wrap:wrap;
  }
  .section-head h2{font-size:38px;}
  .section-head .eyebrow{margin-bottom:6px; display:block;}

  /* ---------- schedule (scoreboard table) ---------- */
  .panel{
    background:var(--panel);
    border:1px solid var(--line);
    border-radius:10px;
    overflow:hidden;
  }
  .panel::before{
    content:"";
    display:block;
    height:4px;
    background:repeating-linear-gradient(-45deg,var(--hazard) 0 8px,#111 8px 16px);
  }
  table{width:100%; border-collapse:collapse;}
  thead th{
    text-align:left;
    font-family:'JetBrains Mono',monospace;
    font-size:10.5px; letter-spacing:1.2px; text-transform:uppercase;
    color:var(--muted);
    padding:14px 22px;
    border-bottom:1px solid var(--line);
    background:var(--panel-raised);
  }
  tbody td{
    padding:15px 22px;
    border-bottom:1px solid var(--line);
    font-size:14.5px;
  }
  tbody tr:last-child td{border-bottom:none;}
  tbody tr:hover{background:rgba(255,255,255,.03);}
  td.day{font-family:'JetBrains Mono',monospace; color:var(--hazard); font-weight:700; font-size:13px; letter-spacing:.5px; text-transform:uppercase;}
  td.time{font-family:'JetBrains Mono',monospace; color:var(--muted);}
  .empty-state{
    padding:40px 22px;
    text-align:center;
    color:var(--muted);
    font-family:'JetBrains Mono',monospace;
    font-size:12px;
    letter-spacing:1px;
    text-transform:uppercase;
  }

  /* ---------- about ---------- */
  .about-grid{
    display:grid;
    grid-template-columns:1.1fr .9fr;
    gap:40px;
    align-items:center;
  }
  .about-grid p{color:var(--muted); font-size:16px; line-height:1.7;}
  .stat-strip{
    display:grid; grid-template-columns:repeat(3,1fr); gap:14px;
  }
  .stat-strip .stat{
    background:var(--panel);
    border:1px solid var(--line);
    border-radius:8px;
    padding:18px;
    text-align:center;
  }
  .stat-strip .stat .num{
    font-family:'JetBrains Mono',monospace;
    font-weight:700; font-size:30px; color:var(--hazard);
  }
  .stat-strip .stat .lbl{
    font-family:'JetBrains Mono',monospace;
    font-size:10px; letter-spacing:1px; text-transform:uppercase; color:var(--muted);
    margin-top:4px;
  }

  /* ---------- facilities ---------- */
  .facility-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(220px,1fr));
    gap:16px;
  }
  .facility-card{
    background:var(--panel);
    border:1px solid var(--line);
    border-radius:10px;
    padding:22px;
  }
  .facility-card .icon{font-size:26px;}
  .facility-card h4{
    font-family:'Inter',sans-serif;
    font-weight:700; font-size:15px;
    margin:14px 0 6px; color:var(--chalk);
  }
  .facility-card p{margin:0; color:var(--muted); font-size:13.5px; line-height:1.5;}

  /* ---------- testimonials ---------- */
  .testimonial-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(280px,1fr));
    gap:20px;
  }
  .testimonial-card{
    background:var(--panel);
    border:1px solid var(--line);
    border-left:3px solid var(--hazard);
    border-radius:8px;
    padding:24px;
  }
  .testimonial-card p{
    font-size:15px; color:var(--chalk); line-height:1.6; margin:0 0 14px;
  }
  .testimonial-card .who{
    font-family:'JetBrains Mono',monospace;
    font-size:12px; color:var(--muted); letter-spacing:.5px;
  }

  /* ---------- footer ---------- */
  footer.site{
    background:var(--panel);
    border-top:1px solid var(--line);
    padding:36px 0 26px;
  }
  .footer-inner{
    max-width:1180px; margin:0 auto; padding:0 28px;
    text-align:center;
  }
  .footer-inner .logo{justify-content:center; margin-bottom:10px;}
  .footer-links{
    display:flex; justify-content:center; gap:8px;
    margin:14px 0; flex-wrap:wrap;
    font-family:'JetBrains Mono',monospace; font-size:12px; color:var(--muted);
  }
  .footer-links a{color:var(--muted); text-decoration:none;}
  .footer-links a:hover{color:var(--hazard);}
  .footer-contact{color:var(--muted); font-size:13.5px; margin:6px 0;}
  .footer-contact a{color:var(--hazard); text-decoration:none;}
  .credit{
    margin-top:18px; font-family:'JetBrains Mono',monospace;
    font-size:11px; color:#5f5d57; letter-spacing:.5px;
  }

  @media (max-width:800px){
    .about-grid{grid-template-columns:1fr;}
    .nav-buttons .btn{padding:9px 14px; font-size:13px;}
    thead{display:none;}
    table,tbody,tr,td{display:block; width:100%;}
    tbody tr{border-bottom:1px solid var(--line); padding:10px 0;}
    tbody td{border-bottom:none; padding:5px 22px; display:flex; justify-content:space-between; text-align:right;}
    tbody td::before{content:attr(data-label); font-family:'JetBrains Mono',monospace; font-size:10px; letter-spacing:1px; text-transform:uppercase; color:var(--muted); text-align:left;}
  }
</style>
</head>
<body>

  <header class="site">
    <div class="nav-inner">
      <div class="logo">HOOD<span>GYM</span></div>
      <div class="nav-buttons">
        <a href="login.php" class="btn btn-ghost">Log In</a>
        <a href="register.php" class="btn btn-primary">Join Now</a>
      </div>
    </div>
    <div class="hazard-rule"></div>
  </header>

  <!-- Hero -->
  <section class="hero">
    <div class="hero-inner">
      <span class="eyebrow">Machakos, Kenya</span>
      <h1>TRAIN HARD.<br>STAY <span>STRONG.</span></h1>
      <p class="tag">Expert coaching, modern equipment, and a community that pushes you further — every single session.</p>
      <div class="hero-cta">
        <a href="register.php" class="btn btn-primary">Start Training</a>
        <a href="#schedule" class="btn btn-ghost">View Class Schedule</a>
      </div>
    </div>
  </section>

  <!-- Schedule -->
  <section id="schedule">
    <div class="wrap">
      <div class="section-head">
        <div>
          <span class="eyebrow">This Week</span>
          <h2>Upcoming Classes</h2>
        </div>
      </div>
      <div class="panel">
        <?php if (!empty($schedule)): ?>
          <table>
            <thead>
              <tr><th>Day</th><th>Class</th><th>Time</th></tr>
            </thead>
            <tbody>
              <?php foreach ($schedule as $class): ?>
                <tr>
                  <td class="day" data-label="Day"><?php echo htmlspecialchars($class['day']); ?></td>
                  <td data-label="Class"><?php echo htmlspecialchars($class['class_name']); ?></td>
                  <td class="time" data-label="Time"><?php echo htmlspecialchars($class['time']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="empty-state">No classes scheduled yet</div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- About -->
  <section id="about">
    <div class="wrap">
      <div class="section-head">
        <div>
          <span class="eyebrow">Who We Are</span>
          <h2>About HoodGym</h2>
        </div>
      </div>
      <div class="about-grid">
        <p>At <strong style="color:var(--chalk)">HoodGym</strong>, we believe fitness is more than a routine — it's a lifestyle. Our mission is to empower every member to reach their goals through expert coaching, modern equipment, and a supportive community. Whether you're a beginner or a pro, we've got the right program for you.</p>
        <div class="stat-strip">
          <div class="stat"><div class="num">500+</div><div class="lbl">Members</div></div>
          <div class="stat"><div class="num">12</div><div class="lbl">Trainers</div></div>
          <div class="stat"><div class="num">7</div><div class="lbl">Days Open</div></div>
        </div>
      </div>
    </div>
  </section>

  <!-- Facilities -->
  <section id="facilities">
    <div class="wrap">
      <div class="section-head">
        <div>
          <span class="eyebrow">What's Inside</span>
          <h2>Our Facilities</h2>
        </div>
      </div>
      <div class="facility-grid">
        <div class="facility-card">
          <div class="icon">💪</div>
          <h4>Strength &amp; Cardio</h4>
          <p>Fully equipped zones for lifting and conditioning work.</p>
        </div>
        <div class="facility-card">
          <div class="icon">🧘</div>
          <h4>Yoga &amp; Wellness</h4>
          <p>A dedicated studio for recovery, mobility, and mindfulness.</p>
        </div>
        <div class="facility-card">
          <div class="icon">🏃</div>
          <h4>Personal Training</h4>
          <p>One-on-one coaching and nutrition guidance built around you.</p>
        </div>
        <div class="facility-card">
          <div class="icon">🚿</div>
          <h4>Showers &amp; Lockers</h4>
          <p>Clean, secure facilities so you can train and go.</p>
        </div>
        <div class="facility-card">
          <div class="icon">📱</div>
          <h4>Online Booking</h4>
          <p>Reserve classes and track your sessions from your phone.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Testimonials -->
  <section id="testimonials">
    <div class="wrap">
      <div class="section-head">
        <div>
          <span class="eyebrow">Member Voices</span>
          <h2>What Our Members Say</h2>
        </div>
      </div>
      <div class="testimonial-grid">
        <div class="testimonial-card">
          <p>"HoodGym transformed my fitness journey! The trainers are amazing and the vibe is unbeatable."</p>
          <div class="who">— Sarah K.</div>
        </div>
        <div class="testimonial-card">
          <p>"I love the energy here. Every workout feels like progress!"</p>
          <div class="who">— James M.</div>
        </div>
      </div>
    </div>
  </section>

  <footer class="site">
    <div class="footer-inner">
      <div class="logo">HOOD<span>GYM</span></div>
      <p class="footer-contact">📍 Machakos, Kenya &nbsp;|&nbsp; ☎ <a href="tel:+254790322423">+254 790 322 423</a> &nbsp;|&nbsp; ✉ <a href="mailto:evansomondi392@gmail.com">evansomondi392@gmail.com</a></p>
<div class="social-links">
      <a href="https://www.instagram.com/yourusername" target="_blank">
        <span class="fa fa-instagram"></i> Instagram
      </a> |
      <a href="https://www.facebook.com/yourusername" target="_blank">
        <span class="fa fa-facebook"></i> Facebook
      </a> |
      <a href="https://www.youtube.com/yourusername" target="_blank">
        <span class="fa fa-youtube"></i> Youtube
      </a> |
      <a href="https://x.com/yourusername" target="_blank">
        <span class="fa fa-twitter"></i> X
      </a> |
      <a href="https://www.tiktok.com/@yourusername" target="_blank">
        <span class="fa fa-tiktok"></i> TikTok
      </a>
    </div>
      <p class="credit">&copy; <?php echo date("Y"); ?> HoodGym. All rights reserved. — Built by Evans Oyola</p>
    </div>
  </footer>
<?php include __DIR__ . '/api/chat_widget.php'; ?>
</body>
</html>