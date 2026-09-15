<?php
session_start();
// Redirect unauthenticated users to login page
if (!isset($_SESSION["user"])) { header("Location: ../../public/login.php"); exit; }
if ($_SESSION["user"]["role"] !== "student") { header("Location: ../../public/unauthorized.php"); exit; }

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../controllers/ExamController.php";

$db = Database::connect();
$studentId = (int)$_SESSION["user"]["id"];
$examId = (int)($_GET["exam_id"] ?? 0);

if ($examId <= 0) {
    header("Location: my_results.php");
    exit;
}

// Get result with details
$stmt = $db->prepare(
    "SELECT r.id, r.exam_id, r.score, r.feedback, r.ai_feedback, r.weak_topics, r.submitted_at,
            e.title AS exam_title,
            COALESCE(e.score_threshold_high, 70) AS passing_grade
     FROM results r
     JOIN exams e ON e.id = r.exam_id
     WHERE r.user_id = ? AND r.exam_id = ?"
);
$stmt->bind_param("ii", $studentId, $examId);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if (!$result) {
    header("Location: my_results.php");
    exit;
}

// Get result details for question answers
$stmtDetails = $db->prepare("SELECT details FROM results WHERE user_id = ? AND exam_id = ?");
$stmtDetails->bind_param("ii", $studentId, $examId);
$stmtDetails->execute();
$detailsRow = $stmtDetails->get_result()->fetch_assoc();
$details = json_decode($detailsRow["details"] ?? "[]", true) ?: [];

// Calculate correct/wrong counts
$correctCount = 0;
$wrongCount = 0;
$totalQuestions = count($details);

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

// Get exam with questions
$ec = new ExamController();
$exam = $ec->getExamWithAllData($examId);

// Parse AI feedback
$aiFeedback = null;
$aiFeedbackText = null;
$isRichFeedback = false;

// Priority 1: New ai_feedback column (Text format)
if (!empty($result["ai_feedback"])) {
    $aiFeedbackText = $result["ai_feedback"];
    // If it happens to be JSON (from prompt constraints), decode it
    $decoded = json_decode($aiFeedbackText, true);
    if (is_array($decoded) && isset($decoded['feedback_text'])) {
        $aiFeedbackText = $decoded['feedback_text'];
    }
} 
// Priority 2: Legacy feedback column (JSON format)
elseif (!empty($result["feedback"])) {
    $aiFeedback = json_decode($result["feedback"], true);
    $isRichFeedback = is_array($aiFeedback) && isset($aiFeedback["status"]) && $aiFeedback["status"] === "ok";
}

