<?php

namespace Tests\Unit;

use App\Models\Mikrotik;
use Tests\TestCase;

class MikrotikCredentialEncryptionTest extends TestCase
{
    public function test_router_credentials_are_encrypted_on_the_model(): void
    {
        $router = new Mikrotik([
            'username' => 'router-admin',
            'password' => 'router-secret',
        ]);

        $this->assertNotSame('router-admin', $router->getAttributes()['username']);
        $this->assertNotSame('router-secret', $router->getAttributes()['password']);
        $this->assertSame('router-admin', $router->username);
        $this->assertSame('router-secret', $router->password);
    }
}
