<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class MakeAdministrator extends Command
{
    protected $signature = 'user:make-admin {email : E-mail of the administrator} {--name= : Display name for a new account}';

    protected $description = 'Creates or promotes a local administrator account.';

    public function handle(): int
    {
        $email = strtolower((string) $this->argument('email'));
        $user = User::where('email', $email)->first();

        if ($user) {
            $user->update(['role' => 'administrator']);
            $this->info("{$user->email} is now an administrator.");

            return self::SUCCESS;
        }

        $name = (string) ($this->option('name') ?: $this->ask('Name'));
        $password = (string) $this->secret('Password (minimum 12 characters)');

        if (mb_strlen($password) < 12) {
            $this->error('The password must contain at least 12 characters.');

            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'role' => 'administrator',
            'password' => Hash::make($password),
        ]);

        $this->info("Administrator {$email} created.");

        return self::SUCCESS;
    }
}
