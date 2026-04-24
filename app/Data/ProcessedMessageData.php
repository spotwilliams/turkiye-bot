<?php

namespace App\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class ProcessedMessageData extends Data
{
    public function __construct(
        public string $translation_en,
        public string $translation_es,
        public string $summary,
        #[DataCollectionOf(ProcessedTaskData::class)]
        public DataCollection $tasks,
    ) {}
}
