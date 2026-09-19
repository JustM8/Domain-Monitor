<?php

namespace App\Modules\Monitoring\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MonitoringOptions
{
    public function fromRequest(Request $request): array
    {
        $data = $request->validate([
            'monitoring_enabled' => ['sometimes', 'boolean'],
            'monitoring_interval' => ['sometimes', 'required', 'integer', 'between:1,60'],
            'monitoring_timeout' => ['sometimes', 'required', 'integer', 'between:2,10'],
            'monitoring_failure_threshold' => ['sometimes', 'required', 'integer', 'between:1,5'],
            'monitoring_status_codes' => ['nullable', 'string', 'max:100'],
            'monitoring_url' => ['nullable', 'url:http,https', 'max:255'],
            'monitoring_content' => ['nullable', 'string', 'max:255'],
            'monitoring_recipient_ids' => ['sometimes', 'array', 'max:100'],
            'monitoring_recipient_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ]);
        if (array_key_exists('monitoring_status_codes', $data)) {
            $codes = trim($data['monitoring_status_codes'] ?? '');
            if ($codes !== '' && ! preg_match('/^[1-5][0-9]{2}(?:\s*,\s*[1-5][0-9]{2})*$/', $codes)) {
                throw ValidationException::withMessages(['monitoring_status_codes' => 'Вкажіть HTTP-коди через кому, наприклад 200,204.']);
            }
            $data['monitoring_status_codes'] = $codes === '' ? null : array_values(array_unique(array_map('intval', explode(',', $codes))));
        }
        if (array_key_exists('monitoring_recipient_ids', $data)) {
            abort_unless($request->user()->canPortal('monitoring.write'), 403);
            $ids = array_map('intval', $data['monitoring_recipient_ids']);
            foreach (User::with('role')->whereIn('id', $ids)->get() as $user) {
                if (! $user->canPortal('monitoring.read') || ! $user->canUseAccessBot() || ! $user->telegramIsLinked()) {
                    throw ValidationException::withMessages(['monitoring_recipient_ids' => 'Оберіть активних Admin/PM із підключеним Access-ботом.']);
                }
            }
            $data['monitoring_recipient_ids'] = $ids ?: null;
        }

        return $data;
    }
}
