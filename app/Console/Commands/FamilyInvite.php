<?php

namespace App\Console\Commands;

use App\Models\FamilyInvite as FamilyInviteModel;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class FamilyInvite extends Command
{
    protected $signature = 'family:invite
        {--name= : Name to bake into the invite}
        {--role= : Role (father|mother)}
        {--expires-in-hours= : Optional expiry in hours}';

    protected $description = 'Generate a single-use Telegram bot invite code for a new family member.';

    public function handle(): int
    {
        $name = (string) $this->option('name');
        $role = (string) $this->option('role');

        if ($name === '' || $role === '') {
            $this->error('Both --name and --role are required.');

            return self::FAILURE;
        }

        $expiresAt = null;
        if ($hours = $this->option('expires-in-hours')) {
            $expiresAt = now()->addHours((int) $hours);
        }

        $code = Str::random(32);

        FamilyInviteModel::create([
            'code' => $code,
            'name' => $name,
            'role' => $role,
            'expires_at' => $expiresAt,
        ]);

        $this->info("Invite created for {$name} ({$role}).");
        $this->line('Code: '.$code);
        $this->line('Have the recipient send:  /start '.$code);

        return self::SUCCESS;
    }
}
