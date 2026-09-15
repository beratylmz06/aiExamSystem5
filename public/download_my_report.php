<?php
session_start();

/* Auth */
if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit;
}
if ($_SESSION["user"]["role"] !== "student") {
    header("Location: unauthorized.php");
    exit;
}

require_once __DIR__ . "/../controllers/ReportController.php";

$examId = (int)($_GET["exam_id"] ?? 0);
$format = strtolower($_GET["format"] ?? "pdf");

if ($examId <= 0) {
    die("Invalid exam.");
}

if (!in_array($format, ["pdf", "word"])) {
    $format = "pdf";
}

/* Student-safe download */
$rc = new ReportController();
$rc->downloadStudentReport(
    $examId,
    (int)$_SESSION["user"]["id"],
    $format
);
