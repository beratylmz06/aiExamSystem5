<?php
session_start();
// Redirect unauthenticated users to login page
if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit;
}
// Load notification controller
require_once __DIR__ . "/../controllers/NotificationController.php";

$user = $_SESSION["user"];
$nc = new NotificationController(); // Fetch unread notification count for the user
$unreadCount = $nc->countUnread((int)$user["id"]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard - AI Exam System</title>
  <link rel="stylesheet" href="/aiExamSystem/assets/css/style.css">
</head>
<body class="page">
<div class="container">

  <div class="topbar">
    <div>
      <h2>Dashboard</h2>
      <div class="muted">
        Welcome,
        <?= htmlspecialchars($user["first_name"] . " " . $user["last_name"]) ?>
        (<?= htmlspecialchars($user["role"]) ?>)
      </div>
    </div>

    <div style="display:flex; gap:10px; align-items:center;">
      <a class="btn btn-light" href="notifications.php">
        Notifications (<?= (int)$unreadCount ?>)
      </a>

      <a class="btn btn-light"
         href="<?= $user["role"] === "teacher"
            ? "../views/teacher/messages.php"
            : "../views/student/messages.php" ?>">
        Messages
      </a>

      <a class="btn btn-light" href="logout.php">Logout</a>
    </div>
  </div>

  <?php if ($user["role"] === "teacher"): ?>
    
    <!-- 🚀 PRIMARY ACTION -->
    <a class="tile hero" href="../views/teacher/generate_questions.php" style="margin-bottom: 30px; padding: 40px; text-align: center;">
      <div style="font-size: 3rem; margin-bottom: 10px;">🤖</div>
      <h3 style="font-size: 2rem;">AI Question Generator</h3>
      <p>Generate high-quality exam questions automatically with one click.</p>
    </a>

    <!-- 🛠️ EXAM MANAGEMENT -->
    <div class="section-title">Exam Management</div>
    <div class="grid">
      <a class="tile" href="../views/teacher/create_exam.php">
        <h3 style="color: #2dd4bf;">Manual Exam</h3>
        <p class="muted">Create custom MCQ, Matching, or Dialog exams manually.</p>
      </a>

      <a class="tile" href="../views/teacher/class_report.php">
        <h3 style="color: #f687b3;">Class Reports</h3>
        <p class="muted">Analyze performance and view detailed analytics.</p>
      </a>
    </div>

    <!-- 🎓 STUDENT MANAGEMENT -->
    <div class="section-title">Classroom</div>
    <div class="grid">
      <a class="tile" href="../views/teacher/students.php">
         <h3 style="color: #fbbf24;">Students</h3>
         <p class="muted">Manage registered students and view profiles.</p>
      </a>
    </div>

  <?php else: ?>
    
    <!-- 🎓 STUDENT DASHBOARD -->
    
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 25px; margin-top: 20px;">
        
        <!-- MAIN ACTION -->
        <a class="tile hero" href="exams.php" style="display: flex; flex-direction: column; justify-content: center; padding: 40px;">
            <h3 style="font-size: 2.2rem; margin-bottom: 10px;">Choose & Start Exam</h3>
            <p style="opacity: 0.9;">Ready to test your knowledge? Select an exam to begin.</p>
            <div style="margin-top: 20px; background: rgba(255,255,255,0.2); padding: 10px 20px; border-radius: 12px; display: inline-block; width: fit-content;">
                👉 Start Now
            </div>
        </a>

        <!-- SIDEBAR / STATS -->
        <div style="display: flex; flex-direction: column; gap: 20px;">
            <a class="tile" href="../views/student/my_results.php" style="flex: 1; border-color: #2dd4bf;">
                <h3 style="color: #2dd4bf;">🏆 My Results</h3>
                <p class="muted">View your past performance.</p>
            </a>
            
            <a class="tile" href="../views/student/messages.php" style="flex: 1;">
                 <h3>📩 Messages</h3>
                 <p class="muted">Check updates from teachers.</p>
            </a>
        </div>
    </div>

  <?php endif; ?>

</div>
<script src="../assets/js/app.js"></script>
</body>
</html>
