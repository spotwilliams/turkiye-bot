<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FamilyMember;
use Inertia\Inertia;
use Inertia\Response;

class AdminFamilyMembersController extends Controller
{
    public function index(): Response
    {
        $members = FamilyMember::query()
            ->orderBy('name')
            ->paginate(25)
            ->through(fn (FamilyMember $member): array => [
                'id' => $member->id,
                'ref' => $this->ref($member->id),
                'name' => $member->name,
                'role' => $member->role,
                'telegram_user_id' => (string) $member->telegram_user_id,
                'telegram_chat_id' => (string) $member->telegram_chat_id,
                'timezone' => $member->timezone,
                'created_at' => $member->created_at,
            ]);

        return Inertia::render('Admin/FamilyMembers/Index', [
            'members' => $members,
        ]);
    }

    public function show(FamilyMember $familyMember): Response
    {
        return Inertia::render('Admin/FamilyMembers/Show', [
            'member' => [
                'id' => $familyMember->id,
                'ref' => $this->ref($familyMember->id),
                'name' => $familyMember->name,
                'role' => $familyMember->role,
                'telegram_user_id' => (string) $familyMember->telegram_user_id,
                'telegram_chat_id' => (string) $familyMember->telegram_chat_id,
                'timezone' => $familyMember->timezone,
                'preferences' => $familyMember->preferences,
                'created_at' => $familyMember->created_at,
                'updated_at' => $familyMember->updated_at,
            ],
        ]);
    }

    private function ref(int $id): string
    {
        return 'FAM-'.str_pad((string) $id, 3, '0', STR_PAD_LEFT);
    }
}
