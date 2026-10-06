<?php

// Standalone worker: no Laravel bootstrap, environment dump, credentials, or shell.
$host = $argv[1] ?? '';
if (! preg_match('/^[a-zA-Z0-9.\-]+$/', $host)) {
    exit(1);
}
$records = @dns_get_record($host, DNS_A | DNS_AAAA);
echo json_encode(array_values(array_unique(array_filter(array_map(fn ($r) => $r['ip'] ?? $r['ipv6'] ?? null, $records ?: [])))));
