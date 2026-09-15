<?php
session_start();
// Supports FR10: Ensure the user session is active
if (!isset($_SESSION["user"])) { header("Location: login.php"); exit; }

require_once __DIR__ . "/../controllers/NotificationController.php";

$userId = (int)$_SESSION["user"]["id"];
$userRole = $_SESSION["user"]["role"] ?? "";
$isTeacher = ($userRole === "teacher");
$nc = new NotificationController();
// Handle "Mark all as read" action - Only teachers can mark as read
if (isset($_GET["read"]) && $isTeacher) {
    if ($_GET["read"] === "all") {
        $nc->markAllRead($userId);
    } elseif (is_numeric($_GET["read"])) {
        $nc->markAsRead((int)$_GET["read"], $userId);
    }
    header("Location: notifications.php");
    exit;
}
// Fetch notification list for the specific Actor (Student/Teacher)
$items = $nc->listForUser($userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Notifications</title>
  <link rel="stylesheet" href="/aiExamSystem/assets/css/style.css">
  <style>
      .btn-xs { padding: 5px 10px; font-size: 0.8rem; border-radius: 8px; }
  </style>
</head>
<body class="page">
<div class="container">
  <div class="topbar">
    <div>
      <h2>Notifications</h2>
      <div class="muted">All system notifications</div>
    </div>
    <div style="display:flex; gap:10px;">
      <?php if ($isTeacher): ?>
      <a class="btn btn-light" href="?read=all">Mark all read</a>
      <?php endif; ?>
      <a class="btn btn-light" href="dashboard.php">Back</a>
    </div>
  </div>

  <div class="card">
    <?php if (empty($items)): ?>
      <div class="muted">No notifications.</div>
    <?php else: ?>
      <table class="table">
        <thead>
          <tr>
            <th>Status</th>
            <th>Message</th>
            <th>Time</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $n): ?>
            <?php $isRead = (int)$n["is_read"] === 1; ?>
          <tr style="<?= $isRead ? 'opacity: 0.6;' : '' ?>">
            <td>
                <?php if($isRead): ?>
                    <span style="color: #2dd4bf;">● Reviewed by Teacher</span>
                <?php else: ?>
                    <span style="color: #ff4d6d;">● Pending Review</span>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($n["message"]) ?></td>
            <td><?= htmlspecialchars($n["created_at"]) ?></td>
            <td>
                <?php if(!$isRead && $isTeacher): ?>
                    <a href="?read=<?= $n['id'] ?>" class="btn btn-light btn-xs">Mark read</a>
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
