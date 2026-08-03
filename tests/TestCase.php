<?php

namespace DantePiazza\LaravelAuth\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\SanctumServiceProvider;
use DantePiazza\LaravelAuth\AuthServiceProvider;
use DantePiazza\LaravelAuth\Traits\HasRefreshTokens;
use DantePiazza\LaravelAuth\Traits\HasVerificationCode;

class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    protected function getPackageProviders($app): array
    {
        return [
            SanctumServiceProvider::class,
            AuthServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('auth.guards.sanctum', [
            'driver'   => 'sanctum',
            'provider' => 'users',
        ]);

        $app['config']->set('auth.providers.users', [
            'driver' => 'eloquent',
            'model'  => TestUser::class,
        ]);

        $app['config']->set('laravel-auth.account_types', [
            'users' => [
                'name'            => 'user',
                'guard'           => 'sanctum',
                'class'           => TestUser::class,
                'identity'        => 'email',
                'resource'        => TestUserResource::class,
                'register_fields' => [
                    'name' => ['required', 'string'],
                ],
            ],
        ]);

        $app['config']->set('laravel-auth.register.login_after_register', true);
        $app['config']->set('laravel-auth.email_verification.enabled', false);
        $app['config']->set('laravel-auth.email_verification.blocking', false);
        $app['config']->set('laravel-auth.refresh_token_expiration', 43200);
        $app['config']->set('sanctum.expiration', 15);
    }

    protected function setUpDatabase(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../stubs/database/migrations');

        $this->app['db']->connection()->getSchemaBuilder()->create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });

        // Sanctum personal_access_tokens
        $this->loadMigrationsFrom(__DIR__ . '/../vendor/laravel/sanctum/database/migrations');
    }

    protected function createUser(array $overrides = []): TestUser
    {
        return TestUser::create(array_merge([
            'name'     => 'Test User',
            'email'    => 'test@example.com',
            'password' => bcrypt('password123'),
        ], $overrides));
    }
}

// Modelo de prueba
class TestUser extends Authenticatable
{
    use HasApiTokens, HasRefreshTokens, HasVerificationCode, HasFactory;

    protected $table    = 'users';
    protected $fillable = ['name', 'email', 'password', 'email_verified_at'];
    protected $hidden   = ['password'];
}

// Resource de prueba
class TestUserResource extends \Illuminate\Http\Resources\Json\JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'    => $this->id,
            'name'  => $this->name,
            'email' => $this->email,
        ];
    }
}
