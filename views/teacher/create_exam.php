<?php
session_start();
// Redirect unauthenticated users
if (!isset($_SESSION["user"])) { header("Location: ../../public/login.php"); exit; }
if ($_SESSION["user"]["role"] !== "teacher") { header("Location: ../../public/unauthorized.php"); exit; }

// Load controllers and repositories
require_once __DIR__ . "/../../controllers/ExamController.php";
require_once __DIR__ . "/../../repositories/UserRepository.php";
require_once __DIR__ . "/../../repositories/ExamAssignmentRepository.php";

// Load students list for assignment
$userRepo = new UserRepository();
$students = $userRepo->listByRole("student");

$msg = "";
$err = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $type  = $_POST["exam_type"] ?? "mcq";
    $title = trim($_POST["title"] ?? "");

    $payload = ["title"=>$title, "exam_type"=>$type];
    
    // Get assignment settings
    $assignmentType = $_POST["assignment_type"] ?? "all";
    $assignedStudents = $_POST["assigned_students"] ?? [];
    $payload["assignment_type"] = $assignmentType;
    $payload["assigned_students"] = $assignedStudents;

    /* ================= MATCHING ================= */
    if ($type === "matching") {

        $qText = trim($_POST["matching_question_text"] ?? "");
        $pairs = [];

        for ($i=1; $i<=4; $i++) {
            $left  = trim($_POST["left_$i"] ?? "");
            $right = trim($_POST["right_$i"] ?? "");
            if ($left === "" || $right === "") {
                $err = "Fill all 4 matching pairs.";
                break;
            }
            $pairs[] = ["left_item"=>$left, "right_item"=>$right];
        }

        if ($err === "" && $qText === "") {
            $err = "Enter matching instructions.";
        }

        $payload["matching_question_text"] = $qText;
        $payload["pairs"] = $pairs;
    }

    /* ================= DIALOG ================= */
    elseif ($type === "dialog") {

        $questions = [];

        for ($i=1; $i<=3; $i++) {
            $qt     = trim($_POST["q{$i}_text"] ?? "");
            $answer = trim($_POST["q{$i}_answer"] ?? "");

            if ($qt === "" && $answer === "") {
                continue;
            }

            if ($qt === "" || $answer === "") {
                $err = "Fill dialog question text and correct answer.";
                break;
            }

            $questions[] = [
                "question_text"  => $qt,
                "correct_answer" => $answer
            ];
        }

        if ($err === "" && count($questions) === 0) {
            $err = "Please add at least one dialog question.";
        }

        $payload["questions"] = $questions;
    }

    /* ================= MCQ (TEST) ================= */
    else {

        $questions = [];

        for ($i=1; $i<=3; $i++) {
            $qt = trim($_POST["q{$i}_text"] ?? "");
            $a  = trim($_POST["q{$i}_a"] ?? "");
            $b  = trim($_POST["q{$i}_b"] ?? "");
            $c  = trim($_POST["q{$i}_c"] ?? "");
            $d  = trim($_POST["q{$i}_d"] ?? "");
            $correct = trim($_POST["q{$i}_correct"] ?? "");

            // tamamen boş satır → geç
            if ($qt==="" && $a==="" && $b==="" && $c==="" && $d==="" && $correct==="") {
                continue;
            }

            // yarım doldurulmuş → hata
            if ($qt==="" || $a==="" || $b==="" || $c==="" || $d==="" || !in_array($correct, ["A","B","C","D"])) {
                $err = "Fill all MCQ fields and select correct option.";
                break;
            }

            $questions[] = [
                "question_text"  => $qt,
                "option_a"       => $a,
                "option_b"       => $b,
                "option_c"       => $c,
                "option_d"       => $d,
                "correct_option" => $correct
            ];
        }

        if ($err === "" && count($questions) === 0) {
            $err = "Please add at least one MCQ question.";
        }

        $payload["questions"] = $questions;
    }

    if ($err === "") {
        $examId = (new ExamController())->createExam($payload, (int)$_SESSION["user"]["id"]);
        if ($examId > 0) {
            // Handle student assignments
            if ($assignmentType === "specific" && !empty($assignedStudents)) {
                $assignmentRepo = new ExamAssignmentRepository();
                $assignmentRepo->assignToStudents($examId, $assignedStudents);
            }
            $msg = "Exam created successfully!";
        } else {
            $err = "Exam creation failed.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Create Exam</title>
<link rel="stylesheet" href="/aiExamSystem/assets/css/style.css">
</head>

<body class="page">
<div class="container">

<div class="topbar">
    <div>
        <h2>Create Exam</h2>
        <div class="muted">Manually create a new exam</div>
    </div>
    <a class="btn btn-light" href="../../public/dashboard.php">Back</a>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><?= $err ?></div><?php endif; ?>

<form method="post" class="card form">

<label>Exam Title</label>
<input name="title" required>

<label>Exam Type</label>
<select name="exam_type" id="exam_type">
  <option value="mcq">MCQ</option>
  <option value="matching">Matching</option>
  <option value="dialog">Dialog</option>
</select>

<div id="mcq_block">
<h3>MCQ Questions</h3>
<?php for ($i=1;$i<=3;$i++): ?>
<input name="q<?= $i ?>_text" placeholder="Question text">
<input name="q<?= $i ?>_a" placeholder="Option A">
<input name="q<?= $i ?>_b" placeholder="Option B">
<input name="q<?= $i ?>_c" placeholder="Option C">
<input name="q<?= $i ?>_d" placeholder="Option D">
<select name="q<?= $i ?>_correct">
  <option value="">Correct</option>
  <option>A</option><option>B</option><option>C</option><option>D</option>
</select>
<hr>
<?php endfor; ?>
</div>

<div id="dialog_block" style="display:none;">
<h3>Dialog Questions</h3>
<?php for ($i=1;$i<=3;$i++): ?>
<input name="q<?= $i ?>_text" placeholder="Dialog question">
<input name="q<?= $i ?>_answer" placeholder="Correct answer">
<hr>
<?php endfor; ?>
</div>

<div id="matching_block" style="display:none;">
<h3>Matching</h3>
<input name="matching_question_text" placeholder="Instruction">
<?php for ($i=1;$i<=4;$i++): ?>
<input name="left_<?= $i ?>" placeholder="Left">
<input name="right_<?= $i ?>" placeholder="Right">
<?php endfor; ?>
</div>

<!-- Student Assignment Section -->
<div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.1);">
    <h3>📋 Assign Exam To</h3>
    <div style="display: flex; gap: 15px; margin-bottom: 15px;">
        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
            <input type="radio" name="assignment_type" value="all" checked onchange="toggleStudentSelect()">
            <span>All Students</span>
        </label>
        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
            <input type="radio" name="assignment_type" value="specific" onchange="toggleStudentSelect()">
            <span>Specific Students</span>
        </label>
    </div>
    
    <div id="studentSelectContainer" style="display: none;">
        <label>Select Students</label>
        <select name="assigned_students[]" id="studentSelect" multiple style="width: 100%; height: 120px;">
            <?php foreach ($students as $student): ?>
                <option value="<?= $student['id'] ?>">
                    <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>
                    <?php if (!empty($student['student_number'])): ?>
                        (<?= htmlspecialchars($student['student_number']) ?>)
                    <?php endif; ?>
                </option>
            <?php endforeach; ?>
        </select>
        <small style="color: #888;">Hold Ctrl/Cmd to select multiple students</small>
    </div>
</div>

<button class="btn">Create Exam</button>
</form>
</div>

<script>
const typeSel = document.getElementById("exam_type");
const mcq = document.getElementById("mcq_block");
const dialog = document.getElementById("dialog_block");
const matching = document.getElementById("matching_block");

function setDisabled(block, disabled) {
  block.querySelectorAll("input, select").forEach(el => el.disabled = disabled);
}

function toggle() {
  mcq.style.display = dialog.style.display = matching.style.display = "none";
  setDisabled(mcq, true);
  setDisabled(dialog, true);
  setDisabled(matching, true);

  if (typeSel.value === "mcq") {
    mcq.style.display = "block";
    setDisabled(mcq, false);
  }
  if (typeSel.value === "dialog") {
    dialog.style.display = "block";
    setDisabled(dialog, false);
  }
  if (typeSel.value === "matching") {
    matching.style.display = "block";
    setDisabled(matching, false);
  }
}

function toggleStudentSelect() {
    const specificRadio = document.querySelector('input[name="assignment_type"][value="specific"]');
    const container = document.getElementById('studentSelectContainer');
    
    if (specificRadio && specificRadio.checked) {
        container.style.display = 'block';
    } else {
        container.style.display = 'none';
    }
}

typeSel.addEventListener("change", toggle);
toggle();
</script>

</body>
</html>
