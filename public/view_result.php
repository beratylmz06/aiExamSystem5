<?php
session_start();
// Start session and ensure user is authenticated
// Redirect unauthenticated users to login page
if (!isset($_SESSION["user"])) { header("Location: login.php"); exit; }
if ($_SESSION["user"]["role"] !== "student") { header("Location: unauthorized.php"); exit; }

require_once __DIR__ . "/../controllers/ResultController.php";
require_once __DIR__ . "/../controllers/ExamController.php";

$examId = (int)($_GET["exam_id"] ?? 0);
$rc = new ResultController();
$ec = new ExamController();
$result = $rc->getStudentResult((int)$_SESSION["user"]["id"], $examId);

if (!$result) die("Result not found.");

// Get exam with questions
$exam = $ec->getExamWithAllData($examId);

// Parse details to calculate correct/wrong counts
$details = json_decode($result["details"] ?? "[]", true) ?: [];
$correctCount = 0;
$wrongCount = 0;
$totalQuestions = count($details);

// Create a lookup map for quick access to answers by question_id
$answerMap = [];
foreach ($details as $d) {
    if (isset($d["question_id"])) {
        $answerMap[$d["question_id"]] = $d;
    } elseif (isset($d["pair_id"])) {
        $answerMap["pair_" . $d["pair_id"]] = $d;
    }
    if (!empty($d["is_correct"])) {
        $correctCount++;
    } else {
        $wrongCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Result</title>
  <link rel="stylesheet" href="/aiExamSystem/assets/css/style.css">
  <style>
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 15px;
      margin: 20px 0;
    }
    .stat-box {
      text-align: center;
      padding: 20px;
      border-radius: 12px;
      background: rgba(255,255,255,0.05);
      border: 1px solid var(--border);
    }
    .stat-box.correct {
      border-color: rgba(45, 212, 191, 0.5);
      background: rgba(45, 212, 191, 0.1);
    }
    .stat-box.wrong {
      border-color: rgba(239, 68, 68, 0.5);
      background: rgba(239, 68, 68, 0.1);
    }
    .stat-box.total {
      border-color: rgba(102, 126, 234, 0.5);
      background: rgba(102, 126, 234, 0.1);
    }
    .stat-number {
      font-size: 2.5rem;
      font-weight: bold;
      display: block;
    }
    .stat-box.correct .stat-number { color: #2dd4bf; }
    .stat-box.wrong .stat-number { color: #ef4444; }
    .stat-box.total .stat-number { color: #667eea; }
    .stat-label {
      font-size: 0.9rem;
      color: var(--muted);
      margin-top: 5px;
    }
    .score-section {
      text-align: center;
      padding: 25px;
      margin-bottom: 20px;
      background: linear-gradient(135deg, rgba(102,126,234,0.1), rgba(118,75,162,0.1));
      border-radius: 12px;
      border: 1px solid var(--border);
    }
    .score-section .score {
      font-size: 2rem;
      color: white;
      margin: 0;
    }
    .score-section .score b {
      font-size: 3rem;
      color: #667eea;
    }
    
    /* Question Review Styles */
    .questions-review {
      margin-top: 30px;
    }
    .questions-review h3 {
      margin-bottom: 20px;
      color: white;
      font-size: 1.3rem;
    }
    .question-item {
      background: rgba(255,255,255,0.03);
      border-radius: 12px;
      padding: 20px;
      margin-bottom: 15px;
      border-left: 4px solid var(--border);
    }
    .question-item.is-correct {
      border-left-color: #2dd4bf;
      background: rgba(45, 212, 191, 0.05);
    }
    .question-item.is-wrong {
      border-left-color: #ef4444;
      background: rgba(239, 68, 68, 0.05);
    }
    .question-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 15px;
    }
    .question-number {
      background: rgba(102, 126, 234, 0.2);
      color: #667eea;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 600;
    }
    .question-status {
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 600;
    }
    .question-status.correct {
      background: rgba(45, 212, 191, 0.2);
      color: #2dd4bf;
    }
    .question-status.wrong {
      background: rgba(239, 68, 68, 0.2);
      color: #ef4444;
    }
    .question-text {
      color: white;
      font-size: 1.05rem;
      margin-bottom: 15px;
      line-height: 1.5;
    }
    .answer-row {
      display: flex;
      gap: 20px;
      flex-wrap: wrap;
    }
    .answer-box {
      flex: 1;
      min-width: 200px;
      padding: 12px 15px;
      border-radius: 8px;
      background: rgba(255,255,255,0.05);
    }
    .answer-box.your-answer {
      border: 1px solid rgba(102, 126, 234, 0.5);
    }
    .answer-box.correct-answer {
      border: 1px solid rgba(45, 212, 191, 0.5);
    }
    .answer-box-label {
      font-size: 0.8rem;
      color: var(--muted);
      margin-bottom: 5px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .answer-box-value {
      color: white;
      font-weight: 500;
    }
    .answer-box.your-answer.is-wrong .answer-box-value {
      color: #ef4444;
    }
    .answer-box.your-answer.is-correct .answer-box-value {
      color: #2dd4bf;
    }
    .answer-box.correct-answer .answer-box-value {
      color: #2dd4bf;
    }
  </style>
</head>
<body class="page">
<div class="container">
  <div class="topbar">
    <div>
      <h2>Result</h2>
      <div class="muted"><?= htmlspecialchars($result["exam_title"]) ?></div>
    </div>
    <a class="btn btn-light" href="exams.php">Back</a>
  </div>

  <div class="card">
    <div class="score-section">
      <div class="score">Score: <b><?= (int)$result["score"] ?></b>/100</div>
      <a class="btn btn-primary" href="/aiExamSystem/views/student/my_results.php" style="margin-top: 15px; display: inline-block;">🏆 Go to My Results</a>
    </div>
    
    <?php if ($totalQuestions > 0): ?>
    <div class="stats-grid">
      <div class="stat-box correct">
        <span class="stat-number"><?= $correctCount ?></span>
        <span class="stat-label">✓ Correct</span>
      </div>
      <div class="stat-box wrong">
        <span class="stat-number"><?= $wrongCount ?></span>
        <span class="stat-label">✗ Wrong</span>
      </div>
      <div class="stat-box total">
        <span class="stat-number"><?= $totalQuestions ?></span>
        <span class="stat-label">📝 Total Questions</span>
      </div>
    </div>
    <?php endif; ?>
    
    <div class="muted">Submitted at: <?= htmlspecialchars($result["submitted_at"]) ?></div>
  </div>

  <!-- Question by Question Review -->
  <?php if ($exam && !empty($exam["questions"])): ?>
  <div class="card questions-review">
    <h3>📋 Question Review</h3>
    
    <?php if ($exam["exam_type"] === "matching"): ?>
      <!-- Matching Questions -->
      <?php $pairNum = 0; ?>
      <?php foreach ($exam["questions"] as $q): ?>
        <?php foreach ($q["pairs"] as $p): ?>
          <?php 
            $pairNum++;
            $answer = $answerMap["pair_" . $p["id"]] ?? null;
            $isCorrect = $answer && !empty($answer["is_correct"]);
            $givenAnswer = $answer["given"] ?? "-";
            $correctAnswer = $p["right_item"];
          ?>
          <div class="question-item <?= $isCorrect ? 'is-correct' : 'is-wrong' ?>">
            <div class="question-header">
              <span class="question-number">Pair <?= $pairNum ?></span>
              <span class="question-status <?= $isCorrect ? 'correct' : 'wrong' ?>">
                <?= $isCorrect ? '✓ Correct' : '✗ Wrong' ?>
              </span>
            </div>
            <div class="question-text"><?= htmlspecialchars($p["left_item"]) ?></div>
            <div class="answer-row">
              <div class="answer-box your-answer <?= $isCorrect ? 'is-correct' : 'is-wrong' ?>">
                <div class="answer-box-label">Your Answer</div>
                <div class="answer-box-value"><?= htmlspecialchars($givenAnswer) ?></div>
              </div>
              <?php if (!$isCorrect): ?>
              <div class="answer-box correct-answer">
                <div class="answer-box-label">Correct Answer</div>
                <div class="answer-box-value"><?= htmlspecialchars($correctAnswer) ?></div>
              </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endforeach; ?>
      
    <?php else: ?>
      <!-- MCQ and Dialog Questions -->
      <?php foreach ($exam["questions"] as $index => $q): ?>
        <?php 
          $answer = $answerMap[$q["id"]] ?? null;
          $isCorrect = $answer && !empty($answer["is_correct"]);
          $givenAnswer = $answer["given"] ?? "-";
          
          // Get correct answer based on exam type
          if ($exam["exam_type"] === "dialog") {
              // For dialog/short answer, check multiple possible fields
              $correctAnswer = $q["correct_answer"] ?? $q["answer_text"] ?? $q["correct_text"] ?? $q["correct_option"] ?? "";
          } else {
              $correctAnswer = $q["correct_option"] ?? "";
              // Also show the option text if available
              $correctOptionField = "option_" . strtolower($correctAnswer);
              if (!empty($q[$correctOptionField])) {
                  $correctAnswer = $correctAnswer . ") " . $q[$correctOptionField];
              }
              // Show given answer with option text too
              $givenOptionField = "option_" . strtolower($givenAnswer);
              if (!empty($q[$givenOptionField])) {
                  $givenAnswer = $givenAnswer . ") " . $q[$givenOptionField];
              }
          }
        ?>
        <div class="question-item <?= $isCorrect ? 'is-correct' : 'is-wrong' ?>">
          <div class="question-header">
            <span class="question-number">Question <?= $index + 1 ?></span>
            <span class="question-status <?= $isCorrect ? 'correct' : 'wrong' ?>">
              <?= $isCorrect ? '✓ Correct' : '✗ Wrong' ?>
            </span>
          </div>
          <div class="question-text"><?= nl2br(htmlspecialchars($q["question_text"])) ?></div>
          <div class="answer-row">
            <div class="answer-box your-answer <?= $isCorrect ? 'is-correct' : 'is-wrong' ?>">
              <div class="answer-box-label">Your Answer</div>
              <div class="answer-box-value"><?= htmlspecialchars($givenAnswer) ?></div>
            </div>
            <?php if (!$isCorrect): ?>
            <div class="answer-box correct-answer">
              <div class="answer-box-label">Correct Answer</div>
              <div class="answer-box-value"><?= htmlspecialchars($correctAnswer) ?></div>
            </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
    
  </div>
  <?php endif; ?>

</div>
</body>
</html>

