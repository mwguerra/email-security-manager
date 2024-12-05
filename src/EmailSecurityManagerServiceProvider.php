<?php

namespace MWGuerra\EmailSecurityManager;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Collection;
use MWGuerra\EmailSecurityManager\Services\EmailSecurityService;
use MWGuerra\EmailSecurityManager\Middleware\EmailSecurityMiddleware;
use MWGuerra\EmailSecurityManager\Models\EmailSecurityAudit;

class EmailSecurityManagerServiceProvider extends ServiceProvider
{
    /**
     * All of the container bindings that should be registered.
     */
    public array $bindings = [
        EmailSecurityService::class => EmailSecurityService::class,
    ];

    /**
     * All of the container singletons that should be registered.
     */
    public array $singletons = [
        EmailSecurityService::class => EmailSecurityService::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerConfig();
        $this->registerService();
        $this->registerMiddleware();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerMigrations();
        $this->registerEventListeners();

        if ($this->app->runningInConsole()) {
            $this->registerCommands();
        }
    }

    /**
     * Register the config.
     */
    protected function registerConfig(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/Config/email-security.php',
            'email-security'
        );
    }

    /**
     * Register the service.
     */
    protected function registerService(): void
    {
        $this->app->singleton(EmailSecurityService::class, function ($app) {
            return new EmailSecurityService(
                config('email-security.verification_expiry_days', 30),
                config('email-security.password_expiry_days', 30)
            );
        });
    }

    /**
     * Register the middleware.
     */
    protected function registerMiddleware(): void
    {
        $this->app->singleton(EmailSecurityMiddleware::class, function ($app) {
            return new EmailSecurityMiddleware($app->make(EmailSecurityService::class));
        });

        $this->app['router']->aliasMiddleware(
            'verify.email',
            EmailSecurityMiddleware::class
        );
    }

    /**
     * Register the package's publishable resources.
     */
    protected function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        // Config
        $this->publishes([
            __DIR__ . '/Config/email-security.php' => config_path('email-security.php'),
        ], 'email-security-config');

        // Migrations
        $this->publishes([
            __DIR__ . '/Database/Migrations/create_email_security_audits_table.php' =>
                $this->getMigrationFileName('create_email_security_audits_table.php'),
        ], 'email-security-migrations');
    }

    /**
     * Register the package's migrations.
     */
    protected function registerMigrations(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');
        }
    }

    /**
     * Register the package's commands.
     */
    protected function registerCommands(): void
    {
        $this->commands([
            // Register commands here
        ]);
    }

    /**
     * Register event listeners for email verification and password reset events.
     */
    protected function registerEventListeners(): void
    {
        Event::listen(Verified::class, function ($event) {
            $event->user->securityAudits()->create([
                'authenticatable_id' => $event->user->id,
                'authenticatable_type' => get_class($event->user),
                'email' => $event->user->email,
                'verified_at' => now(),
                'triggered_by_type' => null,
                'triggered_by' => 'user',
                'reason' => 'Email verification completed'
            ]);
        });

        Event::listen(PasswordReset::class, function ($event) {
            $event->user->securityAudits()->create([
                'authenticatable_id' => $event->user->id,
                'authenticatable_type' => get_class($event->user),
                'email' => $event->user->email,
                'password_changed_at' => now(),
                'triggered_by_type' => null,
                'triggered_by' => 'user',
                'reason' => 'Password reset completed'
            ]);
        });
    }

    /**
     * Returns existing migration file if found, else uses the current timestamp.
     */
    protected function getMigrationFileName(string $migrationFileName): string
    {
        $timestamp = date('Y_m_d_His');

        $filesystem = $this->app['files'];

        return Collection::make([
            $this->app->databasePath().DIRECTORY_SEPARATOR.'migrations'.DIRECTORY_SEPARATOR,
        ])->flatMap(fn (string $path) => [
            $path.'*_'.$migrationFileName,
        ])->flatMap(fn (string $pattern) => $filesystem->glob($pattern))
            ->push($this->app->databasePath().
                DIRECTORY_SEPARATOR.'migrations'.
                DIRECTORY_SEPARATOR.$timestamp.'_'.$migrationFileName)
            ->first();
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            EmailSecurityService::class,
        ];
    }
}