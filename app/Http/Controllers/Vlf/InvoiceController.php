<?php

namespace App\Http\Controllers\Vlf;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Vlf\Invoice;
use App\Models\Vlf\Matter;
use App\Models\Vlf\TimeEntry;
use App\Support\VlfNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

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

        $approvers = $matter->supervisor ? collect([$matter->supervisor]) : User::where('role', 'partner')->where('active', true)->pluck('name');
        $approvers->each(fn ($name) => VlfNotifier::notify($name, 'action',
            "Draft invoice {$invoice->code} for {$invoice->client} (UGX ".number_format($invoice->total).') needs partner approval before issue.',
            ['matter' => $matter->ref, 'tab' => 'billing'], 'Invoice approval'));

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
        ]);

        $invoice = Invoice::where('code', $code)->firstOrFail();

        // Partner sign-off comes from the signed-in account's role, never from the request.
        if ($data['action'] === 'approve' && Gate::denies('approve', $invoice)) {
            abort(403, 'Only a partner can approve an invoice for issue.');
        }
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

        $amount = 'UGX '.number_format($invoice->total);
        if ($data['action'] === 'issue') {
            // Everyone with a portal account for this client.
            User::where('client_id', $invoice->client_id)->where('active', true)->pluck('name')
                ->each(fn ($name) => VlfNotifier::notify($name, 'action', "Invoice {$invoice->code} for {$amount} has been issued. Due {$invoice->due_date}.", ['page' => 'cli-billing'], 'New invoice', 'notify_invoices'));
        } elseif ($data['action'] === 'pay') {
            User::where('role', 'admin')->where('active', true)->pluck('name')
                ->each(fn ($name) => VlfNotifier::notify($name, 'info', "Payment recorded on {$invoice->code} ({$invoice->client}) — UGX ".number_format($invoice->paid).' of '.$amount.'.', ['page' => 'adm-billing'], 'Payment received', 'notify_invoices'));
        }

        return response()->json($invoice->toClient());
    }
}
