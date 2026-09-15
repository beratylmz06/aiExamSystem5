<?php
require_once __DIR__ . "/../repositories/ExamRepository.php";
 // Generate and download class report
class ReportService
{
    /**
     * Download student's AI feedback report
     * Contains: exam name, correct/wrong counts, pass/fail status, AI feedback text
     */
    public function downloadWithAI(int $examId, string $format, array $rows): void
    {
        $format = strtolower($format);
        if (!in_array($format, ["pdf", "word"])) {
            $format = "pdf";
        }

        $examRepo = new ExamRepository();
        $exam = $examRepo->getExamBasic($examId);
        if (!$exam) {
            die("Exam not found.");
        }
        
        // Get first result (student's result)
        $r = $rows[0] ?? null;
        if (!$r) {
            die("Result not found.");
        }
        
        // Get exam thresholds
        $passingGrade = $exam['score_threshold_high'] ?? 70;
        
        // Get questions to calculate correct/wrong counts
        require_once __DIR__ . "/../config/database.php";
        $db = Database::connect();
        
        // Count total questions
        $stmtQ = $db->prepare("SELECT COUNT(*) as cnt FROM questions WHERE exam_id = ?");
        $stmtQ->bind_param("i", $examId);
        $stmtQ->execute();
        $totalQuestions = (int)($stmtQ->get_result()->fetch_assoc()['cnt'] ?? 0);
        
        // Calculate correct/wrong based on score
        $score = (int)$r['score'];
        $correctCount = (int)round(($score / 100) * $totalQuestions);
        $wrongCount = $totalQuestions - $correctCount;
        
        // Determine pass/fail
        $passed = $score >= $passingGrade;
        $passFailText = $passed ? "PASSED" : "FAILED";
        $passFailColor = $passed ? "green" : "red";
        
        // Get AI Feedback
        $aiFeedbackText = $r['ai_feedback'] ?? '';
        if (empty($aiFeedbackText) && !empty($r['feedback'])) {
            $decoded = json_decode($r['feedback'], true);
            if (is_array($decoded) && isset($decoded['feedback_text'])) {
                $aiFeedbackText = $decoded['feedback_text'];
            }
        }
        
        // Format AI feedback for HTML
        $aiFeedbackHtml = '';
        if (!empty($aiFeedbackText)) {
            $aiFeedbackHtml = nl2br(htmlspecialchars($aiFeedbackText));
        } else {
            $aiFeedbackHtml = "<em>No AI feedback available. Generate feedback first.</em>";
        }
        
        $studentName = htmlspecialchars(trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')));
        $examTitle = htmlspecialchars($exam['title'] ?? 'Exam');
        
        $html = "
        <html>
        <head>
            <meta charset='utf-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; }
                h1 { color: #333; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
                .info-box { background: #f5f5f5; padding: 15px; border-radius: 8px; margin: 20px 0; }
                .info-row { margin: 8px 0; }
                .label { font-weight: bold; color: #555; }
                .score-box { text-align: center; padding: 20px; margin: 20px 0; }
                .score { font-size: 48px; font-weight: bold; color: {$passFailColor}; }
                .status { font-size: 24px; color: {$passFailColor}; font-weight: bold; }
                .stats { display: flex; justify-content: center; gap: 40px; margin: 20px 0; }
                .stat-item { text-align: center; }
                .stat-number { font-size: 32px; font-weight: bold; }
                .correct { color: green; }
                .wrong { color: red; }
                .ai-section { margin-top: 30px; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
                .ai-title { font-size: 18px; font-weight: bold; margin-bottom: 15px; color: #667eea; }
            </style>
        </head>
        <body>
            <h1>Exam Result Report</h1>
            
            <div class='info-box'>
                <div class='info-row'><span class='label'>Student:</span> {$studentName}</div>
                <div class='info-row'><span class='label'>Exam:</span> {$examTitle}</div>
                <div class='info-row'><span class='label'>Passing Grade:</span> {$passingGrade}/100</div>
            </div>
            
            <div class='score-box'>
                <div class='score'>{$score}/100</div>
                <div class='status'>{$passFailText}</div>
            </div>
            
            <table border='0' width='100%' style='text-align: center;'>
                <tr>
                    <td style='width: 50%;'>
                        <div style='font-size: 32px; font-weight: bold; color: green;'>{$correctCount}</div>
                        <div>Correct Answers</div>
                    </td>
                    <td style='width: 50%;'>
                        <div style='font-size: 32px; font-weight: bold; color: red;'>{$wrongCount}</div>
                        <div>Wrong Answers</div>
                    </td>
                </tr>
            </table>
            
            <div class='ai-section'>
                <div class='ai-title'>AI Performance Analysis</div>
                <div>{$aiFeedbackHtml}</div>
            </div>
            
        </body>
        </html>";

        $this->export($html, $format, "exam_report_{$examId}");
    }
     // Generate and download individual student report
    public function downloadStudentWithAI(int $examId, array $r, string $format): void
    {
        $format = strtolower($format);
        if (!in_array($format, ["pdf", "word"])) {
            $format = "pdf";
        }

        $riskLabel = (($r["risk_status"] ?? "") === "at_risk")
            ? "<p style='color:red;font-weight:bold'>AT RISK STUDENT</p>"
            : "";

        $weeklyTopic = $this->generateWeeklyTopic($r["score"]);

        $html = "
        <html>
        <head><meta charset='utf-8'></head>
        <body>
        <h1>Exam Report</h1>

        <p>
            <b>Student:</b> " . htmlspecialchars($r["full_name"]) . "<br>
            <b>Exam:</b> " . htmlspecialchars($r["exam_title"]) . "<br>
            <b>Score:</b> {$r["score"]}/100
        </p>

        <h3>Weekly Recommendation</h3>
        <ul><li>{$weeklyTopic}</li></ul>

        {$riskLabel}
        </body>
        </html>";

        $this->export($html, $format, "my_report_{$examId}");
    }

    private function buildClassHtml(array $exam, array $rows, int $avg): string
    {
        $tableRows = "";

        foreach ($rows as $r) {
            $name = htmlspecialchars(trim(($r["first_name"] ?? "") . " " . ($r["last_name"] ?? "")));

            $risk = (($r["risk_status"] ?? "") === "at_risk")
                ? "<br><span style='color:red;font-weight:bold;'>AT RISK STUDENT</span>"
                : "";

            $weeklyTopic = $this->generateWeeklyTopic($r["score"]);

            $tableRows .= "
            <tr>
                <td>{$name}{$risk}</td>
                <td>{$r["score"]}/100</td>
                <td><ul><li>{$weeklyTopic}</li></ul></td>
            </tr>";
        }

        return "
        <html>
        <head><meta charset='utf-8'></head>
        <body>

        <h1>Class Report</h1>
        <p><b>Average:</b> {$avg}/100</p>

        <table border='1' width='100%' cellpadding='6'>
            <tr>
                <th>Student</th>
                <th>Score</th>
                <th>Weekly Topic</th>
            </tr>
            {$tableRows}
        </table>

        </body>
        </html>";
    }

    
    private function generateWeeklyTopic(int $score): string
    {
        if ($score >= 100) {
            return "No deficiencies – Excellent performance";
        } elseif ($score >= 67) {
            return "General Revision";
        } elseif ($score >= 34) {
            return "General Knowledge";
        } else {
            return "General Knowledge & Previous Topics";
        }
    }

    private function export(string $html, string $format, string $filename): void
    {
        if ($format === "word") {
            header("Content-Type: application/msword; charset=utf-8");
            header("Content-Disposition: attachment; filename={$filename}.doc");
            echo $html;
            exit;
        }

        require_once __DIR__ . "/../vendor/autoload.php";
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->render();

        header("Content-Type: application/pdf");
        header("Content-Disposition: attachment; filename={$filename}.pdf");
        echo $dompdf->output();
        exit;
    }
}
