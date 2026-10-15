<?php
session_start();
include('db.php');
if(!isset($_SESSION['admin_id'])){ header("Location: admin_login.php"); exit(); }

// Add Class
if(isset($_POST['add'])){
    $class_name = $_POST['class_name'];
    $trainer = $_POST['trainer'];
    $schedule = $_POST['schedule'];
    $capacity = $_POST['capacity'];
    $stmt = $conn->prepare("INSERT INTO classes (class_name,trainer,schedule,capacity) VALUES (?,?,?,?)");
    $stmt->bind_param("sssi",$class_name,$trainer,$schedule,$capacity);
    $stmt->execute();
}

// Delete Class
if(isset($_GET['delete'])){
    $id = $_GET['delete'];
    $conn->query("DELETE FROM classes WHERE id=$id");
}

$classes = $conn->query("SELECT * FROM classes");
?>
<!DOCTYPE html>
<html>
<head><title>Classes</title></head>
<body>
<h1>Classes Management</h1>
<form method="POST">
    <input type="text" name="class_name" placeholder="Class Name" required>
    <input type="text" name="trainer" placeholder="Trainer" required>
    <input type="datetime-local" name="schedule" required>
    <input type="number" name="capacity" placeholder="Capacity" required>
    <button type="submit" name="add">Add Class</button>
</form>

<table border="1">
<tr><th>ID</th><th>Name</th><th>Trainer</th><th>Schedule</th><th>Capacity</th><th>Action</th></tr>
<?php while($row=$classes->fetch_assoc()){ ?>
<tr>
    <td><?= $row['id'] ?></td>
    <td><?= $row['class_name'] ?></td>
    <td><?= $row['trainer'] ?></td>
    <td><?= $row['schedule'] ?></td>
    <td><?= $row['capacity'] ?></td>
    <td><a href="?delete=<?= $row['id'] ?>">Delete</a></td>
</tr>
<?php } ?>
</table>
</body>
</html>
