<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OCR Configuration for Trading Card Recognition
    |--------------------------------------------------------------------------
    |
    | Configuration settings for the OCR (Optical Character Recognition)
    | system used to recognize trading cards from uploaded images.
    |
    */

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
    | Image Preprocessing
    |--------------------------------------------------------------------------
    */
    'preprocessing' => [
        'target_width' => 1000,
        'min_width_for_scale_up' => 500,
        'scale_up_target' => 800,
        'brightness' => 15,
        'contrast' => 40,
        'sharpen' => 15,
        'quality' => 100,
        'temp_prefix' => 'card_ocr_',
        'format' => 'png',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tesseract OCR Configuration
    |--------------------------------------------------------------------------
    */
    'tesseract' => [
        'language' => 'eng',
        'psm_modes' => [11, 3, 7], // Sparse text, Default segmentation, Single line
        'timeout' => 30, // seconds
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
        'auto_assignment_threshold' => 80.0,
        'exact_match' => 100.0,
        'contains_match' => 85.0,
        'base_ocr_confidence' => 50.0,
        'max_confidence' => 95.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | OCR Confidence Boosters
    |--------------------------------------------------------------------------
    */
    'confidence_boosters' => [
        'long_text_20_chars' => 10,
        'long_text_50_chars' => 10,
        'title_case_words' => 15,
        'contains_numbers' => 5,
        'special_chars_penalty' => 20,
        'special_chars_threshold' => 0.3, // 30% special chars triggers penalty
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
        'sample_results_count' => 3,
        'save_processed_images' => env('OCR_SAVE_PROCESSED', false),
    ],

];
