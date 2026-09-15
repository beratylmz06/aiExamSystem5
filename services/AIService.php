<?php
/**
 * AIService - Core service for LLM API communication
 * 
 * Handles all interactions with external LLM APIs (OpenAI, Gemini, Anthropic)
 * with centralized prompt management, error handling, and retry logic.
 */

class AIService {
    private array $config;
    private string $promptsDir;
    
    public function __construct() {
        $this->config = require __DIR__ . "/../config/ai.php";
        $this->promptsDir = __DIR__ . "/../prompts";
    }
    
    /**
     * Generate AI response using the configured LLM provider
     * 
     * @param string $module Module name (feedback, question_generator, grader)
     * @param array $variables Variables to substitute in the prompt template
     * @return array Parsed JSON response from LLM
     */
    public function generate(string $module, array $variables): array {
        // Load system prompt
        $systemPrompt = $this->loadPromptFile("system_prompt.json");
        if (!$systemPrompt) {
            return $this->errorResponse("System prompt not found");
        }
        
        // Load module-specific prompt
        $modulePromptFile = $this->getPromptFileName($module);
        $modulePrompt = $this->loadPromptFile($modulePromptFile);
        if (!$modulePrompt) {
            return $this->errorResponse("Module prompt not found: {$module}");
        }
        
        // Substitute variables in user prompt template
        $userPrompt = $this->substituteVariables(
            $modulePrompt['user_prompt_template'] ?? '',
            $variables
        );
        
        // Send request to LLM with retry logic
        $response = $this->sendRequest(
            $systemPrompt['content'],
            $userPrompt
        );
        
        return $response;
    }

    /**
     * Generate personalized student feedback
     * 
     * @param array $attemptData Contains score, exam title, questions, answers, etc.
     * @return string|array Feedback text or error
     */
    public function generateFeedback(array $attemptData): string {
        // Load feedback prompt
        $promptConfig = $this->loadPromptFile("feedback_prompt.json");
        if (!$promptConfig) {
            return "Error: Feedback prompt configuration not found.";
        }

        // Prepare variables for substitution
        $variables = [
            'exam_title' => $attemptData['exam_title'],
            'score' => $attemptData['score'],
            'total_score' => 100, // Assuming 100 based on UI
            'correct_count' => $attemptData['correct_count'] ?? 'N/A',
            'wrong_count' => $attemptData['wrong_count'] ?? 'N/A',
            'topics_summary' => $attemptData['topics_summary'] ?? 'Not available',
            'questions_analysis' => $attemptData['questions_analysis'] ?? 'No detailed question data available'
        ];

        // Substitute variables
        $userPrompt = $this->substituteVariables(
            $promptConfig['user_prompt_template'] ?? '',
            $variables
        );

        // Get system prompt
        $systemPrompt = $promptConfig['system_prompt'] ?? "You are a helpful educational AI assistant.";

        // Send to AI
        $response = $this->sendRequest($systemPrompt, $userPrompt);

        if (isset($response['status']) && $response['status'] === 'error') {
            return "AI Error: " . ($response['message'] ?? 'Unknown error');
        }

        // Return the content directly (assuming text response as per user request)
        // If the AI returns JSON wrapped in text, we might need parsing, 
        // but the request asked for text output with sections.
        // The sendRequest returns parsed JSON if the provider returns JSON, 
        // or a structure with 'content' if it's text? 
        // Let's check sendRequest implementation.
        // It returns 'choices'[0]['message']['content'] decoded as JSON if possible.
        // If the user prompt asks for text, parseJsonResponse might fail if it's not JSON.
        // I need to adjust parsing logic or just return the raw text if parsing fails?
        // AIService::sendRequest calls parseJsonResponse which errors if not JSON.
        // I should probably use a raw send method or ensure AIService handles non-JSON.
        // Looking at AIService:
        // parseJsonResponse: "return $this->errorResponse(...)" if invalid JSON.
        // This is a problem if we want text output.
        // I will MODIFY AIService to allow text return if JSON parsing fails, or add a flag.
        
        // For now, let's assume I'll fix AIService parsing logic to be more lenient 
        // OR I will wrap the text in a JSON field in the prompt.
        // "Output JSON schema: { \"feedback_text\": \"...\" }"
        // This is safer.
        
        return $response['feedback_text'] ?? ($response['content'] ?? "Error: No feedback content generated.");
    }
    
