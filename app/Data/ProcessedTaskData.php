<?php

namespace App\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class ProcessedTaskData extends Data
{
    public function __construct(
        public string $description,
        public string $category,
        public string $due_date,
        public ?string $due_time,
        public ?float $amount,
        public ?string $currency,
        #[DataCollectionOf(ProcessedReminderData::class)]
        public DataCollection $reminders,
    ) {}
}
