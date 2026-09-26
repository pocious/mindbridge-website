<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Vlf\FirmController;
use App\Models\Vlf\Client;
use App\Models\Vlf\Comment;
use App\Models\Vlf\CourtEvent;
use App\Models\Vlf\Deadline;
use App\Models\Vlf\Document;
use App\Models\Vlf\Invoice;
use App\Models\Vlf\Matter;
use App\Models\Vlf\Message;
use App\Models\Vlf\Notification;
use App\Models\Vlf\Setting;
use App\Models\Vlf\Staff;
use App\Models\Vlf\Task;
use App\Models\Vlf\TimeEntry;
use App\Support\VlfNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * JSON API for the VLF prototype page (public/vlf-fixed.html).
 * Response shapes mirror the in-page JavaScript objects so public/js/vlf-api.js
 * can drop them straight into TASKS, TIME_ENTRIES, INVOICES, etc.
 */
class VlfController extends Controller
{
    public function state(): JsonResponse
    {
        return response()->json([
            'matters' => Matter::with('client')->orderBy('ref')->get()->mapWithKeys(fn (Matter $m) => [$m->ref => $m->toClient()]),
            'clients' => Client::with('matters')->orderBy('name')->get()->map->toClient(),
            'events' => CourtEvent::orderBy('date')->orderBy('time')->get()->map->toClient(),
            'deadlines' => Deadline::orderBy('due_date')->orderBy('due_time')->get()->map->toClient(),
            'staff' => Staff::orderBy('id')->get()->map->toClient(),
            'settings' => (object) Setting::whereIn('key', FirmController::SETTING_KEYS)->pluck('value', 'key')->all(),
            'notifications' => Notification::latest('id')->limit(200)->get()->groupBy('recipient')->map(fn ($rows) => $rows->map->toClient()->values()),
            'tasks' => Task::orderBy('id')->get()->map->toClient(),
            'timeEntries' => TimeEntry::orderByDesc('id')->get()->map->toClient(),
            'invoices' => Invoice::orderBy('id')->get()->map->toClient(),
            'messages' => Message::orderBy('id')->get()->groupBy('channel')->map(fn ($rows) => $rows->map->toClient()->values()),
            'comments' => Comment::orderBy('id')->get()->groupBy('matter_ref')->map(fn ($rows) => $rows->map->toClient()->values()),
            'documents' => Document::all()->mapWithKeys(fn (Document $d) => [$d->key => $d->toClient()]),
        ]);
    }

