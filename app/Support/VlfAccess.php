<?php

namespace App\Support;

use App\Models\User;
use App\Models\Vlf\Matter;

/**
 * Which matters a person may see. Firm staff see every matter; a client sees only
 * the matters of the client organisation their account belongs to.
 */
class VlfAccess
{
    /**
     * @return list<string>|null matter references, or null meaning "all matters"
     */
    public static function matterRefs(User $user): ?array
    {
        if ($user->isStaff()) {
            return null;
        }

        return $user->client_id
            ? Matter::where('client_id', $user->client_id)->pluck('ref')->all()
            : [];
    }

    public static function canSeeMatter(User $user, ?string $ref): bool
    {
        $refs = self::matterRefs($user);

        return $refs === null || ($ref !== null && in_array($ref, $refs, true));
    }

    /** Invoices a client may see: issued or paid, never drafts. */
    public const CLIENT_INVOICE_STATUSES = ['ISSUED', 'OVERDUE', 'PAID'];
}
