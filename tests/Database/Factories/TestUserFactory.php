<?php

namespace MWGuerra\EmailSecurityManager\Tests\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use MWGuerra\EmailSecurityManager\Tests\Models\TestUser;

class TestUserFactory extends Factory
{
    protected $model = TestUser::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
        ];
    }
}