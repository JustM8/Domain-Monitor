<?php

return [
    // Exact origin => exact RFC1918/ULA IP addresses. GET only; never control requests.
    'internal_targets' => json_decode(env('OUTBOUND_INTERNAL_TARGETS', '{}'), true) ?: [],
];
