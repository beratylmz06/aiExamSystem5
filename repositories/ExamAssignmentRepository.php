<?php
require_once __DIR__ . "/../config/database.php";

/**
 * ExamAssignmentRepository - Manages exam-to-student assignments
 * 
 * If an exam has assignments, only those students can see the exam.
 * If an exam has no assignments, all students can see it.
 */
class ExamAssignmentRepository {

    /**
     * Assign exam to specific students
     * 
     * @param int $examId Exam ID
     * @param array $studentIds Array of student user IDs
     * @return bool Success status
     */
    public function assignToStudents(int $examId, array $studentIds): bool {
        if (empty($studentIds)) {
            return true; // No students to assign, that's fine
        }

        $db = Database::connect();
        
        $stmt = $db->prepare("INSERT IGNORE INTO exam_assignments (exam_id, student_id) VALUES (?, ?)");
        
        foreach ($studentIds as $studentId) {
            $studentId = (int)$studentId;
            $stmt->bind_param("ii", $examId, $studentId);
            $stmt->execute();
        }
        
        return true;
    }

    /**
     * Check if an exam has any specific assignments
     * 
     * @param int $examId Exam ID
     * @return bool True if exam has specific assignments
     */
    public function hasAssignments(int $examId): bool {
        $db = Database::connect();
        
        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM exam_assignments WHERE exam_id = ?");
        $stmt->bind_param("i", $examId);
        $stmt->execute();
        
        $result = $stmt->get_result()->fetch_assoc();
        return ($result['cnt'] ?? 0) > 0;
    }

    /**
     * Check if a student is assigned to an exam
     * 
     * @param int $examId Exam ID
     * @param int $studentId Student user ID
     * @return bool True if student is assigned or exam has no specific assignments
     */
    public function isStudentAssigned(int $examId, int $studentId): bool {
        // If exam has no assignments, all students can access
        if (!$this->hasAssignments($examId)) {
            return true;
        }

        $db = Database::connect();
        
        $stmt = $db->prepare("SELECT id FROM exam_assignments WHERE exam_id = ? AND student_id = ?");
        $stmt->bind_param("ii", $examId, $studentId);
        $stmt->execute();
        
        return $stmt->get_result()->num_rows > 0;
    }

    /**
     * Get all students assigned to an exam
     * 
     * @param int $examId Exam ID
     * @return array Array of student IDs
     */
    public function getAssignedStudents(int $examId): array {
        $db = Database::connect();
        
        $stmt = $db->prepare("
            SELECT u.id, u.first_name, u.last_name, u.student_number, u.email
            FROM exam_assignments ea
            JOIN users u ON ea.student_id = u.id
            WHERE ea.exam_id = ?
            ORDER BY u.first_name, u.last_name
        ");
        $stmt->bind_param("i", $examId);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Remove all assignments for an exam
     * 
     * @param int $examId Exam ID
     * @return bool Success status
     */
    public function clearAssignments(int $examId): bool {
        $db = Database::connect();
        
        $stmt = $db->prepare("DELETE FROM exam_assignments WHERE exam_id = ?");
        $stmt->bind_param("i", $examId);
        
        return $stmt->execute();
    }

    /**
     * Get exams assigned to a specific student
     * Returns exam IDs that the student is specifically assigned to
     * 
     * @param int $studentId Student user ID
     * @return array Array of exam IDs
     */
    public function getExamsForStudent(int $studentId): array {
        $db = Database::connect();
        
        $stmt = $db->prepare("SELECT exam_id FROM exam_assignments WHERE student_id = ?");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        return array_column($result, 'exam_id');
    }
}
