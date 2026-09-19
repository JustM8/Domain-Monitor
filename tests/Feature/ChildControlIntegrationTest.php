<?php

namespace Tests\Feature;

use App\PortalControl\EnforcePortalControl;
use App\PortalControl\PortalControlEndpoint;
use App\PortalControl\PortalControlState;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

require_once __DIR__.'/../../docs/child-site/PortalControlState.php';
require_once __DIR__.'/../../docs/child-site/PortalControlEndpoint.php';
require_once __DIR__.'/../../docs/child-site/EnforcePortalControl.php';

class ChildControlIntegrationTest extends TestCase
{
    private string $statePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->statePath = tempnam(sys_get_temp_dir(), 'portal-child-test-');
        unlink($this->statePath);
        $this->app->instance(PortalControlState::class, new PortalControlState($this->statePath));
        config(['portal_control.site_id' => 42, 'portal_control.token' => str_repeat('s', 60)]);
        Route::middleware(EnforcePortalControl::class)->group(function () {
            Route::get('/test-child', fn () => response('Site content')->header('Content-Security-Policy', "default-src 'self'")->header('X-Frame-Options', 'SAMEORIGIN'));
            Route::post('/api/portal/sync', PortalControlEndpoint::class);
        });
    }

    protected function tearDown(): void
    {
        foreach ([$this->statePath, $this->statePath.'.lock'] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    private function payload(string $state, int $version): array
    {
        return ['site_id' => 42, 'state' => $state, 'version' => $version, 'issued_at' => time(), 'reason' => null, 'display_mode' => 'iframe', 'embed_origins' => ['https://client.example']];
    }

    public function test_disable_restore_stale_commands_and_iframe_headers(): void
    {
        $this->get('/test-child')->assertOk();
        $this->postJson('/api/portal/sync', $this->payload('disabled', 1))->assertUnauthorized();
        $this->withHeader('X-Site-Token', str_repeat('s', 60))->postJson('/api/portal/sync', $this->payload('disabled', 1))->assertOk()->assertJson(['status' => 'disabled', 'version' => 1]);
        $response = $this->get('/test-child')->assertStatus(503)->assertHeaderMissing('X-Frame-Options');
        $this->assertStringContainsString('frame-ancestors https://client.example', $response->headers->get('Content-Security-Policy'));
        $this->postJson('/api/portal/sync', $this->payload('active', 0))->assertStatus(409);
        $this->postJson('/api/portal/sync', $this->payload('active', 1))->assertStatus(409);
        $this->postJson('/api/portal/sync', $this->payload('disabled', 1))->assertOk();
        $this->postJson('/api/portal/sync', $this->payload('active', 2))->assertOk();
        $response = $this->get('/test-child')->assertOk()->assertHeaderMissing('X-Frame-Options');
        $this->assertStringContainsString("default-src 'self'", $response->headers->get('Content-Security-Policy'));
        // Requests to the portal are not part of the middleware's visitor path.
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    public function test_wrong_site_expired_commands_and_corrupt_state_are_rejected(): void
    {
        $this->withHeader('X-Site-Token', str_repeat('s', 60));
        $data = $this->payload('active', 1);
        $data['site_id'] = 43;
        $this->postJson('/api/portal/sync', $data)->assertForbidden();
        $data['site_id'] = 42;
        $data['issued_at'] = time() - 600;
        $this->postJson('/api/portal/sync', $data)->assertStatus(409);
        file_put_contents($this->statePath, '{corrupt');
        $this->get('/test-child')->assertStatus(503);
    }
}
