<?php
session_start();

if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit;
}
// Allow access only to student users

if ($_SESSION["user"]["role"] !== "student") {
    header("Location: unauthorized.php");
    exit;
}
// Load required controllers
require_once __DIR__ . "/../controllers/ExamController.php";
require_once __DIR__ . "/../controllers/ResultController.php";
require_once __DIR__ . "/../repositories/ExamAssignmentRepository.php";
require_once __DIR__ . "/../repositories/ResultRepository.php";

$examId = (int)($_GET["exam_id"] ?? 0);
$studentId = (int)$_SESSION["user"]["id"];

// Fetch exam data including questions and related structures
$ec = new ExamController();
$exam = $ec->getExamWithAllData($examId);
// Stop execution if exam does not exist
if (!$exam) {
    die("Exam not found.");
}

// Check if student is assigned to this exam
$assignmentRepo = new ExamAssignmentRepository();
if (!$assignmentRepo->isStudentAssigned($examId, $studentId)) {
    die("You are not authorized to take this exam.");
}

// Check if student has already completed this exam
$resultRepo = new ResultRepository();
$existingResult = $resultRepo->getStudentResult($studentId, $examId);
if ($existingResult) {
    // Already completed - redirect to result page
    header("Location: view_result.php?exam_id=" . $examId);
    exit;
}

$error = "";
 // Redirect to result page on success
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $answers  = $_POST["answers"]  ?? [];
    $matching = $_POST["matching"] ?? [];

    $rc = new ResultController();

    $ok = $rc->submitExam(
        (int)$_SESSION["user"]["id"],
        (int)$exam["id"],
        $answers,
        $matching
    );

    if ($ok) {
        header("Location: view_result.php?exam_id=".(int)$exam["id"]);
        exit;
    } else {
        $error = "Submission failed. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Take Exam</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="/aiExamSystem/assets/css/style.css">
</head>
<body class="page">

<div class="container">

  <div class="topbar">
    <div>
      <h2><?= htmlspecialchars($exam["title"]) ?></h2>
      <div class="muted">
        Type: <?= htmlspecialchars(strtoupper($exam["exam_type"])) ?>
      </div>
    </div>

    <a class="btn btn-light"
       href="exams.php?type=<?= htmlspecialchars($exam["exam_type"]) ?>">
       Back
    </a>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="post" class="card">

  <?php if ($exam["exam_type"] === "matching"): ?>

    <?php foreach ($exam["questions"] as $q): ?>
      <div class="question">
        <div class="qtext"><?= htmlspecialchars($q["question_text"]) ?></div>

        <?php foreach ($q["pairs"] as $p): ?>
          <div style="display:flex; gap:10px; margin:8px 0;">
            <div style="flex:1;font-weight:600;">
              <?= htmlspecialchars($p["left_item"]) ?>
            </div>

            <select name="matching[<?= (int)$p["id"] ?>]" required style="flex:1;">
              <option value="">Select match</option>
              <?php foreach ($q["right_pool"] as $right): ?>
                <option value="<?= htmlspecialchars($right) ?>">
                  <?= htmlspecialchars($right) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endforeach; ?>
      </div>
      <hr>
    <?php endforeach; ?>

  <?php elseif ($exam["exam_type"] === "mcq"): ?>

    <?php foreach ($exam["questions"] as $q): ?>
      <div class="question">
        <div class="qtext"><?= htmlspecialchars($q["question_text"]) ?></div>

        <?php
          $options = [];
          if (!empty($q["options"])) {
              $options = json_decode($q["options"], true);
          }

          if (empty($options)) {
              foreach (["A","B","C","D"] as $opt) {
                  $field = "option_" . strtolower($opt);
                  if (!empty($q[$field])) {
                      $options[$opt] = $q[$field];
                  }
              }
          }
        ?>

        <?php foreach ($options as $key => $text): ?>
          <label class="radio" style="display:flex; align-items:center; gap:10px;">
            <input type="radio"
                   name="answers[<?= (int)$q["id"] ?>]"
                   value="<?= htmlspecialchars($key) ?>"
                   required>
            <strong><?= htmlspecialchars($key) ?>)</strong>
            <span><?= htmlspecialchars($text) ?></span>
          </label>
        <?php endforeach; ?>

      </div>
      <hr>
    <?php endforeach; ?>

  <?php elseif ($exam["exam_type"] === "dialog"): ?>

    <?php foreach ($exam["questions"] as $q): ?>
      <div class="question">
        <div class="qtext">
          <?= nl2br(htmlspecialchars($q["question_text"])) ?>
        </div>

        <div style="margin-top:8px;">
          <input type="text"
                 name="answers[<?= (int)$q["id"] ?>]"
                 placeholder="Type your answer here"
                 required
                 style="width:100%;padding:8px;">
        </div>
      </div>
      <hr>
    <?php endforeach; ?>

  <?php elseif ($exam["exam_type"] === "true_false"): ?>

    <?php foreach ($exam["questions"] as $q): ?>
      <div class="question">
        <div class="qtext"><?= htmlspecialchars($q["question_text"]) ?></div>

        <?php
          // True/False questions have option_a = "True" and option_b = "False"
          $options = [
              'A' => $q['option_a'] ?? 'True',
              'B' => $q['option_b'] ?? 'False'
          ];
        ?>

        <?php foreach ($options as $key => $text): ?>
          <label class="radio" style="display:flex; align-items:center; gap:10px;">
            <input type="radio"
                   name="answers[<?= (int)$q["id"] ?>]"
                   value="<?= htmlspecialchars($key) ?>"
                   required>
            <strong><?= htmlspecialchars($key) ?>)</strong>
            <span><?= htmlspecialchars($text) ?></span>
          </label>
        <?php endforeach; ?>

      </div>
      <hr>
    <?php endforeach; ?>

  <?php endif; ?>

    <button class="btn" type="submit">Submit Exam</button>
  </form>

</div>
</body>
</html>
