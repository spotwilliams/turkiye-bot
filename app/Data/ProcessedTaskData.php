<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class ProcessedTaskData extends Data
{
    public function __construct(
        public string $description,
        public string $category,
        public string $due_date,
        public ?string $due_time,
        public ?float $amount,
        public ?string $currency,
    ) {}
}
