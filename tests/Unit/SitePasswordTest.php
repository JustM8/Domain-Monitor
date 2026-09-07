<?php

namespace Tests\Unit;

use App\Modules\Site\Models\Site;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class SitePasswordTest extends TestCase
{
    public function test_it_decrypts_admin_password_stored_with_encrypt_string(): void
    {
        $site = new Site();
        $site->admin_password = Crypt::encryptString('admin-pass');

        $this->assertSame('admin-pass', $site->decryptedAdminPassword());
    }

    public function test_it_keeps_compatibility_with_legacy_encrypt_payloads(): void
    {
        $site = new Site();
        $site->admin_password = Crypt::encrypt('legacy-pass');

        $this->assertSame('legacy-pass', $site->decryptedAdminPassword());
    }
}
