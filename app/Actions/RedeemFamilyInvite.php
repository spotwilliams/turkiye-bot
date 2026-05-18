<?php

namespace App\Actions;

use App\Models\FamilyInvite;
use App\Models\FamilyMember;
use Illuminate\Support\Facades\DB;

class RedemptionResult
{
    public function __construct(
        public readonly string $status,
        public readonly ?FamilyMember $familyMember = null,
    ) {}
}

class RedeemFamilyInvite
{
    public function execute(string $code, int $fromUserId, int $chatId): RedemptionResult
    {
        return DB::transaction(function () use ($code, $fromUserId, $chatId): RedemptionResult {
            $existing = FamilyMember::where('telegram_user_id', $fromUserId)->first();
            if ($existing !== null) {
                return new RedemptionResult('already_registered', $existing);
            }

            $invite = FamilyInvite::where('code', $code)->lockForUpdate()->first();
            if ($invite === null || ! $invite->isClaimable()) {
                return new RedemptionResult('invalid');
            }

            $member = FamilyMember::create([
                'name' => $invite->name,
                'role' => $invite->role,
                'telegram_user_id' => $fromUserId,
                'telegram_chat_id' => $chatId,
            ]);

            $invite->update([
                'used_at' => now(),
                'used_by_family_member_id' => $member->id,
            ]);

            return new RedemptionResult('registered', $member);
        });
    }
}
