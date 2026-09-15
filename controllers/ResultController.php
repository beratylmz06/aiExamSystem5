<?php
require_once __DIR__ . "/../services/ResultService.php";

class ResultController {      
    public function submitExam(int $studentId, int $examId, array $answers, array $matching): bool {
        $svc = new ResultService();
        return $svc->submitExam($studentId, $examId, $answers, $matching);
    }
    public function getStudentResult(int $studentId, int $examId): ?array {
        $svc = new ResultService();
        return $svc->getStudentResult($studentId, $examId);
    }

    public function getClassReport(int $examId): array {
        $svc = new ResultService();
        return $svc->getClassReport($examId);
    }
}
