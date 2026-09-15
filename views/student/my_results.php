<?php
session_start();
// Redirect unauthenticated users to login page
if (!isset($_SESSION["user"])) {
    header("Location: ../../public/login.php");
    exit;
}
// Allow access only to student users
if ($_SESSION["user"]["role"] !== "student") {
    header("Location: ../../public/unauthorized.php");
    exit;
}
// Load database configuration
require_once __DIR__ . "/../../config/database.php";
// Establish database connection
$db = Database::connect();
$studentId = (int)$_SESSION["user"]["id"];
// Retrieve all exam results for the logged-in student
$stmt = $db->prepare(
  "SELECT r.exam_id, e.title AS exam_title, r.score, r.submitted_at, r.feedback, r.ai_feedback,
          COALESCE(e.score_threshold_high, 70) AS threshold_high,
          COALESCE(e.score_threshold_medium, 50) AS threshold_medium
   FROM results r
   JOIN exams e ON e.id = r.exam_id
   WHERE r.user_id = ?
   ORDER BY r.id DESC"
);
$stmt->bind_param("i", $studentId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Exam Results</title>
  <link rel="stylesheet" href="/aiExamSystem/assets/css/style.css">
  <style>
    .score-badge {
      display: inline-block;
      padding: 4px 12px;
      border-radius: 20px;
      font-weight: bold;
      color: white;
    }
    .score-high { background: #28a745; }
    .score-medium { background: #ffc107; color: #333; }
    .score-low { background: #dc3545; }
    .ai-indicator {
      display: inline-block;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 2px 8px;
      border-radius: 10px;
      font-size: 10px;
      margin-left: 5px;
    }
  </style>
</head>
<body class="page">

<div class="container">
  <div class="topbar">
    <div>
      <h2>My Exam Results</h2>
      <div class="muted">All your exam results</div>
    </div>
    <a class="btn btn-light" href="../../public/dashboard.php">Back</a>
  </div>

  <div class="card">
    <?php if (empty($rows)): ?>
      <div class="muted">No exam results found.</div>
    <?php else: ?>
      <table class="table">
        <thead>
          <tr>
            <th>Exam</th>
            <th>Score</th>
            <th>Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): 
          $score = (int)$r["score"];
          $passingGrade = (int)$r["threshold_high"];
          $scoreClass = $score >= $passingGrade ? "score-high" : "score-low";
          
          // Check if has AI feedback
          $feedback = $r["feedback"] ?? "";
          $hasAiFeedback = false;
          if (!empty($feedback)) {
            $decoded = json_decode($feedback, true);
            $hasAiFeedback = is_array($decoded) && isset($decoded["status"]) && $decoded["status"] === "ok";
          }
           // Also check text feedback
          if (!empty($r["ai_feedback"])) {
              $hasAiFeedback = true;
          }
        ?>
          <tr>
            <td>
              <?= htmlspecialchars($r["exam_title"]) ?>
              <?php if ($hasAiFeedback): ?>
                <span class="ai-indicator">AI</span>
              <?php endif; ?>
            </td>
            <td>
              <span class="score-badge <?= $scoreClass ?>"><?= $score ?></span>/100
            </td>
            <td><?= htmlspecialchars($r["submitted_at"]) ?></td>
            <td>
              <a class="btn btn-light" href="result_detail.php?exam_id=<?= (int)$r["exam_id"] ?>">
                Details
              </a>
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
