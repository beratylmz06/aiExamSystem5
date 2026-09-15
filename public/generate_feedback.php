<?php
session_start();
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../services/AIService.php";

// 1. Validation
if (!isset($_SESSION["user"]) || $_SESSION["user"]["role"] !== "student") {
    http_response_code(403);
    die("Unauthorized");
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Method not allowed");
}

$attemptId = (int)($_POST["attempt_id"] ?? 0);
if ($attemptId <= 0) {
    die("Invalid request");
}

$userId = (int)$_SESSION["user"]["id"];
$db = Database::connect();

// 2. Fetch attempt data (verify ownership)
$stmt = $db->prepare(
    "SELECT r.id, r.score, r.exam_id, e.title AS exam_title, r.details
     FROM results r
     JOIN exams e ON e.id = r.exam_id
     WHERE r.id = ? AND r.user_id = ?"
);
$stmt->bind_param("ii", $attemptId, $userId);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if (!$result) {
    die("Attempt not found");
}

// 3. Prepare data for AI
// details contains question-by-question info with is_correct, given, correct fields

$attemptData = [
    'exam_title' => $result['exam_title'],
    'score' => $result['score'],
    'correct_count' => 0,
    'wrong_count' => 0,
    'questions_analysis' => ''
];

// Parse details to extract question-by-question analysis
if (!empty($result['details'])) {
    $details = json_decode($result['details'], true);
    if (is_array($details)) {
        $correctCount = 0;
        $wrongCount = 0;
        $questionsAnalysis = [];
        
        // Fetch questions from database for detailed analysis
        $examId = (int)$result['exam_id'];
        $stmtQ = $db->prepare("SELECT id, question_text, topic, correct_option FROM questions WHERE exam_id = ?");
        $stmtQ->bind_param("i", $examId);
        $stmtQ->execute();
        $questionsResult = $stmtQ->get_result();
        $questionsMap = [];
        while ($qRow = $questionsResult->fetch_assoc()) {
            $questionsMap[$qRow['id']] = $qRow;
        }
        
        foreach ($details as $index => $q) {
            $isCorrect = !empty($q['is_correct']);
            if ($isCorrect) {
                $correctCount++;
            } else {
                $wrongCount++;
                
                // Try to get question text from details first, then from database
                $questionText = $q['question_text'] ?? null;
                if (empty($questionText) && isset($q['question_id']) && isset($questionsMap[$q['question_id']])) {
                    $questionText = $questionsMap[$q['question_id']]['question_text'];
                }
                if (empty($questionText)) {
                    $questionText = "Question " . ($index + 1);
                }
                
                $givenAnswer = $q['given'] ?? 'No answer';
                $correctAnswer = $q['correct'] ?? 'Unknown';
                
                // Get topic if available
                $topic = $q['topic'] ?? '';
                if (empty($topic) && isset($q['question_id']) && isset($questionsMap[$q['question_id']])) {
                    $topic = $questionsMap[$q['question_id']]['topic'] ?? '';
                }
                
                $analysisLine = "- Question: " . $questionText . 
                    "\n  Student's Answer: " . $givenAnswer . 
                    "\n  Correct Answer: " . $correctAnswer;
                if (!empty($topic)) {
                    $analysisLine .= "\n  Topic: " . $topic;
                }
                
                $questionsAnalysis[] = $analysisLine;
            }
        }
        
        $attemptData['correct_count'] = $correctCount;
        $attemptData['wrong_count'] = $wrongCount;
        
        if (!empty($questionsAnalysis)) {
            $attemptData['questions_analysis'] = "WRONG ANSWERS DETAIL:\n" . implode("\n\n", $questionsAnalysis);
        } else {
            $attemptData['questions_analysis'] = "All questions were answered correctly!";
        }
    }
}

// 4. Generate Feedback
$ai = new AIService();
if (!$ai->isConfigured()) {
    die("AI Service not configured");
}

try {
    $feedbackText = $ai->generateFeedback($attemptData);
    
    // 5. Save to DB
    // Use ai_feedback column
    $stmtUpd = $db->prepare("UPDATE results SET ai_feedback = ?, ai_feedback_generated_at = NOW() WHERE id = ?");
    $stmtUpd->bind_param("si", $feedbackText, $attemptId);
    $stmtUpd->execute();
    
    // Redirect back
    header("Location: ../views/student/result_detail.php?exam_id=" . $result['exam_id'] . "&feedback_generated=1");
    exit;

} catch (Exception $e) {
    die("Error generating feedback: " . $e->getMessage());
}
?>
