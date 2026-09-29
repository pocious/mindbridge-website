<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Vlf\FirmController;
use App\Http\Controllers\Vlf\SignupController;
use App\Models\User;
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
use App\Support\VlfAccess;
use App\Support\VlfNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * JSON API for the VLF app (resources/vlf/app.html).
 * Response shapes mirror the in-page JavaScript objects so public/js/vlf-api.js
 * can drop them straight into TASKS, TIME_ENTRIES, INVOICES, etc.
 * The acting person is always the signed-in user.
 */
class VlfController extends Controller
{
    /** Document fields only the review workflow may change — never a plain save. */
    private const DOCUMENT_WORKFLOW_FIELDS = ['currentStatus', 'currentLifecycleStep', 'approvalChain', 'history', 'reviewer', 'returnReason', 'author', 'file'];

    /**
     * Everything the page needs, limited to what this person may see.
     * Clients get only their own organisation's matters, issued invoices, client-approved
     * documents and client channels; firm-internal records (tasks, time, comments,
     * deadlines, staff) are not sent to them at all.
     */
    public function state(Request $request): JsonResponse
    {
        $user = $request->user();
        $refs = VlfAccess::matterRefs($user);
        $inScope = fn ($query, string $column = 'matter_ref') => $refs === null ? $query : $query->whereIn($column, $refs);
        $staffOnly = fn (callable $load) => $user->isStaff() ? $load() : [];

        return response()->json([
            'me' => $user->toClient(),
            'matters' => $inScope(Matter::with('client'), 'ref')->orderBy('ref')->get()->mapWithKeys(fn (Matter $m) => [$m->ref => $m->toClient()]),
            'clients' => ($user->isStaff() ? Client::query() : Client::whereKey($user->client_id))->with('matters')->orderBy('name')->get()->map->toClient(),
            'events' => $inScope(CourtEvent::query())->orderBy('date')->orderBy('time')->get()->map->toClient(),
            'deadlines' => $staffOnly(fn () => Deadline::orderBy('due_date')->orderBy('due_time')->get()->map->toClient()),
            'staff' => $staffOnly(fn () => Staff::orderBy('id')->get()->map->toClient()),
            'signupRequests' => $user->isFirmAdmin() ? SignupController::pending() : [],
            'settings' => (object) Setting::whereIn('key', FirmController::SETTING_KEYS)->pluck('value', 'key')->all(),
            'notifications' => (object) [$user->name => Notification::where('recipient', $user->name)->latest('id')->limit(100)->get()->map->toClient()],
            'tasks' => $staffOnly(fn () => Task::orderBy('id')->get()->map->toClient()),
            'timeEntries' => $staffOnly(fn () => TimeEntry::orderByDesc('id')->get()->map->toClient()),
            'invoices' => Invoice::orderBy('id')->get()->filter(fn (Invoice $i) => Gate::forUser($user)->allows('view', $i))->values()->map->toClient(),
            'messages' => Message::orderBy('id')->get()
                ->filter(fn (Message $m) => $this->canUseChannel($user, $m->channel))
                ->groupBy('channel')->map(fn ($rows) => $rows->map(fn (Message $m) => $m->toClient($user))->values()),
            'comments' => $staffOnly(fn () => Comment::orderBy('id')->get()->groupBy('matter_ref')->map(fn ($rows) => $rows->map->toClient()->values())),
            'documents' => (object) Document::all()->filter(fn (Document $d) => Gate::forUser($user)->allows('view', $d))
                ->mapWithKeys(fn (Document $d) => [$d->key => $d->toClient()])->all(),
        ]);
    }

