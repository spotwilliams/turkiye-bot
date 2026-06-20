<?php

namespace App\Console\Commands;

use App\Mail\DailyDigestMail;
use App\Models\FamilyMember;
use App\Models\Task;
use App\Models\User;
use App\Services\TelegramService;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;

class SendDailyDigest extends Command
{
    protected $signature = 'digest:send';

    protected $description = 'Send the daily digest to Telegram chats and web task creators';

    private string $today;

    private string $tomorrow;

    private string $endOfWeek;

    public function handle(TelegramService $telegram): int
    {
        $this->today = now()->toDateString();
        $this->tomorrow = now()->addDay()->toDateString();
        $this->endOfWeek = now()->endOfWeek()->toDateString();

        $this->sendTelegramDigests($telegram);
        $this->sendEmailDigests();

        $this->info('Daily digest sent.');

        return self::SUCCESS;
    }

    private function sendTelegramDigests(TelegramService $telegram): void
    {
        $chatIds = FamilyMember::query()
            ->whereNotNull('telegram_chat_id')
            ->distinct()
            ->pluck('telegram_chat_id');

        foreach ($chatIds as $chatId) {
            [$today, $tomorrow, $week] = $this->counts(fn (Closure $base) => $base()->where('telegram_chat_id', $chatId));

            $text = ($today + $tomorrow + $week) === 0
                ? "Good morning! No school tasks pending — you're all set."
                : "Daily briefing\nToday: {$today}\nTomorrow: {$tomorrow}\nThis week: {$week}";

            $telegram->sendMessage((int) $chatId, $text);
        }
    }

    private function sendEmailDigests(): void
    {
        $userIds = Task::query()
            ->where('status', 'pending')
            ->whereNotNull('created_by')
            ->distinct()
            ->pluck('created_by');

        foreach ($userIds as $userId) {
            $user = User::find($userId);
            if ($user === null) {
                continue;
            }

            [$today, $tomorrow, $week] = $this->counts(fn (Closure $base) => $base()->where('created_by', $userId));

            Mail::to($user->email)->queue(new DailyDigestMail($today, $tomorrow, $week));
        }
    }

    /**
     * Count pending tasks in the today / tomorrow / rest-of-week buckets for a
     * recipient scope.
     *
     * @param  Closure(Closure():Builder<Task>): Builder<Task>  $scope
     * @return array{0: int, 1: int, 2: int}
     */
    private function counts(Closure $scope): array
    {
        $base = fn () => $scope(fn () => Task::query()->where('status', 'pending'));

        return [
            (clone $base())->whereDate('due_date', $this->today)->count(),
            (clone $base())->whereDate('due_date', $this->tomorrow)->count(),
            (clone $base())->whereBetween('due_date', [$this->tomorrow, $this->endOfWeek])->count(),
        ];
    }
}
