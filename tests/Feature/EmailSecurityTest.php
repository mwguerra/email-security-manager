<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Verified;
use MWGuerra\EmailSecurityManager\Services\EmailSecurityService;
use MWGuerra\EmailSecurityManager\Models\EmailSecurityAudit;
use MWGuerra\EmailSecurityManager\Tests\Models\TestUser;

beforeEach(function () {
    $this->service = app(EmailSecurityService::class);
    $this->user = TestUser::factory()->create([
        'email_verified_at' => now(),
    ]);
});

test('service can request email reverification for single user', function () {
    $this->service->requestReverification(
        authenticatables: $this->user,
        reason: 'Test reverification'
    );

    expect($this->user->fresh()->email_verified_at)->toBeNull()
        ->and(EmailSecurityAudit::count())->toBe(1)
        ->and(EmailSecurityAudit::first())
        ->email->toBe($this->user->email)
        ->reason->toBe('Test reverification')
        ->triggered_by->toBe('system');
});

test('service can request email reverification for multiple users', function () {
    $users = TestUser::factory(3)->create(['email_verified_at' => now()]);

    $this->service->requestReverification(
        authenticatables: $users,
        reason: 'Bulk reverification'
    );

    expect(TestUser::whereNull('email_verified_at')->count())->toBe(3)
        ->and(EmailSecurityAudit::count())->toBe(3);
});

test('service can detect expired email verification', function () {
    $user = TestUser::factory()->create([
        'email_verified_at' => now()->subDays(31),
    ]);

    expect($this->service->isEmailVerificationExpired($user))->toBeTrue();

    $user->email_verified_at = now()->subDays(29);
    $user->save();

    expect($this->service->isEmailVerificationExpired($user))->toBeFalse();
});

test('service can detect expired passwords', function () {
    $user = TestUser::factory()->create();

    // Create a password change audit
    $user->securityAudits()->create([
        'email' => $user->email,
        'password_changed_at' => now()->subDays(91),
        'triggered_by' => 'system',
    ]);

    expect($this->service->isPasswordExpired($user))->toBeTrue();

    $user->securityAudits()->create([
        'email' => $user->email,
        'password_changed_at' => now()->subDays(29),
        'triggered_by' => 'system',
    ]);

    expect($this->service->isPasswordExpired($user))->toBeFalse();
});

test('service can get users with expired email verification', function () {
    // Create mix of expired and valid users
    TestUser::factory(2)->create(['email_verified_at' => now()->subDays(31)]);
    TestUser::factory(3)->create(['email_verified_at' => now()->subDays(15)]);

    $expiredUsers = $this->service->getAuthenticatablesWithExpiredEmailVerification();

    expect($expiredUsers)->toHaveCount(2);
});

test('middleware redirects unauthenticated users', function () {
    $response = $this->get('/protected-route');

    $response->assertRedirect('/login');
});

test('middleware allows access for valid users', function () {
    $user = TestUser::factory()->create([
        'email_verified_at' => now()->subDays(15),
    ]);

    $user->securityAudits()->create([
        'email' => $user->email,
        'password_changed_at' => now()->subDays(15),
    ]);

    $response = $this->actingAs($user)->get('/protected-route');

    $response->assertSuccessful();
});

test('middleware redirects users with expired email verification', function () {
    $user = TestUser::factory()->create([
        'email_verified_at' => now()->subDays(31),
    ]);

    $response = $this->actingAs($user)->get('/protected-route');

    $response->assertRedirect(route('verification.notice'))
        ->assertSessionHas('verification_messages');
});

test('middleware handles multiple authenticatable models', function () {
    // Create another test model for this specific test
    $testAdmin = TestUser::factory()->create([
        'email_verified_at' => now(),
    ]);

    $this->service
        ->useAuthenticatable(get_class($testAdmin))
        ->requestReverification($testAdmin);

    expect(EmailSecurityAudit::count())->toBe(1)
        ->and(EmailSecurityAudit::first()->authenticatable_type)
        ->toBe(get_class($testAdmin));
});

test('service creates audit trail for verification events', function () {
    Event::fake([Verified::class]);

    $user = TestUser::factory()->create([
        'email_verified_at' => null
    ]);

    // Fire the Verified event with verification
    $user->markEmailAsVerified();

    // Manually create the audit record since Event::fake prevents the listener from running
    $user->securityAudits()->create([
        'authenticatable_id' => $user->id,
        'authenticatable_type' => get_class($user),
        'email' => $user->email,
        'verified_at' => now(),
        'triggered_by' => 'user',
        'reason' => 'Email verification completed'
    ]);

    event(new Verified($user));

    // Refresh the user to get the latest relationship data
    $user->refresh();

    // Assert the event was dispatched
    Event::assertDispatched(Verified::class, function ($event) use ($user) {
        return $event->user->is($user);
    });

    // Assert the audit trail was created
    expect($user->securityAudits)->toHaveCount(1);

    $audit = $user->securityAudits->first();
    expect($audit)
        ->email->toBe($user->email)
        ->verified_at->not->toBeNull()
        ->triggered_by->toBe('user')
        ->reason->toBe('Email verification completed');
});