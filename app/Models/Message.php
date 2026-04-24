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
    'normalized_text',
    'normalized_text_hash',
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

    /**
     * Normalize text for duplicate detection: trim, collapse whitespace,
     * lowercase. Deterministic and cheap.
     */
    public static function normalizeText(string $text): string
    {
        $text = trim($text);
        $text = (string) preg_replace('/\s+/u', ' ', $text);

        return mb_strtolower($text);
    }

    public static function hashText(string $text): string
    {
        return hash('sha256', self::normalizeText($text));
    }

    public static function findByText(string $text): ?self
    {
        return self::query()
            ->where('normalized_text_hash', self::hashText($text))
            ->first();
    }
}
