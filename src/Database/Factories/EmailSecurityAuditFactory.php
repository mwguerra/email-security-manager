<?php

namespace MWGuerra\EmailSecurityManager\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use MWGuerra\EmailSecurityManager\Models\EmailSecurityAudit;
use MWGuerra\EmailSecurityManager\Tests\Models\TestUser;

class EmailSecurityAuditFactory extends Factory
{
    protected $model = EmailSecurityAudit::class;

    public function definition(): array
    {
        $authenticatable = TestUser::factory()->create();

        return [
            'authenticatable_type' => get_class($authenticatable),
            'authenticatable_id' => $authenticatable->id,
            'email' => $authenticatable->email,
            'verified_at' => $this->faker->optional(0.7)->dateTimeBetween('-60 days', '-1 day'),
            'password_changed_at' => $this->faker->optional(0.5)->dateTimeBetween('-60 days', '-1 day'),
            'triggered_by_type' => null,
            'triggered_by' => $this->faker->randomElement([
                null,
                'system',
                TestUser::factory()->create()->id
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
            'triggered_by_type' => null,
            'triggered_by' => 'system',
            'reason' => 'Automatic system verification',
        ]);
    }

    /**
     * Indicate that this was admin triggered.
     */
    public function adminTriggered(): static
    {
        $admin = TestUser::factory()->create(['is_admin' => true]);

        return $this->state(fn (array $attributes) => [
            'triggered_by_type' => get_class($admin),
            'triggered_by' => $admin->id,
            'reason' => 'Administrative request',
        ]);
    }
}