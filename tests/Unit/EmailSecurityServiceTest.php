<?php

use MWGuerra\EmailSecurityManager\Services\EmailSecurityService;
use MWGuerra\EmailSecurityManager\Tests\Models\TestUser;

test('service can detect expired email verification', function () {
    $service = new EmailSecurityService(30, 30); // Explicitly set 30 days

    $user = TestUser::factory()->create([
        'email_verified_at' => now()->subDays(31),
    ]);

    expect($service->isEmailVerificationExpired($user))->toBeTrue();

    $user->email_verified_at = now()->subDays(29);
    $user->save();

    expect($service->isEmailVerificationExpired($user))->toBeFalse();
});

test('service can get users with expired email verification', function () {
    $service = new EmailSecurityService(30, 30); // Explicitly set 30 days

    // Create mix of expired and valid users
    TestUser::factory(2)->create(['email_verified_at' => now()->subDays(31)]);
    TestUser::factory(3)->create(['email_verified_at' => now()->subDays(15)]);

    $expiredUsers = $service->getAuthenticatablesWithExpiredEmailVerification();

    expect($expiredUsers)->toHaveCount(2);
});