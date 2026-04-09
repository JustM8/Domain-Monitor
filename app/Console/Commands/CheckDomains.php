<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Domain;
use App\Models\Setting;
use App\Models\Check;
use App\Services\DomainCheckService;
use App\Mail\DomainReportMail;
use Illuminate\Support\Facades\Mail;

class CheckDomains extends Command
{
    protected $signature = 'domains:check';
    protected $description = 'Check all domains based on individual or global intervals';

    public function handle(DomainCheckService $service)
    {
        $settings = Setting::first();

        $globalInterval = $settings->check_interval ?? 86400;

        Domain::chunk(50, function ($domains) use ($service, $globalInterval) {
            foreach ($domains as $domain) {

                $interval = $domain->check_interval ?: $globalInterval;

                // Перевіряємо, чи настав час для нової перевірки
                if (
                    !$domain->last_checked_at ||
                    $domain->last_checked_at->addSeconds($interval)->isPast()
                ) {
                    $service->check($domain);
                }
            }
        });

        if ($settings && $settings->notification_email) {
            $checks = Check::with('domain')
                ->where('checked_at', '>=', now()->subMinute())
                ->get();

            if ($checks->where('is_success', false)->count() > 0) {
                Mail::to($settings->notification_email)
                    ->send(new DomainReportMail($checks));
            }
        }

        return Command::SUCCESS;
    }
}
