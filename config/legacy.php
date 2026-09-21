<?php

return [
    'key' => env('LEGACY_APP_KEY'),
    'previous_keys' => array_filter(explode(',', env('LEGACY_APP_PREVIOUS_KEYS', ''))),
];
