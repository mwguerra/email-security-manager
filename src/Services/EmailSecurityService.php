<?php

namespace MWGuerra\EmailSecurityManager\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;
use MWGuerra\EmailSecurityManager\Tests\Models\TestUser;

class EmailSecurityService
{
    protected string $defaultAuthenticatableClass;

    public function __construct(
        protected int $verificationExpiryDays = 30,
        protected int $passwordExpiryDays = 30,
        ?string $authenticatableClass = null
    ) {
        // Only use config values if no explicit values provided
        $this->verificationExpiryDays = $verificationExpiryDays ?: config('email-security.verification_expiry_days', 30);
        $this->passwordExpiryDays = $passwordExpiryDays ?: config('email-security.password_expiry_days', 30);

        // Rest of the constructor remains the same
        if ($authenticatableClass) {
            $this->defaultAuthenticatableClass = $authenticatableClass;
            return;
        }

        if (app()->environment('testing')) {
            $this->defaultAuthenticatableClass = TestUser::class;
            return;
        }

        $defaultKey = config('email-security.default_authenticatable', 'default');
        $this->defaultAuthenticatableClass = config("email-security.authenticatable_models.{$defaultKey}");
    }

    /**
     * Request reverification for specified authenticatables
     */
    public function requestReverification(
        Builder|Collection|Authenticatable|array $authenticatables,
        ?string $reason = null,
        Model|string|null $triggeredBy = null,
        ?string $authenticatableClass = null
    ): void {
        $authenticatables = $this->resolveAuthenticatables($authenticatables, $authenticatableClass);

        foreach ($authenticatables as $authenticatable) {
            $authenticatable->email_verified_at = null;
            $authenticatable->save();

            // Create audit record with morphTo relationship
            $authenticatable->securityAudits()->create([
                'email' => $authenticatable->email,
                'triggered_by_type' => $triggeredBy instanceof Model ? get_class($triggeredBy) : null,
                'triggered_by' => $triggeredBy instanceof Model ? $triggeredBy->getKey() : ($triggeredBy ?? 'system'),
                'reason' => $reason
            ]);

            // Send verification email
            $authenticatable->sendEmailVerificationNotification();
        }
    }

    /**
     * Request password change for specified authenticatables
     */
    public function requestPasswordChange(
        Builder|Collection|Authenticatable|array $authenticatables,
        ?string $reason = null,
        Model|string|null $triggeredBy = null,
        ?string $authenticatableClass = null
    ): void {
        $authenticatables = $this->resolveAuthenticatables($authenticatables, $authenticatableClass);

        foreach ($authenticatables as $authenticatable) {
            // Create audit record
            $authenticatable->securityAudits()->create([
                'email' => $authenticatable->email,
                'triggered_by_type' => $triggeredBy instanceof Model ? get_class($triggeredBy) : null,
                'triggered_by' => $triggeredBy instanceof Model ? $triggeredBy->getKey() : ($triggeredBy ?? 'system'),
                'reason' => $reason
            ]);

            // Send password reset notification
            $authenticatable->sendPasswordResetNotification(
                Str::random(60)
            );
        }
    }

    /**
     * Check if email verification is expired
     */
    public function isEmailVerificationExpired(Authenticatable $authenticatable): bool
    {
        if (!$authenticatable->email_verified_at) {
            return true;
        }

        return Carbon::parse($authenticatable->email_verified_at)
            ->addDays($this->verificationExpiryDays)
            ->isPast();
    }

    /**
     * Check if password change is expired
     */
    public function isPasswordExpired(Authenticatable $authenticatable): bool
    {
        $lastPasswordChange = $authenticatable->securityAudits()
            ->whereNotNull('password_changed_at')
            ->latest('password_changed_at')
            ->first();

        if (!$lastPasswordChange) {
            return true;
        }

        return Carbon::parse($lastPasswordChange->password_changed_at)
            ->addDays($this->passwordExpiryDays)
            ->isPast();
    }

    /**
     * Get authenticatables with expired email verification
     */
    public function getAuthenticatablesWithExpiredEmailVerification(
        ?string $authenticatableClass = null
    ): Collection {
        $class = $authenticatableClass ?? $this->defaultAuthenticatableClass;

        return $class::query()
            ->where(function (Builder $query) {
                $query->whereNull('email_verified_at')
                    ->orWhere('email_verified_at', '<=',
                        now()->subDays($this->verificationExpiryDays));
            })
            ->get();
    }

    /**
     * Get authenticatables with expired passwords
     */
    public function getAuthenticatablesWithExpiredPasswords(
        ?string $authenticatableClass = null
    ): Collection {
        $class = $authenticatableClass ?? $this->defaultAuthenticatableClass;
        $expiryDate = now()->subDays($this->passwordExpiryDays);

        return $class::query()
            ->whereHas('securityAudits', function (Builder $query) use ($expiryDate) {
                $query->whereNotNull('password_changed_at')
                    ->where('password_changed_at', '<=', $expiryDate);
            })
            ->orWhereDoesntHave('securityAudits', function (Builder $query) {
                $query->whereNotNull('password_changed_at');
            })
            ->get();
    }

    /**
     * Get all authenticatables that require action (expired email or password)
     */
    public function getAuthenticatablesRequiringAction(
        ?string $authenticatableClass = null
    ): Collection {
        $expiredEmails = $this->getAuthenticatablesWithExpiredEmailVerification($authenticatableClass);
        $expiredPasswords = $this->getAuthenticatablesWithExpiredPasswords($authenticatableClass);

        return $expiredEmails->merge($expiredPasswords)->unique('id');
    }

    /**
     * Change the default authenticatable class
     */
    public function useAuthenticatable(string $authenticatableClass): self
    {
        $this->defaultAuthenticatableClass = $authenticatableClass;
        return $this;
    }

    /**
     * Set verification expiry days
     */
    public function setVerificationExpiryDays(int $days): self
    {
        $this->verificationExpiryDays = $days;
        return $this;
    }

    /**
     * Set password expiry days
     */
    public function setPasswordExpiryDays(int $days): self
    {
        $this->passwordExpiryDays = $days;
        return $this;
    }

    /**
     * Resolve authenticatables from various input types
     */
    public function resolveAuthenticatables(
        Builder|Collection|Authenticatable|array $authenticatables,
        ?string $authenticatableClass = null
    ): Collection {
        $class = $authenticatableClass ?? $this->defaultAuthenticatableClass;

        if ($authenticatables instanceof Authenticatable) {
            return collect([$authenticatables]);
        }

        if ($authenticatables instanceof Builder) {
            return $authenticatables->get();
        }

        if (is_array($authenticatables)) {
            // If IDs provided, fetch models
            if (is_numeric(array_key_first($authenticatables))) {
                return $class::whereIn('id', $authenticatables)->get();
            }
            return collect($authenticatables);
        }

        return $authenticatables;
    }

    public function getVerificationExpiryDays(): int
    {
        return $this->verificationExpiryDays;
    }

    public function getPasswordExpiryDays(): int
    {
        return $this->passwordExpiryDays;
    }
}