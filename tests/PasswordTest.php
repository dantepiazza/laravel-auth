<?php

use DantePiazza\LaravelAuth\Tests\TestCase;
use DantePiazza\LaravelAuth\Tests\TestUser;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;

uses(TestCase::class);

describe('recover password', function () {

    it('envía el código de recuperación', function () {
        Mail::fake();

        $this->createUser();

        $response = $this->postJson('/v1/users/auth/password/recover', [
            'identity' => 'test@example.com',
        ]);

        $response->assertStatus(200);
        Mail::assertSent(\DantePiazza\LaravelAuth\Mails\VerificationCodeEmail::class);
    });

    it('falla si el identity no existe', function () {
        $response = $this->postJson('/v1/users/auth/password/recover', [
            'identity' => 'noexiste@example.com',
        ]);

        $response->assertStatus(404);
    });

    it('falla si no se envía identity', function () {
        $response = $this->postJson('/v1/users/auth/password/recover', []);

        $response->assertStatus(422);
    });
});

describe('restore password', function () {

    it('restablece la contraseña con código válido', function () {
        $user = $this->createUser();
        $code = $user->generateVerificationCode('password.recover');

        $response = $this->postJson('/v1/users/auth/password/restore', [
            'identity'              => 'test@example.com',
            'code'                  => (string) $code,
            'password'              => 'nueva_password123',
            'password_confirmation' => 'nueva_password123',
        ]);

        $response->assertStatus(200);

        expect(Hash::check('nueva_password123', $user->fresh()->password))->toBeTrue();
    });

    it('falla con código incorrecto', function () {
        $this->createUser();

        $response = $this->postJson('/v1/users/auth/password/restore', [
            'identity'              => 'test@example.com',
            'code'                  => '000000',
            'password'              => 'nueva_password123',
            'password_confirmation' => 'nueva_password123',
        ]);

        $response->assertStatus(422);
    });

    it('falla con código expirado', function () {
        $user = $this->createUser();
        $code = $user->generateVerificationCode('password.recover', -1); // expirado

        $response = $this->postJson('/v1/users/auth/password/restore', [
            'identity'              => 'test@example.com',
            'code'                  => (string) $code,
            'password'              => 'nueva_password123',
            'password_confirmation' => 'nueva_password123',
        ]);

        $response->assertStatus(422);
    });
});

describe('change password', function () {

    it('cambia la contraseña con la actual correcta', function () {
        $user  = $this->createUser();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/v1/users/auth/password/change', [
                'current_password'      => 'password123',
                'password'              => 'nueva_password123',
                'password_confirmation' => 'nueva_password123',
            ]);

        $response->assertStatus(200);
        expect(Hash::check('nueva_password123', $user->fresh()->password))->toBeTrue();
    });

    it('falla si la contraseña actual es incorrecta', function () {
        $user  = $this->createUser();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/v1/users/auth/password/change', [
                'current_password'      => 'incorrecta',
                'password'              => 'nueva_password123',
                'password_confirmation' => 'nueva_password123',
            ]);

        $response->assertStatus(422);
    });

    it('falla sin autenticación', function () {
        $response = $this->postJson('/v1/users/auth/password/change', [
            'current_password'      => 'password123',
            'password'              => 'nueva_password123',
            'password_confirmation' => 'nueva_password123',
        ]);

        $response->assertStatus(401);
    });

    it('falla si las contraseñas no coinciden', function () {
        $user  = $this->createUser();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/v1/users/auth/password/change', [
                'current_password'      => 'password123',
                'password'              => 'nueva_password123',
                'password_confirmation' => 'diferente',
            ]);

        $response->assertStatus(422);
    });
});
