<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('user:create creates a verified account that can authenticate', function () {
    $this->artisan('user:create', [
        '--name' => 'Ricardo',
        '--email' => 'ricardo@example.com',
        '--password' => 'sekretpass',
    ])->assertSuccessful();

    $user = User::where('email', 'ricardo@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Ricardo');
    expect($user->hasVerifiedEmail())->toBeTrue();
    expect(Hash::check('sekretpass', $user->password))->toBeTrue();
});

test('user:create rejects a duplicate email without creating a second row', function () {
    User::factory()->create(['email' => 'dup@example.com']);

    $this->artisan('user:create', [
        '--name' => 'Second',
        '--email' => 'dup@example.com',
        '--password' => 'whatever123',
    ])
        ->expectsOutputToContain('already exists')
        ->assertFailed();

    expect(User::where('email', 'dup@example.com')->count())->toBe(1);
});
