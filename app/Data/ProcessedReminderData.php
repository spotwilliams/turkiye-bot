<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class ProcessedReminderData extends Data
{
    public function __construct(
        public string $scheduled_at,
        public string $message,
        public string $type,
    ) {}
}
