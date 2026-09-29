<?php

namespace App\Http\Controllers\Vlf;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Vlf\Client;
use App\Models\Vlf\Setting;
use App\Models\Vlf\Staff;
use App\Support\VlfAccounts;
use App\Support\VlfNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Account requests made from the sign-in page. A partner or the administrator
 * decides what each person becomes: a staff member (with a job title) or a client
 * contact (linked to an existing client or a new one). Until then they can't sign in.
 */
class SignupController extends Controller
{
    public function approve(Request $request, User $user): JsonResponse
    {
        abort_unless($user->isPendingSignup(), 404);

        $data = $request->validate([
            'as' => 'required|in:staff,client',
            'title' => 'required_if:as,staff|nullable|string|max:100',
            'rate' => 'nullable|integer|min:0|max:10000000',
            'clientId' => 'nullable|integer|exists:vlf_clients,id',
            'clientName' => 'required_if:as,client|nullable|string|max:255',
        ]);

        if ($data['as'] === 'staff') {
            abort_if(Staff::where('name', $user->name)->exists(), 422, "A staff member called {$user->name} already exists.");
        }

        $result = DB::transaction(function () use ($user, $data) {
            if ($data['as'] === 'staff') {
                $staff = Staff::create([
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'initials' => $user->initials(),
                    'role' => $data['title'],
                    'email' => $user->email,
                    'rate' => $data['rate'] ?? 0,
                    'status' => 'Available',
                ]);
                $user->update(['role' => VlfAccounts::roleForTitle($data['title']), 'client_id' => null]);

                return ['staff' => $staff->fresh()->toClient()];
            }

            $client = ! empty($data['clientId'])
                ? Client::findOrFail($data['clientId'])
                : Client::create([
                    'name' => $data['clientName'],
                    'type' => $user->signup_organisation ? 'Company' : 'Individual',
                    'contact_name' => $user->name,
                    'contact_email' => $user->email,
                    'contact_phone' => $user->phone,
                ]);
            $user->update(['role' => 'client', 'client_id' => $client->id]);

            return ['client' => $client->load('matters')->toClient()];
        });

        $user->update(['active' => true, 'signup_status' => null]);
        VlfNotifier::notify($user->name, 'info', 'Your '.$this->firmName().' account has been approved. You can sign in now at '.route('login').'.', null, 'Account approved');

        return response()->json($result + ['approved' => $user->id]);
    }

    public function decline(User $user): JsonResponse
    {
        abort_unless($user->isPendingSignup(), 404);

        $email = $user->email;
        $user->delete();

        try {
            Mail::raw('Thank you for your interest. '.$this->firmName().' was not able to approve your account request. If you think this is a mistake, please contact the firm directly.', function ($message) use ($email) {
                $message->to($email)->subject('[VLF] Account request');
            });
        } catch (Throwable $e) {
            Log::warning('VLF decline email failed', ['to' => $email, 'error' => $e->getMessage()]);
        }

        return response()->json(['declined' => true]);
    }

    /** What the People screen shows for each waiting request. */
    public static function pending(): array
    {
        return User::where('signup_status', 'pending')->orderBy('id')->get()->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'phone' => $u->phone,
            'as' => $u->signup_as,
            'organisation' => $u->signup_organisation,
            'requestedAt' => $u->created_at?->toIso8601String(),
        ])->all();
    }

    private function firmName(): string
    {
        return (string) (Setting::where('key', 'firm_name')->first()?->value ?: 'GAVEL.CO');
    }
}
