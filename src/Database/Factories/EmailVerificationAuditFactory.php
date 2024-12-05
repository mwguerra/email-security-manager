<?php

namespace MWGuerra\EmailSecurityManager\Database\Factories;

use App\Models\EmailVerificationAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmailVerificationAuditFactory extends Factory
{
    protected $model = EmailVerificationAudit::class;

    public function definition(): array
    {
        $user = User::factory()->create();

        return [
            'user_id' => $user->id,
            'email' => $user->email,
            'verified_at' => $this->faker->optional(0.7)->dateTimeBetween('-60 days', '-1 day'),
            'password_changed_at' => $this->faker->optional(0.5)->dateTimeBetween('-60 days', '-1 day'),
            'triggered_by' => $this->faker->randomElement([
                null,
                'system',
                User::factory(),
                $user->id
            ]),
            'reason' => $this->faker->optional(0.8)->randomElement([
                'Manual request',
                'Security policy',
                'Suspicious activity detected',
                'Periodic reverification',
                'Admin request',
                'Password expired',
                'Email change'
            ]),
            'created_at' => $this->faker->dateTimeBetween('-60 days', 'now'),
            'updated_at' => function (array $attributes) {
                return $this->faker->dateTimeBetween($attributes['created_at'], 'now');
            },
        ];
    }

    /**
     * Indicate that this is a password change audit.
     */
    public function passwordChange(): static
    {
        return $this->state(fn (array $attributes) => [
            'password_changed_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'verified_at' => null,
        ]);
    }

    /**
     * Indicate that this is an email verification audit.
     */
    public function emailVerification(): static
    {
        return $this->state(fn (array $attributes) => [
            'verified_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'password_changed_at' => null,
        ]);
    }

    /**
     * Indicate that this was system triggered.
     */
    public function systemTriggered(): static
    {
        return $this->state(fn (array $attributes) => [
            'triggered_by' => 'system',
            'reason' => 'Automatic system verification',
        ]);
    }

    /**
     * Indicate that this was admin triggered.
     */
    public function adminTriggered(): static
    {
        return $this->state(fn (array $attributes) => [
            'triggered_by' => User::factory()->create(['is_admin' => true]),
            'reason' => 'Administrative request',
        ]);
    }
}
