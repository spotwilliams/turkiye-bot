<?php

use App\Actions\RedeemFamilyInvite;
use App\Models\FamilyInvite;
use App\Models\FamilyMember;

test('a valid unused code creates a family member and marks the invite used', function () {
    $invite = FamilyInvite::create([
        'code' => 'abc123',
        'name' => 'Ricardo',
        'role' => 'father',
    ]);

    $action = app(RedeemFamilyInvite::class);
    $result = $action->execute('abc123', fromUserId: 555, chatId: 777);

    expect($result->status)->toBe('registered');
    expect($result->familyMember)->not->toBeNull();

    $member = FamilyMember::where('telegram_user_id', 555)->first();
    expect($member)->not->toBeNull();
    expect($member->name)->toBe('Ricardo');
    expect($member->role)->toBe('father');
    expect($member->telegram_chat_id)->toBe(777);

    $invite->refresh();
    expect($invite->used_at)->not->toBeNull();
    expect($invite->used_by_family_member_id)->toBe($member->id);
});

test('an already-used code is rejected as invalid', function () {
    FamilyInvite::create([
        'code' => 'used-code',
        'name' => 'X',
        'role' => 'father',
        'used_at' => now(),
    ]);

    $result = app(RedeemFamilyInvite::class)->execute('used-code', 100, 100);

    expect($result->status)->toBe('invalid');
    expect(FamilyMember::count())->toBe(0);
});

test('an unknown code is rejected as invalid', function () {
    $result = app(RedeemFamilyInvite::class)->execute('nope', 1, 1);

    expect($result->status)->toBe('invalid');
    expect(FamilyMember::count())->toBe(0);
});

test('an expired code is rejected as invalid', function () {
    FamilyInvite::create([
        'code' => 'expired',
        'name' => 'X',
        'role' => 'father',
        'expires_at' => now()->subHour(),
    ]);

    $result = app(RedeemFamilyInvite::class)->execute('expired', 1, 1);

    expect($result->status)->toBe('invalid');
    expect(FamilyMember::count())->toBe(0);
});

test('a re-redemption by an already-registered telegram user is idempotent', function () {
    FamilyInvite::create([
        'code' => 'first-code',
        'name' => 'Ricardo',
        'role' => 'father',
    ]);
    app(RedeemFamilyInvite::class)->execute('first-code', 999, 999);

    FamilyInvite::create([
        'code' => 'second-code',
        'name' => 'Mara',
        'role' => 'mother',
    ]);
    $result = app(RedeemFamilyInvite::class)->execute('second-code', 999, 999);

    expect($result->status)->toBe('already_registered');
    expect(FamilyMember::count())->toBe(1);
});
