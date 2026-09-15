<?php
/**
 * QuestionGeneratorService - AI-powered question generation
 * 
 * Module 2: AI Question Bank
 * Generates exam questions for teachers using LLM, supporting
 * MCQ, true/false, and short answer question types.
 */

require_once __DIR__ . "/AIService.php";
require_once __DIR__ . "/../repositories/ExamRepository.php";
require_once __DIR__ . "/../repositories/ExamAssignmentRepository.php";

class QuestionGeneratorService {
    private AIService $ai;
    
    public function __construct() {
        $this->ai = new AIService();
    }
    
    /**
     * Generate exam questions using AI
     * 
     * @param array $params Contains:
     *   - subject: string (e.g., "Java OOP", "Calculus", "SQL")
     *   - topic: string
     *   - difficulty: string (easy|medium|hard)
     *   - question_type: string (mcq|true_false|short_answer)
     *   - num_questions: int (1-10)
     *   - constraints: array (optional) {
     *       avoid_topics: array,
     *       language: string,
     *       time_limit_minutes: int,
     *       style: string,
     *       needs_explanations: bool
     *     }
     * 
     * @return array JSON response matching Module 2 schema
     */
    public function generate(array $params): array {
        // Validate required fields
        $required = ['subject', 'topic', 'difficulty', 'question_type', 'num_questions'];
        $missing = [];
        foreach ($required as $field) {
            if (empty($params[$field])) {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            return [
                'status' => 'needs_more_info',
                'missing' => $missing
            ];
        }
        
        // Validate enum values
        $validDifficulty = ['easy', 'medium', 'hard'];
        $validTypes = ['mcq', 'true_false', 'short_answer'];
        
        if (!in_array($params['difficulty'], $validDifficulty)) {
            return [
                'status' => 'error',
                'message' => "Invalid difficulty. Must be: " . implode(', ', $validDifficulty)
            ];
        }
        
        if (!in_array($params['question_type'], $validTypes)) {
            return [
                'status' => 'error',
                'message' => "Invalid question_type. Must be: " . implode(', ', $validTypes)
            ];
        }
        
        // Validate num_questions
        $numQuestions = (int)$params['num_questions'];
        if ($numQuestions < 1 || $numQuestions > 10) {
            return [
                'status' => 'error',
                'message' => "num_questions must be between 1 and 10"
            ];
        }
        
        // Check if AI is configured
        if (!$this->ai->isConfigured()) {
            return [
                'status' => 'error',
                'message' => 'AI service not configured. Please set API key in config/ai.php'
            ];
        }
        
        // Prepare constraints with defaults
        $constraints = $params['constraints'] ?? [];
        $variables = [
            'subject' => $params['subject'],
            'topic' => $params['topic'],
            'difficulty' => $params['difficulty'],
            'question_type' => $params['question_type'],
            'num_questions' => $numQuestions,
            'avoid_topics' => $constraints['avoid_topics'] ?? [],
            'language' => $constraints['language'] ?? 'tr',
            'time_limit_minutes' => $constraints['time_limit_minutes'] ?? 30,
            'style' => $constraints['style'] ?? 'university_exam',
            'needs_explanations' => $constraints['needs_explanations'] ?? true
        ];
        
        // Generate questions via AI
        $response = $this->ai->generate('question_generator', $variables);
        
        return $response;
    }
    
    /**
     * Save AI-generated questions to an existing exam
     * 
     * @param int $examId Target exam ID
     * @param array $questions Array of question objects from AI response
     * @return array ['success' => bool, 'saved_count' => int, 'errors' => array]
     */
    public function saveToExam(int $examId, array $questions): array {
        $repo = new ExamRepository();
        $savedCount = 0;
        $errors = [];
        
        foreach ($questions as $index => $q) {
            $questionData = $this->transformToDbFormat($q);
            
            if ($questionData === null) {
                $errors[] = "Question " . ($index + 1) . ": Invalid format";
                continue;
            }
            
            $questionData['exam_id'] = $examId;
            
            $success = $repo->addQuestion($questionData);
            if ($success) {
                $savedCount++;
            } else {
                $errors[] = "Question " . ($index + 1) . ": Database save failed";
            }
        }
        
        return [
            'success' => empty($errors),
            'saved_count' => $savedCount,
            'errors' => $errors
        ];
    }
    
    /**
     * Transform AI-generated question to database format
     * 
     * @param array $question AI-generated question object
     * @return array|null Database-compatible question array
     */
    private function transformToDbFormat(array $question): ?array {
        if (empty($question['prompt']) || empty($question['type'])) {
            return null;
        }
        
        $type = $question['type'];
        
        $data = [
            'question_text' => $question['prompt'],
            'topic' => implode(', ', $question['tags'] ?? ['General'])
        ];
        
        // Handle different question types
        if ($type === 'mcq') {
            $choices = $question['choices'] ?? [];
            foreach ($choices as $choice) {
                $key = strtolower($choice['key'] ?? '');
                if ($key === 'a') $data['option_a'] = $choice['text'];
                if ($key === 'b') $data['option_b'] = $choice['text'];
                if ($key === 'c') $data['option_c'] = $choice['text'];
                if ($key === 'd') $data['option_d'] = $choice['text'];
            }
            $data['correct_option'] = strtoupper($question['answer_key'] ?? '');
            
        } elseif ($type === 'true_false') {
            $data['option_a'] = 'True';
            $data['option_b'] = 'False';
            $data['option_c'] = null;
            $data['option_d'] = null;
            $answerKey = strtolower($question['answer_key'] ?? '');
            $data['correct_option'] = $answerKey === 'true' ? 'A' : 'B';
            
        } elseif ($type === 'short_answer') {
            // For short answer/dialog type, store expected answer
            $data['option_a'] = null;
            $data['option_b'] = null;
            $data['option_c'] = null;
            $data['option_d'] = null;
            $data['correct_option'] = $question['answer_key'] ?? '';
        }
        
        return $data;
    }
    
    /**
     * Create a new exam with AI-generated questions
     * 
     * @param array $examData Exam metadata (title, exam_type, teacher_id, assignment_type, assigned_students)
     * @param array $questions AI-generated questions
     * @return array ['success' => bool, 'exam_id' => int|null, 'errors' => array]
     */
    public function createExamWithQuestions(array $examData, array $questions): array {
        $repo = new ExamRepository();
        
        // Create exam first
        $examPayload = [
            'title' => $examData['title'] ?? 'AI Generated Exam',
            'exam_type' => $this->mapQuestionTypeToExamType($questions[0]['type'] ?? 'mcq'),
            'score_threshold_high' => $examData['score_threshold_high'] ?? 70,
            'score_threshold_medium' => $examData['score_threshold_medium'] ?? 50
        ];
        
        $teacherId = (int)($examData['teacher_id'] ?? 0);
        if ($teacherId <= 0) {
            return [
                'success' => false,
                'exam_id' => null,
                'errors' => ['teacher_id is required']
            ];
        }
        
        // Build full exam structure
        $examPayload['questions'] = [];
        foreach ($questions as $q) {
            $examPayload['questions'][] = $this->transformToDbFormat($q);
        }
        
        $examId = $repo->createExam($examPayload, $teacherId);
        
        if ($examId > 0) {
            // Handle student assignments
            if (isset($examData['assignment_type'])) {
                $assignmentType = $examData['assignment_type'];
                
                if ($assignmentType === 'specific' && !empty($examData['assigned_students'])) {
                    $assignmentRepo = new ExamAssignmentRepository();
                    $assignmentRepo->assignToStudents($examId, $examData['assigned_students']);
                }
                // If 'all', no assignments are created - exam is available to everyone
            }
            
            return [
                'success' => true,
                'exam_id' => $examId,
                'errors' => []
            ];
        }
        
        return [
            'success' => false,
            'exam_id' => null,
            'errors' => ['Failed to create exam']
        ];
    }
    
    /**
     * Map question type to exam type
     */
    private function mapQuestionTypeToExamType(string $questionType): string {
        return match($questionType) {
            'multiple_choice' => 'mcq',
            'true_false' => 'true_false',  // True/False questions saved as separate category
            'short_answer' => 'dialog',
            default => 'mcq'
        };
    }
}
