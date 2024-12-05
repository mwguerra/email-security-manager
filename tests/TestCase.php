<?php

namespace MWGuerra\EmailSecurityManager\Tests;

use MWGuerra\EmailSecurityManager\EmailSecurityManagerServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Illuminate\Support\Facades\Route;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Define verification routes for testing
        Route::get('/email/verify/{id}/{hash}', function () {
            return response()->json(['message' => 'Verify Email']);
        })->name('verification.verify');

        Route::get('/email/verify', function () {
            return response()->json(['message' => 'Verification Notice']);
        })->name('verification.notice');

        Route::post('/email/verification-notification', function () {
            return response()->json(['message' => 'Verification Sent']);
        })->name('verification.send');

        // Define auth routes for testing
        Route::get('/login', function () {
            return 'login';
        })->name('login');

        Route::get('/password/reset', function () {
            return 'reset';
        })->name('password.request');

        // Define protected route
        Route::get('/protected-route', function () {
            return response()->json(['message' => 'Protected content']);
        })->middleware(['auth', 'verify.email']);

        $this->app->register(EmailSecurityManagerServiceProvider::class);
    }

    protected function defineEnvironment($app): void
    {
        // Test configuration
        $app['config']->set('email-security', [
            'verification_expiry_days' => 45,
            'password_expiry_days' => 60,
            'authenticatable_models' => [
                'default' => \MWGuerra\EmailSecurityManager\Tests\Models\TestUser::class,
            ],
            'redirect_route' => 'verification.notice',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');
    }

    protected function getPackageProviders($app): array
    {
        return [
            EmailSecurityManagerServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Use memory SQLite database
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}