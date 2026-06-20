<?php

declare(strict_types=1);

namespace App\Actions\Dto;

use App\Models\FamilyMember;

class RedemptionResult
{
    public function __construct(
        public readonly string $status,
        public readonly ?FamilyMember $familyMember = null,
    ) {}
}
