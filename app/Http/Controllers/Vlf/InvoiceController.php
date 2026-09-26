<?php

namespace App\Http\Controllers\Vlf;

use App\Http\Controllers\Controller;
use App\Models\Vlf\Client;
use App\Models\Vlf\Invoice;
use App\Models\Vlf\Matter;
use App\Models\Vlf\Staff;
use App\Models\Vlf\TimeEntry;
use App\Support\VlfNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Invoice lifecycle: Draft (from unbilled time) → Approved (partner) → Issued → Paid.
 */
class InvoiceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'matter' => 'required|string|exists:vlf_matters,ref',
            'extraLines' => 'nullable|array|max:20',
            'extraLines.*.desc' => 'required|string|max:500',
            'extraLines.*.amount' => 'required|integer|min:0',
        ]);

        $matter = Matter::where('ref', $data['matter'])->firstOrFail();

        $invoice = DB::transaction(function () use ($data, $matter) {
            $entries = TimeEntry::where('matter_ref', $matter->ref)
                ->where('billable', true)->whereNull('invoice_code')
                ->lockForUpdate()->orderBy('id')->get();

            $lines = $entries->map(fn (TimeEntry $e) => [
                'desc' => "{$e->advocate} — {$e->description}",
                'hours' => $e->duration,
                'amount' => (int) $e->amount,
            ])->all();
            foreach ($data['extraLines'] ?? [] as $line) {
                $lines[] = ['desc' => $line['desc'], 'hours' => null, 'amount' => (int) $line['amount']];
            }

            abort_if($lines === [], 422, 'There is no unbilled time on this matter to invoice.');

            $year = now()->year;
            $last = Invoice::where('code', 'like', "KSC-INV-{$year}-%")->lockForUpdate()->pluck('code')
                ->map(fn ($code) => (int) substr($code, -4))->max() ?? 0;
            $code = sprintf('KSC-INV-%d-%04d', $year, $last + 1);

            $invoice = Invoice::create([
                'code' => $code,
                'matter_ref' => $matter->ref,
                'client_id' => $matter->client_id,
                'client' => $matter->client?->name ?? 'Client',
                'status' => 'DRAFT',
                'lines' => $lines,
                'total' => array_sum(array_column($lines, 'amount')),
                'paid' => 0,
            ]);

            TimeEntry::whereIn('id', $entries->pluck('id'))->update(['invoice_code' => $code]);

            return $invoice;
        });

        VlfNotifier::notify($matter->supervisor ?? 'Margaret Ssempebwa', 'action',
            "Draft invoice {$invoice->code} for {$invoice->client} (UGX ".number_format($invoice->total).') needs partner approval before issue.',
            ['matter' => $matter->ref, 'tab' => 'billing'], 'Invoice approval');

        return response()->json($invoice->toClient(), 201);
    }

    /**
     * approve (partner sign-off), issue (send to client), pay (record full payment).
     */
    public function transition(Request $request, string $code): JsonResponse
    {
        $data = $request->validate([
            'action' => 'required|in:approve,issue,pay',
            'amount' => 'nullable|integer|min:1',
            'actor' => 'required_if:action,approve|nullable|string|max:100',
        ]);

        if ($data['action'] === 'approve') {
            $role = Staff::where('name', $data['actor'])->value('role') ?? '';
            abort_unless(str_contains($role, 'Partner'), 422, 'Only a partner can approve an invoice for issue.');
        }

        $invoice = Invoice::where('code', $code)->firstOrFail();
        $today = now('Africa/Kampala');

        $allowedFrom = ['approve' => ['DRAFT'], 'issue' => ['APPROVED'], 'pay' => ['ISSUED', 'OVERDUE']][$data['action']];
        abort_unless(in_array($invoice->status, $allowedFrom, true), 422, match ($data['action']) {
            'approve' => 'Only draft invoices can be approved.',
            'issue' => 'An invoice must be approved by a partner before it is issued.',
            'pay' => 'Only issued invoices can be marked paid.',
        });

        match ($data['action']) {
            'approve' => $invoice->update(['status' => 'APPROVED']),
            'issue' => $invoice->update([
                'status' => 'ISSUED',
                'issue_date' => $today->format('j M Y'),
                'due_date' => $today->copy()->addDays(14)->format('j M Y'),
            ]),
            'pay' => $invoice->update([
                'paid' => min($invoice->total, $invoice->paid + ($data['amount'] ?? $invoice->total - $invoice->paid)),
                'paid_date' => $today->format('j M Y'),
            ]),
        };

        if ($data['action'] === 'pay' && $invoice->paid >= $invoice->total) {
            $invoice->update(['status' => 'PAID']);
        }

        $contact = $invoice->client_id ? Client::find($invoice->client_id)?->contact_name : null;
        $amount = 'UGX '.number_format($invoice->total);
        match ($data['action']) {
            'approve' => null,
            'issue' => VlfNotifier::notify($contact, 'action', "Invoice {$invoice->code} for {$amount} has been issued. Due {$invoice->due_date}.", ['page' => 'cli-billing'], 'New invoice', 'notify_invoices'),
            'pay' => VlfNotifier::notify('Grace Akello', 'info', "Payment recorded on {$invoice->code} ({$invoice->client}) — UGX ".number_format($invoice->paid).' of '.$amount.'.', ['page' => 'adm-billing'], 'Payment received', 'notify_invoices'),
        };

        return response()->json($invoice->toClient());
    }
}
