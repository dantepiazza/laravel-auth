<?php

use DantePiazza\LaravelAuth\Tests\TestCase;
use DantePiazza\LaravelAuth\Tests\TestUser;
use Illuminate\Support\Facades\Mail;

uses(TestCase::class);

describe('login', function () {

    it('inicia sesión con credenciales correctas', function () {
        $user = $this->createUser();

        $response = $this->postJson('/v1/users/auth/login', [
            'identity' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'access_token',
                    'refresh_token',
                    'model' => ['id', 'name', 'email'],
                ],
            ]);
    });

    it('falla con credenciales incorrectas', function () {
        $this->createUser();

        $response = $this->postJson('/v1/users/auth/login', [
            'identity' => 'test@example.com',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(401);
    });

    it('falla si el identity no existe', function () {
        $response = $this->postJson('/v1/users/auth/login', [
            'identity' => 'noexiste@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(404);
    });

    it('falla si falta el campo identity', function () {
        $response = $this->postJson('/v1/users/auth/login', [
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
    });

    it('falla si falta el campo password', function () {
        $response = $this->postJson('/v1/users/auth/login', [
            'identity' => 'test@example.com',
        ]);

        $response->assertStatus(422);
    });

    it('retorna un refresh token en la cookie', function () {
        $this->createUser();

        $response = $this->postJson('/v1/users/auth/login', [
            'identity' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertCookieNotExpired('user_refresh_token');
    });

    it('falla con tipo de cuenta inválido', function () {
        $response = $this->postJson('/v1/admins/auth/login', [
            'identity' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(404);
    });
});
