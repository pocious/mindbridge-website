<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vlf\Invoice;
use App\Support\VlfAccess;

class InvoicePolicy
{
    /** A client sees only issued or paid invoices on their own matters. */
    public function view(User $user, Invoice $invoice): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        return in_array($invoice->status, VlfAccess::CLIENT_INVOICE_STATUSES, true)
            && VlfAccess::canSeeMatter($user, $invoice->matter_ref);
    }

    /** Approving an invoice for issue is a partner's decision (from the account's role, not the request). */
    public function approve(User $user, Invoice $invoice): bool
    {
        return $user->isPartner();
    }

    public function manage(User $user, Invoice $invoice): bool
    {
        return $user->isStaff();
    }
}
