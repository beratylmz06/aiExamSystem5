<?php
require_once __DIR__ . "/../repositories/ExamRepository.php";
require_once __DIR__ . "/AIService.php";

/**
 * GradingService - Exam grading with AI-powered short answer evaluation
 * 
 * Module 3: AI Rubric Grading
 * Grades MCQ, matching, and short answer questions. Uses LLM for
 * rubric-based short answer grading with partial credit.
 */
class GradingService {
    private ?AIService $ai = null;

    public function __construct() {
        $this->ai = new AIService();
    }

    public function grade(int $examId, array $answers, array $matching): array {
        $repo = new ExamRepository();
        $exam = $repo->getExamWithAllData($examId);

        if (!$exam) {
            return ["score" => 0, "details" => []];
        }

        if ($exam["exam_type"] === "matching") {
            return $this->gradeMatching($exam, $matching);
        }

        // mcq and dialog
        return $this->gradeMCQ($exam, $answers);
    }

  
    private function gradeMCQ(array $exam, array $answers): array {
        $details = [];
        $correct = 0;
        $total   = 0;

        foreach ($exam["questions"] as $q) {
            $total++;
            $qid = (int)$q["id"];
            $given = isset($answers[$qid]) ? (string)$answers[$qid] : "";

           
            if ($exam["exam_type"] === "dialog") {

                // dialog sorularında doğru cevap METİN alandır
                $correctAnswer =
                    $q["correct_answer"]
                    ?? $q["answer_text"]
                    ?? $q["correct_text"]
                    ?? "";

                $givenNorm   = mb_strtolower(trim($given));
                $correctNorm = mb_strtolower(trim((string)$correctAnswer));

                $isCorrect = ($givenNorm !== "" && $givenNorm === $correctNorm);

            } else {
                // mcq birebir (A,B,C,D)
                $isCorrect = ($given !== "" && $given === (string)$q["correct_option"]);
            }

            if ($isCorrect) {
                $correct++;
            }

            $details[] = [
                "question_id" => $qid,
                "question_text" => $q["question_text"] ?? "",
                "topic"       => $q["topic"] ?? "General",
                "given"       => $given,
                "correct"     => $exam["exam_type"] === "dialog"
                                    ? ($correctAnswer ?? "")
                                    : (string)$q["correct_option"],
                "is_correct"  => $isCorrect
            ];
        }

        $score = (int)round(($correct / max(1, $total)) * 100);

        return [
            "score"   => $score,
            "details" => $details
        ];
    }

 
    private function gradeMatching(array $exam, array $matching): array {
        $details = [];
        $correct = 0;
        $total   = 0;

        foreach ($exam["questions"] as $q) {
            foreach ($q["pairs"] as $p) {
                $total++;
                $pid = (int)$p["id"];
                $given = isset($matching[$pid]) ? (string)$matching[$pid] : "";

                $isCorrect = (
                    $given !== "" &&
                    $given === (string)$p["right_item"]
                );

                if ($isCorrect) {
                    $correct++;
                }

                $details[] = [
                    "pair_id"    => $pid,
                    "question_text" => $p["left_item"] ?? "",
                    "topic"      => $q["topic"] ?? "General",
                    "left"       => $p["left_item"],
                    "given"      => $given,
                    "correct"    => $p["right_item"],
                    "is_correct" => $isCorrect
                ];
            }
        }

        $score = (int)round(($correct / max(1, $total)) * 100);

        return [
            "score"   => $score,
            "details" => $details
        ];
    }

