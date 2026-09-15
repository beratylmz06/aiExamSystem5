<?php
require_once __DIR__ . "/../config/database.php";

class ResultRepository {

    
      // SAVE RESULT 
      
    public function saveResult(
        int $studentId,
        int $examId,
        int $score,
        string $details
    ): bool {

        $db = Database::connect();

        $stmt = $db->prepare("
            INSERT INTO results (user_id, exam_id, score, details)
            VALUES (?, ?, ?, ?)
        ");

        if (!$stmt) {
            die("SQL PREPARE ERROR (saveResult): " . $db->error);
        }

        $stmt->bind_param("iiis", $studentId, $examId, $score, $details);
        return $stmt->execute();
    }

      // STUDENT – SINGLE RESULT
      
    public function getStudentResult(
        int $studentId,
        int $examId
    ): ?array {

        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT r.*, 
                   e.title AS exam_title,
                   e.exam_type
            FROM results r
            JOIN exams e ON e.id = r.exam_id
            WHERE r.user_id = ? AND r.exam_id = ?
            ORDER BY r.id DESC
            LIMIT 1
        ");

        if (!$stmt) {
            die("SQL PREPARE ERROR (getStudentResult): " . $db->error);
        }

        $stmt->bind_param("ii", $studentId, $examId);
        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }

    
       // STUDENT – REPORT (PDF/WORD)
      
    public function getStudentReport(
        int $examId,
        int $studentId
    ): array {

        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT r.*, 
                   u.first_name, 
                   u.last_name
            FROM results r
            JOIN users u ON u.id = r.user_id
            WHERE r.exam_id = ? AND r.user_id = ?
            ORDER BY r.id DESC
        ");

        if (!$stmt) {
            die("SQL PREPARE ERROR (getStudentReport): " . $db->error);
        }

        $stmt->bind_param("ii", $examId, $studentId);
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }


    public function getClassReport(
        int $examId
    ): array {

        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT r.*, 
                   u.first_name, 
                   u.last_name
            FROM results r
            JOIN users u ON u.id = r.user_id
            WHERE r.exam_id = ?
            ORDER BY r.score DESC
        ");

        if (!$stmt) {
            die("SQL PREPARE ERROR (getClassReport): " . $db->error);
        }

        $stmt->bind_param("i", $examId);
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Find result by ID
     */
    public function findById(int $resultId): ?array {
        $db = Database::connect();
        
        $stmt = $db->prepare("SELECT * FROM results WHERE id = ?");
        if (!$stmt) {
            die("SQL PREPARE ERROR (findById): " . $db->error);
        }
        
        $stmt->bind_param("i", $resultId);
        $stmt->execute();
        
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }
    
    /**
     * Create a new result record with feedback
     * 
     * @param array $data Contains: student_id, exam_id, score, feedback, weak_topics
     * @return int|null Inserted ID or null on failure
     */
    public function create(array $data): ?int {
        $db = Database::connect();
        
        $stmt = $db->prepare("
            INSERT INTO results (user_id, exam_id, score, feedback, weak_topics)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        if (!$stmt) {
            die("SQL PREPARE ERROR (create): " . $db->error);
        }
        
        $studentId = (int)$data['student_id'];
        $examId = (int)$data['exam_id'];
        $score = (int)$data['score'];
        $feedback = $data['feedback'] ?? '';
        $weakTopics = $data['weak_topics'] ?? '[]';
        
        $stmt->bind_param("iiiss", $studentId, $examId, $score, $feedback, $weakTopics);
        
        if ($stmt->execute()) {
            return $db->insert_id;
        }
        
        return null;
    }
    
    /**
     * Check if student is at risk (failed multiple exams)
     * 
     * @param int $studentId Student user ID
     * @return bool True if student should be marked as at risk
     */
    public function checkStudentRisk(int $studentId): bool {
        $db = Database::connect();
        
        // Student is at risk if they have 2+ exams with score < 50
        $stmt = $db->prepare("
            SELECT COUNT(*) as low_score_count
            FROM results
            WHERE user_id = ? AND score < 50
        ");
        
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        
        $row = $stmt->get_result()->fetch_assoc();
        return ($row['low_score_count'] ?? 0) >= 2;
    }
}
