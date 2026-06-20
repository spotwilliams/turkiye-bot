<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class UserCreate extends Command
{
    protected $signature = 'user:create
        {--name= : Full name of the parent}
        {--email= : Login email}
        {--password= : Initial password}';

    protected $description = 'Create a verified web account for a parent (the only way to mint web logins).';

    public function handle(): int
    {
        $name = (string) ($this->option('name') ?: $this->ask('Name'));
        $email = (string) ($this->option('email') ?: $this->ask('Email'));
        $password = (string) ($this->option('password') ?: $this->secret('Password'));

        if (User::where('email', $email)->exists()) {
            $this->error("A user with email {$email} already exists.");

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        $this->info("Account created for {$name} <{$email}>.");

        return self::SUCCESS;
    }
}
