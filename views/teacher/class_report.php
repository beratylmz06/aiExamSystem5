<?php
session_start();

/* Teacher-only access */
if (!isset($_SESSION["user"])) { header("Location: ../../public/login.php"); exit; }
if ($_SESSION["user"]["role"] !== "teacher") { header("Location: ../../public/unauthorized.php"); exit; }

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../controllers/ResultController.php";

$db = Database::connect();


$exams = $db->query(
    "SELECT id, title, exam_type FROM exams ORDER BY id DESC"
)->fetch_all(MYSQLI_ASSOC);

$selectedExamId = (int)($_GET["exam_id"] ?? ($exams[0]["id"] ?? 0));
$report = [];

if ($selectedExamId > 0) {
    $report = (new ResultController())->getClassReport($selectedExamId);
}


function weeklyTopicFromScore(int $score): string {
    if ($score >= 100) {
        return "No deficiencies – Excellent performance";
    } elseif ($score >= 67) {
        return "General Revision";
    } elseif ($score >= 34) {
        return "General Knowledge";
    } else {
        return "General Knowledge & Previous Topics";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Class Report</title>
  <link rel="stylesheet" href="/aiExamSystem/assets/css/style.css">
</head>
<body class="page">

<div class="container">
  <div class="topbar">
    <div>
      <h2>Class Report</h2>
      <div class="muted">AI-based performance analysis</div>
    </div>
    <a class="btn btn-light" href="../../public/dashboard.php">Back</a>
  </div>

  <div class="card">

    <form method="get" class="form-inline">
      <label>Select Exam:</label>
      <select name="exam_id" onchange="this.form.submit()">
        <?php foreach ($exams as $e): ?>
          <option value="<?= (int)$e["id"] ?>"
            <?= ((int)$e["id"] === $selectedExamId) ? "selected" : "" ?>>
            <?= htmlspecialchars($e["title"]) ?>
            (<?= strtoupper(htmlspecialchars($e["exam_type"])) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </form>

    <hr>

    <div class="form-inline">
      <a class="btn btn-light"
         href="../../public/download_report.php?format=pdf&exam_id=<?= (int)$selectedExamId ?>">
         Download PDF
      </a>
      <a class="btn btn-light"
         href="../../public/download_report.php?format=word&exam_id=<?= (int)$selectedExamId ?>">
         Download Word
      </a>
    </div>

    <hr>

    <?php if ($selectedExamId === 0): ?>
      <div class="muted">No exams available.</div>

    <?php elseif (empty($report)): ?>
      <div class="muted">No submissions yet.</div>

    <?php else: ?>

      <table class="table">
        <thead>
          <tr>
            <th>Student</th>
            <th>Score</th>
            <th>Weekly Topic Recommendation</th>
          </tr>
        </thead>
        <tbody>

        <?php
          $sum = 0;
          $count = 0;

          foreach ($report as $r):
            $sum += (int)$r["score"];
            $count++;

            $weeklyTopic = weeklyTopicFromScore((int)$r["score"]);
        ?>
          <tr>
            <td>
              <?= htmlspecialchars($r["first_name"] . " " . $r["last_name"]) ?>

              <?php if (($r["risk_status"] ?? "") === "at_risk"): ?>
                <span style="color:#ff6b6b;font-weight:bold;">
                  AT RISK STUDENT
                </span>
              <?php endif; ?>
            </td>

            <td><b><?= (int)$r["score"] ?></b>/100</td>

            <td>
              <ul style="margin:0;padding-left:16px;">
                <li><?= htmlspecialchars($weeklyTopic) ?></li>
              </ul>
            </td>
          </tr>
        <?php endforeach; ?>

        </tbody>
      </table>

      <?php $avg = (int)round($sum / max(1, $count)); ?>

      <div class="summary">
        <div><b>Submissions:</b> <?= $count ?></div>
        <div><b>Average Score:</b> <?= $avg ?>/100</div>
      </div>

    <?php endif; ?>

  </div>
</div>

</body>
</html>
