<?php

namespace Tests\Feature;

use App\Modules\Ftp\Models\FtpAccount;
use App\Modules\Hosting\Models\Hosting;
use App\Modules\Hosting\Models\HostingAccount;
use App\Modules\Shared\Models\Company;
use App\Modules\Shared\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\Concerns\CreatesPortalRecords;
use Tests\TestCase;

class PortalDetailsTest extends TestCase
{
    use CreatesPortalRecords, RefreshDatabase;

    public function test_populated_details_render_for_admin_pm_and_developer(): void
    {
        $this->withoutExceptionHandling();
        $this->seed(\Database\Seeders\StatusSeeder::class);
        $company = Company::create(['name' => 'Company']);
        $site = $this->site(['company_id' => $company->id, 'status_id' => Status::first()->id, 'admin_password' => Crypt::encryptString('test-password')]);
        $hosting = Hosting::create(['name' => 'Hosting', 'provider' => 'Test']);
        $account = HostingAccount::create(['hosting_id' => $hosting->id, 'company_id' => $company->id, 'title' => 'Account', 'password' => Crypt::encryptString('account-password')]);
        $ftp = FtpAccount::create(['company_id' => $company->id, 'host' => 'ftp.example.com', 'password' => Crypt::encryptString('ftp-password')]);
        $site->ftpAccounts()->attach($ftp);
        $site->hostingAccounts()->attach($account);
        foreach (['admin', 'pm', 'developer'] as $role) {
            $this->actingAs($this->portalUser($role));
            foreach (['/portal/sites/'.$site->id, '/portal/companies/'.$company->id.'/edit', '/portal/ftp/'.$ftp->id.'/edit', '/portal/hosting/'.$hosting->id.'/edit', '/portal/hosting-accounts/'.$account->id.'/edit', '/portal/statuses/'.Status::first()->id.'/edit'] as $path) {
                $this->get($path)->assertOk();
            }
            if ($role !== 'developer') {
                $this->get('/portal/monitoring/sites/'.$site->id)->assertRedirect('/portal/monitoring/monitors/'.$site->primaryMonitor->id);
                $this->get('/portal/monitoring/monitors/'.$site->primaryMonitor->id)->assertOk();
            }
        }
    }
}
