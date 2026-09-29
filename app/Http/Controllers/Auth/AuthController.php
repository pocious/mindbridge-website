<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\VlfNotifier;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Sign in, sign out, account requests and password reset for the VLF app.
 * Accounts the firm creates use the reset link to set their first password;
 * people who ask for an account here wait for a partner or the administrator to approve it.
 */
class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $key = 'login:'.Str::lower($credentials['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many sign-in attempts. Try again in '.RateLimiter::availableIn($key).' seconds.',
            ]);
        }

        if (! Auth::attempt($credentials + ['active' => true], $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);

            // Only someone who knows the password learns that their request is still waiting.
            $pending = User::where('email', $credentials['email'])->where('signup_status', 'pending')->first();
            if ($pending && Hash::check($credentials['password'], $pending->password)) {
                throw ValidationException::withMessages(['email' => 'Your account request is waiting for the firm to approve it. You’ll get an email when it’s ready.']);
            }

            throw ValidationException::withMessages(['email' => 'Those details don’t match an active account.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended(route('app'));
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:50',
            'signup_as' => 'required|in:client,staff',
            'organisation' => 'nullable|string|max:255',
            'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->numbers()],
        ], [
            'email.unique' => 'That email already has an account. Sign in, or use “Forgot password?”.',
        ]);

        // No access until approved: inactive, and not linked to any client or staff record.
        $user = User::create([
            'name' => trim($data['name']),
            'email' => Str::lower($data['email']),
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'role' => 'client',
            'active' => false,
            'signup_status' => 'pending',
            'signup_as' => $data['signup_as'],
            'signup_organisation' => $data['organisation'] ?? null,
        ]);

        $what = $user->signup_as === 'staff' ? 'a staff account' : 'a client account'.($user->signup_organisation ? ' for '.$user->signup_organisation : '');
        User::whereIn('role', ['partner', 'admin'])->where('active', true)->pluck('name')->each(
            fn (string $admin) => VlfNotifier::notify($admin, 'action', "{$user->name} ({$user->email}) asked for {$what}. Approve or decline it under People.", ['page' => 'adm-people'], 'Account request')
        );

        return redirect()->route('login')->with('status', 'Thanks — your request has been sent. You can sign in as soon as the firm approves it.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function showForgot(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => 'required|email']);

        Password::sendResetLink($request->only('email'));

        // Same answer whether or not the address has an account, so accounts can't be discovered.
        return back()->with('status', 'If that email has an account, a link to set a new password is on its way.');
    }

    public function showReset(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->numbers()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return redirect()->route('login')->with('status', 'Password set. You can sign in now.');
    }
}
