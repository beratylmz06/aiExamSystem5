<?php
session_start();

if (!isset($_SESSION["user"])) {
    header("Location: ../../public/login.php");
    exit;
}
if ($_SESSION["user"]["role"] !== "teacher") {
    header("Location: ../../public/unauthorized.php");
    exit;
}

require_once __DIR__ . "/../../controllers/MessageController.php";

$mc = new MessageController();
$teacherId = (int)$_SESSION["user"]["id"];

// Students list
$students = $mc->listUsersForChat("student");
$studentId = (int)($_GET["with"] ?? ($students[0]["id"] ?? 0));

// Send message
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $content = trim($_POST["content"] ?? "");
    if ($content !== "" && $studentId > 0) {
        $mc->send($teacherId, $studentId, $content);
    }
    header("Location: messages.php?with=" . $studentId);
    exit;
}

// Conversation
$conversation = $studentId ? $mc->inbox($teacherId, $studentId) : [];
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
      <div class="muted">Chat with students</div>
    </div>
    <a class="btn btn-light" href="../../public/dashboard.php">Back</a>
  </div>

  <div class="card">

    <!-- Student selector -->
    <form method="get" class="form-inline">
      <label>Student:</label>
      <select name="with" onchange="this.form.submit()">
        <?php foreach ($students as $s): ?>
          <option value="<?= (int)$s["id"] ?>"
            <?= ((int)$s["id"] === $studentId) ? "selected" : "" ?>>
            <?= htmlspecialchars($s["first_name"] . " " . $s["last_name"]) ?>
            (<?= htmlspecialchars($s["email"]) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </form>

    <hr>

    <?php if (!$studentId): ?>

      <div class="muted">No student found.</div>

    <?php else: ?>

      <!-- Conversation -->
      <div style="max-height:320px; overflow:auto; border:1px solid #223150; padding:10px; border-radius:12px;">
        <?php if (empty($conversation)): ?>
          <div class="muted">No messages yet.</div>
        <?php else: ?>
          <?php foreach ($conversation as $m): ?>
            <div style="margin-bottom:10px;">
              <b><?= ((int)$m["sender_id"] === $teacherId) ? "Me" : "Student" ?>:</b>
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
