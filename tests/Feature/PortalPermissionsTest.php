<?php

namespace Tests\Feature;

use App\Modules\Shared\Models\Company;
use App\Modules\Shared\Support\PortalAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPortalRecords;
use Tests\TestCase;

class PortalPermissionsTest extends TestCase
{
    use CreatesPortalRecords, RefreshDatabase;

    public function test_role_landing_pages_and_access_matrix(): void
    {
        $paths = ['/portal', '/portal/sites', '/portal/companies', '/portal/statuses', '/portal/ftp', '/portal/hosting', '/portal/hosting-accounts', '/portal/support', '/portal/support/analytics', '/portal/support/clients', '/portal/support/settings', '/portal/users', '/portal/activity', '/portal/monitoring', '/portal/profile'];
        foreach (['admin', 'pm', 'developer', 'manager'] as $role) {
            $user = $this->portalUser($role);
            $this->actingAs($user)->get('/home')->assertRedirect($user->portalHome());
            foreach ($paths as $path) {
                $allowed = in_array($role, ['admin', 'pm'], true)
                    || ($role === 'manager' && (str_starts_with($path, '/portal/support') || $path === '/portal/profile'))
                    || ($role === 'developer' && in_array($path, ['/portal/sites', '/portal/companies', '/portal/statuses', '/portal/ftp', '/portal/hosting', '/portal/hosting-accounts', '/portal/profile'], true));
                if ($allowed) {
                    $this->withoutExceptionHandling();
                } else {
                    $this->withExceptionHandling();
                }
                $this->get($path)->assertStatus($allowed ? 200 : 403);
                $this->withExceptionHandling();
            }
        }
    }

    public function test_manager_navigation_does_not_expose_portal_credentials_or_audit(): void
    {
        $this->actingAs($this->portalUser('manager'))->get('/portal/profile')->assertOk()
            ->assertDontSee('href="http://localhost/portal/sites"', false)
            ->assertDontSee('href="http://localhost/portal/monitoring"', false);
    }

    public function test_active_pm_can_approve_only_other_pending_pm_or_developer(): void
    {
        $pm = $this->portalUser('pm');
        foreach (['pm', 'developer'] as $role) {
            $target = $this->portalUser($role, 'pending');
            $this->actingAs($pm)->post('/portal/users/'.$target->id.'/approve')->assertRedirect();
            $this->assertTrue($target->fresh()->portalIsActive());
        }
        foreach ([$pm, $this->portalUser('admin', 'pending'), $this->portalUser('developer', 'blocked')] as $target) {
            $this->post('/portal/users/'.$target->id.'/approve')->assertForbidden();
        }
        $pending = $this->portalUser('pm', 'pending');
        $this->actingAs($pending)->post('/portal/users/'.$pending->id.'/approve')->assertRedirect('/portal/pending-approval');
        $this->assertFalse($pending->fresh()->portalIsActive());
    }

    public function test_mutation_permissions_are_enforced_on_server_and_company_delete_keeps_sites(): void
    {
        $company = Company::create(['name' => 'Company']);
        $site = $this->site(['company_id' => $company->id]);
        $this->actingAs($this->portalUser('developer'))->post('/portal/companies', ['name' => 'Developer company'])->assertRedirect();
        $this->delete('/portal/sites/'.$site->id)->assertForbidden();
        $this->post('/portal/sites/'.$site->id.'/disable', ['disabled_reason' => 'test'])->assertForbidden();
        $this->delete('/portal/companies/'.$company->id)->assertForbidden();
        $this->actingAs($this->portalUser('pm'))->put('/portal/sites/'.$site->id, ['name' => 'No'])->assertForbidden();
        $this->delete('/portal/companies/'.$company->id)->assertRedirect();
        $this->assertSoftDeleted('companies', ['id' => $company->id]);
        $this->assertNotNull($site->fresh());
        $this->post('/portal/companies/'.$company->id.'/force-delete')->assertForbidden();
    }

    public function test_blocked_accounts_and_admin_email_without_role_cannot_bypass_permissions(): void
    {
        $user = $this->portalUser('developer', 'active', ['email' => 'admin@admin.com']);
        $this->assertFalse($user->isAdmin());
        $this->assertFalse(PortalAccess::allows($user, 'users.write'));
        $this->actingAs($this->portalUser('admin', 'blocked'))->get('/portal/users')->assertRedirect('/portal/pending-approval');
    }

    public function test_iframe_and_business_statuses_are_independent(): void
    {
        $this->seed(\Database\Seeders\StatusSeeder::class);
        $this->assertSame(6, \App\Modules\Shared\Models\Status::count());
        $this->actingAs($this->portalUser('developer'))->post('/portal/sites', [
            'name' => '3D embed', 'url' => 'https://example.com', 'site_type' => '3d', 'environment' => 'prod',
            'display_mode' => 'iframe', 'embed_origins_text' => 'https://client.example',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('sites', ['name' => '3D embed', 'display_mode' => 'iframe', 'environment' => 'prod', 'is_active' => true]);
        $this->post('/portal/sites', ['name' => 'Invalid', 'url' => 'https://example.com', 'site_type' => 'site', 'environment' => 'dev', 'display_mode' => 'iframe'])->assertSessionHasErrors('display_mode');
        $this->post('/portal/sites', ['name' => 'No control', 'url' => 'https://example.com', 'site_type' => 'site', 'environment' => 'dev', 'remote_control_enabled' => 1])->assertSessionHasErrors('remote_control_enabled');
    }
}
