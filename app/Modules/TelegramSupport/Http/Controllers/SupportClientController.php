<?php

namespace App\Modules\TelegramSupport\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Models\Company;
use App\Modules\TelegramSupport\Models\SupportClient;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportClientController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->string('search'));

        $clients = SupportClient::query()
            ->with('company')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('full_name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%")
                        ->orWhere('telegram_username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhereHas('company', function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('last_active_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('portal.support.clients.index', [
            'clients' => $clients,
            'search' => $search,
        ]);
    }

    public function add()
    {
        return view('portal.support.clients.add', [
            'client' => new SupportClient(),
            'companies' => Company::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['state'] = 'ready';

        SupportClient::create($data);

        return redirect()->route('portal.support.clients.index')->with('success', __('portal.saved'));
    }

    public function edit(SupportClient $client)
    {
        return view('portal.support.clients.edit', [
            'client' => $client->load('company'),
            'companies' => Company::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, SupportClient $client)
    {
        $data = $this->validateData($request, $client);
        $data['state'] = $client->state;

        $client->update($data);

        return redirect()->route('portal.support.clients.edit', $client)->with('success', __('portal.saved'));
    }

    protected function validateData(Request $request, ?SupportClient $client = null): array
    {
        $clientId = $client?->id;

        return $request->validate([
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')],
            'company_name' => ['nullable', 'string', 'max:255'],
            'telegram_user_id' => [
                'required',
                'string',
                'max:255',
                Rule::unique('support_clients', 'telegram_user_id')->ignore($clientId),
            ],
            'telegram_chat_id' => [
                'required',
                'string',
                'max:255',
                Rule::unique('support_clients', 'telegram_chat_id')->ignore($clientId),
            ],
            'telegram_username' => ['nullable', 'string', 'max:255'],
            'telegram_first_name' => ['nullable', 'string', 'max:255'],
            'telegram_last_name' => ['nullable', 'string', 'max:255'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);
    }
}
