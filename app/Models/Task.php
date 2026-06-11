<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $message_id
 * @property int|null $telegram_chat_id
 * @property int|null $created_by
 * @property string $description
 * @property string $category
 * @property Carbon|null $due_date
 * @property string|null $due_time
 * @property string|null $amount
 * @property string $currency
 * @property string $assigned_to
 * @property string $status
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Message|null $message
 * @property-read Collection<int, Reminder> $reminders
 * @property-read int $reminders_count
 */
#[Fillable([
    'message_id',
    'telegram_chat_id',
    'created_by',
    'description',
    'category',
    'due_date',
    'due_time',
    'amount',
    'currency',
    'assigned_to',
    'status',
    'completed_at',
])]
class Task extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'amount' => 'decimal:2',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }
}
