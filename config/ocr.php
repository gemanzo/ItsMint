<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OCR Configuration for Trading Card Recognition
    |--------------------------------------------------------------------------
    |
    | Configuration settings for the OCR (Optical Character Recognition)
    | system using Google Cloud Vision API to recognize trading cards.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Google Vision API Settings
    |--------------------------------------------------------------------------
    */
    'google_vision' => [
        'enabled' => env('GOOGLE_VISION_ENABLED', false),
        'confidence_threshold' => 80.0,
        'max_requests_per_batch' => 10,
        'timeout' => 30, // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | File Upload Settings
    |--------------------------------------------------------------------------
    */
    'upload' => [
        'max_file_size' => 10 * 1024, // KB (10MB)
        'min_width' => 100,
        'min_height' => 100,
        'allowed_sources' => ['upload', 'camera'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Preprocessing (Lighter for Google Vision)
    |--------------------------------------------------------------------------
    */
    'preprocessing' => [
        'max_width' => 2000, // Google Vision handles optimization internally
        'quality' => 90,
        'temp_prefix' => 'gv_ocr_',
        'format' => 'jpg',
    ],

    /*
    |--------------------------------------------------------------------------
    | Search & Matching Configuration
    |--------------------------------------------------------------------------
    */
    'search' => [
        'min_text_length' => 3,
        'max_results' => 10,
        'expansion_code_limit' => 6,
        'card_name_limit' => 5,
        'default_expansion_prefix' => 'OP01', // For numeric-only codes
    ],

    /*
    |--------------------------------------------------------------------------
    | Confidence Thresholds
    |--------------------------------------------------------------------------
    */
    'confidence' => [
        'expansion_code_match' => 95.0,
        'card_name_threshold' => 70.0,
        'auto_assignment_threshold' => 85.0, // Higher threshold for Google Vision
        'exact_match' => 100.0,
        'contains_match' => 85.0,
        'high_confidence_text' => 80.0, // Google Vision specific
        'max_confidence' => 95.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Expansion Code Patterns
    |--------------------------------------------------------------------------
    |
    | Regex patterns for detecting card expansion codes.
    | Order matters - first match wins.
    |
    */
    'expansion_patterns' => [
        '/\b([A-Z]{2,3}\d{2,3}[-\.]\d{2,3})\b/i',           // OP01-001, ST01-001, OP01.100
        '/\b([A-Z]{2,3}\s?\d{2,3}\s?[-\.]\s?\d{2,3})\b/i',  // OP 01-001, OP01 - 100
        '/\b(OP\d{2,3}[-\.]\d{2,3})\b/i',                   // One Piece specific
        '/\b(ST\d{2,3}[-\.]\d{2,3})\b/i',                   // Starter specific
        '/\b(\d{2,3}[-\.]\d{2,3})\b/',                      // Numeric only
        '/\b(\d{3})\s*$/i',                                  // End-of-text card number
    ],

    /*
    |--------------------------------------------------------------------------
    | Card Information Extraction
    |--------------------------------------------------------------------------
    */
    'extraction' => [
        'min_name_length' => 3,
        'max_name_length' => 50,
        'code_patterns' => [
            'standard' => '/\b[A-Z]{2,4}[\-\s]?\d{1,4}(?:\/\d{1,4})?\b/',
            'numbers' => '/\b\d{1,4}\/\d{1,4}\b/',
            'names' => '/\b[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*\b/',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination Settings
    |--------------------------------------------------------------------------
    */
    'pagination' => [
        'default_per_page' => 15,
        'max_per_page' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Proxy Configuration
    |--------------------------------------------------------------------------
    */
    'image_proxy' => [
        'allowed_domains' => [
            'cardtrader.com',
        ],
        'cache_duration' => 3600, // 1 hour in seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Development & Debugging
    |--------------------------------------------------------------------------
    */
    'debug' => [
        'log_ocr_results' => env('OCR_LOG_RESULTS', false),
        'log_search_results' => env('OCR_LOG_SEARCH', false),
        'log_vision_responses' => env('OCR_LOG_VISION', false),
        'sample_results_count' => 3,
        'save_processed_images' => env('OCR_SAVE_PROCESSED', false),
    ],

];
