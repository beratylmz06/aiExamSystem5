<?php
/**
 * QuestionController - AI-powered question generation endpoints
 * 
 * Handles teacher requests for generating exam questions using LLM.
 */

require_once __DIR__ . "/../services/QuestionGeneratorService.php";
require_once __DIR__ . "/../services/ExamService.php";

class QuestionController {
    
    /**
     * Generate AI questions without saving
     * 
     * POST /question/generate
     * 
     * @param array $params Request parameters
     * @return array JSON response with generated questions
     */
    public function generate(array $params): array {
        $service = new QuestionGeneratorService();
        return $service->generate($params);
    }
    
    /**
     * Generate AI questions and save to an existing exam
     * 
     * POST /question/generate-and-save
     * 
     * @param int $examId Target exam ID
     * @param array $params Question generation parameters
     * @return array JSON response with save results
     */
    public function generateAndSave(int $examId, array $params): array {
        $service = new QuestionGeneratorService();
        
        // First generate questions
        $generated = $service->generate($params);
        
        if (isset($generated['status']) && $generated['status'] !== 'ok') {
            return $generated;
        }
        
        if (empty($generated['questions'])) {
            return [
                'status' => 'error',
                'message' => 'No questions were generated'
            ];
        }
        
        // Save to exam
        $saveResult = $service->saveToExam($examId, $generated['questions']);
        
        return [
            'status' => $saveResult['success'] ? 'ok' : 'partial',
            'generated_count' => count($generated['questions']),
            'saved_count' => $saveResult['saved_count'],
            'errors' => $saveResult['errors'],
            'questions' => $generated['questions']
        ];
    }
    
    /**
     * Create a new exam with AI-generated questions
     * 
     * POST /question/create-exam
     * 
     * @param array $examData Exam metadata (title, teacher_id)
     * @param array $questionParams Question generation parameters
     * @return array JSON response with new exam details
     */
    public function createExamWithQuestions(array $examData, array $questionParams): array {
        $service = new QuestionGeneratorService();
        
        // First generate questions
        $generated = $service->generate($questionParams);
        
        if (isset($generated['status']) && $generated['status'] !== 'ok') {
            return $generated;
        }
        
        if (empty($generated['questions'])) {
            return [
                'status' => 'error',
                'message' => 'No questions were generated'
            ];
        }
        
        // Create exam with questions
        $result = $service->createExamWithQuestions($examData, $generated['questions']);
        
        return [
            'status' => $result['success'] ? 'ok' : 'error',
            'exam_id' => $result['exam_id'],
            'question_count' => count($generated['questions']),
            'errors' => $result['errors'],
            'questions' => $generated['questions']
        ];
    }
    
    /**
     * Get available generation options (for UI dropdowns)
     * 
     * GET /question/options
     * 
     * @return array Available options for question generation
     */
    public function getOptions(): array {
        return [
            'difficulties' => [
                ['value' => 'easy', 'label' => 'Easy'],
                ['value' => 'medium', 'label' => 'Medium'],
                ['value' => 'hard', 'label' => 'Hard']
            ],
            'question_types' => [
                ['value' => 'mcq', 'label' => 'Multiple Choice'],
                ['value' => 'true_false', 'label' => 'True/False'],
                ['value' => 'short_answer', 'label' => 'Short Answer']
            ],
            'styles' => [
                ['value' => 'university_exam', 'label' => 'Exam'],
                ['value' => 'practice', 'label' => 'Practice'],
                ['value' => 'quiz', 'label' => 'Quiz']
            ],
            'num_questions_range' => ['min' => 1, 'max' => 10],
            'subjects' => [
                'Java OOP',
                'Python',
                'SQL',
                'Data Structures',
                'Algorithms',
                'Web Development',
                'Database Management',
                'Network Fundamentals',
                'Mathematics',
                'Physics'
            ]
        ];
    }
}