    /**
     * Send raw prompts to LLM API (for custom prompts)
     * 
     * @param string $systemPrompt System message content
     * @param string $userPrompt User message content
     * @return array Parsed JSON response
     */
    public function sendRequest(string $systemPrompt, string $userPrompt): array {
        $provider = $this->config['provider'];
        $maxRetries = $this->config['max_retries'] ?? 2;
        
        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = match($provider) {
                    'openai' => $this->sendOpenAI($systemPrompt, $userPrompt),
                    'gemini' => $this->sendGemini($systemPrompt, $userPrompt),
                    'anthropic' => $this->sendAnthropic($systemPrompt, $userPrompt),
                    default => $this->errorResponse("Unknown provider: {$provider}")
                };
                
                // If successful, return
                if (isset($response['status']) && $response['status'] !== 'error') {
                    return $response;
                }
                
                // If it's the last attempt, return the error
                if ($attempt === $maxRetries) {
                    return $response;
                }
                
                // Wait before retry
                usleep(($this->config['retry_delay_ms'] ?? 1000) * 1000);
                
            } catch (Exception $e) {
                if ($attempt === $maxRetries) {
                    return $this->errorResponse($e->getMessage());
                }
                usleep(($this->config['retry_delay_ms'] ?? 1000) * 1000);
            }
        }
        
        return $this->errorResponse("Max retries exceeded");
    }
    
    /**
     * Send request to OpenAI API
     */
    private function sendOpenAI(string $systemPrompt, string $userPrompt): array {
        $url = $this->config['endpoints']['openai'];
        $model = $this->config['models']['openai'];
        
        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt]
            ],
            'max_tokens' => $this->config['max_tokens'],
            'temperature' => $this->config['temperature'],
            'response_format' => ['type' => 'json_object']
        ];
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->config['api_key']
        ];
        
        $response = $this->httpPost($url, $payload, $headers);
        
        if (isset($response['error'])) {
            return $this->errorResponse($response['error']['message'] ?? 'OpenAI API error');
        }
        
        $content = $response['choices'][0]['message']['content'] ?? '';
        return $this->parseJsonResponse($content);
    }
    
    /**
     * Send request to Google Gemini API
     */
    private function sendGemini(string $systemPrompt, string $userPrompt): array {
        $model = $this->config['models']['gemini'];
        $url = str_replace('{model}', $model, $this->config['endpoints']['gemini']);
        $url .= '?key=' . $this->config['api_key'];
        
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $systemPrompt . "\n\n" . $userPrompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'maxOutputTokens' => $this->config['max_tokens'],
                'temperature' => $this->config['temperature']
            ]
        ];
        
        $headers = ['Content-Type: application/json'];
        
        $response = $this->httpPost($url, $payload, $headers);
        
        if (isset($response['error'])) {
            return $this->errorResponse($response['error']['message'] ?? 'Gemini API error');
        }
        
        $content = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';
        return $this->parseJsonResponse($content);
    }
    
    /**
     * Send request to Anthropic Claude API
     */
    private function sendAnthropic(string $systemPrompt, string $userPrompt): array {
        $url = $this->config['endpoints']['anthropic'];
        $model = $this->config['models']['anthropic'];
        
        $payload = [
            'model' => $model,
            'max_tokens' => $this->config['max_tokens'],
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $userPrompt]
            ]
        ];
        
        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . $this->config['api_key'],
            'anthropic-version: 2023-06-01'
        ];
        
        $response = $this->httpPost($url, $payload, $headers);
        
        if (isset($response['error'])) {
            return $this->errorResponse($response['error']['message'] ?? 'Anthropic API error');
        }
        
        $content = $response['content'][0]['text'] ?? '';
        return $this->parseJsonResponse($content);
    }
    
    /**
     * Make HTTP POST request using cURL
     */
    private function httpPost(string $url, array $payload, array $headers): array {
        $ch = curl_init($url);
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $this->config['timeout'] ?? 60,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['error' => ['message' => "cURL error: {$error}"]];
        }
        
        if ($httpCode >= 400) {
            $decoded = json_decode($response, true);
            return ['error' => ['message' => $decoded['error']['message'] ?? "HTTP {$httpCode}"]];
        }
        
        return json_decode($response, true) ?? [];
    }
    
    /**
     * Parse JSON response from LLM, handling potential formatting issues
     */
    private function parseJsonResponse(string $content): array {
        // Clean up content - remove markdown code blocks if present
        $content = trim($content);
        $content = preg_replace('/^```json\s*/i', '', $content);
        $content = preg_replace('/```\s*$/', '', $content);
        $content = trim($content);
        
        $decoded = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // If not JSON, return clear text wrapped in an array
            return ['content' => $content];
        }
        
        return $decoded;
    }
    
    /**
     * Load prompt file from prompts directory
     */
    private function loadPromptFile(string $filename): ?array {
        $path = $this->promptsDir . "/" . $filename;
        
        if (!file_exists($path)) {
            return null;
        }
        
        $content = file_get_contents($path);
        return json_decode($content, true);
    }
    
    /**
     * Get prompt filename for given module
     */
    private function getPromptFileName(string $module): string {
        return match($module) {
            'feedback' => 'feedback_prompt.json',
            'question_generator' => 'question_generator_prompt.json',
            'grader' => 'grader_prompt.json',
            default => "{$module}_prompt.json"
        };
    }
    
    /**
     * Substitute {{variable}} placeholders in template
     */
    private function substituteVariables(string $template, array $variables): string {
        foreach ($variables as $key => $value) {
            // Handle arrays/objects by encoding to JSON
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            } elseif (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif (is_null($value)) {
                $value = 'null';
            }
            
            $template = str_replace("{{{$key}}}", (string)$value, $template);
        }
        
        return $template;
    }
    
    /**
     * Create standardized error response
     */
    private function errorResponse(string $message): array {
        return [
            'status' => 'error',
            'message' => $message
        ];
    }
    
    /**
     * Check if API is properly configured
     */
    public function isConfigured(): bool {
        $apiKey = $this->config['api_key'] ?? '';
        return !empty($apiKey) && $apiKey !== 'your-api-key-here';
    }
    
    /**
     * Get current provider name
     */
    public function getProvider(): string {
        return $this->config['provider'] ?? 'unknown';
    }
}
