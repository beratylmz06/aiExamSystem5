<?php
/**
 * ExamController - Exam management and submission with AI feedback
 * 
 * Handles exam listing, creation, and submission with AI-powered
 * personalized feedback generation.
 */

require_once __DIR__ . "/../services/ExamService.php";
require_once __DIR__ . "/../services/GradingService.php";
require_once __DIR__ . "/../services/FeedbackService.php";
require_once __DIR__ . "/../repositories/ResultRepository.php";
require_once __DIR__ . "/../repositories/UserRepository.php";
require_once __DIR__ . "/../repositories/ExamRepository.php";

class ExamController {

    /**
     * Lists exams based on their category
     */
    public function listExamsByType(string $type): array {           
        $svc = new ExamService();                                      
        return $svc->listExamsByType($type);  
    }

    /**
     * Get exam with all related data (questions, options, pairs)
     */
    public function getExamWithAllData(int $examId): ?array {
        $svc = new ExamService();
        return $svc->getExamWithAllData($examId);
    }

    /**
     * Teacher creates an exam
     * Returns exam_id on success, 0 on failure
     */
    public function createExam(array $payload, int $teacherId): int {
        $svc = new ExamService();
        return $svc->createExam($payload, $teacherId);
    }

    /**
     * Submit exam and generate AI-powered personalized feedback
     * 
     * @param int $examId Exam ID
     * @param int $studentId Student user ID
     * @param array $answers MCQ answers [question_id => selected_option]
     * @param array $matching Matching pairs [pair_id => right_item]
     * @param array $studentInfo Additional student info (name, time_spent, etc.)
     * @return array Submission result with AI feedback
     */
    public function submitExam(
        int $examId,
        int $studentId,
        array $answers,
        array $matching,
        array $studentInfo = []
    ): array {

        // Grade the exam
        $gradingService = new GradingService();
        $result = $gradingService->grade($examId, $answers, $matching);

        $score   = $result["score"];
        $details = $result["details"];

        // Analyze topics
        $weakTopics = $gradingService->analyzeWeakTopics($details);
        $strongTopics = $gradingService->analyzeStrongTopics($details);

        // Get exam info for feedback context
        $examRepo = new ExamRepository();
        $exam = $examRepo->getExamBasic($examId);
        $examTitle = $exam['title'] ?? 'Sınav';

        // Get student name
        $userRepo = new UserRepository();
        $student = $userRepo->findById($studentId);
        $studentName = trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
        if (empty($studentName)) {
            $studentName = $studentInfo['student_name'] ?? 'Öğrenci';
        }

        // Calculate class average if available
        $resultRepo = new ResultRepository();
        $classResults = $resultRepo->getClassReport($examId);
        $classAverage = null;
        if (!empty($classResults)) {
            $totalScore = 0;
            foreach ($classResults as $r) {
                $totalScore += (int)$r['score'];
            }
            $classAverage = (int)round($totalScore / count($classResults));
        }

        // Count correct answers
        $correctCount = 0;
        foreach ($details as $d) {
            if (!empty($d['is_correct'])) {
                $correctCount++;
            }
        }
        $totalQuestions = count($details);

        // Build feedback data for AI
        $feedbackData = [
            'student_name' => $studentName,
            'exam_title' => $examTitle,
            'score' => $score,
            'correct_count' => $correctCount,
            'total_questions' => $totalQuestions,
            'weak_topics' => $weakTopics,
            'strong_topics' => $strongTopics,
            'missed_question_types' => $this->extractMissedTypes($details),
            'time_spent_minutes' => (int)($studentInfo['time_spent_minutes'] ?? 0),
            'class_average' => $classAverage,
            'target_score' => $studentInfo['target_score'] ?? null
        ];

        // Generate AI-powered personalized feedback
        $feedbackService = new FeedbackService();
        $aiFeedback = $feedbackService->generatePersonalized($feedbackData);

        // For backward compatibility, also get simple feedback text
        $simpleFeedback = $feedbackService->generate($score);

        // Save result to database
        // Store full AI feedback as JSON, simple feedback as text
        $resultId = $resultRepo->create([
            "student_id"  => $studentId,
            "exam_id"     => $examId,
            "score"       => $score,
            "feedback"    => json_encode($aiFeedback, JSON_UNESCAPED_UNICODE),
            "weak_topics" => json_encode($weakTopics, JSON_UNESCAPED_UNICODE)
        ]);

        // At risk student detection
        if ($resultRepo->checkStudentRisk($studentId)) {
            $userRepo->updateRiskStatus($studentId, "at_risk");
        }

        return [
            "result_id"     => $resultId,
            "score"         => $score,
            "details"       => $details,
            "weak_topics"   => $weakTopics,
            "strong_topics" => $strongTopics,
            "feedback"      => $simpleFeedback,
            "ai_feedback"   => $aiFeedback
        ];
    }

    /**
     * Submit a short answer for AI grading
     * 
     * @param array $params Contains question, expected_answer, rubric, student_answer, max_points
     * @return array AI grading result
     */
    public function gradeShortAnswer(array $params): array {
        $gradingService = new GradingService();
        return $gradingService->gradeShortAnswer($params);
    }

    /**
     * Get detailed AI feedback for a result
     * 
     * @param int $resultId Result ID
     * @param int $studentId Student ID (for authorization)
     * @return array|null AI feedback or null if not found
     */
    public function getAIFeedback(int $resultId, int $studentId): ?array {
        $resultRepo = new ResultRepository();
        $result = $resultRepo->findById($resultId);

        if (!$result || (int)$result['user_id'] !== $studentId) {
            return null;
        }

        $feedback = $result['feedback'] ?? '';
        
        // Try to decode JSON feedback
        $decoded = json_decode($feedback, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // Return simple feedback format
        return [
            'status' => 'ok',
            'summary' => [
                'exam_title' => 'Sınav',
                'score' => (int)$result['score'],
                'diagnosis' => $feedback,
                'confidence' => 'low'
            ],
            'strengths' => [],
            'weaknesses' => json_decode($result['weak_topics'] ?? '[]', true) ?: [],
            'action_plan_7_days' => [],
            'micro_tips' => [],
            'next_exam_strategy' => [
                'time_management' => [],
                'revision_method' => [],
                'practice_suggestion' => []
            ],
            'teacher_note' => ''
        ];
    }

    /**
     * Extract missed question types from grading details
     */
    private function extractMissedTypes(array $details): array {
        $missedTypes = [];
        foreach ($details as $d) {
            if (empty($d['is_correct'])) {
                $topic = $d['topic'] ?? 'General';
                if (!in_array($topic, $missedTypes)) {
                    $missedTypes[] = $topic;
                }
            }
        }
        return array_slice($missedTypes, 0, 5);
    }
}