$weakTopics = json_decode($result["weak_topics"] ?? "[]", true) ?: [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Exam Result - <?= htmlspecialchars($result["exam_title"]) ?></title>
    <link rel="stylesheet" href="/aiExamSystem/assets/css/style.css">
    <style>
        .ai-feedback-container {
            background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
            border-radius: 12px;
            padding: 25px;
            margin-top: 20px;
        }
        .ai-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .score-display {
            text-align: center;
            padding: 30px;
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            border: 1px solid var(--border);
            color: white;
        }
        .score-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 36px;
            font-weight: bold;
            color: white;
        }
        .score-high { background: linear-gradient(135deg, #28a745, #20c997); }
        .score-medium { background: linear-gradient(135deg, #ffc107, #fd7e14); }
        .score-low { background: linear-gradient(135deg, #dc3545, #e83e8c); }
        
        /* Stats Grid */
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
        
        /* Question Review Styles */
        .questions-review { margin-top: 30px; }
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
        .answer-box.your-answer { border: 1px solid rgba(102, 126, 234, 0.5); }
        .answer-box.correct-answer { border: 1px solid rgba(45, 212, 191, 0.5); }
        .answer-box-label {
            font-size: 0.8rem;
            color: var(--muted);
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .answer-box-value { color: white; font-weight: 500; }
        .answer-box.your-answer.is-wrong .answer-box-value { color: #ef4444; }
        .answer-box.your-answer.is-correct .answer-box-value { color: #2dd4bf; }
        .answer-box.correct-answer .answer-box-value { color: #2dd4bf; }
        
        .diagnosis-box {
            background: rgba(255,255,255,0.05);
            padding: 15px 20px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid #667eea;
            color: white;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: bold;
            margin: 25px 0 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: white;
        }
        
        .topic-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        .topic-card {
            background: rgba(255,255,255,0.05);
            padding: 15px;
            border-radius: 8px;
            color: white;
        }
        .topic-card.strength {
            border-left: 4px solid #28a745;
        }
        .topic-card.weakness {
            border-left: 4px solid #dc3545;
        }
        
        .action-plan {
            background: rgba(255,255,255,0.05);
            border-radius: 8px;
            overflow: hidden;
        }
        .day-item {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: background 0.2s;
            color: white;
        }
        .day-item:hover {
            background: rgba(255,255,255,0.08);
        }
        .day-item:last-child {
            border-bottom: none;
        }
        .day-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .day-number {
            background: #667eea;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }
        .day-focus {
            flex: 1;
            margin-left: 15px;
            font-weight: 500;
        }
        .day-tasks {
            display: none;
            padding-top: 10px;
            margin-top: 10px;
            border-top: 1px dashed var(--border);
        }
        .day-tasks.active {
            display: block;
        }
        .day-tasks ul {
            margin: 0;
            padding-left: 45px;
        }
        .day-tasks li {
            padding: 5px 0;
            color: var(--muted);
        }
        
        .tips-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 12px;
        }
        .tip-item {
            background: rgba(255,255,255,0.05);
            padding: 12px 15px;
            border-radius: 8px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            color: white;
        }
        .tip-icon {
            font-size: 20px;
        }
        
        .strategy-section {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }
        .strategy-card {
            background: rgba(255,255,255,0.05);
            padding: 15px;
            border-radius: 8px;
            color: white;
        }
        .strategy-card h4 {
            margin: 0 0 10px;
            font-size: 14px;
            color: #667eea;
            text-transform: uppercase;
        }
        .strategy-card ul {
            margin: 0;
            padding-left: 20px;
        }
        .strategy-card li {
            padding: 4px 0;
            font-size: 14px;
            color: var(--muted);
        }
        
        .teacher-note {
            background: rgba(255, 243, 205, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.3);
            padding: 15px 20px;
            border-radius: 8px;
            margin-top: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            color: #ffc107;
        }
        
        .simple-feedback {
            background: rgba(255,255,255,0.05);
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            color: white;
            border: 1px solid var(--border);
        }
        
        /* Loading Overlay */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }
        .loading-overlay.active {
            display: flex;
        }
        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 4px solid rgba(102, 126, 234, 0.3);
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .loading-text {
            margin-top: 20px;
            color: white;
            font-size: 1.2rem;
            font-weight: 500;
        }
        .loading-subtext {
            margin-top: 8px;
            color: var(--muted);
            font-size: 0.9rem;
        }
    </style>
</head>

<body class="page">

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
    <div class="loading-text">🤖 Generating AI Feedback...</div>
    <div class="loading-subtext">This may take a few seconds</div>
</div>
<div class="container">

    <div class="topbar">
        <div>
            <h2><?= htmlspecialchars($result["exam_title"]) ?></h2>
            <div class="muted"><?= htmlspecialchars($result["submitted_at"]) ?></div>
        </div>
        <div>
            <a class="btn btn-light" href="my_results.php">Back</a>
        </div>
    </div>

    <!-- Score Display -->
    <div class="score-display">
        <?php 
        $score = (int)$result["score"];
        $passingGrade = (int)$result["passing_grade"];
        $scoreClass = $score >= $passingGrade ? "score-high" : "score-low";
        ?>
        <div class="score-circle <?= $scoreClass ?>">
            <?= $score ?>
        </div>
        <h3>Score: <?= $score ?>/100</h3>
        <?php if ($isRichFeedback && isset($aiFeedback["summary"]["confidence"])): ?>
        <span class="ai-badge">AI Analysis (<?= ucfirst($aiFeedback["summary"]["confidence"]) ?> confidence)</span>
        <?php endif; ?>
    </div>
    
    <!-- Stats Grid -->
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
    
    <!-- Question Review -->
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

    <?php if ($aiFeedbackText): ?>
    <!-- New Text-Based AI Feedback -->
    <div class="ai-feedback-container">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div class="section-title" style="margin:0;">🤖 AI Performance Analysis</div>
            <form action="../../public/generate_feedback.php" method="POST" onsubmit="return confirmAndLoad()">
                <input type="hidden" name="attempt_id" value="<?= $result['id'] ?>">
                <button type="submit" class="btn btn-light btn-xs">🔄 Regenerate</button>
            </form>
        </div>
        
        <div class="simple-feedback" style="white-space: pre-wrap; font-family: 'Inter', sans-serif; line-height: 1.6;">
<?= htmlspecialchars($aiFeedbackText) ?>
        </div>
        
        <!-- Download AI Feedback -->
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--border); text-align: center;">
            <p class="text-muted" style="margin-bottom: 15px;">📥 Download your AI Feedback</p>
            <a class="btn btn-light" href="../../public/download_student_report.php?format=pdf&exam_id=<?= $examId ?>&include_feedback=1" style="margin-right: 10px;">
                📄 Download PDF
            </a>
            <a class="btn btn-light" href="../../public/download_student_report.php?format=word&exam_id=<?= $examId ?>&include_feedback=1">
                📝 Download Word
            </a>
        </div>
    </div>
    
    <?php elseif ($isRichFeedback): ?>
    <!-- Rich AI Feedback (Legacy JSON) -->
    <div class="ai-feedback-container">
        <!-- ... existing rich feedback code ... -->
        
        <!-- Diagnosis -->
        <?php if (!empty($aiFeedback["summary"]["diagnosis"])): ?>
        <div class="diagnosis-box">
            <strong>📊 Değerlendirme:</strong> <?= htmlspecialchars($aiFeedback["summary"]["diagnosis"]) ?>
        </div>
        <?php endif; ?>
        
        <!-- Strengths & Weaknesses -->
        <div class="section-title">💪 Güçlü ve Zayıf Yönler</div>
        <div class="topic-grid">
            <?php foreach ($aiFeedback["strengths"] ?? [] as $strength): ?>
            <div class="topic-card strength">
                <strong>✓</strong> <?= htmlspecialchars($strength) ?>
            </div>
            <?php endforeach; ?>
            <?php foreach ($aiFeedback["weaknesses"] ?? [] as $weakness): ?>
            <div class="topic-card weakness">
                <strong>✗</strong> <?= htmlspecialchars($weakness) ?>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- 7-Day Action Plan -->
        <?php if (!empty($aiFeedback["action_plan_7_days"])): ?>
        <div class="section-title">📅 7 Günlük Çalışma Planı</div>
        <div class="action-plan">
            <?php foreach ($aiFeedback["action_plan_7_days"] as $day): ?>
            <div class="day-item" onclick="toggleDay(this)">
                <div class="day-header">
                    <div class="day-number"><?= (int)$day["day"] ?></div>
                    <div class="day-focus"><?= htmlspecialchars($day["focus"] ?? "") ?></div>
                    <span>▼</span>
                </div>
                <div class="day-tasks">
                    <ul>
                        <?php foreach ($day["tasks"] ?? [] as $task): ?>
                        <li><?= htmlspecialchars($task) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Micro Tips -->
        <?php if (!empty($aiFeedback["micro_tips"])): ?>
        <div class="section-title">💡 Hızlı İpuçları</div>
        <div class="tips-grid">
            <?php $icons = ["📚", "✏️", "🎯", "⏰", "🔍", "💪"]; ?>
            <?php foreach ($aiFeedback["micro_tips"] as $i => $tip): ?>
            <div class="tip-item">
                <span class="tip-icon"><?= $icons[$i % count($icons)] ?></span>
                <span><?= htmlspecialchars($tip) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Next Exam Strategy -->
        <?php if (!empty($aiFeedback["next_exam_strategy"])): ?>
        <div class="section-title">🎯 Sonraki Sınav Stratejisi</div>
        <div class="strategy-section">
            <?php if (!empty($aiFeedback["next_exam_strategy"]["time_management"])): ?>
            <div class="strategy-card">
                <h4>⏱️ Zaman Yönetimi</h4>
                <ul>
                    <?php foreach ($aiFeedback["next_exam_strategy"]["time_management"] as $item): ?>
                    <li><?= htmlspecialchars($item) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            <?php if (!empty($aiFeedback["next_exam_strategy"]["revision_method"])): ?>
            <div class="strategy-card">
                <h4>📖 Tekrar Yöntemi</h4>
                <ul>
                    <?php foreach ($aiFeedback["next_exam_strategy"]["revision_method"] as $item): ?>
                    <li><?= htmlspecialchars($item) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            <?php if (!empty($aiFeedback["next_exam_strategy"]["practice_suggestion"])): ?>
            <div class="strategy-card">
                <h4>✍️ Pratik Önerisi</h4>
                <ul>
                    <?php foreach ($aiFeedback["next_exam_strategy"]["practice_suggestion"] as $item): ?>
                    <li><?= htmlspecialchars($item) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Teacher Note -->
        <?php if (!empty($aiFeedback["teacher_note"])): ?>
        <div class="teacher-note">
            <span style="font-size: 24px;">👨‍🏫</span>
            <div>
                <strong>Öğretmen Notu:</strong><br>
                <?= htmlspecialchars($aiFeedback["teacher_note"]) ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Download AI Feedback -->
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--border); text-align: center;">
            <p class="text-muted" style="margin-bottom: 15px;">📥 Download your AI Feedback</p>
            <a class="btn btn-light" href="../../public/download_student_report.php?format=pdf&exam_id=<?= $examId ?>&include_feedback=1" style="margin-right: 10px;">
                📄 Download PDF
            </a>
            <a class="btn btn-light" href="../../public/download_student_report.php?format=word&exam_id=<?= $examId ?>&include_feedback=1">
                📝 Download Word
            </a>
        </div>
        
    </div>
    
    <?php else: ?>
    <!-- No Feedback - Show Generate Button -->
    <div class="simple-feedback" style="text-align: center; padding: 40px;">
        <div style="font-size: 40px; margin-bottom: 20px;">🤖</div>
        <h3>AI Powered Feedback</h3>
        <p class="text-muted" style="margin-bottom: 25px;">Generate a personalized analysis and study plan based on your exam performance.</p>
        
        <form action="../../public/generate_feedback.php" method="POST" onsubmit="showLoading()">
            <input type="hidden" name="attempt_id" value="<?= $result['id'] ?>">
            <button type="submit" class="btn btn-primary" style="padding: 12px 30px; font-size: 1.1rem;">
                ✨ Generate AI Feedback
            </button>
        </form>
    </div>
    <?php endif; ?>

</div>

<script>
function toggleDay(element) {
    const tasks = element.querySelector('.day-tasks');
    const wasActive = tasks.classList.contains('active');
    
    // Close all
    document.querySelectorAll('.day-tasks').forEach(t => t.classList.remove('active'));
    
    // Toggle clicked
    if (!wasActive) {
        tasks.classList.add('active');
    }
}

function showLoading() {
    document.getElementById('loadingOverlay').classList.add('active');
}

function confirmAndLoad() {
    if (confirm('Do you want to clear the current analysis and generate a new one?')) {
        showLoading();
        return true;
    }
    return false;
}
</script>

</body>
</html>
