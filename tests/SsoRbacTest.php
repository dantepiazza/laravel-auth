<?php

use DantePiazza\LaravelAuth\Tests\TestCase;
use DantePiazza\LaravelAuth\Tests\TestUser;

uses(TestCase::class);

// Modelo de prueba que sí implementa las convenciones RBAC opcionales.
class TestUserWithRbac extends TestUser
{
    public function getSsoRoles(): array
    {
        return ['admin'];
    }

    public function getSsoPermissions(): array
    {
        return ['manage-infrastructure'];
    }
}

describe('SSO RBAC (opt-in)', function () {

    it('no agrega la clave rbac si el modo está desactivado', function () {
        $this->createUser();

        $response = $this->postJson('/v1/users/auth/login', [
            'identity' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)->assertJsonMissingPath('data.rbac');
    });

    it('no agrega la clave rbac si el modelo no implementa las convenciones', function () {
        config(['laravel-auth.sso.rbac.enabled' => true]);

        $this->createUser();

        $response = $this->postJson('/v1/users/auth/login', [
            'identity' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)->assertJsonMissingPath('data.rbac');
    });

    it('agrega roles y permisos si están habilitados y el modelo los expone', function () {
        config([
            'laravel-auth.sso.rbac.enabled'          => true,
            'laravel-auth.account_types.users.class' => TestUserWithRbac::class,
        ]);

        $this->createUser();

        $response = $this->postJson('/v1/users/auth/login', [
            'identity' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.rbac.roles', ['admin'])
            ->assertJsonPath('data.rbac.permissions', ['manage-infrastructure']);
    });
});
