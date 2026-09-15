<?php
session_start();

// Only students allowed
if (!isset($_SESSION["user"])) {
    header("Location: ../../public/login.php");
    exit;
}
if ($_SESSION["user"]["role"] !== "student") {
    header("Location: ../../public/unauthorized.php");
    exit;
}

require_once __DIR__ . "/../../controllers/MessageController.php";

$mc = new MessageController();
$studentId = (int)$_SESSION["user"]["id"];

// Teachers list
$teachers = $mc->listUsersForChat("teacher");
$teacherId = (int)($_GET["with"] ?? ($teachers[0]["id"] ?? 0));

// Send message
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $content = trim($_POST["content"] ?? "");
    if ($content !== "" && $teacherId > 0) {
        $mc->send($studentId, $teacherId, $content);
    }
    header("Location: messages.php?with=" . $teacherId);
    exit;
}

// Conversation
$conversation = $teacherId ? $mc->inbox($studentId, $teacherId) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Messages</title>
  <link rel="stylesheet" href="/aiExamSystem/assets/css/style.css">
</head>
<body class="page">
<div class="container">

  <div class="topbar">
    <div>
      <h2>Messages</h2>
      <div class="muted">Chat with teacher</div>
    </div>
    <a class="btn btn-light" href="../../public/dashboard.php">Back</a>
  </div>

  <div class="card">

    <!-- Teacher selector -->
    <form method="get" class="form-inline">
      <label>Teacher:</label>
      <select name="with" onchange="this.form.submit()">
        <?php foreach ($teachers as $t): ?>
          <option value="<?= (int)$t["id"] ?>"
            <?= ((int)$t["id"] === $teacherId) ? "selected" : "" ?>>
            <?= htmlspecialchars($t["first_name"] . " " . $t["last_name"]) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>

    <hr>

    <?php if (!$teacherId): ?>

      <div class="muted">No teacher found.</div>

    <?php else: ?>

      <!-- Conversation -->
      <div style="max-height:320px; overflow:auto; border:1px solid #223150; padding:10px; border-radius:12px;">
        <?php if (empty($conversation)): ?>
          <div class="muted">No messages yet.</div>
        <?php else: ?>
          <?php foreach ($conversation as $m): ?>
            <div style="margin-bottom:10px;">
              <b><?= ((int)$m["sender_id"] === $studentId) ? "Me" : "Teacher" ?>:</b>
              <?= htmlspecialchars($m["content"]) ?>
              <div class="muted" style="font-size:12px;">
                <?= htmlspecialchars($m["created_at"]) ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Send message -->
      <form method="post" class="form" style="margin-top:12px;">
        <label>Message</label>
        <input name="content" required placeholder="Type your message...">
        <button class="btn" type="submit">Send</button>
      </form>

    <?php endif; ?>

  </div>
</div>
</body>
</html>
