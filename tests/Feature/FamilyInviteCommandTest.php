<?php

use App\Models\FamilyInvite;

test('family:invite creates an invite row with the given name and role and prints the code', function () {
    $this->artisan('family:invite', ['--name' => 'Ricardo', '--role' => 'father'])
        ->assertSuccessful();

    $invite = FamilyInvite::first();
    expect($invite)->not->toBeNull();
    expect($invite->name)->toBe('Ricardo');
    expect($invite->role)->toBe('father');
    expect(strlen($invite->code))->toBeGreaterThanOrEqual(16);
    expect($invite->used_at)->toBeNull();
});

test('family:invite supports an optional expiry in hours', function () {
    $this->artisan('family:invite', ['--name' => 'X', '--role' => 'mother', '--expires-in-hours' => 24])
        ->assertSuccessful();

    $invite = FamilyInvite::first();
    expect($invite->expires_at)->not->toBeNull();
    expect($invite->expires_at->diffInHours(now(), true))->toBeLessThanOrEqual(25);
});
