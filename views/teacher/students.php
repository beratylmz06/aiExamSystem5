<?php
session_start();

if (!isset($_SESSION["user"]) || $_SESSION["user"]["role"] !== "teacher") {
    header("Location: ../../public/unauthorized.php");
    exit;
}

require_once __DIR__ . "/../../repositories/UserRepository.php";

$repo = new UserRepository();
$students = $repo->listByRole("student");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Students</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="/aiExamSystem/assets/css/style.css">
</head>
<body class="page">
<div class="container">

  <div class="topbar">
    <h2>Student List</h2>
    <a class="btn btn-light" href="../../public/dashboard.php">Back</a>
  </div>

  <div class="card">

    <?php if (empty($students)): ?>
      <div class="muted">No students registered yet.</div>
    <?php else: ?>

      <table class="table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Student Number</th>
            <th>Email</th>
            <th>Risk Status</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($students as $s): ?>
          <tr>
            <td><?= htmlspecialchars($s["first_name"] . " " . $s["last_name"]) ?></td>
            <td><?= htmlspecialchars($s["student_number"] ?? "-") ?></td>
            <td><?= htmlspecialchars($s["email"]) ?></td>
            <td>
              <?php if (($s["risk_status"] ?? "") === "at_risk"): ?>
                <span style="color:#ff6b6b;font-weight:bold;">⚠ At Risk</span>
              <?php else: ?>
                <span style="color:#51cf66;">Normal</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

    <?php endif; ?>

  </div>
</div>
</body>
</html>