    /**
     * Grade a short answer question using AI rubric grading
     * 
     * Module 3: AI Rubric Grading
     * 
     * @param array $params Contains:
     *   - exam_title: string
     *   - question: string (the question text)
     *   - expected_answer: string
     *   - rubric: array of criterion objects [
     *       {"criterion": string, "points": int, "what_good_looks_like": string}
     *     ]
     *   - student_answer: string
     *   - max_points: int
     * 
     * @return array JSON response matching Module 3 schema
     */
    public function gradeShortAnswer(array $params): array {
        // Validate required fields
        $required = ['exam_title', 'question', 'expected_answer', 'rubric', 'student_answer', 'max_points'];
        $missing = [];
        foreach ($required as $field) {
            if (!isset($params[$field])) {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            return [
                'status' => 'needs_more_info',
                'missing' => $missing
            ];
        }
        
        // Check if AI is configured
        if (!$this->ai || !$this->ai->isConfigured()) {
            return $this->gradeShortAnswerBasic($params);
        }
        
        // Prepare variables for prompt substitution
        $variables = [
            'exam_title' => $params['exam_title'],
            'question' => $params['question'],
            'expected_answer' => $params['expected_answer'],
            'rubric' => $params['rubric'],
            'student_answer' => $params['student_answer'],
            'max_points' => (int)$params['max_points']
        ];
        
        // Generate AI grading
        $response = $this->ai->generate('grader', $variables);
        
        // If AI failed, fall back to basic grading
        if (isset($response['status']) && $response['status'] === 'error') {
            return $this->gradeShortAnswerBasic($params);
        }
        
        return $response;
    }
    
    /**
     * Basic short answer grading without AI (fallback)
     * Uses simple text similarity comparison
     */
    private function gradeShortAnswerBasic(array $params): array {
        $studentAnswer = mb_strtolower(trim($params['student_answer'] ?? ''));
        $expectedAnswer = mb_strtolower(trim($params['expected_answer'] ?? ''));
        $maxPoints = (int)($params['max_points'] ?? 10);
        $rubric = $params['rubric'] ?? [];
        
        // Check for empty/nonsense answers
        if (empty($studentAnswer) || strlen($studentAnswer) < 3) {
            return [
                'status' => 'ok',
                'grade' => [
                    'score' => 0,
                    'max_points' => $maxPoints,
                    'percentage' => 0.0
                ],
                'criterion_breakdown' => [],
                'feedback' => [
                    'what_was_good' => [],
                    'what_to_improve' => ['Cevap boş veya çok kısa'],
                    'model_answer' => $params['expected_answer'] ?? '',
                    'next_steps' => ['Konuyu tekrar gözden geçirin']
                ],
                'flags' => [
                    'suspected_plagiarism' => false,
                    'nonsense_or_empty' => true,
                    'prompt_injection_attempt' => false
                ]
            ];
        }
        
        // Check for prompt injection attempts
        $injectionPatterns = [
            'ignore rubric', 'ignore previous', 'system prompt',
            'forget instructions', 'new instructions', 'act as',
            'pretend', 'roleplay'
        ];
        $hasInjection = false;
        foreach ($injectionPatterns as $pattern) {
            if (stripos($studentAnswer, $pattern) !== false) {
                $hasInjection = true;
                break;
            }
        }
        
        // Calculate simple similarity score
        similar_text($studentAnswer, $expectedAnswer, $percent);
        $similarityScore = $percent / 100;
        
        // Award points based on similarity
        $awardedScore = (int)round($maxPoints * $similarityScore);
        $percentage = round(($awardedScore / max(1, $maxPoints)) * 100, 1);
        
        // Build criterion breakdown
        $breakdown = [];
        if (!empty($rubric)) {
            foreach ($rubric as $criterion) {
                $criterionPoints = (int)($criterion['points'] ?? 0);
                $awarded = (int)round($criterionPoints * $similarityScore);
                $breakdown[] = [
                    'criterion' => $criterion['criterion'] ?? 'Genel',
                    'awarded' => $awarded,
                    'max' => $criterionPoints,
                    'reason' => 'Otomatik benzerlik değerlendirmesi'
                ];
            }
        } else {
            $breakdown[] = [
                'criterion' => 'Genel Değerlendirme',
                'awarded' => $awardedScore,
                'max' => $maxPoints,
                'reason' => 'Metin benzerliğine dayalı değerlendirme'
            ];
        }
        
        return [
            'status' => 'ok',
            'grade' => [
                'score' => $awardedScore,
                'max_points' => $maxPoints,
                'percentage' => $percentage
            ],
            'criterion_breakdown' => $breakdown,
            'feedback' => [
                'what_was_good' => $similarityScore >= 0.5 
                    ? ['Cevap beklenen içerikle kısmen örtüşüyor'] 
                    : [],
                'what_to_improve' => $similarityScore < 0.7 
                    ? ['Cevabınızı beklenen yanıtla karşılaştırarak geliştirin'] 
                    : [],
                'model_answer' => $params['expected_answer'] ?? '',
                'next_steps' => ['Konuyu tekrar çalışın', 'Model cevabı inceleyin']
            ],
            'flags' => [
                'suspected_plagiarism' => false,
                'nonsense_or_empty' => false,
                'prompt_injection_attempt' => $hasInjection
            ]
        ];
    }

   
    public function analyzeWeakTopics(array $details): array {
        $topicErrors = [];

        foreach ($details as $d) {
            if (isset($d["is_correct"]) && !$d["is_correct"]) {
                $topic = $d["topic"] ?? "General";
                $topicErrors[$topic] = ($topicErrors[$topic] ?? 0) + 1;
            }
        }

        if (empty($topicErrors)) {
            return [];
        }

        arsort($topicErrors);

        
        return array_slice(array_keys($topicErrors), 0, 2);
    }
    
    /**
     * Analyze exam details to find strong topics
     * 
     * @param array $details Grading details
     * @return array List of strong topic names
     */
    public function analyzeStrongTopics(array $details): array {
        $topicCorrect = [];
        $topicTotal = [];

        foreach ($details as $d) {
            $topic = $d["topic"] ?? "General";
            $topicTotal[$topic] = ($topicTotal[$topic] ?? 0) + 1;
            if (!empty($d["is_correct"])) {
                $topicCorrect[$topic] = ($topicCorrect[$topic] ?? 0) + 1;
            }
        }

        $strong = [];
        foreach ($topicTotal as $topic => $total) {
            $correct = $topicCorrect[$topic] ?? 0;
            if ($total > 0 && ($correct / $total) >= 0.8) {
                $strong[] = $topic;
            }
        }

        return array_slice($strong, 0, 4);
    }
}
