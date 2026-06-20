<?php

return [

    'default_code_length' => (int) env('INVITATION_CODE_DEFAULT_LENGTH', 8),

    'min_code_length' => (int) env('INVITATION_CODE_MIN_LENGTH', 4),

    'max_code_length' => (int) env('INVITATION_CODE_MAX_LENGTH', 20),

    'batch_max' => (int) env('INVITATION_CODE_BATCH_MAX', 100),

    'max_uses_limit' => (int) env('INVITATION_CODE_MAX_USES_LIMIT', 1000000),

    'queue_enabled' => (bool) env('INVITATION_CODE_QUEUE_ENABLED', false),

    'queue' => env('INVITATION_CODE_QUEUE', 'invitation-codes'),

    'expired_cleanup_days' => (int) env('INVITATION_CODE_EXPIRED_CLEANUP_DAYS', 30),

    'customer_groups' => [
        'normal' => env('CUSTOMER_GROUP_NORMAL_CODE', 'NORMAL'),
        'silver' => env('CUSTOMER_GROUP_SILVER_CODE', 'SILVER'),
        'gold' => env('CUSTOMER_GROUP_GOLD_CODE', 'GOLD'),
        'diamond' => env('CUSTOMER_GROUP_DIAMOND_CODE', 'DIAMOND'),
    ],

];
