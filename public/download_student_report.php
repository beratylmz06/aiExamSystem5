<?php
session_start();
// Redirect unauthenticated users to login page
if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit;
}
// Allow access only to student users
if ($_SESSION["user"]["role"] !== "student") {
    header("Location: unauthorized.php");
    exit;
}
// Load report controller
require_once __DIR__ . "/../controllers/ReportController.php";

$examId = (int)($_GET["exam_id"] ?? 0);
$format = strtolower($_GET["format"] ?? "pdf");

if (!in_array($format, ["pdf", "word"])) {
    $format = "pdf";
}
// Validate exam identifier
if ($examId <= 0) {
    die("Invalid exam.");
}

$studentId = (int)$_SESSION["user"]["id"];

$rc = new ReportController();
$rc->downloadStudent($examId, $studentId, $format);
