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
    protected $description = 'Check all domains';

    public function handle(DomainCheckService $service)
    {
        $settings = Setting::get();

        Domain::chunk(50, function ($domains) use ($service, $settings) {
            foreach ($domains as $domain) {

                $interval = $domain->check_interval ?: $settings->check_interval;

                if (
                    !$domain->last_checked_at ||
                    $domain->last_checked_at->lte(now()->subSeconds($interval))
                ) {
                    $service->check($domain);
                }
            }
        });

        if ($settings->notification_email) {

            $checks = Check::with('domain')
                ->where('checked_at', '>=', now()->subMinute())
                ->get();

            // шлемо тільки якщо є проблеми
            if ($checks->where('is_success', false)->count()) {
                Mail::to($settings->notification_email)
                    ->send(new DomainReportMail($checks));
            }
        }

        return Command::SUCCESS;
    }
}
