<?php

use App\Models\FamilyMember;
use App\Models\User;

test('unauthenticated users are redirected from family members index', function () {
    $this->get(route('admin.family-members.index'))->assertRedirect();
});

test('family members index renders paginated members', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    FamilyMember::factory()->count(3)->create();

    $this->actingAs($user)
        ->get(route('admin.family-members.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/FamilyMembers/Index')
            ->has('members.data', 3)
            ->has('members.data.0.ref')
        );
});

test('family members index renders empty paginator', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('admin.family-members.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('members.data', 0)
            ->where('members.total', 0)
        );
});

test('family member show renders detail', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $member = FamilyMember::factory()->create(['name' => 'Ayşe', 'role' => 'mother']);

    $this->actingAs($user)
        ->get(route('admin.family-members.show', $member))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/FamilyMembers/Show')
            ->where('member.id', $member->id)
            ->where('member.name', 'Ayşe')
            ->where('member.role', 'mother')
        );
});

test('family member show 404s for unknown id', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get('/admin/family-members/9999')
        ->assertNotFound();
});
