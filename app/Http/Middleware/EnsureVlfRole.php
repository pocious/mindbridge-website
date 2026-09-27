<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route guard by kind of account: "staff" (anyone at the firm) or
 * "firm-admin" (partners and the firm administrator). Usage: vlf.role:staff
 */
class EnsureVlfRole
{
    public function handle(Request $request, Closure $next, string $kind): Response
    {
        $user = $request->user();

        $allowed = match ($kind) {
            'staff' => $user?->isStaff(),
            'firm-admin' => $user?->isFirmAdmin(),
            default => false,
        };

        abort_unless($allowed, 403, $kind === 'staff'
            ? 'This action is only available to firm staff.'
            : 'Only a partner or the firm administrator can do this.');

        return $next($request);
    }
}
