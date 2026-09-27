<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Vlf\Staff;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Creates a VLF sign-in account from the command line — used for the first partner or
 * administrator; everyone else is added from the app (People & Workload, Clients).
 *
 *   php artisan vlf:user owner@firm.com "Jane Namutebi" partner
 *   php artisan vlf:user admin@firm.com "Grace Akello" admin --password="a-strong-password"
 */
class CreateVlfUser extends Command
{
    protected $signature = 'vlf:user
        {email : Sign-in email address}
        {name : Full name as it should appear in the app}
        {role=partner : partner, associate, junior, clerk or admin}
        {--password= : Set this password instead of emailing a set-your-password link}
        {--rate=0 : Hourly rate in UGX, for time recording}';

    protected $description = 'Create a firm staff account for the VLF app';

    private const TITLES = ['partner' => 'Partner', 'associate' => 'Associate', 'junior' => 'Junior Associate', 'clerk' => 'Clerk', 'admin' => 'Firm Administrator'];

    public function handle(): int
    {
        $email = Str::lower($this->argument('email'));
        $name = trim($this->argument('name'));
        $role = $this->argument('role');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('That is not a valid email address.');

            return self::FAILURE;
        }
        if (! isset(self::TITLES[$role])) {
            $this->error('Role must be one of: '.implode(', ', array_keys(self::TITLES)).'. (Client accounts are created from the Clients screen.)');

            return self::FAILURE;
        }
        if (User::where('email', $email)->exists()) {
            $this->error("An account with {$email} already exists.");

            return self::FAILURE;
        }
        if (Staff::where('name', $name)->exists()) {
            $this->error("A staff member called {$name} already exists.");

            return self::FAILURE;
        }

        $password = $this->option('password');
        if ($password !== null && strlen($password) < 10) {
            $this->error('Use a password of at least 10 characters.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($email, $name, $role, $password) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password ?? Str::password(32),
                'role' => $role,
            ]);
            Staff::create([
                'user_id' => $user->id,
                'name' => $name,
                'initials' => $user->initials(),
                'role' => self::TITLES[$role],
                'email' => $email,
                'rate' => (int) $this->option('rate'),
                'status' => 'Available',
            ]);
        });

        if ($password === null) {
            Password::sendResetLink(['email' => $email]);
            $this->info("Account created for {$name} ({$role}). A set-your-password link has been emailed to {$email}.");
            if (config('mail.default') === 'log') {
                $this->warn('Mail is set to "log", so the link is in storage/logs/laravel.log rather than an inbox.');
            }
        } else {
            $this->info("Account created for {$name} ({$role}). They can sign in at ".url('/login').'.');
        }

        return self::SUCCESS;
    }
}
