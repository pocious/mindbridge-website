<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vlf\Document;
use App\Support\VlfAccess;

class DocumentPolicy
{
    /**
     * Staff see all firm documents. A client sees a document only when it is marked
     * client-approved and belongs to one of their own matters — privileged and
     * internal documents are never visible to clients, whatever the page asks for.
     */
    public function view(User $user, Document $document): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        return ($document->data['visibility'] ?? null) === 'CLIENT_APPROVED'
            && VlfAccess::canSeeMatter($user, $document->data['matterId'] ?? null);
    }
}
