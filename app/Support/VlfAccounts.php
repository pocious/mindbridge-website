<?php

namespace App\Support;

use App\Models\User;
use App\Models\Vlf\Client;
use App\Models\Vlf\Staff;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates sign-in accounts for staff and client contacts. New accounts get a random
 * password and a "set your password" email (Laravel's password-reset link).
 */
class VlfAccounts
{
    /** Map a staff job title ("Senior Partner", "Pupil Advocate") to an access role. */
    public static function roleForTitle(string $title): string
    {
        return match (true) {
            str_contains($title, 'Partner') => 'partner',
            str_contains($title, 'Administrator') => 'admin',
            str_contains($title, 'Junior') || str_contains($title, 'Pupil') => 'junior',
            str_contains($title, 'Clerk') || str_contains($title, 'Assistant') => 'clerk',
            default => 'associate',
        };
    }

    public static function forStaff(Staff $staff, bool $sendInvite = true): ?User
    {
        if (! $staff->email) {
            return null;
        }

        $user = User::where('email', $staff->email)->first();
        if ($user && $user->isClient()) {
            throw ValidationException::withMessages(['email' => 'That email belongs to a client account.']);
        }

        $user ??= User::create([
            'name' => $staff->name,
            'email' => $staff->email,
            'password' => Str::password(32),
            'role' => self::roleForTitle($staff->role),
        ]);
        // A just-created staff row has no "active" value in memory until reloaded (the column defaults to true).
        $user->update(['name' => $staff->name, 'role' => self::roleForTitle($staff->role), 'active' => $staff->active ?? true]);
        $staff->update(['user_id' => $user->id]);

        if ($sendInvite && $user->wasRecentlyCreated) {
            Password::sendResetLink(['email' => $user->email]);
        }

        return $user;
    }

    public static function forClientContact(Client $client, bool $sendInvite = true): User
    {
        if (! $client->contact_email || ! $client->contact_name) {
            throw ValidationException::withMessages(['contactEmail' => 'Add the contact person’s name and email before inviting them.']);
        }

        $user = User::where('email', $client->contact_email)->first();
        if ($user && ! ($user->isClient() && $user->client_id === $client->id)) {
            throw ValidationException::withMessages(['contactEmail' => 'That email already has an account.']);
        }

        $user ??= User::create([
            'name' => $client->contact_name,
            'email' => $client->contact_email,
            'password' => Str::password(32),
            'role' => 'client',
            'client_id' => $client->id,
        ]);

        if ($sendInvite) {
            Password::sendResetLink(['email' => $user->email]);
        }

        return $user;
    }
}
