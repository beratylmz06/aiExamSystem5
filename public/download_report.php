<?php
session_start();

// Must be logged in
if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . "/../controllers/ReportController.php";

$user = $_SESSION["user"];

// INPUTS
$examId = (int)($_GET["exam_id"] ?? 0);
$format = strtolower($_GET["format"] ?? "word");

if (!in_array($format, ["word", "pdf"])) {
    $format = "word";
}

if ($examId <= 0) {
    die("Invalid exam_id.");
}

// ROLE LOGIC
// Teacher: class report
// Student: personal report
$rc = new ReportController();

if ($user["role"] === "teacher") {

    // Teacher downloads class-wide report
    $rc->download($examId, $format);

} elseif ($user["role"] === "student") {

    // Student downloads ONLY own report
    $rc->downloadStudent(
        $examId,
        (int)$user["id"],
        $format
    );

} else {
    header("Location: unauthorized.php");
    exit;
}
