<?php
/**
 * AI/LLM Configuration
 * 
 * SECURITY: Add this file to .gitignore and use environment variables for API keys
 */

return [
    // Provider: openai | gemini | anthropic | custom
    'provider' => getenv('AI_PROVIDER') ?: 'openai',
    
    // API Key - prefer environment variable
    'api_key' => getenv('AI_API_KEY') ?: '',
    
    // Model selection per provider
    'models' => [
        'openai' => 'gpt-4o-mini',
        'gemini' => 'gemini-1.5-flash',
        'anthropic' => 'claude-3-5-sonnet-20241022',
    ],
    
    // API endpoints per provider
    'endpoints' => [
        'openai' => 'https://api.openai.com/v1/chat/completions',
        'gemini' => 'https://generativelanguage.googleapis.com/v1/models/{model}:generateContent',
        'anthropic' => 'https://api.anthropic.com/v1/messages',
    ],
    
    // Request settings
    'timeout' => 60,
    'max_tokens' => 4096,
    'temperature' => 0.7,
    
    // Retry settings
    'max_retries' => 2,
    'retry_delay_ms' => 1000,
    
    // Default language for AI responses
    'default_language' => 'tr', // Turkish
];