    public function storeTask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'matter' => 'required|string|max:50|exists:vlf_matters,ref',
            'matterTitle' => 'nullable|string|max:255',
            'class' => 'required|in:A,B,C',
            'title' => 'required|string|max:255',
            'desc' => 'nullable|string|max:5000',
            'priority' => 'nullable|string|max:20',
            'assignedTo' => 'required|string|max:100',
            'assignedBy' => 'nullable|string|max:100',
            'deadline' => 'nullable|string|max:100',
            'estimatedTime' => 'nullable|string|max:30',
            'billable' => 'boolean',
            'relatedDoc' => 'nullable|string|max:255',
        ]);

        $task = Task::create([
            'code' => (string) Str::uuid(),
            'matter_ref' => $data['matter'],
            'matter_title' => $data['matterTitle'] ?? null,
            'class' => $data['class'],
            'title' => $data['title'],
            'description' => $data['desc'] ?? null,
            'priority' => $data['priority'] ?? null,
            'assigned_to' => $data['assignedTo'],
            'assigned_by' => $data['assignedBy'] ?? null,
            'deadline' => $data['deadline'] ?? null,
            'status' => 'PENDING',
            'estimated_time' => $data['estimatedTime'] ?? null,
            'billable' => $data['billable'] ?? true,
            'related_doc' => $data['relatedDoc'] ?? null,
        ]);
        $task->update(['code' => sprintf('TSK-KSC-%s-%03d', substr($task->matter_ref, -4), 100 + $task->id)]);

        if ($task->assigned_to !== $task->assigned_by) {
            VlfNotifier::notify($task->assigned_to, 'task',
                ($task->assigned_by ?? 'A colleague')." assigned you: {$task->title} ({$task->matter_ref}, Class {$task->class})".($task->deadline ? " — due {$task->deadline}" : '').'.',
                ['matter' => $task->matter_ref, 'tab' => 'work']);
        }

        return response()->json($task->toClient(), 201);
    }

    public function updateTask(Request $request, string $code): JsonResponse
    {
        $task = Task::where('code', $code)->firstOrFail();

        $data = $request->validate([
            'status' => 'required|in:PENDING,IN_PROGRESS,BLOCKED,DONE',
            'blockedBy' => 'required_if:status,BLOCKED|nullable|string|max:255',
            'actor' => 'nullable|string|max:100',
        ]);

        $task->update([
            'status' => $data['status'],
            'blocked_by' => $data['status'] === 'BLOCKED' ? $data['blockedBy'] : null,
        ]);

        $actor = $data['actor'] ?? $task->assigned_to;
        if (in_array($data['status'], ['DONE', 'BLOCKED'], true) && $task->assigned_by && $task->assigned_by !== $actor) {
            VlfNotifier::notify($task->assigned_by, $data['status'] === 'DONE' ? 'info' : 'warn',
                $data['status'] === 'DONE'
                    ? "{$actor} completed: {$task->title} ({$task->matter_ref})."
                    : "{$actor} is blocked on: {$task->title} — {$task->blocked_by}",
                ['matter' => $task->matter_ref, 'tab' => 'work'],
                $data['status'] === 'DONE' ? 'Task completed' : 'Task blocked');
        }

        return response()->json($task->toClient());
    }

    public function storeTimeEntry(Request $request): JsonResponse
    {
        $data = $request->validate([
            'matter' => 'required|string|max:50|exists:vlf_matters,ref',
            'task' => 'nullable|string|max:50',
            'desc' => 'required|string|max:1000',
            'advocate' => 'required|string|max:100',
            'durationMins' => 'required|integer|min:1|max:1440',
            'billable' => 'boolean',
            'rate' => 'required|integer|min:0|max:10000000',
        ]);

        $mins = $data['durationMins'];
        $billable = $data['billable'] ?? true;

        $entry = TimeEntry::create([
            'code' => (string) Str::uuid(),
            'matter_ref' => $data['matter'],
            'task_code' => $data['task'] ?? null,
            'description' => $data['desc'],
            'advocate' => $data['advocate'],
            'duration' => intdiv($mins, 60) > 0 ? intdiv($mins, 60).'h '.($mins % 60).'m' : $mins.'m',
            'duration_mins' => $mins,
            'billable' => $billable,
            'rate' => $data['rate'],
            'amount' => $billable ? (int) round($mins / 60 * $data['rate']) : 0,
        ]);
        $entry->update(['code' => sprintf('TE-%03d', $entry->id)]);
        Staff::where('name', $entry->advocate)->increment('month_hours', round($mins / 60, 1));

        return response()->json($entry->toClient(), 201);
    }

    public function storeMessage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel' => 'required|string|max:100',
            'from' => 'required|string|max:8',
            'name' => 'nullable|string|max:100',
            'text' => 'required|string|max:5000',
        ]);

        $message = Message::create([
            'channel' => $data['channel'],
            'author_av' => $data['from'],
            'author_name' => $data['name'] ?? null,
            'text' => $data['text'],
            'mine' => true,
        ]);

        if ($message->channel === 'equity-client' && $message->author_av !== 'JO') {
            VlfNotifier::notify('James Opolot', 'info', ($message->author_name ?? 'Your legal team').': '.Str::limit($message->text, 160), ['page' => 'cli-messages'], 'New message');
        }

        return response()->json($message->toClient(), 201);
    }

    public function storeComment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'matter' => 'required|string|max:50|exists:vlf_matters,ref',
            'author' => 'required|string|max:100',
            'av' => 'required|string|max:8',
            'context' => 'nullable|string|max:255',
            'text' => 'required|string|max:5000',
        ]);

        $comment = Comment::create([
            'matter_ref' => $data['matter'],
            'author' => $data['author'],
            'author_av' => $data['av'],
            'context' => $data['context'] ?? null,
            'text' => $data['text'],
            'replies' => [],
        ]);

        return response()->json($comment->toClient(), 201);
    }

    public function updateInvoice(Request $request, string $code): JsonResponse
    {
        $invoice = Invoice::where('code', $code)->firstOrFail();
        abort_unless(in_array($invoice->status, ['DRAFT', 'APPROVED'], true), 422,
            'This invoice has been issued — issued invoices are amended with a credit note, not edited.');

        $data = $request->validate([
            'lines' => 'required|array|min:1|max:50',
            'lines.*.desc' => 'required|string|max:500',
            'lines.*.hours' => 'nullable|string|max:30',
            'lines.*.amount' => 'required|integer|min:0',
        ]);

        $lines = array_map(fn ($l) => [
            'desc' => $l['desc'],
            'hours' => $l['hours'] ?? null,
            'amount' => (int) $l['amount'],
        ], $data['lines']);

        // Changing an approved invoice sends it back for partner approval.
        $invoice->update(['lines' => $lines, 'total' => array_sum(array_column($lines, 'amount')), 'status' => 'DRAFT']);

        return response()->json($invoice->toClient());
    }

    public function updateMatter(Request $request, string $ref): JsonResponse
    {
        $matter = Matter::where('ref', $ref)->firstOrFail();

        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'court' => 'sometimes|nullable|string|max:255',
            'judge' => 'sometimes|nullable|string|max:255',
            'advocate' => 'sometimes|nullable|string|max:100',
            'stage' => 'sometimes|nullable|string|max:100',
            'supervisor' => 'sometimes|nullable|string|max:100',
            'statusLabel' => 'sometimes|nullable|string|max:100',
            'statusLevel' => 'sometimes|nullable|in:urgent,warn,ok',
            'riskLevel' => 'sometimes|nullable|string|max:20',
            'riskNote' => 'sometimes|nullable|string|max:1000',
        ]);

        $matter->update(collect($data)->mapWithKeys(fn ($v, $k) => [Str::snake($k) => $v])->all());

        return response()->json($matter->toClient());
    }

    public function updateDocument(Request $request, string $key): JsonResponse
    {
        abort_unless(preg_match('/^[A-Za-z0-9_-]{1,100}$/', $key), 404);

        $request->validate([
            'data' => 'required|array',
            'data.title' => 'required|string|max:255',
        ]);

        // validate() would return only data.title; the document is a free-form nested object.
        $document = Document::updateOrCreate(['key' => $key], ['data' => $request->input('data')]);

        return response()->json(['key' => $document->key, 'data' => $document->data]);
    }
}
