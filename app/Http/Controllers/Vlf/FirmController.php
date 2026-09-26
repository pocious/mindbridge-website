<?php

namespace App\Http\Controllers\Vlf;

use App\Http\Controllers\Controller;
use App\Models\Vlf\Notification;
use App\Models\Vlf\Setting;
use App\Models\Vlf\Staff;
use App\Support\VlfNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
            'email' => 'nullable|email|max:255',
            'rate' => 'nullable|integer|min:0|max:10000000',
            'status' => 'nullable|string|max:50',
        ]);

        $staff = Staff::create([
            'name' => $data['name'],
            'initials' => Str::upper(collect(explode(' ', $data['name']))->filter()->map(fn ($p) => $p[0])->take(2)->implode('')),
            'role' => $data['role'],
            'email' => $data['email'] ?? null,
            'rate' => $data['rate'] ?? 0,
            'status' => $data['status'] ?? 'Available',
        ]);

        return response()->json($staff->toClient(), 201);
    }

    public function updateStaff(Request $request, Staff $staff): JsonResponse
    {
        $data = $request->validate([
            'role' => 'sometimes|string|max:100',
            'email' => 'sometimes|nullable|email|max:255',
            'rate' => 'sometimes|integer|min:0|max:10000000',
            'status' => 'sometimes|string|max:50',
            'active' => 'sometimes|boolean',
        ]);

        $staff->update($data);

        return response()->json($staff->toClient());
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

    public function notifications(Request $request): JsonResponse
    {
        $recipient = $request->validate(['recipient' => 'required|string|max:100'])['recipient'];

        return response()->json(
            Notification::where('recipient', $recipient)->latest('id')->limit(50)->get()->map->toClient()
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
        $notification->update(['read_at' => $notification->read_at ?? now()]);

        return response()->json($notification->toClient());
    }

    public function readAllNotifications(Request $request): JsonResponse
    {
        $recipient = $request->validate(['recipient' => 'required|string|max:100'])['recipient'];

        $count = Notification::where('recipient', $recipient)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['marked' => $count]);
    }
}