    public function storeTask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'matter' => 'required|string|max:50|exists:vlf_matters,ref',
            'class' => 'required|in:A,B,C',
            'title' => 'required|string|max:255',
            'desc' => 'nullable|string|max:5000',
            'priority' => 'nullable|string|max:20',
            'assignedTo' => 'required|string|max:100|exists:vlf_staff,name',
            'deadline' => 'nullable|string|max:100',
            'estimatedTime' => 'nullable|string|max:30',
            'billable' => 'boolean',
            'relatedDoc' => 'nullable|string|max:255',
        ]);

        $assigner = $request->user()->name;
        $task = Task::create([
            'code' => (string) Str::uuid(),
            'matter_ref' => $data['matter'],
            'matter_title' => Matter::where('ref', $data['matter'])->value('title'),
            'class' => $data['class'],
            'title' => $data['title'],
            'description' => $data['desc'] ?? null,
            'priority' => $data['priority'] ?? null,
            'assigned_to' => $data['assignedTo'],
            'assigned_by' => $assigner,
            'deadline' => $data['deadline'] ?? null,
            'status' => 'PENDING',
            'estimated_time' => $data['estimatedTime'] ?? null,
            'billable' => $data['billable'] ?? true,
            'related_doc' => $data['relatedDoc'] ?? null,
        ]);
        $task->update(['code' => sprintf('TSK-%s-%03d', substr($task->matter_ref, -4), 100 + $task->id)]);

        if ($task->assigned_to !== $assigner) {
            VlfNotifier::notify($task->assigned_to, 'task',
                "{$assigner} assigned you: {$task->title} ({$task->matter_ref}, Class {$task->class})".($task->deadline ? " — due {$task->deadline}" : '').'.',
                ['matter' => $task->matter_ref, 'tab' => 'work']);
        }

        return response()->json($task->toClient(), 201);
    }

    public function updateTask(Request $request, string $code): JsonResponse
    {
        $task = Task::where('code', $code)->firstOrFail();
        $user = $request->user();
        abort_unless(in_array($user->name, [$task->assigned_to, $task->assigned_by], true) || $user->isPartner(), 403,
            'Only the person assigned, the person who assigned it, or a partner can change this task.');

        $data = $request->validate([
            'status' => 'required|in:PENDING,IN_PROGRESS,BLOCKED,DONE',
            'blockedBy' => 'required_if:status,BLOCKED|nullable|string|max:255',
        ]);

        $task->update([
            'status' => $data['status'],
            'blocked_by' => $data['status'] === 'BLOCKED' ? $data['blockedBy'] : null,
        ]);

        $actor = $user->name;
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

    /** Time is always logged as the signed-in person, at the rate on their staff record. */
    public function storeTimeEntry(Request $request): JsonResponse
    {
        $data = $request->validate([
            'matter' => 'required|string|max:50|exists:vlf_matters,ref',
            'task' => 'nullable|string|max:50',
            'desc' => 'required|string|max:1000',
            'durationMins' => 'required|integer|min:1|max:1440',
            'billable' => 'boolean',
        ]);

        $user = $request->user();
        $staff = Staff::where('user_id', $user->id)->first() ?? Staff::where('name', $user->name)->first();
        $rate = (int) ($staff?->rate ?? 0);
        $mins = $data['durationMins'];
        $billable = $data['billable'] ?? true;

        $entry = TimeEntry::create([
            'code' => (string) Str::uuid(),
            'matter_ref' => $data['matter'],
            'task_code' => $data['task'] ?? null,
            'description' => $data['desc'],
            'advocate' => $user->name,
            'duration' => intdiv($mins, 60) > 0 ? intdiv($mins, 60).'h '.($mins % 60).'m' : $mins.'m',
            'duration_mins' => $mins,
            'billable' => $billable,
            'rate' => $rate,
            'amount' => $billable ? (int) round($mins / 60 * $rate) : 0,
        ]);
        $entry->update(['code' => sprintf('TE-%03d', $entry->id)]);
        $staff?->increment('month_hours', round($mins / 60, 1));

        return response()->json($entry->toClient(), 201);
    }

    /**
     * Channels: "matter-{ref}" (firm-internal), "client-{ref}" (firm and that matter's client),
     * "dm-{staffId}-{staffId}" (two staff members). Anything else is firm-internal.
     */
    public function canUseChannel(User $user, string $channel): bool
    {
        if (str_starts_with($channel, 'client-')) {
            return VlfAccess::canSeeMatter($user, substr($channel, 7));
        }
        if (! $user->isStaff()) {
            return false;
        }
        if (preg_match('/^dm-(\d+)-(\d+)$/', $channel, $m)) {
            $staffId = Staff::where('user_id', $user->id)->value('id');

            return $staffId !== null && in_array((string) $staffId, [$m[1], $m[2]], true);
        }

        return true;
    }

    public function storeMessage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel' => 'required|string|max:100',
            'text' => 'required|string|max:5000',
        ]);

        $user = $request->user();
        abort_unless($this->canUseChannel($user, $data['channel']), 403, 'You can’t post in this conversation.');

        $message = Message::create([
            'channel' => $data['channel'],
            'user_id' => $user->id,
            'author_av' => $user->initials(),
            'author_name' => $user->name,
            'text' => $data['text'],
            'mine' => true,
        ]);

        $preview = $user->name.': '.Str::limit($message->text, 160);
        if (str_starts_with($message->channel, 'client-')) {
            $matter = Matter::where('ref', substr($message->channel, 7))->first();
            if ($user->isStaff()) {
                User::where('client_id', $matter?->client_id)->where('active', true)->pluck('name')
                    ->each(fn ($name) => VlfNotifier::notify($name, 'info', $preview, ['page' => 'cli-messages'], 'New message'));
            } else {
                VlfNotifier::notify($matter?->advocate, 'action', $preview, ['page' => 'adv-comms'], 'Client message');
            }
        } elseif (preg_match('/^dm-(\d+)-(\d+)$/', $message->channel, $m)) {
            Staff::whereIn('id', [$m[1], $m[2]])->where('name', '!=', $user->name)->pluck('name')
                ->each(fn ($name) => VlfNotifier::notify($name, 'info', $preview, ['page' => 'adv-comms'], 'Direct message'));
        }

        return response()->json($message->toClient($user), 201);
    }

    public function storeComment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'matter' => 'required|string|max:50|exists:vlf_matters,ref',
            'context' => 'nullable|string|max:255',
            'text' => 'required|string|max:5000',
        ]);

        $user = $request->user();
        $comment = Comment::create([
            'matter_ref' => $data['matter'],
            'user_id' => $user->id,
            'author' => $user->name,
            'author_av' => $user->initials(),
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
        Gate::authorize('update', $matter);

        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'court' => 'sometimes|nullable|string|max:255',
            'judge' => 'sometimes|nullable|string|max:255',
            'advocate' => 'sometimes|nullable|string|max:100|exists:vlf_staff,name',
            'stage' => 'sometimes|nullable|string|max:100',
            'supervisor' => 'sometimes|nullable|string|max:100|exists:vlf_staff,name',
            'statusLabel' => 'sometimes|nullable|string|max:100',
            'statusLevel' => 'sometimes|nullable|in:urgent,warn,ok',
            'riskLevel' => 'sometimes|nullable|string|max:20',
            'riskNote' => 'sometimes|nullable|string|max:1000',
        ]);

        $matter->update(collect($data)->mapWithKeys(fn ($v, $k) => [Str::snake($k) => $v])->all());

        return response()->json($matter->load('client')->toClient());
    }

    /**
     * Saves a working document's content. The review fields (status, approvals, history,
     * author) are kept from the stored record, so a save can never approve or file a
     * document — only the review workflow can. Documents under review are locked.
     */
    public function updateDocument(Request $request, string $key): JsonResponse
    {
        abort_unless(preg_match('/^[A-Za-z0-9_-]{1,100}$/', $key), 404);

        $request->validate([
            'data' => 'required|array',
            'data.title' => 'required|string|max:255',
            'data.matterId' => 'required|string|exists:vlf_matters,ref',
        ]);

        $incoming = $request->input('data');
        $existing = Document::where('key', $key)->first();

        if ($existing) {
            $status = $existing->data['currentStatus'] ?? 'DRAFT';
            abort_unless(in_array($status, ['DRAFT', 'REJECTED'], true), 422,
                'This document is '.strtolower(str_replace('_', ' ', $status)).' — its content is locked. Return it for revision to change it.');
            foreach (self::DOCUMENT_WORKFLOW_FIELDS as $field) {
                if (array_key_exists($field, $existing->data)) {
                    $incoming[$field] = $existing->data[$field];
                } else {
                    unset($incoming[$field]);
                }
            }
            $existing->update(['data' => $incoming]);
            $document = $existing;
        } else {
            $incoming['author'] = $request->user()->name;
            $incoming['currentStatus'] = 'DRAFT';
            $incoming['currentLifecycleStep'] = 0;
            unset($incoming['reviewer'], $incoming['returnReason'], $incoming['file']);
            $document = Document::create(['key' => $key, 'data' => $incoming]);
        }

        return response()->json(['key' => $document->key, 'data' => $document->toClient()]);
    }
}
