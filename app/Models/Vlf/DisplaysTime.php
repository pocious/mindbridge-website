<?php

namespace App\Models\Vlf;

trait DisplaysTime
{
    /**
     * Seeded rows carry a fixed label from the prototype script ("Today · 9:30 AM");
     * rows created through the API get one derived from when they were saved.
     */
    protected function displayTime(?string $label): string
    {
        if ($label) {
            return $label;
        }

        $at = $this->created_at->copy()->setTimezone('Africa/Kampala');
        $now = now('Africa/Kampala');

        return match (true) {
            $at->isSameDay($now) => 'Today · '.$at->format('g:i A'),
            $at->isSameDay($now->copy()->subDay()) => 'Yesterday · '.$at->format('g:i A'),
            default => $at->format('j M · g:i A'),
        };
    }
}
