<?php

namespace App\PortalControl;

use RuntimeException;

/** Persistent state, independent of Laravel's disposable application cache. */
class PortalControlState
{
    public function __construct(private ?string $path = null)
    {
        $this->path ??= storage_path('app/portal-control/state.json');
    }

    public function read(): array
    {
        if (! is_file($this->path)) {
            return ['version' => -1, 'state' => 'active', 'reason' => null, 'display_mode' => 'standalone', 'embed_origins' => []];
        }
        $data = json_decode(file_get_contents($this->path), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($data) || ! isset($data['version'], $data['state'])) {
            throw new RuntimeException('Invalid control state');
        }

        return $data;
    }

    public function apply(array $command): array
    {
        $directory = dirname($this->path);
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Cannot create control storage');
        }
        $lock = fopen($this->path.'.lock', 'c');
        if (! $lock || ! flock($lock, LOCK_EX)) {
            throw new RuntimeException('Cannot lock control state');
        }
        try {
            $current = $this->read();
            $next = array_intersect_key($command, array_flip(['version', 'state', 'reason', 'display_mode', 'embed_origins']));
            if ($next['version'] < $current['version']) {
                throw new RuntimeException('Stale command', 409);
            }
            if ($next['version'] === $current['version']) {
                if ($next != $current) {
                    throw new RuntimeException('Conflicting command version', 409);
                }

                return $current;
            }
            // Rename publishes the complete document atomically; readers never see a partially written JSON.
            $temporary = tempnam($directory, 'control-');
            if ($temporary === false) {
                throw new RuntimeException('Cannot create control file');
            }
            try {
                $json = json_encode($next, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                if (file_put_contents($temporary, $json) !== strlen($json) || ! rename($temporary, $this->path)) {
                    throw new RuntimeException('Cannot persist control state');
                }
            } finally {
                if (is_file($temporary)) {
                    unlink($temporary);
                }
            }

            return $next;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
