<?php
session_start();  // Script for exam selection interface
// Supports FR10: Restrict access to authorized sessions
if (!isset($_SESSION["user"])) { header("Location: login.php"); exit; }
if ($_SESSION["user"]["role"] !== "student") { header("Location: unauthorized.php"); exit; }

require_once __DIR__ . "/../controllers/ExamController.php";
require_once __DIR__ . "/../repositories/ExamAssignmentRepository.php";
require_once __DIR__ . "/../repositories/ResultRepository.php";

$ec = new ExamController();
$assignmentRepo = new ExamAssignmentRepository();
$resultRepo = new ResultRepository();
$studentId = (int)$_SESSION["user"]["id"];

$type = $_GET["type"] ?? "mcq"; // Supports FR2: Navigation between MCQ, Matching, and Dialog types
if (!in_array($type, ["mcq","matching","dialog","true_false"])) $type = "mcq";

$allExams = $ec->listExamsByType($type);

// Filter exams based on assignments
// Show only exams that are either assigned to this student or have no specific assignments (open to all)
$exams = array_filter($allExams, function($exam) use ($assignmentRepo, $studentId) {
    return $assignmentRepo->isStudentAssigned($exam["id"], $studentId);
});
$exams = array_values($exams); // Re-index array

// Check which exams are completed by this student
$completedExams = [];
foreach ($exams as $exam) {
    $result = $resultRepo->getStudentResult($studentId, $exam["id"]);
    if ($result) {
        $completedExams[$exam["id"]] = $result;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Choose Exam</title>
  <link rel="stylesheet" href="/aiExamSystem/assets/css/style.css">
  <style>
    .completed-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: rgba(45, 212, 191, 0.15);
      color: #2dd4bf;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 600;
    }
    .completed-badge .checkmark {
      font-size: 1rem;
    }
    .btn-disabled {
      opacity: 0.5;
      pointer-events: none;
      cursor: not-allowed;
    }
    .score-preview {
      font-size: 0.85rem;
      color: var(--muted);
    }
  </style>
</head>
<body class="page">
<div class="container">
  <div class="topbar">
    <div>
      <h2>Choose Exam Type</h2>
      <div class="muted">Select one of three exam types</div>
    </div>
    <a class="btn btn-light" href="dashboard.php">Back</a>
  </div>

  <div class="card">
    <div class="form-inline">
      <a class="btn btn-light" href="?type=mcq">Multiple Choice</a>
      <a class="btn btn-light" href="?type=true_false">True/False</a>
      <a class="btn btn-light" href="?type=dialog">Short Answer</a>
    </div>

    <hr>

    <?php if (empty($exams)): ?>
      <div class="muted">No exams available for this type yet.</div>
    <?php else: ?>
      <table class="table">
        <thead>
          <tr>
            <th>Exam</th>
            <th>Type</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($exams as $e): ?>
          <?php $isCompleted = isset($completedExams[$e["id"]]); ?>
          <tr>
            <td><?= htmlspecialchars($e["title"]) ?></td>
            <td><?= htmlspecialchars(strtoupper($e["exam_type"])) ?></td>
            <td>
              <?php if ($isCompleted): ?>
                <span class="completed-badge">
                  <span class="checkmark">✓</span> Completed
                </span>
                <div class="score-preview">Score: <?= (int)$completedExams[$e["id"]]["score"] ?>/100</div>
              <?php else: ?>
                <span style="color: var(--muted);">Not started</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($isCompleted): ?>
                <a class="link" href="view_result.php?exam_id=<?= (int)$e["id"] ?>">View Result</a>
              <?php else: ?>
                <a class="link" href="take_exam.php?exam_id=<?= (int)$e["id"] ?>">Start</a>
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
