<?php include('db.php'); ?>
<!DOCTYPE html>
<html>
<head>
  <title>Classes - HOOD GYM</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<h2>Classes</h2>

<form method="POST">
  <input type="text" name="class_name" placeholder="Class Name" required>
  <input type="text" name="instructor" placeholder="Instructor">
  <input type="date" name="date" required>
  <input type="time" name="time" required>
  <button type="submit" name="add">Add Class</button>
</form>

<?php
if(isset($_POST['add'])){
  $cn=$_POST['class_name']; $ins=$_POST['instructor']; $d=$_POST['date']; $t=$_POST['time'];
  $conn->query("INSERT INTO classes(class_name,instructor,schedule_date,schedule_time) VALUES('$cn','$ins','$d','$t')");
  echo "<p>Class added successfully!</p>";
}
?>

<table>
  <tr><th>ID</th><th>Class</th><th>Instructor</th><th>Date</th><th>Time</th><th>Status</th></tr>
  <?php
  $res=$conn->query("SELECT * FROM classes");
  while($row=$res->fetch_assoc()){
    echo "<tr><td>{$row['id']}</td><td>{$row['class_name']}</td><td>{$row['instructor']}</td><td>{$row['schedule_date']}</td><td>{$row['schedule_time']}</td><td>{$row['status']}</td></tr>";
  }
  ?>
</table>
</body>
</html>
