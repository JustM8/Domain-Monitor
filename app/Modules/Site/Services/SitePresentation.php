<?php

namespace App\Modules\Site\Services;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SitePresentation
{
    public function fromRequest(Request $request): array
    {
        $data = $request->validate(['display_mode' => ['nullable', 'in:standalone,iframe'], 'embed_origins_text' => ['nullable', 'string', 'max:3000']]);
        $mode = $data['display_mode'] ?? 'standalone';
        if ($mode === 'iframe' && $request->input('site_type') !== '3d') {
            throw ValidationException::withMessages(['display_mode' => 'iframe доступний для 3D-сайтів.']);
        }
        $origins = [];
        foreach (preg_split('/[\s,]+/', trim($data['embed_origins_text'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $origin) {
            $parts = parse_url($origin);
            if (! filter_var($origin, FILTER_VALIDATE_URL) || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true)
                || isset($parts['user'], $parts['pass']) || isset($parts['user']) || isset($parts['query']) || isset($parts['fragment']) || ! in_array($parts['path'] ?? '', ['', '/'], true)) {
                throw ValidationException::withMessages(['embed_origins_text' => 'Вказуйте origin: https://client.example, без шляху, логіна та параметрів.']);
            }
            $origins[] = rtrim(strtolower($origin), '/');
        }
        if ($mode === 'iframe' && ! $origins) {
            throw ValidationException::withMessages(['embed_origins_text' => 'Для iframe вкажіть хоча б один дозволений origin.']);
        }

        return ['display_mode' => $mode, 'embed_origins' => $mode === 'iframe' ? array_values(array_unique($origins)) : []];
    }
}
