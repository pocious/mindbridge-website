<?php

namespace App\Http\Controllers\Vlf;

use App\Http\Controllers\Controller;
use App\Models\Vlf\CourtEvent;
use App\Models\Vlf\Deadline;
use App\Support\VlfNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Court diary events (hearings, mentions, conferences) and matter deadlines.
 */
class DiaryController extends Controller
{
    public function storeEvent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'matter' => 'required|string|max:50|exists:vlf_matters,ref',
            'title' => 'required|string|max:255',
            'court' => 'nullable|string|max:255',
            'judge' => 'nullable|string|max:255',
            'date' => 'required|date',
            'time' => 'nullable|string|max:20',
            'advocate' => 'nullable|string|max:100',
            'level' => 'nullable|in:critical,scheduled,complete',
            'notes' => 'nullable|string|max:2000',
            'checklist' => 'nullable|array|max:30',
            'checklist.*' => 'string|max:255',
        ]);

        $event = CourtEvent::create([
            'matter_ref' => $data['matter'],
            'title' => $data['title'],
            'court' => $data['court'] ?? null,
            'judge' => $data['judge'] ?? null,
            'date' => $data['date'],
            'time' => $data['time'] ?? null,
            'advocate' => $data['advocate'] ?? null,
            'level' => $data['level'] ?? 'scheduled',
            'notes' => $data['notes'] ?? null,
            'checklist' => array_map(fn ($text) => ['text' => $text, 'done' => false], $data['checklist'] ?? []),
        ]);

        VlfNotifier::notify($event->advocate, 'action',
            "Court event added: {$event->title} ({$event->matter_ref}) — {$event->date->format('D j M Y')}".($event->time ? " at {$event->time}" : '').'.',
            ['page' => 'adv-diary'], 'Hearing scheduled', 'notify_hearings');

        return response()->json($event->toClient(), 201);
    }

    public function updateEvent(Request $request, CourtEvent $event): JsonResponse
    {
        $data = $request->validate([
            'level' => 'sometimes|in:critical,scheduled,complete',
            'notes' => 'sometimes|nullable|string|max:2000',
            'checklist' => 'sometimes|array|max:30',
            'checklist.*.text' => 'required_with:checklist|string|max:255',
            'checklist.*.done' => 'required_with:checklist|boolean',
        ]);

        $event->update($data);

        return response()->json($event->toClient());
    }

    public function storeDeadline(Request $request): JsonResponse
    {
        $data = $request->validate([
            'matter' => 'required|string|max:50|exists:vlf_matters,ref',
            'title' => 'required|string|max:255',
            'dueDate' => 'required|date',
            'dueTime' => 'nullable|string|max:20',
            'owner' => 'nullable|string|max:100',
            'severity' => 'nullable|in:critical,warn,normal',
        ]);

        $deadline = Deadline::create([
            'matter_ref' => $data['matter'],
            'title' => $data['title'],
            'due_date' => $data['dueDate'],
            'due_time' => $data['dueTime'] ?? null,
            'owner' => $data['owner'] ?? null,
            'severity' => $data['severity'] ?? 'normal',
        ]);

        VlfNotifier::notify($deadline->owner, $deadline->severity === 'critical' ? 'critical' : 'action',
            "Deadline: {$deadline->title} ({$deadline->matter_ref}) — due {$deadline->due_date->format('D j M Y')}".($deadline->due_time ? " {$deadline->due_time}" : '').'.',
            ['page' => 'adv-deadlines'], 'Deadline set', 'notify_deadlines');

        return response()->json($deadline->toClient(), 201);
    }

    public function updateDeadline(Request $request, Deadline $deadline): JsonResponse
    {
        $data = $request->validate(['done' => 'required|boolean']);

        $deadline->update(['done_at' => $data['done'] ? now() : null]);

        return response()->json($deadline->toClient());
    }
}
