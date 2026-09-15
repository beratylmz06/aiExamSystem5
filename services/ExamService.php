<?php
require_once __DIR__ . "/../repositories/ExamRepository.php";
require_once __DIR__ . "/../services/NotificationService.php";
require_once __DIR__ . "/../repositories/UserRepository.php";

class ExamService {
    public function listExamsByType(string $type): array {
        $repo = new ExamRepository();
        return $repo->listExamsByType($type);
    }

    public function getExamWithAllData(int $examId): ?array {
        $repo = new ExamRepository();
        return $repo->getExamWithAllData($examId);
    }

    // Returns exam_id on success, 0 on failure
    public function createExam(array $payload, int $teacherId): int {
        $title = trim($payload["title"] ?? "");
        $type = $payload["exam_type"] ?? "mcq";
        if ($title === "" || !in_array($type, ["mcq","matching","dialog","true_false"])) return 0;

        $repo = new ExamRepository();
        $examId = $repo->createExam($payload, $teacherId);

        if ($examId > 0) {
            // Send notifications to students
            $userRepo = new UserRepository();
            $ns = new NotificationService();
            
            // Check if specific students are assigned
            $assignmentType = $payload["assignment_type"] ?? "all";
            $assignedStudents = $payload["assigned_students"] ?? [];
            
            if ($assignmentType === "specific" && !empty($assignedStudents)) {
                // Notify only assigned students
                foreach ($assignedStudents as $studentId) {
                    $ns->notify((int)$studentId, "New exam assigned to you: {$title} (".strtoupper($type).")");
                }
            } else {
                // Notify all students
                $students = $userRepo->listByRole("student");
                foreach ($students as $s) {
                    $ns->notify((int)$s["id"], "New exam created: {$title} (".strtoupper($type).")");
                }
            }
        }
        return $examId;
    }
}
