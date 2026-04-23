<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'telegram_chat_id',
    'telegram_message_id',
    'original_text',
    'translation_en',
    'translation_es',
    'summary',
    'raw_processor_response',
    'processed_at',
])]
class Message extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
            'raw_processor_response' => 'array',
        ];
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
