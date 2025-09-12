<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'cardtrader' => [
        'api_token' => env('CARDTRADER_API_TOKEN'),
        'api_base_url' => env('CARDTRADER_API_BASE_URL'),
        'one_piece_game_id' => env('CARDTRADER_ONE_PIECE_GAME_ID'),
        'one_piece_single_card_category_id' => env('CARDTRADER_ONE_PIECE_SINGLE_CARD_CATEGORY_ID', 192),
        'one_piece_booster_boxes_category_id' => env('CARDTRADER_ONE_PIECE_BOOSTER_BOXES_CATEGORY_ID', 193),
        'one_piece_boosters_category_id' => env('CARDTRADER_ONE_PIECE_BOOSTERS_CATEGORY_ID', 194),
        'one_piece_starter_decks_category_id' => env('CARDTRADER_ONE_PIECE_STARTER_DECKS_CATEGORY_ID', 195),
        'one_piece_playmats_category_id' => env('CARDTRADER_ONE_PIECE_PLAYMATS_CATEGORY_ID', 196),
        'one_piece_sleeves_category_id' => env('CARDTRADER_ONE_PIECE_SLEEVES_CATEGORY_ID', 197),
        'one_piece_storage_and_deckboxes_category_id' => env('CARDTRADER_ONE_PIECE_STORAGE_AND_DECKBOXES_CATEGORY_ID', 198),
        'one_piece_albums_category_id' => env('CARDTRADER_ONE_PIECE_ALBUMS_CATEGORY_ID', 199),
        'one_piece_bundles_and_sets_category_id' => env('CARDTRADER_ONE_PIECE_BUNDLES_AND_SETS_CATEGORY_ID', 200),
        'one_piece_extra_box_sets_and_displays_category_id' => env('CARDTRADER_ONE_PIECE_EXTRA_BOX_SETS_AND_DISPLAYS_CATEGORY_ID', 201),
        'one_piece_uncut_sheets_category_id' => env('CARDTRADER_ONE_PIECE_UNCUT_SHEETS_CATEGORY_ID', 253),
        'one_piece_don_token_category_id' => env('CARDTRADER_ONE_PIECE_DON_TOKEN_CATEGORY_ID', 255),
        'one_piece_tins_category_id' => env('CARDTRADER_ONE_PIECE_TINS_CATEGORY_ID', 256),
        'one_piece_memorabilia_category_id' => env('CARDTRADER_ONE_PIECE_MEMORABILIA_CATEGORY_ID', 257),
    ],

    'elasticsearch' => [
        'host' => env('ELASTICSEARCH_HOST', 'elasticsearch:9200'),
        'username' => env('ELASTICSEARCH_USERNAME'),
        'password' => env('ELASTICSEARCH_PASSWORD'),
    ],

    'google' => [
        'vision' => [
            'project_id' => env('GOOGLE_CLOUD_PROJECT_ID'),
            'key_path' => env('GOOGLE_APPLICATION_CREDENTIALS'),
            'enabled' => env('GOOGLE_VISION_ENABLED', false),
        ],
    ],

    'cardmarket' => [
        'op_price_guide_url' => env('CARDMARKET_OP_PRICE_GUIDE'),
        'op_price_guide_path' => env('CARDMARKET_OP_PRICE_GUIDE_PATH', storage_path('app/public/cardmarket/price_guide_18.json')),
    ],

];
