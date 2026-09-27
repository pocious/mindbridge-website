<?php

namespace App\Http\Controllers\Vlf;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Vlf\Document;
use App\Models\Vlf\Matter;
use App\Support\VlfNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * File uploads and the review lifecycle for working documents:
 * Draft → Under review → Approved → Filed, with "returned for revision" going back to the author.
 */
class DocumentController extends Controller
{
    private const LIFECYCLE = ['Draft', 'Review', 'Approved', 'IRIS Sealed', 'Filed', 'Archived'];

    public function upload(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => 'required|file|max:20480|mimes:pdf,doc,docx,jpg,jpeg,png,txt',
            'title' => 'required|string|max:255',
            'matter' => 'required|string|exists:vlf_matters,ref',
            'folder' => 'required|string|max:100',
            'visibility' => 'required|in:PRIVILEGED,INTERNAL,CLIENT_APPROVED',
        ]);
        $data['author'] = $request->user()->name;

        $file = $request->file('file');
        $sha = hash_file('sha256', $file->getRealPath());
        $path = $file->store('vlf-uploads');
        $matter = Matter::where('ref', $data['matter'])->first();

        $document = Document::create([
            'key' => 'upload-'.substr($sha, 0, 12).'-'.now()->timestamp,
            'data' => $this->newDocument($data['title'], $matter, $data['author'], $data['folder'], $data['visibility'], $sha,
                'Uploaded '.$file->getClientOriginalName().' ('.number_format($file->getSize() / 1024, 0).' KB)'),
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_mime' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'file_sha256' => $sha,
        ]);

        return response()->json(['key' => $document->key, 'data' => $document->toClient()], 201);
    }

    public function download(Request $request, string $key): StreamedResponse
    {
        $document = Document::where('key', $key)->whereNotNull('file_path')->firstOrFail();
        Gate::authorize('view', $document);
        $disposition = $request->boolean('inline') ? 'inline' : 'attachment';

        return Storage::response($document->file_path, $document->file_name, [], $disposition);
    }

    public function transition(Request $request, string $key): JsonResponse
    {
        $data = $request->validate([
            'action' => 'required|in:submit,approve,return,file',
            'reason' => 'required_if:action,return|nullable|string|max:2000',
        ]);

        $user = $request->user();
        $document = Document::where('key', $key)->firstOrFail();
        $doc = $document->data;
        $status = $doc['currentStatus'] ?? 'DRAFT';
        $actor = $user->name;
        $author = $doc['author'] ?? null;
        $matter = Matter::where('ref', $doc['matterId'] ?? '')->first();
        $firstPartner = fn () => User::where('role', 'partner')->where('active', true)->where('name', '!=', $author)->value('name');
        $reviewer = ($matter?->advocate && $matter->advocate !== $author) ? $matter->advocate : ($matter?->supervisor ?? $firstPartner());

        $inReview = ['UNDER_REVIEW', 'PENDING_PARTNER_APPROVAL'];
        $allowedFrom = ['submit' => ['DRAFT', 'REJECTED'], 'approve' => $inReview, 'return' => $inReview, 'file' => ['APPROVED']];
        abort_unless(in_array($status, $allowedFrom[$data['action']], true), 422, "This document is {$status}; it can't be {$data['action']}ed now.");

        // Who may act comes from the signed-in account, never from the request.
        match ($data['action']) {
            'submit' => abort_unless($actor === $author || $user->isPartner(), 403, 'Only the author can submit this document for review.'),
            'approve', 'return' => abort_unless($actor !== $author && ($actor === ($doc['reviewer'] ?? null) || $user->isPartner()), 403,
                $actor === $author ? 'You cannot approve or return your own document — another advocate must review it.' : 'Only the assigned reviewer or a partner can review this document.'),
            // Filing is a Class A act: it needs a partner.
            'file' => abort_unless($user->isPartner(), 403, 'Filing is a Class A action — only a partner can file.'),
        };

        $ts = 'Today · '.now('Africa/Kampala')->format('g:i A');
        $iris = strtoupper($data['action']).'-'.now()->timestamp;
        [$newStatus, $step, $historyType, $title, $desc] = match ($data['action']) {
            'submit' => ['UNDER_REVIEW', 1, 'review', "Submitted for review by {$actor}", "Sent to {$reviewer} for review."],
            'approve' => ['APPROVED', 2, 'approved', "Approved by {$actor}", 'Cleared for sealing and filing.'],
            'return' => ['REJECTED', 0, 'rejected', "Returned for revision by {$actor}", $data['reason']],
            'file' => ['FILED', 4, 'sealed', "Filed by {$actor}", 'Document filed and sealed into the matter record.'],
        };

        $doc['currentStatus'] = $newStatus;
        $doc['currentLifecycleStep'] = $step;
        $doc['lifecycleStates'] ??= self::LIFECYCLE;
        $doc['reviewer'] = $data['action'] === 'submit' ? $reviewer : ($doc['reviewer'] ?? $reviewer);
        $doc['returnReason'] = $data['action'] === 'return' ? $data['reason'] : null;
        $doc['history'][] = ['type' => $historyType, 'ts' => $ts, 'title' => $title, 'desc' => $desc, 'iris' => $iris];
        $doc['approvalChain'][] = [
            'step' => count($doc['approvalChain'] ?? []) + 1,
            'who' => $actor,
            'role' => $data['action'] === 'submit' ? 'Author' : 'Reviewer',
            'action' => $title.($data['action'] === 'return' ? ' — '.$data['reason'] : ''),
            'status' => 'complete',
            'date' => $ts,
            'iris' => $iris,
            'class' => $data['action'] === 'file' ? 'A' : 'B',
        ];

        $document->update(['data' => $doc]);

        $docTitle = $doc['title'] ?? 'Document';
        $link = ['matter' => $doc['matterId'] ?? null, 'doc' => $key];
        match ($data['action']) {
            'submit' => VlfNotifier::notify($doc['reviewer'], 'action', "{$actor} submitted \"{$docTitle}\" for your review.", $link, 'Review requested'),
            'approve' => VlfNotifier::notify($author, 'info', "{$actor} approved \"{$docTitle}\".", $link, 'Document approved'),
            'return' => VlfNotifier::notify($author, 'warn', "{$actor} returned \"{$docTitle}\" for revision: {$data['reason']}", $link, 'Returned for revision'),
            'file' => VlfNotifier::notify($author, 'info', "\"{$docTitle}\" has been filed.", $link, 'Document filed'),
        };

        return response()->json(['key' => $key, 'data' => $document->toClient()]);
    }

    private function newDocument(string $title, ?Matter $matter, string $author, string $folder, string $visibility, string $sha, string $note): array
    {
        $ts = 'Today · '.now('Africa/Kampala')->format('g:i A');

        return [
            'id' => 'GVL-'.($matter?->ref ?? 'KSC').'-UP-'.strtoupper(substr($sha, 0, 6)),
            'title' => $title,
            'matterId' => $matter?->ref,
            'matterTitle' => $matter?->title,
            'folder' => $folder,
            'class' => 'B',
            'currentStatus' => 'DRAFT',
            'currentVersion' => 1,
            'visibility' => $visibility,
            'author' => $author,
            'pages' => 1,
            'versions' => [['v' => 1, 'summary' => $note, 'author' => $author, 'date' => $ts, 'status' => 'CURRENT', 'sha' => substr($sha, 0, 16), 'iris' => 'UPLOAD-'.substr($sha, 0, 8), 'notes' => 'SHA-256 '.$sha]],
            'approvalChain' => [['step' => 1, 'who' => $author, 'role' => 'Author', 'action' => 'Uploaded', 'status' => 'complete', 'date' => $ts, 'iris' => null, 'class' => 'B']],
            'history' => [['type' => 'draft', 'ts' => $ts, 'title' => 'Uploaded by '.$author, 'desc' => $note, 'iris' => 'UPLOAD-'.substr($sha, 0, 8)]],
            'lifecycleStates' => self::LIFECYCLE,
            'currentLifecycleStep' => 0,
        ];
    }
}
