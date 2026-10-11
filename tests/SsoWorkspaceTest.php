<?php

use DantePiazza\LaravelAuth\Tests\TestCase;
use DantePiazza\LaravelAuth\Tests\TestUser;

uses(TestCase::class);

// Modelo de prueba que sí implementa las convenciones opcionales de workspace.
class TestUserWithWorkspace extends TestUser
{
    public function getSsoActiveWorkspace(): array
    {
        return ['id' => 1, 'name' => 'Acme'];
    }

    public function getSsoWorkspaces(): array
    {
        return [
            ['id' => 1, 'name' => 'Acme'],
            ['id' => 2, 'name' => 'Beta'],
        ];
    }
}

describe('SSO workspace (opt-in)', function () {

    it('no agrega las claves workspace/workspaces si el modo está desactivado', function () {
        $this->createUser();

        $response = $this->postJson('/v1/users/auth/login', [
            'identity' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonMissingPath('data.workspace')
            ->assertJsonMissingPath('data.workspaces');
    });

    it('no agrega las claves si el modelo no implementa las convenciones', function () {
        config(['laravel-auth.sso.workspace.enabled' => true]);

        $this->createUser();

        $response = $this->postJson('/v1/users/auth/login', [
            'identity' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonMissingPath('data.workspace')
            ->assertJsonMissingPath('data.workspaces');
    });

    it('agrega workspace/workspaces si están habilitados y el modelo los expone', function () {
        config([
            'laravel-auth.sso.workspace.enabled'     => true,
            'laravel-auth.account_types.users.class' => TestUserWithWorkspace::class,
        ]);

        $this->createUser();

        $response = $this->postJson('/v1/users/auth/login', [
            'identity' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.workspace.name', 'Acme')
            ->assertJsonPath('data.workspaces.1.name', 'Beta');
    });

    it('también viaja en el endpoint /auth/current', function () {
        config([
            'laravel-auth.sso.workspace.enabled'     => true,
            'laravel-auth.account_types.users.class' => TestUserWithWorkspace::class,
        ]);

        $user = $this->createUser();

        // Sanctum resuelve Auth::user() por tokenable_type/tokenable_id — el
        // token hay que crearlo desde la clase configurada en
        // account_types.users.class, no desde $user (siempre TestUser en el
        // helper createUser()), o current() resuelve el modelo base sin las
        // convenciones opcionales.
        $token = TestUserWithWorkspace::find($user->id)->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/v1/users/auth/current');

        $response->assertStatus(200)
            ->assertJsonPath('data.workspace.name', 'Acme')
            ->assertJsonPath('data.workspaces.0.name', 'Acme');
    });
});
