<?php
/**
 * FeedbackService - AI-powered personalized study feedback
 * 
 * Module 1: LLM Study Coach
 * Generates personalized study feedback for students based on exam results,
 * including a 7-day action plan, strengths/weaknesses analysis, and study tips.
 */

require_once __DIR__ . "/AIService.php";

class FeedbackService {
    private AIService $ai;
    
    public function __construct() {
        $this->ai = new AIService();
    }
    
    /**
     * Generate personalized AI feedback for a student's exam results
     * 
     * @param array $examData Contains all required fields for feedback generation:
     *   - student_name: string
     *   - exam_title: string
     *   - score: int (0-100)
     *   - correct_count: int
     *   - total_questions: int
     *   - weak_topics: array of strings
     *   - strong_topics: array of strings
     *   - missed_question_types: array of strings
     *   - time_spent_minutes: int
     *   - class_average: int|null (0-100 or null)
     *   - target_score: int|null (0-100 or null)
     * 
     * @return array JSON response matching Module 1 schema
     */
    public function generatePersonalized(array $examData): array {
        // Validate required fields
        $required = ['student_name', 'exam_title', 'score', 'correct_count', 'total_questions'];
        $missing = [];
        foreach ($required as $field) {
            if (!isset($examData[$field])) {
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
        if (!$this->ai->isConfigured()) {
            // Fall back to basic feedback
            return $this->generateBasicResponse($examData);
        }
        
        // Prepare variables for prompt substitution
        $variables = [
            'student_name' => $examData['student_name'],
            'exam_title' => $examData['exam_title'],
            'score' => (int)$examData['score'],
            'correct_count' => (int)$examData['correct_count'],
            'total_questions' => (int)$examData['total_questions'],
            'weak_topics' => $examData['weak_topics'] ?? [],
            'strong_topics' => $examData['strong_topics'] ?? [],
            'missed_question_types' => $examData['missed_question_types'] ?? [],
            'time_spent_minutes' => (int)($examData['time_spent_minutes'] ?? 0),
            'class_average' => $examData['class_average'] ?? null,
            'target_score' => $examData['target_score'] ?? null
        ];
        
        // Generate AI feedback
        $response = $this->ai->generate('feedback', $variables);
        
        // If AI failed, fall back to basic feedback
        if (isset($response['status']) && $response['status'] === 'error') {
            return $this->generateBasicResponse($examData);
        }
        
        return $response;
    }
    
    /**
     * Generate basic feedback without AI (fallback)
     * 
     * @param array $examData Exam data
     * @return array Basic feedback response
     */
    private function generateBasicResponse(array $examData): array {
        $score = (int)$examData['score'];
        $examTitle = $examData['exam_title'] ?? 'Sınav';
        $weakTopics = $examData['weak_topics'] ?? [];
        $strongTopics = $examData['strong_topics'] ?? [];
        
        // Determine diagnosis and confidence
        if ($score >= 90) {
            $diagnosis = "Mükemmel performans! Konulara çok iyi hakimsin.";
            $confidence = "high";
        } elseif ($score >= 70) {
            $diagnosis = "İyi bir performans, ancak bazı konularda iyileştirme yapılabilir.";
            $confidence = "medium";
        } elseif ($score >= 50) {
            $diagnosis = "Ortalama bir performans. Zayıf konulara odaklanman gerekiyor.";
            $confidence = "medium";
        } else {
            $diagnosis = "Geliştirilmesi gereken alanlar var. Düzenli çalışma planı önerilir.";
            $confidence = "high";
        }
        
        // Build basic action plan
        $actionPlan = [];
        for ($day = 1; $day <= 7; $day++) {
            $focus = $day <= 4 ? "Zayıf konuları gözden geçir" : "Genel tekrar ve alıştırma";
            $tasks = [
                "Ders notlarını gözden geçir",
                "Örnek sorular çöz",
                "Öğrenilenleri özetle"
            ];
            
            // Add specific weak topic focus if available
            if (!empty($weakTopics) && $day <= count($weakTopics)) {
                $focus = $weakTopics[$day - 1] . " konusunu çalış";
            }
            
            $actionPlan[] = [
                'day' => $day,
                'focus' => $focus,
                'tasks' => $tasks
            ];
        }
        
        return [
            'status' => 'ok',
            'summary' => [
                'exam_title' => $examTitle,
                'score' => $score,
                'diagnosis' => $diagnosis,
                'confidence' => $confidence
            ],
            'strengths' => array_slice($strongTopics, 0, 4) ?: ["Sınavı tamamladın"],
            'weaknesses' => array_slice($weakTopics, 0, 4) ?: ["Belirli zayıf konu tespit edilmedi"],
            'action_plan_7_days' => $actionPlan,
            'micro_tips' => [
                "Her gün düzenli çalışma alışkanlığı edin",
                "Anlamadığın konuları hemen not al",
                "Çözdüğün soruları tekrar gözden geçir",
                "Kendini test etmeyi ihmal etme"
            ],
            'next_exam_strategy' => [
                'time_management' => ["Soruları önce hızlıca gözden geçir", "Zor sorulara geçmeden önce kolay olanları tamamla"],
                'revision_method' => ["Aktif hatırlama tekniği kullan", "Kendi kendine soru sor"],
                'practice_suggestion' => ["Geçmiş sınav sorularını çöz", "Zaman tutarak alıştırma yap"]
            ],
            'teacher_note' => "Öğrenci düzenli çalışma ile performansını artırabilir."
        ];
    }
    
    /**
     * Legacy method - simple score-based feedback (backward compatibility)
     * 
     * @param int $score Exam score (0-100)
     * @return string Simple feedback text
     */
    public function generate(int $score): string {
        if ($score >= 90) return "Mükemmel! Çok başarılı bir performans gösterdin.";
        if ($score >= 70) return "İyi iş! Eksik kaldığın yerleri gözden geçirerek daha da gelişebilirsin.";
        if ($score >= 50) return "Orta düzey bir performans. Zayıf konulara odaklanarak tekrar dene.";
        return "Geliştirilmesi gereken alanlar var. Lütfen konuları gözden geçir ve daha fazla pratik yap.";
    }
    
    /**
     * Analyze exam details to extract strong and weak topics
     * 
     * @param array $details Grading details from GradingService
     * @return array ['strong_topics' => [...], 'weak_topics' => [...]]
     */
    public function analyzeTopics(array $details): array {
        $topicCorrect = [];
        $topicTotal = [];
        
        foreach ($details as $d) {
            $topic = $d['topic'] ?? 'Genel';
            $topicTotal[$topic] = ($topicTotal[$topic] ?? 0) + 1;
            if (!empty($d['is_correct'])) {
                $topicCorrect[$topic] = ($topicCorrect[$topic] ?? 0) + 1;
            }
        }
        
        $strong = [];
        $weak = [];
        
        foreach ($topicTotal as $topic => $total) {
            $correct = $topicCorrect[$topic] ?? 0;
            $rate = $total > 0 ? ($correct / $total) : 0;
            
            if ($rate >= 0.8) {
                $strong[] = $topic;
            } elseif ($rate < 0.5) {
                $weak[] = $topic;
            }
        }
        
        return [
            'strong_topics' => array_slice($strong, 0, 4),
            'weak_topics' => array_slice($weak, 0, 4)
        ];
    }
}
