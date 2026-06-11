<?php

declare(strict_types=1);

namespace App\Actions\Dto;

use App\Models\Task;

class CompleteTaskResult
{
    public function __construct(
        public readonly string $status,
        public readonly ?Task $task = null,
        public readonly int $remaining = 0,
    ) {}
}
