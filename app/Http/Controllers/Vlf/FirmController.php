<?php

namespace App\Http\Controllers\Vlf;

use App\Http\Controllers\Controller;
use App\Models\Vlf\Notification;
use App\Models\Vlf\Setting;
use App\Models\Vlf\Staff;
use App\Support\VlfAccounts;
use App\Support\VlfNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Firm administration: staff, firm settings and notifications.
 */
class FirmController extends Controller
{
    public const SETTING_KEYS = ['firm_name', 'firm_address', 'notify_deadlines', 'notify_hearings', 'notify_invoices', 'notify_unassigned'];

    public function storeStaff(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100|unique:vlf_staff,name',
            'role' => 'required|string|max:100',
            'email' => 'nullable|email|max:255|unique:users,email',
            'rate' => 'nullable|integer|min:0|max:10000000',
            'status' => 'nullable|string|max:50',
        ]);

        $staff = DB::transaction(function () use ($data) {
            $staff = Staff::create([
                'name' => $data['name'],
                'initials' => Str::upper(collect(explode(' ', $data['name']))->filter()->map(fn ($p) => $p[0])->take(2)->implode('')),
                'role' => $data['role'],
                'email' => $data['email'] ?? null,
                'rate' => $data['rate'] ?? 0,
                'status' => $data['status'] ?? 'Available',
            ]);
            // With an email, the staff member gets an account and a "set your password" email.
            VlfAccounts::forStaff($staff);

            return $staff;
        });

        return response()->json($staff->fresh()->toClient(), 201);
    }

    public function updateStaff(Request $request, Staff $staff): JsonResponse
    {
        $data = $request->validate([
            'role' => 'sometimes|string|max:100',
            'email' => ['sometimes', 'nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff->user_id)],
            'rate' => 'sometimes|integer|min:0|max:10000000',
            'status' => 'sometimes|string|max:50',
            'active' => 'sometimes|boolean',
        ]);

        abort_if(($data['active'] ?? true) === false && $staff->user_id === $request->user()->id, 422, 'You can’t deactivate your own account.');

        DB::transaction(function () use ($staff, $data) {
            $staff->update($data);
            if ($staff->user) {
                // Keep the sign-in account in step: role follows the job title; deactivating blocks sign-in.
                $staff->user->update(array_filter([
                    'email' => $staff->email,
                    'role' => VlfAccounts::roleForTitle($staff->role),
                    'active' => $staff->active,
                ], fn ($v) => $v !== null));
            } elseif ($staff->email) {
                VlfAccounts::forStaff($staff);
            }
        });

        return response()->json($staff->fresh()->toClient());
    }

    /** Resend the "set your password" email. */
    public function inviteStaff(Staff $staff): JsonResponse
    {
        abort_unless($staff->email, 422, 'Add an email address first.');
        $user = $staff->user ?? VlfAccounts::forStaff($staff, false);
        Password::sendResetLink(['email' => $user->email]);

        return response()->json($staff->fresh()->toClient());
    }

    public function updateSetting(Request $request, string $key): JsonResponse
    {
        abort_unless(in_array($key, self::SETTING_KEYS, true), 404);

        $value = str_starts_with($key, 'notify_')
            ? $request->validate(['value' => 'required|boolean'])['value']
            : $request->validate(['value' => 'required|string|max:255'])['value'];

        Setting::updateOrCreate(['key' => $key], ['value' => $value]);

        return response()->json(['key' => $key, 'value' => $value]);
    }

    /** The signed-in person's own notifications (never anyone else's). */
    public function notifications(Request $request): JsonResponse
    {
        return response()->json(
            Notification::where('recipient', $request->user()->name)->latest('id')->limit(50)->get()->map->toClient()
        );
    }

    public function storeNotification(Request $request): JsonResponse
    {
        $data = $request->validate([
            'recipient' => 'required|string|max:100',
            'type' => 'required|in:critical,action,task,info,warn',
            'text' => 'required|string|max:1000',
            'label' => 'nullable|string|max:100',
            'link' => 'nullable|array',
            'link.matter' => 'nullable|string|max:50',
            'link.tab' => 'nullable|string|max:30',
            'link.doc' => 'nullable|string|max:100',
            'link.page' => 'nullable|string|max:50',
        ]);

        $notification = VlfNotifier::notify($data['recipient'], $data['type'], $data['text'], $data['link'] ?? null, $data['label'] ?? null);

        return response()->json($notification->toClient(), 201);
    }

    public function readNotification(Notification $notification): JsonResponse
    {
        Gate::authorize('update', $notification);
        $notification->update(['read_at' => $notification->read_at ?? now()]);

        return response()->json($notification->toClient());
    }

    public function readAllNotifications(Request $request): JsonResponse
    {
        $count = Notification::where('recipient', $request->user()->name)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['marked' => $count]);
    }
}
