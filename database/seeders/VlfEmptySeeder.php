<?php

namespace Database\Seeders;

use App\Models\Vlf\Staff;

/**
 * Starts the VLF app empty: no matters, clients, tasks, time, invoices, messages,
 * documents, diary events, deadlines or notifications. Keeps the firm's staff
 * (with no hours logged) and firm settings so work can be assigned.
 * Run: php artisan db:seed --class=VlfEmptySeeder
 */
class VlfEmptySeeder extends VlfSeeder
{
    public function run(): void
    {
        $this->wipe();
        $this->seedFirm();
        Staff::query()->update(['month_hours' => 0, 'status' => 'Available']);
    }
}
