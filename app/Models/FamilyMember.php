<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'role',
    'telegram_user_id',
    'telegram_chat_id',
    'timezone',
    'preferences',
])]
class FamilyMember extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'preferences' => 'array',
        ];
    }
}
