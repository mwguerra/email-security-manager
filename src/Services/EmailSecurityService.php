<?php

namespace MWGuerra\EmailSecurityManager\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EmailSecurityService
{
    public function __construct(
        protected int $verificationExpiryDays = 30,
        protected int $passwordExpiryDays = 30
    ) {
        $this->verificationExpiryDays = config('email-verification.verification_expiry_days');
        $this->passwordExpiryDays = config('email-verification.password_expiry_days');
    }

    /**
     * Request reverification for specified users
     */
    public function requestReverification(
        Builder|Collection|User|array $users,
        string $reason = null,
        string $triggeredBy = null
    ): void {
        $users = $this->resolveUsers($users);

        foreach ($users as $user) {
            $user->email_verified_at = null;
            $user->save();

            // Create audit record
            $user->verificationAudits()->create([
                'email' => $user->email,
                'triggered_by' => $triggeredBy ?? 'system',
                'reason' => $reason
            ]);

            // Send verification email
            $user->sendEmailVerificationNotification();
        }
    }

    /**
     * Request password change for specified users
     */
    public function requestPasswordChange(
        Builder|Collection|User|array $users,
        string $reason = null,
        string $triggeredBy = null
    ): void {
        $users = $this->resolveUsers($users);

        foreach ($users as $user) {
            // Create audit record
            $user->verificationAudits()->create([
                'email' => $user->email,
                'triggered_by' => $triggeredBy ?? 'system',
                'reason' => $reason
            ]);

            // Send password reset notification
            $user->sendPasswordResetNotification(
                \Illuminate\Support\Str::random(60)
            );
        }
    }

    /**
     * Check if email verification is expired
     */
    public function isEmailVerificationExpired(User $user): bool
    {
        if (!$user->email_verified_at) {
            return true;
        }

        return $user->email_verified_at->addDays($this->verificationExpiryDays)
            ->isPast();
    }

    /**
     * Check if password change is expired
     */
    public function isPasswordExpired(User $user): bool
    {
        $lastPasswordChange = $user->verificationAudits()
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
     * Get users with expired email verification
     */
    public function getUsersWithExpiredEmailVerification(): Collection
    {
        return User::query()
            ->where(function (Builder $query) {
                $query->whereNull('email_verified_at')
                    ->orWhere('email_verified_at', '<=',
                        now()->subDays($this->verificationExpiryDays));
            })
            ->get();
    }

    /**
     * Get users with expired passwords
     */
    public function getUsersWithExpiredPasswords(): Collection
    {
        $expiryDate = now()->subDays($this->passwordExpiryDays);

        return User::query()
            ->whereHas('verificationAudits', function (Builder $query) use ($expiryDate) {
                $query->whereNotNull('password_changed_at')
                    ->where('password_changed_at', '<=', $expiryDate);
            })
            ->orWhereDoesntHave('verificationAudits', function (Builder $query) {
                $query->whereNotNull('password_changed_at');
            })
            ->get();
    }

    /**
     * Resolve users from various input types
     */
    protected function resolveUsers(Builder|Collection|User|array $users): Collection
    {
        if ($users instanceof User) {
            return collect([$users]);
        }

        if ($users instanceof Builder) {
            return $users->get();
        }

        if (is_array($users)) {
            return collect($users);
        }

        return $users;
    }
}
