<?php

require_once __DIR__ . "/../repositories/ResultRepository.php";
require_once __DIR__ . "/../services/ReportService.php";
require_once __DIR__ . "/../services/GradingService.php";


class ReportController
{
    
       //TEACHER – CLASS REPORT
       
    public function download(int $examId, string $format): void
    {
        $repo = new ResultRepository();
        $results = $repo->getClassReport($examId);

        $results = $this->injectAI($results);

        $svc = new ReportService();
        $svc->downloadWithAI($examId, $format, $results);
    }

   
      // STUDENT – PERSONAL REPORT
       
    public function downloadStudent(int $examId, int $studentId, string $format): void
    {
        $repo = new ResultRepository();
        $results = $repo->getStudentReport($examId, $studentId);

        if (empty($results)) {
            die("Result not found.");
        }

        $results = $this->injectAI($results);

        $svc = new ReportService();
        $svc->downloadWithAI($examId, $format, $results);
    }

     
    private function injectAI(array $results): array
    {
        $grading = new GradingService();

        foreach ($results as &$r) {
            $details = json_decode($r["details"] ?? "[]", true);

            $r["weak_topics"] = $grading->analyzeWeakTopics($details);
            $r["risk_status"] = ((int)$r["score"] < 50) ? "at_risk" : "normal";
        }
        unset($r);

        return $results;
    }
}
