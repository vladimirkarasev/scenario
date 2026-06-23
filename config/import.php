<?php

return [
    'chunk_delay_seconds' => (int)env('IMPORT_CHUNK_DELAY_SECONDS', 0),
    'default_chunk_size' => (int)env('IMPORT_DEFAULT_CHUNK_SIZE', 500),
];
