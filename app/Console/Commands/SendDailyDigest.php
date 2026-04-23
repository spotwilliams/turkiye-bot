<?php

namespace App\Console\Commands;

use App\Models\FamilyMember;
use App\Models\Task;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class SendDailyDigest extends Command
{
    protected $signature = 'digest:send';

    protected $description = 'Send the daily digest to registered family chats';

    public function handle(TelegramService $telegram): int
    {
        $today = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();
        $endOfWeek = now()->endOfWeek()->toDateString();

        $chatIds = FamilyMember::query()->distinct()->pluck('telegram_chat_id');
        if ($chatIds->isEmpty()) {
            $this->info('No family members found.');

            return self::SUCCESS;
        }

        foreach ($chatIds as $chatId) {
            $todayCount = Task::query()
                ->where('telegram_chat_id', $chatId)
                ->where('status', 'pending')
                ->whereDate('due_date', $today)
                ->count();
            $tomorrowCount = Task::query()
                ->where('telegram_chat_id', $chatId)
                ->where('status', 'pending')
                ->whereDate('due_date', $tomorrow)
                ->count();
            $weekCount = Task::query()
                ->where('telegram_chat_id', $chatId)
                ->where('status', 'pending')
                ->whereBetween('due_date', [$tomorrow, $endOfWeek])
                ->count();

            $telegram->sendMessage(
                (int) $chatId,
                "Daily briefing\nToday: {$todayCount}\nTomorrow: {$tomorrowCount}\nThis week: {$weekCount}"
            );
        }

        $this->info('Daily digest sent.');

        return self::SUCCESS;
    }
}
