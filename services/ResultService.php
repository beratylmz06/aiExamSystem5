<?php
require_once __DIR__ . "/../repositories/ResultRepository.php";
require_once __DIR__ . "/../repositories/ExamRepository.php";
require_once __DIR__ . "/../services/GradingService.php";
require_once __DIR__ . "/../services/FeedbackService.php";
require_once __DIR__ . "/../services/NotificationService.php";

class ResultService {

    
    public function submitExam(
        int $studentId,
        int $examId,
        array $answers,
        array $matching
    ): bool {

        $grading = new GradingService();

       
        $graded = $grading->grade($examId, $answers, $matching);

        $score   = (int)($graded["score"] ?? 0);
        $details = json_encode($graded["details"] ?? [], JSON_UNESCAPED_UNICODE);

        // Human readable feedback (
        $feedbackText = (new FeedbackService())->generate($score);

        $repo = new ResultRepository();

       
        $ok = $repo->saveResult(
            $studentId,
            $examId,
            $score,
            $details
        );

        if ($ok) {
            $examRepo = new ExamRepository();
            $exam = $examRepo->getExamBasic($examId);

            $ns = new NotificationService();

            // Student notification
            $ns->notify(
                $studentId,
                "You submitted exam: {$exam['title']} (Score: {$score})"
            );

            // Teacher notification
            if (!empty($exam["created_by"])) {
                $ns->notify(
                    (int)$exam["created_by"],
                    "A student completed exam: {$exam['title']} (Score: {$score})"
                );
            }
        }

        return $ok;
    }

    /* STUDENT – VIEW RESULT */
    public function getStudentResult(int $studentId, int $examId): ?array {
        $repo = new ResultRepository();
        return $repo->getStudentResult($studentId, $examId);
    }

    /* TEACHER – CLASS REPORT */
    public function getClassReport(int $examId): array {
        $repo = new ResultRepository();
        return $repo->getClassReport($examId);
    }
}
