<?php

namespace Tests\Feature;

use App\Modules\Shared\Http\OutboundAddressPolicy;
use App\Modules\Shared\Http\SafeHttp;
use App\Modules\Site\Services\SiteControlService;
use App\Modules\Site\Services\SiteSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesPortalRecords;
use Tests\TestCase;

class OutboundSecurityTest extends TestCase
{
    use CreatesPortalRecords, RefreshDatabase;

    private function policy(): OutboundAddressPolicy
    {
        return new class extends OutboundAddressPolicy
        {
            protected function resolve(string $host): array
            {
                return $host === 'private.example' ? ['127.0.0.1'] : ['93.184.216.34'];
            }
        };
    }

    public function test_private_reserved_and_mapped_addresses_are_rejected(): void
    {
        $policy = $this->policy();
        foreach (['127.0.0.1', '10.0.0.1', '172.16.0.1', '192.168.0.1', '169.254.169.254', '100.64.0.1', '224.0.0.1', '::1', 'fe80::1', '::ffff:127.0.0.1', '2002:7f00:1::'] as $ip) {
            $this->assertFalse($policy->isPublic($ip), $ip);
        }
        $this->assertTrue($policy->isPublic('93.184.216.34'));
        $this->assertTrue($policy->isPublic('2606:4700:4700::1111'));
    }

    public function test_redirect_is_revalidated_before_second_request(): void
    {
        Http::fake(['*' => Http::response('', 302, ['Location' => 'http://private.example/secret'])]);
        try {
            (new SafeHttp($this->policy()))->get('https://example.com');
            $this->fail('Private redirect was allowed');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Внутрішні', $e->getMessage());
        }
        Http::assertSentCount(1);
    }

    public function test_control_requires_https_and_never_follows_redirect_with_token(): void
    {
        $http = new SafeHttp($this->policy());
        Http::fake(['*' => Http::response('', 302, ['Location' => 'https://elsewhere.example'])]);
        $http->postControl('https://example.com/api/portal/sync', 'secret', ['state' => 'active']);
        Http::assertSentCount(1);
        $this->expectException(\InvalidArgumentException::class);
        $http->postControl('http://example.com/api/portal/sync', 'secret', []);
    }

    public function test_remote_confirmation_must_match_state_and_version(): void
    {
        $this->app->instance(OutboundAddressPolicy::class, $this->policy());
        $site = $this->site(['remote_control_enabled' => true]);
        $actor = $this->portalUser('pm');
        $site = app(SiteControlService::class)->change($site, $actor, false, 'Планове вимкнення');
        $this->assertSame(1, $site->control_version);
        $attempt = 0;
        Http::fake(function ($request) use ($site, &$attempt) {
            $attempt++;
            if ($attempt === 4) {
                $site->forceFill(['control_version' => 2, 'is_active' => true])->save();
            }

            return Http::response(['status' => $attempt === 1 ? 'active' : 'disabled', 'version' => $attempt === 2 ? 0 : 1]);
        });
        $service = app(SiteSyncService::class);
        $this->assertFalse($service->sync($site, 'disabled')['ok']);
        $this->assertFalse($service->sync($site, 'disabled')['ok']);
        $this->assertTrue($service->sync($site, 'disabled')['ok']);
        $this->assertSame('disabled', $site->fresh()->confirmed_state);
        $this->assertFalse($service->sync($site, 'disabled')['ok']);
        $this->assertSame(2, $site->fresh()->control_version);
        $this->assertSame(1, $site->fresh()->confirmed_control_version);
    }

    public function test_metadata_changes_invalidate_old_confirmations_and_new_target_rotates_key(): void
    {
        $site = $this->site(['site_type' => '3d', 'remote_control_enabled' => true]);
        $site->forceFill(['confirmed_state' => 'active', 'confirmed_control_version' => 0])->save();
        $service = app(\App\Modules\Site\Services\SiteMetadataService::class);
        $site = $service->update($site, ['display_mode' => 'iframe', 'embed_origins' => ['https://client.example']]);
        $this->assertSame(1, $site->control_version);
        $this->assertSame(0, $site->confirmed_control_version);
        // Changing a managed target now requires the current active command to be acknowledged.
        try {
            $service->update($site, ['url' => 'https://new.example']);
            $this->fail('Unconfirmed control version must block detachment.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('remote_control_enabled', $e->errors());
        }
        $site->forceFill(['confirmed_state' => 'active', 'confirmed_control_version' => 1])->save();
        $oldToken = $site->api_token;
        $site = $service->update($site, ['url' => 'https://new.example']);
        $this->assertSame(2, $site->control_version);
        $this->assertFalse($site->remote_control_enabled);
        $this->assertNull($site->confirmed_state);
        $this->assertNotSame($oldToken, $site->api_token);
    }
}
