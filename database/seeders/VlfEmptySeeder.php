<?php

namespace Database\Seeders;

/**
 * Starts the VLF app as a real, empty firm: no accounts, staff, matters, clients,
 * tasks, time, invoices, messages, documents, diary events, deadlines or notifications.
 * Only the firm settings are created. Then create the first account:
 *   php artisan vlf:user you@yourfirm.com "Your Name" partner
 *
 * WARNING: this deletes all VLF data and all sign-in accounts.
 * Run: php artisan db:seed --class=VlfEmptySeeder
 */
class VlfEmptySeeder extends VlfSeeder
{
    public function run(): void
    {
        $this->wipe();
        $this->seedSettings();
    }
}
