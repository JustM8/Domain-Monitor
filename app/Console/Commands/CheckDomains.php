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
    protected $description = 'Check domains with interval control';

    public function handle(DomainCheckService $service)
    {
        $settings = Setting::first();

        $globalInterval = (int) ($settings->check_interval ?? 86400);

        Domain::chunk(50, function ($domains) use ($service, $globalInterval) {
            foreach ($domains as $domain) {

                $interval = (int) ($domain->check_interval ?: $globalInterval);


                if (!$domain->last_checked_at) {
                    $service->check($domain);
                    continue;
                }


                $secondsSinceLastCheck = now()->diffInSeconds($domain->last_checked_at);


                if ($secondsSinceLastCheck >= $interval) {
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
