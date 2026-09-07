<?php

namespace App\Console\Commands;

use App\Modules\Ftp\Models\FtpAccount;
use App\Modules\Hosting\Models\Hosting;
use App\Modules\Hosting\Models\HostingAccount;
use App\Modules\Shared\Models\Company;
use App\Modules\Site\Models\Site;
use Illuminate\Console\Command;

class PrunePortalData extends Command
{
    protected $signature = 'portal:prune';
    protected $description = 'Force delete soft deleted portal records older than 21 days';

    public function handle(): int
    {
        $deletedBefore = now()->subDays(21);

        foreach ([Site::class, Company::class, Hosting::class, HostingAccount::class, FtpAccount::class] as $model) {
            $model::onlyTrashed()
                ->where('deleted_at', '<', $deletedBefore)
                ->forceDelete();
        }

        $this->info('Portal data pruned.');

        return self::SUCCESS;
    }
}
