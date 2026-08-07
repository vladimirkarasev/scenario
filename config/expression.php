<?php

declare(strict_types=1);

return [
    'rate_limits' => [
        'single_per_second' => (int) env('EXPRESSION_RENDER_SINGLE_PER_SECOND', 20),
        'single_per_minute' => (int) env('EXPRESSION_RENDER_SINGLE_PER_MINUTE', 600),
        'batch_per_second' => (int) env('EXPRESSION_RENDER_BATCH_PER_SECOND', 5),
        'batch_per_minute' => (int) env('EXPRESSION_RENDER_BATCH_PER_MINUTE', 60),
    ],
];
