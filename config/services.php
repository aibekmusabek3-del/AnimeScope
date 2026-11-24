<?php
return [
    'shikimori' => [
        'ca_bundle' => env('SHIKIMORI_CA_BUNDLE'),
        'timeout' => (int) env('SHIKIMORI_TIMEOUT', 9),
        'retry_delay_ms' => (int) env('SHIKIMORI_RETRY_DELAY_MS', 1200),
    ],
];
