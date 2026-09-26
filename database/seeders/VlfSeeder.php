<?php

namespace Database\Seeders;

use App\Models\Vlf\Comment;
use App\Models\Vlf\Document;
use App\Models\Vlf\Invoice;
use App\Models\Vlf\Matter;
use App\Models\Vlf\Message;
use App\Models\Vlf\Task;
use App\Models\Vlf\TimeEntry;
use Illuminate\Database\Seeder;

/**
 * Resets the VLF prototype to its starting data (the values that were
 * hard-coded in public/vlf-fixed.html). Run: php artisan db:seed --class=VlfSeeder
 */
class VlfSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([Matter::class, Task::class, TimeEntry::class, Invoice::class, Message::class, Comment::class, Document::class] as $model) {
            $model::query()->delete();
        }

        Matter::create([
            'ref' => 'KSC-2026-0891',
            'title' => 'Equity Bank Uganda Ltd v. Ssekandi Enterprises Ltd',
            'court' => 'High Court · Commercial Division',
            'judge' => 'Justice Tibatemwa',
            'advocate' => 'Peter Ssali',
            'stage' => 'Case management',
        ]);
        Matter::create([
            'ref' => 'KSC-2026-0823',
            'title' => 'Mubiru Estate — Succession & Land Dispute',
            'court' => 'High Court · Land Division',
            'judge' => 'Justice Mugenyi',
            'advocate' => 'Peter Ssali',
            'stage' => 'Hearing',
        ]);

        $equity = ['matter_ref' => 'KSC-2026-0891', 'matter_title' => 'Equity Bank v Ssekandi'];
        foreach ([
            $equity + ['code' => 'TSK-KSC-0891-014', 'class' => 'A', 'title' => 'File witness statement with court registry', 'assigned_to' => 'Peter Ssali', 'assigned_by' => 'Margaret Ssempebwa', 'deadline' => 'Today · 5:00 PM', 'status' => 'BLOCKED', 'blocked_by' => 'Awaiting partner approval', 'estimated_time' => '0h 30m'],
            $equity + ['code' => 'TSK-KSC-0891-015', 'class' => 'B', 'title' => 'Prepare authorities bundle for Scheduling Conference', 'assigned_to' => 'Tendo Mukasa', 'assigned_by' => 'Peter Ssali', 'deadline' => 'Today · 4:00 PM', 'status' => 'IN_PROGRESS', 'estimated_time' => '2h 00m'],
            $equity + ['code' => 'TSK-KSC-0891-016', 'class' => 'B', 'title' => 'Prepare conference brief for Margaret Ssempebwa', 'assigned_to' => 'Peter Ssali', 'assigned_by' => 'Margaret Ssempebwa', 'deadline' => 'Today · 6:00 PM', 'status' => 'PENDING', 'estimated_time' => '1h 30m'],
            ['code' => 'TSK-KSC-0823-009', 'matter_ref' => 'KSC-2026-0823', 'matter_title' => 'Mubiru Estate', 'class' => 'B', 'title' => 'Review Letters of Administration petition — Mubiru Estate', 'assigned_to' => 'Peter Ssali', 'assigned_by' => 'Margaret Ssempebwa', 'deadline' => 'Tomorrow · 12:00 PM', 'status' => 'PENDING', 'estimated_time' => '1h 00m'],
        ] as $task) {
            Task::create($task + ['billable' => true]);
        }

        // Inserted oldest-first; the API lists newest (highest id) first, matching the page.
        foreach ([
            ['code' => 'TE-004', 'task_code' => null, 'description' => 'Research on Commercial Division summary judgment procedure — Order 33 analysis', 'advocate' => 'Tendo Mukasa', 'date_label' => '14 Aug · 10:00 AM', 'duration' => '2h 30m', 'duration_mins' => 150, 'billable' => false, 'rate' => 200000, 'amount' => 0],
            ['code' => 'TE-003', 'task_code' => 'TSK-KSC-0891-011', 'description' => 'Attended scheduling call with Equity Bank in-house counsel (James Opolot)', 'advocate' => 'Peter Ssali', 'date_label' => '15 Aug · 2:00 PM', 'duration' => '1h 00m', 'duration_mins' => 60, 'billable' => true, 'rate' => 350000, 'amount' => 350000],
            ['code' => 'TE-002', 'task_code' => 'TSK-KSC-0891-012', 'description' => 'Reviewed Witness Statement v1 — returned to Tendo for revision of paragraph 7', 'advocate' => 'Peter Ssali', 'date_label' => 'Yesterday · 4:22 PM', 'duration' => '0h 30m', 'duration_mins' => 30, 'billable' => true, 'rate' => 350000, 'amount' => 175000],
            ['code' => 'TE-001', 'task_code' => 'TSK-KSC-0891-013', 'description' => 'Reviewed and approved Witness Statement v2 for partner sign-off', 'advocate' => 'Peter Ssali', 'date_label' => 'Today · 9:30 AM', 'duration' => '0h 45m', 'duration_mins' => 45, 'billable' => true, 'rate' => 350000, 'amount' => 262500],
        ] as $entry) {
            TimeEntry::create($entry + ['matter_ref' => 'KSC-2026-0891']);
        }

        Invoice::create([
            'code' => 'KSC-INV-2026-0077', 'matter_ref' => 'KSC-2026-0891', 'client' => 'Equity Bank Uganda Ltd',
            'status' => 'PAID', 'issue_date' => '31 Jul 2026', 'due_date' => '14 Aug 2026', 'paid_date' => '10 Aug 2026',
            'lines' => [
                ['desc' => 'Professional fees — July 2026 (plaint review, interlocutory applications, client meetings)', 'hours' => '18h 30m', 'amount' => 6475000],
                ['desc' => 'Disbursements — court filing fees, process serving', 'hours' => null, 'amount' => 285000],
            ],
            'total' => 6760000, 'paid' => 6760000,
        ]);
        Invoice::create([
            'code' => 'KSC-INV-2026-0078', 'matter_ref' => 'KSC-2026-0891', 'client' => 'Equity Bank Uganda Ltd',
            'status' => 'ISSUED', 'issue_date' => '1 Aug 2026', 'due_date' => '15 Aug 2026', 'paid_date' => null,
            'lines' => [
                ['desc' => 'Professional fees — August 2026 (witness statement preparation, case management)', 'hours' => '12h 15m', 'amount' => 4287500],
                ['desc' => 'Disbursements — correspondence, registry searches', 'hours' => null, 'amount' => 95000],
            ],
            'total' => 4382500, 'paid' => 0,
        ]);

        $messages = [
            'ksc-0891-internal' => [
                ['PS', 'Peter Ssali', 'Today · 9:32 AM', false, 'Witness statement v2 is ready. Para 7 has been fully substantiated with the interest computation. Submitted to Margaret for approval.'],
                ['MS', 'Margaret Ssempebwa', 'Today · 10:15 AM', false, 'Thank you Peter. Reviewing now. I will approve before 2 PM. Please prepare the conference brief tonight and leave it on my desk.'],
                ['PS', 'Peter Ssali', 'Today · 10:22 AM', true, 'Understood. Conference brief will be ready by 6 PM. I will also ask Tendo to prepare the authorities bundle.'],
            ],
            'ksc-0823-internal' => [
                ['PS', 'Peter Ssali', 'Yesterday · 3:11 PM', true, 'Letters of Administration petition is drafted. Awaiting the death certificate from the client before we can file.'],
            ],
            'equity-client' => [
                ['JO', 'James Opolot', 'Today · 8:00 AM', false, 'Good morning Peter. Has the witness statement been filed? The Scheduling Conference is tomorrow at 9:30 AM.'],
                ['PS', 'Peter Ssali', 'Today · 8:45 AM', true, 'Good morning Counsel Opolot. The statement is complete and awaiting final partner approval, which is expected by 2:00 PM today. Filing will follow immediately. I will confirm once done.'],
            ],
            'PS-MS' => [
                ['MS', 'Margaret Ssempebwa', 'Today · 10:15 AM', false, 'Peter — I will approve the witness statement before 2 PM. Please ensure the conference brief is ready tonight.'],
            ],
            'PS-TM' => [
                ['PS', 'Peter Ssali', 'Today · 10:25 AM', true, 'Tendo — please prepare the authorities bundle for the Scheduling Conference. I need it by 4 PM. Check the Intelligence tab for the authority list.'],
                ['TM', 'Tendo Mukasa', 'Today · 10:28 AM', false, 'Understood Counsel. I will have it ready by 3:30 PM.'],
            ],
            // The pop-up direct-message thread keeps its own history in the page (MESSAGES).
            'thread:PS-MS' => [
                ['PS', null, 'Today · 9:32 AM', false, 'Margaret — witness statement v2 is ready. Para 7 has been fully substantiated. Awaiting your approval.'],
                ['MS', null, 'Today · 10:15 AM', true, 'Thank you Peter. I will review and approve before 2 PM. Please prepare the conference brief tonight.'],
            ],
        ];
        foreach ($messages as $channel => $rows) {
            foreach ($rows as [$av, $name, $ts, $mine, $text]) {
                Message::create(['channel' => $channel, 'author_av' => $av, 'author_name' => $name, 'ts_label' => $ts, 'mine' => $mine, 'text' => $text]);
            }
        }

        Comment::create([
            'matter_ref' => 'KSC-2026-0891', 'author' => 'Peter Ssali', 'author_av' => 'PS', 'ts_label' => 'Today · 9:30 AM', 'context' => 'Witness Statement v2',
            'text' => 'Paragraph 7 has been revised — the full computation of the outstanding balance is now at para 7 with the breakdown of drawdowns, repayments and accrued interest. Submitted to Margaret for approval.',
            'replies' => [],
        ]);
        Comment::create([
            'matter_ref' => 'KSC-2026-0891', 'author' => 'Margaret Ssempebwa', 'author_av' => 'MS', 'ts_label' => 'Today · 10:14 AM', 'context' => 'KSC-2026-0891',
            'text' => 'I have reviewed v2. Paragraph 7 is now acceptable. I will approve before 2:00 PM. Please ensure the conference brief is on my desk before 6:00 PM tonight.',
            'replies' => [],
        ]);
    }
}
