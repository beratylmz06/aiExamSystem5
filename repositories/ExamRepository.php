<?php
require_once __DIR__ . "/../config/database.php";

class ExamRepository {
    // Fetch exams by category for Student selection
    public function listExamsByType(string $type): array {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT id, title, exam_type, created_at FROM exams WHERE exam_type=? ORDER BY id DESC");
        $stmt->bind_param("s", $type);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    // Get basic metadata for a specific exam
    public function getExamBasic(int $examId): ?array {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM exams WHERE id=?");
        $stmt->bind_param("i", $examId);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        return $r ?: null;
    }
    // Deep fetch including questions and matching pairs
    public function getExamWithAllData(int $examId): ?array {
        $db = Database::connect();

        $stmt = $db->prepare("SELECT * FROM exams WHERE id=?");
        $stmt->bind_param("i", $examId);
        $stmt->execute();
        $exam = $stmt->get_result()->fetch_assoc();
        if (!$exam) return null;

        $stmtQ = $db->prepare("SELECT * FROM questions WHERE exam_id=? ORDER BY id ASC");
        $stmtQ->bind_param("i", $examId);
        $stmtQ->execute();
        $questions = $stmtQ->get_result()->fetch_all(MYSQLI_ASSOC);

        if ($exam["exam_type"] === "matching") {
            // attach pairs for each question
            foreach ($questions as &$q) {
                $qid = (int)$q["id"];
                $stmtP = $db->prepare("SELECT * FROM matching_pairs WHERE question_id=? ORDER BY id ASC");
                $stmtP->bind_param("i", $qid);
                $stmtP->execute();
                $pairs = $stmtP->get_result()->fetch_all(MYSQLI_ASSOC);
                $q["pairs"] = $pairs;

                // right choices pool (shuffle in UI not needed)
                $rightPool = [];
                foreach ($pairs as $p) $rightPool[] = $p["right_item"];
                $q["right_pool"] = $rightPool;
            }
            unset($q);
        }

        $exam["questions"] = $questions;
        return $exam;
    }

    // payload:
    // title, exam_type, questions[]:
    // mcq/dialog question: text, option_a..d, correct_option(A-D)
    // matching question: question_text + pairs: left/right
    // Returns: exam_id on success, 0 on failure
    public function createExam(array $payload, int $teacherId): int {
        $db = Database::connect();
        $db->begin_transaction();

        try {
            $title = $payload["title"];
            $type  = $payload["exam_type"];
            $thresholdHigh = $payload["score_threshold_high"] ?? 70;
            $thresholdMedium = $payload["score_threshold_medium"] ?? 50;

            $stmt = $db->prepare("INSERT INTO exams (title, exam_type, created_by, score_threshold_high, score_threshold_medium) VALUES (?,?,?,?,?)");
            $stmt->bind_param("ssiii", $title, $type, $teacherId, $thresholdHigh, $thresholdMedium);
            $stmt->execute();
            $examId = $db->insert_id;

            if ($type === "matching") {
                // we store 1 question with multiple pairs
                // payload["matching_question_text"], payload["pairs"] (array)
                $qText = $payload["matching_question_text"];
                $stmtQ = $db->prepare("INSERT INTO questions (exam_id, question_text) VALUES (?,?)");
                $stmtQ->bind_param("is", $examId, $qText);
                $stmtQ->execute();
                $questionId = $db->insert_id;

                $stmtP = $db->prepare("INSERT INTO matching_pairs (question_id, left_item, right_item) VALUES (?,?,?)");
                foreach ($payload["pairs"] as $pair) {
                    $left = $pair["left_item"];
                    $right = $pair["right_item"];
                    $stmtP->bind_param("iss", $questionId, $left, $right);
                    $stmtP->execute();
                }
            } else {
               
                $stmtQ = $db->prepare(
                    "INSERT INTO questions (exam_id, question_text, topic, option_a, option_b, option_c, option_d, correct_option)
                     VALUES (?,?,?,?,?,?,?,?)"
                );
                foreach ($payload["questions"] as $q) {
                    $questionText = $q["question_text"] ?? "";
                    $topic = $q["topic"] ?? "General";
                    $optionA = $q["option_a"] ?? null;
                    $optionB = $q["option_b"] ?? null;
                    $optionC = $q["option_c"] ?? null;
                    $optionD = $q["option_d"] ?? null;
                    // For dialog type, use correct_answer field; for MCQ use correct_option
                    $correctOption = $q["correct_option"] ?? $q["correct_answer"] ?? "";
                    
                    $stmtQ->bind_param(
                        "isssssss",
                        $examId,
                        $questionText,
                        $topic,
                        $optionA,
                        $optionB,
                        $optionC,
                        $optionD,
                        $correctOption
                    );
                    $stmtQ->execute();
                }
            }

            $db->commit();
            return $examId;
        } catch (Exception $e) {
            $db->rollback();
            return 0;
        }
    }
    
    /**
     * Add a single question to an existing exam
     * 
     * @param array $questionData Contains: exam_id, question_text, topic, option_a-d, correct_option
     * @return bool Success status
     */
    public function addQuestion(array $questionData): bool {
        $db = Database::connect();
        
        $stmt = $db->prepare(
            "INSERT INTO questions (exam_id, question_text, topic, option_a, option_b, option_c, option_d, correct_option)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        
        if (!$stmt) {
            return false;
        }
        
        $examId = (int)$questionData['exam_id'];
        $questionText = $questionData['question_text'] ?? '';
        $topic = $questionData['topic'] ?? 'General';
        $optionA = $questionData['option_a'] ?? null;
        $optionB = $questionData['option_b'] ?? null;
        $optionC = $questionData['option_c'] ?? null;
        $optionD = $questionData['option_d'] ?? null;
        $correctOption = $questionData['correct_option'] ?? '';
        
        $stmt->bind_param(
            "isssssss",
            $examId,
            $questionText,
            $topic,
            $optionA,
            $optionB,
            $optionC,
            $optionD,
            $correctOption
        );
        
        return $stmt->execute();
    }
    
    /**
     * Get all exams (for admin/teacher listing)
     */
    public function listAllExams(): array {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT id, title, exam_type, created_at FROM exams ORDER BY id DESC");
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
