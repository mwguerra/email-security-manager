# Laravel Email Security Manager

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mwguerra/email-security-manager.svg?style=flat-square)](https://packagist.org/packages/mwguerra/email-security-manager)
[![Total Downloads](https://img.shields.io/packagist/dt/mwguerra/email-security-manager.svg?style=flat-square)](https://packagist.org/packages/mwguerra/email-security-manager)
[![License](https://img.shields.io/github/license/mwguerra/email-security-manager.svg?style=flat-square)](LICENSE.md)

A comprehensive Laravel package for managing email verification and password security with built-in audit trails. This package helps you enforce security best practices and comply with data protection regulations.

## Key Features

- 🛡️ **Enhanced Security**
    - Force periodic email reverification
    - Require regular password changes
    - Support for multiple authentication models
    - Configurable expiry periods

- 📊 **Complete Audit Trail**
    - Track all verification events
    - Monitor password changes
    - Record security-related actions
    - Polymorphic relationships for flexibility

- 🔄 **Automated Security**
    - Middleware for automatic checks
    - Event-driven audit logging
    - Bulk operation support
    - Configurable security policies

- 📜 **Compliance Ready**
    - GDPR compliance support
    - LGPD requirements
    - CCPA alignment
    - Security best practices

## Requirements

- PHP 8.2 or higher
- Laravel 11.0 or higher

## Installation

```bash
composer require mwguerra/email-security-manager
```

## Setup

1. Publish the configuration and migrations:
```bash
php artisan vendor:publish --provider="MWGuerra\EmailSecurityManager\EmailSecurityManagerServiceProvider"
```

2. Run the migrations:
```bash
php artisan migrate
```

3. Add the `HasEmailSecurity` trait to your authenticatable models:
```php
use MWGuerra\EmailSecurityManager\Traits\HasEmailSecurity;

class User extends Authenticatable
{
    use HasEmailSecurity;
}
```

## Configuration

### Basic Configuration
Configure your authenticatable models and security settings in `config/email-security.php`:

```php
return [
    // Configure authenticatable models
    'authenticatable_models' => [
        'default' => \App\Models\User::class,
        'admin' => \App\Models\Admin::class,
        'customer' => \App\Models\Customer::class,
    ],

    // Set expiry periods
    'verification_expiry_days' => env('EMAIL_VERIFICATION_EXPIRY_DAYS', 30),
    'password_expiry_days' => env('PASSWORD_EXPIRY_DAYS', 90),

    // Configure redirect route
    'redirect_route' => 'verification.notice',

    // Routes to skip verification
    'skip_routes' => [
        'verification.notice',
        'verification.verify',
        'verification.send',
        'password.request',
        'password.reset',
        'password.update',
        'logout'
    ],
];
```

### Middleware Setup

Add the middleware to your `app/Http/Kernel.php`:

```php
protected $routeMiddleware = [
    'verify.email' => \MWGuerra\EmailSecurityManager\Middleware\EmailSecurityMiddleware::class,
];
```

## Usage

### Basic Usage

```php
use MWGuerra\EmailSecurityManager\Services\EmailSecurityService;

class SecurityController extends Controller
{
    public function __construct(
        protected EmailSecurityService $securityService
    ) {}

    public function requireVerification(User $user)
    {
        $this->securityService->requestReverification(
            authenticatable: $user,
            reason: 'Security policy update',
            triggeredBy: auth()->user()
        );
    }
}
```

### Multiple Authentication Models

```php
// Using different authenticatable models
$this->securityService
    ->useAuthenticatable(Admin::class)
    ->requestReverification($admin);

// Or specify in the method call
$this->securityService->requestReverification(
    authenticatable: $customer,
    authenticatableClass: Customer::class
);
```

### Bulk Operations

```php
// Force reverification for multiple users
$users = User::where('department', 'IT')->get();
$this->securityService->requestReverification(
    authenticatables: $users,
    reason: 'Department security update'
);

// Request password change for all active admins
$admins = Admin::where('is_active', true)->get();
$this->securityService
    ->useAuthenticatable(Admin::class)
    ->requestPasswordChange($admins);
```

### Middleware Usage

```php
// In your routes file
Route::middleware(['auth', 'verify.email'])->group(function () {
    // Protected routes requiring valid email verification
});
```

### Audit Trail

```php
// Get verification history
$user->securityAudits()->latest()->get();

// Get recent verifications
$user->securityAudits()
    ->emailVerifications()
    ->recent()
    ->get();

// Get password changes
$user->securityAudits()
    ->passwordChanges()
    ->get();
```

### Advanced Features

```php
// Custom expiry periods
$this->securityService
    ->setVerificationExpiryDays(60)
    ->setPasswordExpiryDays(45)
    ->requestReverification($user);

// Get entities requiring action
$needsAction = $this->securityService->getAuthenticatablesRequiringAction();
```

## Events

The package automatically listens for and logs these Laravel events:
- `Illuminate\Auth\Events\Verified`
- `Illuminate\Auth\Events\PasswordReset`

## Testing

```bash
composer test
```

## Security

If you discover any security issues, please email mwguerra@gmail.com instead of using the issue tracker.

## Credits

- [Marcelo W. Guerra](https://github.com/mwguerra)
- [All Contributors](../../contributors)

## Special Thanks

Special thanks to the [Beer and Code Laravel Community](https://github.com/beerandcodeteam) for all the support, feedback, and great discussions that helped shape this package. Their dedication to sharing knowledge and fostering collaboration in the Laravel ecosystem is truly inspiring. 🍺👨‍💻

## About

I'm a software engineer specializing in Laravel and PHP development. Visit [mwguerra.com](https://mwguerra.com) to learn more about my work.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.