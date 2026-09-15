<?php
session_start();
require_once __DIR__ . "/../controllers/AuthController.php";
// Redirect authenticated users to dashboard
if (isset($_SESSION["user"])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";
// Handle registration form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $controller = new AuthController();     // Create authentication controller instance
    $ok = $controller->register($_POST);

    if ($ok) {
        header("Location: login.php?registered=1");
        exit;
    } else {
        $error = "Registration failed. Email may already exist.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Register - AI Exam System</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="/aiExamSystem/assets/css/style.css">
</head>
<body class="page">

<div class="card" style="width:420px">

  <h1>AI Exam System</h1>
  <p class="muted">Create your account</p>

  <?php if ($error): ?>
    <div class="alert alert-danger">
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <form method="post" class="form">

    <label>First Name</label>
    <input type="text" name="first_name" required>

    <label>Last Name</label>
    <input type="text" name="last_name" required>

    <label>Role</label>
    <select name="role" id="role" onchange="toggleStudent()" required>
      <option value="">Select Role</option>
      <option value="student">Student</option>
      <option value="teacher">Teacher</option>
    </select>

    <div id="student_wrap" style="display:none">
      <label>Student Number</label>
      <input type="text" name="student_number" placeholder="e.g. 20231234">
    </div>

    <label>Email</label>
    <input type="email" name="email" required>

    <label>Password</label>
    <input type="password" name="password" required>

    <button type="submit" class="btn">Register</button>

  </form>

  <div class="hint" style="text-align:center">
    Already have an account?
    <a class="link" href="login.php">Login</a>
  </div>

</div>

<script>
function toggleStudent() {
  const role = document.getElementById("role").value;
  document.getElementById("student_wrap").style.display =
    role === "student" ? "block" : "none";
}
</script>

</body>
</html>
