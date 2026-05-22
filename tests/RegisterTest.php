<?php

use DantePiazza\LaravelAuth\Tests\TestCase;
use DantePiazza\LaravelAuth\Tests\TestUser;
use Illuminate\Support\Facades\Event;
use DantePiazza\LaravelAuth\Events\UserRegistered;

uses(TestCase::class);

describe('register', function () {

    it('registra un usuario y devuelve tokens', function () {
        $response = $this->postJson('/v1/users/auth/register', [
            'name'                  => 'Nuevo Usuario',
            'email'                 => 'nuevo@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'access_token',
                    'refresh_token',
                    'model' => ['id', 'name', 'email'],
                ],
            ]);

        expect(TestUser::where('email', 'nuevo@example.com')->exists())->toBeTrue();
    });

    it('hashea la contraseña al registrar', function () {
        $this->postJson('/v1/users/auth/register', [
            'name'                  => 'Nuevo Usuario',
            'email'                 => 'nuevo@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = TestUser::where('email', 'nuevo@example.com')->first();

        expect($user->password)->not->toBe('password123');
        expect(\Illuminate\Support\Facades\Hash::check('password123', $user->password))->toBeTrue();
    });

    it('dispara el evento UserRegistered', function () {
        Event::fake();

        $this->postJson('/v1/users/auth/register', [
            'name'                  => 'Nuevo Usuario',
            'email'                 => 'nuevo@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        Event::assertDispatched(UserRegistered::class, function ($event) {
            return $event->model->email === 'nuevo@example.com';
        });
    });

    it('falla si el email ya está registrado', function () {
        $this->createUser(['email' => 'existe@example.com']);

        $response = $this->postJson('/v1/users/auth/register', [
            'name'                  => 'Otro Usuario',
            'email'                 => 'existe@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422);
    });

    it('falla si las contraseñas no coinciden', function () {
        $response = $this->postJson('/v1/users/auth/register', [
            'name'                  => 'Nuevo Usuario',
            'email'                 => 'nuevo@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'diferente',
        ]);

        $response->assertStatus(422);
    });

    it('falla si la contraseña tiene menos de 8 caracteres', function () {
        $response = $this->postJson('/v1/users/auth/register', [
            'name'                  => 'Nuevo Usuario',
            'email'                 => 'nuevo@example.com',
            'password'              => 'corta',
            'password_confirmation' => 'corta',
        ]);

        $response->assertStatus(422);
    });

    it('verifica disponibilidad de identity', function () {
        $this->createUser(['email' => 'ocupado@example.com']);

        $ocupado = $this->postJson('/v1/users/auth/register/check-identity', [
            'identity' => 'ocupado@example.com',
        ]);

        $ocupado->assertStatus(200)
            ->assertJsonPath('data.available', false);

        $libre = $this->postJson('/v1/users/auth/register/check-identity', [
            'identity' => 'libre@example.com',
        ]);

        $libre->assertStatus(200)
            ->assertJsonPath('data.available', true);
    });
});
