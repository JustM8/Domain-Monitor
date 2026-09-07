<?php

namespace App\Modules\TelegramSupport\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\TelegramSupport\Models\SupportTopic;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupportSettingsController extends Controller
{
    public function index()
    {
        $topics = SupportTopic::query()
            ->with('responsibleUsers')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $users = User::query()
            ->with('role')
            ->orderBy('full_name')
            ->orderBy('name')
            ->get();

        return view('portal.support.settings', [
            'topics' => $topics,
            'users' => $users,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'topics' => ['required', 'array'],
            'topics.*.id' => ['nullable', 'integer'],
            'topics.*.label' => ['nullable', 'string', 'max:255'],
            'topics.*.telegram_chat_id' => ['nullable', 'string', 'max:255'],
            'topics.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'topics.*.assigned_role' => ['nullable', 'string', 'max:255'],
            'topics.*.is_active' => ['nullable'],
            'topics.*.responsible_user_ids' => ['nullable', 'array'],
            'topics.*.responsible_user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['topics'] as $row) {
                $label = trim((string) Arr::get($row, 'label', ''));
                $chatId = trim((string) Arr::get($row, 'telegram_chat_id', ''));
                $assignedRole = trim((string) Arr::get($row, 'assigned_role', ''));
                $sortOrder = (int) Arr::get($row, 'sort_order', 0);
                $isActive = (bool) Arr::get($row, 'is_active', false);
                $responsibleIds = array_values(array_filter(
                    array_map('intval', Arr::get($row, 'responsible_user_ids', [])),
                    fn (int $value) => $value > 0
                ));
                $topicId = Arr::get($row, 'id');

                if ($label === '' && $chatId === '' && blank($topicId) && ! $isActive && $assignedRole === '' && $responsibleIds === []) {
                    continue;
                }

                if ($label === '') {
                    continue;
                }

                if ($topicId) {
                    $topic = SupportTopic::query()->findOrFail($topicId);
                    $topic->forceFill([
                        'label' => $label,
                        'telegram_chat_id' => $chatId !== '' ? $chatId : null,
                        'sort_order' => $sortOrder,
                        'assigned_role' => $assignedRole !== '' ? $assignedRole : null,
                        'is_active' => $isActive,
                    ])->save();
                } else {
                    $topic = SupportTopic::create([
                        'code' => $this->generateUniqueCode($label),
                        'label' => $label,
                        'telegram_chat_id' => $chatId !== '' ? $chatId : null,
                        'sort_order' => $sortOrder,
                        'assigned_role' => $assignedRole !== '' ? $assignedRole : null,
                        'is_active' => $isActive,
                    ]);
                }

                $topic->responsibleUsers()->sync($responsibleIds);
            }
        });

        return back()->with('success', __('portal.saved'));
    }

    protected function generateUniqueCode(string $label): string
    {
        $base = Str::slug($label) ?: 'support-topic';
        $code = $base;
        $index = 2;

        while (SupportTopic::query()->where('code', $code)->exists()) {
            $code = $base . '-' . $index;
            $index++;
        }

        return $code;
    }
}
