<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Vlf\Client;
use App\Models\Vlf\Comment;
use App\Models\Vlf\CourtEvent;
use App\Models\Vlf\Deadline;
use App\Models\Vlf\Document;
use App\Models\Vlf\Invoice;
use App\Models\Vlf\Matter;
use App\Models\Vlf\Message;
use App\Models\Vlf\Notification;
use App\Models\Vlf\Setting;
use App\Models\Vlf\Staff;
use App\Models\Vlf\Task;
use App\Models\Vlf\TimeEntry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Resets the VLF prototype to its starting data (the values that were
 * hard-coded in public/vlf-fixed.html). Run: php artisan db:seed --class=VlfSeeder
 */
class VlfSeeder extends Seeder
{
    public function run(): void
    {
        $this->wipe();
        $this->seedSettings();
        $this->seedDemoStaff();
        $clients = $this->seedClients();
        // Demo client portal login for Equity Bank's in-house counsel.
        User::create([
            'name' => 'James Opolot', 'email' => 'james.opolot@equitybank.example', 'password' => 'password',
            'role' => 'client', 'client_id' => $clients['Equity Bank Uganda Ltd']->id,
        ]);
        $this->seedMatters($clients);
        $this->seedDiary();

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
            'code' => 'KSC-INV-2026-0077', 'matter_ref' => 'KSC-2026-0891', 'client_id' => $clients['Equity Bank Uganda Ltd']->id, 'client' => 'Equity Bank Uganda Ltd',
            'status' => 'PAID', 'issue_date' => '31 Jul 2026', 'due_date' => '14 Aug 2026', 'paid_date' => '10 Aug 2026',
            'lines' => [
                ['desc' => 'Professional fees — July 2026 (plaint review, interlocutory applications, client meetings)', 'hours' => '18h 30m', 'amount' => 6475000],
                ['desc' => 'Disbursements — court filing fees, process serving', 'hours' => null, 'amount' => 285000],
            ],
            'total' => 6760000, 'paid' => 6760000,
        ]);
        Invoice::create([
            'code' => 'KSC-INV-2026-0078', 'matter_ref' => 'KSC-2026-0891', 'client_id' => $clients['Equity Bank Uganda Ltd']->id, 'client' => 'Equity Bank Uganda Ltd',
            'status' => 'ISSUED', 'issue_date' => '1 Aug 2026', 'due_date' => '15 Aug 2026', 'paid_date' => null,
            'lines' => [
                ['desc' => 'Professional fees — August 2026 (witness statement preparation, case management)', 'hours' => '12h 15m', 'amount' => 4287500],
                ['desc' => 'Disbursements — correspondence, registry searches', 'hours' => null, 'amount' => 95000],
            ],
            'total' => 4382500, 'paid' => 0,
        ]);

        $messages = [
            'matter-KSC-2026-0891' => [
                ['PS', 'Peter Ssali', 'Today · 9:32 AM', false, 'Witness statement v2 is ready. Para 7 has been fully substantiated with the interest computation. Submitted to Margaret for approval.'],
                ['MS', 'Margaret Ssempebwa', 'Today · 10:15 AM', false, 'Thank you Peter. Reviewing now. I will approve before 2 PM. Please prepare the conference brief tonight and leave it on my desk.'],
                ['PS', 'Peter Ssali', 'Today · 10:22 AM', true, 'Understood. Conference brief will be ready by 6 PM. I will also ask Tendo to prepare the authorities bundle.'],
            ],
            'matter-KSC-2026-0823' => [
                ['PS', 'Peter Ssali', 'Yesterday · 3:11 PM', true, 'Letters of Administration petition is drafted. Awaiting the death certificate from the client before we can file.'],
            ],
            'client-KSC-2026-0891' => [
                ['JO', 'James Opolot', 'Today · 8:00 AM', false, 'Good morning Peter. Has the witness statement been filed? The Scheduling Conference is tomorrow at 9:30 AM.'],
                ['PS', 'Peter Ssali', 'Today · 8:45 AM', true, 'Good morning Counsel Opolot. The statement is complete and awaiting final partner approval, which is expected by 2:00 PM today. Filing will follow immediately. I will confirm once done.'],
            ],
            'dm-'.$this->staffId('Peter Ssali', 'Margaret Ssempebwa') => [
                ['MS', 'Margaret Ssempebwa', 'Today · 10:15 AM', false, 'Peter — I will approve the witness statement before 2 PM. Please ensure the conference brief is ready tonight.'],
            ],
            'dm-'.$this->staffId('Peter Ssali', 'Tendo Mukasa') => [
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
                $author = $name ? User::where('name', $name)->value('id') : null;
                Message::create(['channel' => $channel, 'user_id' => $author, 'author_av' => $av, 'author_name' => $name, 'ts_label' => $ts, 'mine' => $mine, 'text' => $text]);
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

        $this->seedNotifications();
    }

    /** Direct-message channel id for two staff members: "{lowerId}-{higherId}". */
    private function staffId(string $a, string $b): string
    {
        $ids = Staff::whereIn('name', [$a, $b])->pluck('id')->sort()->values();

        return $ids->implode('-');
    }

    /**
     * Deletes every VLF record, uploaded file and sign-in account.
     */
    protected function wipe(): void
    {
        foreach ([Notification::class, Setting::class, Staff::class, Deadline::class, CourtEvent::class, Task::class, TimeEntry::class, Invoice::class, Message::class, Comment::class, Document::class, Matter::class, Client::class] as $model) {
            $model::query()->delete();
        }
        Storage::deleteDirectory('vlf-uploads');

        // Sign-in accounts and their sessions and reset links.
        DB::table('sessions')->delete();
        DB::table('password_reset_tokens')->delete();
        User::query()->delete();
    }

    /**
     * Demo staff, each with a sign-in account (password "password" — demo only).
     * Addresses use the reserved .example domain so no real mailbox is ever emailed.
     */
    protected function seedDemoStaff(): void
    {
        $roles = ['Senior Partner' => 'partner', 'Associate' => 'associate', 'Junior Associate' => 'junior', 'Firm Administrator' => 'admin'];
        foreach ([
            ['Margaret Ssempebwa', 'MS', 'Senior Partner', 'margaret@ksc-advocates.example', 600000, 'Available', 52.0],
            ['Peter Ssali', 'PS', 'Associate', 'peter@ksc-advocates.example', 450000, 'In court', 47.5],
            ['Tendo Mukasa', 'TM', 'Junior Associate', 'tendo@ksc-advocates.example', 110000, 'Available', 38.0],
            ['James Ouma', 'JO', 'Junior Associate', 'james.ouma@ksc-advocates.example', 110000, 'Available', 20.0],
            ['Grace Akello', 'GA', 'Firm Administrator', 'grace@ksc-advocates.example', 0, 'Available', 0],
        ] as [$name, $initials, $role, $email, $rate, $status, $hours]) {
            $user = User::create(['name' => $name, 'email' => $email, 'password' => 'password', 'role' => $roles[$role]]);
            Staff::create(compact('name', 'initials', 'role', 'email', 'rate', 'status') + ['month_hours' => $hours, 'user_id' => $user->id]);
        }
    }

    protected function seedSettings(): void
    {
        foreach ([
            'firm_name' => 'GAVEL.CO',
            'firm_address' => 'Plot 18 Hannington Road, Kampala',
            'notify_deadlines' => true,
            'notify_hearings' => true,
            'notify_invoices' => true,
            'notify_unassigned' => false,
        ] as $key => $value) {
            Setting::create(['key' => $key, 'value' => $value]);
        }
    }

    /**
     * @return array<string, Client>
     */
    private function seedClients(): array
    {
        $clients = [];
        foreach ([
            ['Equity Bank Uganda Ltd', 'Company', '1007-441-002', 'James Opolot', 'james.opolot@equitybank.example', true],
            ['Mubiru Estate', 'Estate', null, 'Sarah Mubiru', null, true],
            ['Stanbic Bank Uganda Ltd', 'Company', null, null, null, true],
            ['Nile Breweries Ltd', 'Company', null, null, null, true],
            ['ABC Ltd', 'Company', null, null, null, false],
            ['Kampala Serena Hotel', 'Company', null, null, null, false],
            ['Nile Trading Co.', 'Company', null, null, null, false],
            ['Mukasa Holdings', 'Company', null, null, null, false],
            ['Kyambogo University', 'Government', null, null, null, false],
            ['Lakeside Traders Ltd', 'Company', null, null, null, false],
        ] as [$name, $type, $tin, $contact, $email, $verified]) {
            $clients[$name] = Client::create([
                'name' => $name, 'type' => $type, 'tin' => $tin,
                'contact_name' => $contact, 'contact_email' => $email, 'verified' => $verified,
            ]);
        }

        return $clients;
    }

    /**
     * @param  array<string, Client>  $clients
     */
    private function seedMatters(array $clients): void
    {
        $partner = 'Margaret Ssempebwa';
        foreach ([
            ['KSC-2026-0891', 'Equity Bank Uganda Ltd', 'Equity Bank Uganda Ltd v. Ssekandi Enterprises Ltd', 'Ssekandi Enterprises Ltd', 'Commercial Litigation', 'High Court · Commercial Division', 'Justice Tibatemwa', 'Peter Ssali', 'Case management', 'Witness stmt due 5PM', 'urgent', 'Hourly · UGX 450,000/hr', '2025-10-28'],
            ['KSC-2026-0823', 'Mubiru Estate', 'Mubiru Estate — Succession & Land Dispute', 'Competing family claimants', 'Probate', 'High Court · Land Division', 'Justice Mugenyi', 'Peter Ssali', 'Hearing', 'Hearing today', 'urgent', 'Hourly · UGX 450,000/hr', '2025-09-02'],
            ['KSC-2026-0944', 'Stanbic Bank Uganda Ltd', 'Stanbic Bank Uganda Ltd — ICC Arbitration', 'Respondent (confidential)', 'Arbitration', 'Arbitration · Kampala', 'Dr. Kamya', 'Peter Ssali', 'Arbitration Hearing', 'Submissions Fri', 'warn', 'Hourly · UGX 450,000/hr', '2026-01-15'],
            ['KSC-2026-0771', 'ABC Ltd', 'ABC Ltd v. XYZ Ltd — Contract Dispute', 'XYZ Ltd', 'Litigation', 'Magistrates Court · Kampala', null, 'Peter Ssali', 'Pleadings', 'On track', 'ok', 'Fixed fee', '2025-07-20'],
            ['KSC-2026-0856', 'Nile Breweries Ltd', 'Nile Breweries — Title Verification & Conveyance', null, 'Conveyancing', 'Registry · Kampala', null, 'Peter Ssali', 'Due Diligence', 'Title review pending', 'warn', 'Fixed fee', '2026-02-10'],
            ['KSC-2026-0790', 'Kampala Serena Hotel', 'Kampala Serena Hotel — Employment Dispute', 'Former employee', 'Employment', 'Labour Tribunal', null, 'Peter Ssali', 'Mediation', 'On track', 'ok', 'Hourly · UGX 450,000/hr', '2025-11-04'],
            ['KSC-2026-0834', 'Nile Trading Co.', 'Nile Trading Co. — Commercial Dispute', 'Kampala Wholesalers Ltd', 'Commercial Litigation', 'High Court · Commercial Division', null, $partner, 'Pleadings', 'Invoice 38 days', 'urgent', 'Hourly · UGX 600,000/hr', '2025-12-01'],
            ['KSC-2026-0756', 'Mukasa Holdings', 'Mukasa Holdings — Shareholder Restructuring', null, 'Corporate', 'Registry · Kampala', null, 'Peter Ssali', 'Advisory', 'On track', 'ok', 'Retainer', '2025-06-18'],
            ['KSC-2026-0987', 'Kyambogo University', 'Kyambogo University — Employment Dispute', 'Staff association', 'Employment', 'Labour Tribunal', null, null, 'Intake', 'No advocate', 'urgent', 'To be agreed', '2026-08-14'],
            ['KSC-2026-0991', 'Lakeside Traders Ltd', 'Lakeside Traders Ltd — Lease Dispute', 'Landlord (to be confirmed)', 'Litigation', 'Magistrates Court · Kampala', null, null, 'Intake', 'No advocate', 'urgent', 'To be agreed', '2026-08-15'],
        ] as [$ref, $client, $title, $opposing, $area, $court, $judge, $advocate, $stage, $label, $level, $fee, $date]) {
            Matter::create([
                'ref' => $ref, 'client_id' => $clients[$client]->id, 'title' => $title, 'opposing_party' => $opposing,
                'practice_area' => $area, 'court' => $court, 'judge' => $judge, 'advocate' => $advocate, 'supervisor' => $partner,
                'stage' => $stage, 'status_label' => $label, 'status_level' => $level, 'fee_arrangement' => $fee, 'instruction_date' => $date,
            ]);
        }
    }

    private function seedDiary(): void
    {
        $checklist = fn (array $done, array $pending) => array_merge(
            array_map(fn ($t) => ['text' => $t, 'done' => true], $done),
            array_map(fn ($t) => ['text' => $t, 'done' => false], $pending),
        );

        CourtEvent::create([
            'key' => 'mubiru', 'matter_ref' => 'KSC-2026-0823', 'title' => 'Succession & Land Dispute — Substantive Hearing',
            'court' => 'High Court — Land Division · Courtroom 4', 'judge' => 'Justice Mugenyi', 'date' => '2026-08-17', 'time' => '09:30',
            'advocate' => 'Peter Ssali', 'level' => 'critical', 'notes' => 'All parties confirmed',
            'checklist' => $checklist([
                'Previous court order reviewed — 14 Jun 2026', 'Witness list confirmed — 3 witnesses', 'Hearing bundle prepared — 47 pages',
                'Authorities filed — Mukasa v Mubiru [2018] UGCA', 'Client instructions received and confirmed',
            ], ['Confirm client witness transport to court']),
        ]);
        CourtEvent::create([
            'matter_ref' => 'KSC-2026-0771', 'title' => 'Contract Dispute — Mention', 'court' => 'Chief Magistrates Court — Kampala · Courtroom 2',
            'date' => '2026-08-17', 'time' => '14:30', 'advocate' => 'James Ouma', 'level' => 'scheduled', 'checklist' => [],
        ]);
        CourtEvent::create([
            'matter_ref' => 'KSC-2026-0891', 'title' => 'Scheduling Conference — Recovery UGX 847M', 'court' => 'High Court — Commercial Division · Courtroom 7',
            'judge' => 'Justice Tibatemwa', 'date' => '2026-08-18', 'time' => '09:30', 'advocate' => 'Margaret Ssempebwa', 'level' => 'critical',
            'notes' => 'Margaret Ssempebwa & Peter Ssali · Witness statement must be filed first',
            'checklist' => $checklist(
                ['Previous scheduling order reviewed', 'Witness statement drafted and under review', 'Client instructions received (Equity Bank in-house)', 'Brief for Margaret Ssempebwa prepared'],
                ['Witness statement filed — partner approval needed', 'Authorities bundle attached', 'Court filing receipt confirmed'],
            ),
        ]);
        CourtEvent::create([
            'matter_ref' => 'KSC-2026-0944', 'title' => 'Arbitration — Preliminary Submissions Deadline', 'court' => 'CADER — Arbitration · Kampala',
            'judge' => 'Arbitrator: Dr. Kamya', 'date' => '2026-08-22', 'time' => 'All day', 'advocate' => 'Peter Ssali', 'level' => 'scheduled',
            'notes' => 'Submissions in drafting', 'checklist' => [],
        ]);

        foreach ([
            ['KSC-2026-0891', 'Witness Statement — Equity Bank v Ssekandi', '2026-08-17', '5:00 PM', 'critical'],
            ['KSC-2026-0856', 'Title Verification Advice — Nile Breweries', '2026-08-21', null, 'warn'],
            ['KSC-2026-0944', 'Arbitration Preliminary Submissions', '2026-08-22', null, 'warn'],
        ] as [$ref, $title, $date, $time, $severity]) {
            Deadline::create(['matter_ref' => $ref, 'title' => $title, 'due_date' => $date, 'due_time' => $time, 'owner' => 'Peter Ssali', 'severity' => $severity]);
        }
    }

    private function seedNotifications(): void
    {
        $witness = ['matter' => 'KSC-2026-0891', 'doc' => 'witness-statement'];
        // Listed newest first; inserted oldest first so the newest gets the highest id.
        foreach (array_reverse([
            ['Peter Ssali', 'critical', 'stop', 'Critical — Action required', 'Witness statement filing deadline in 3 hours. Margaret\'s approval still pending.', $witness, 'Today · 2:00 PM', false],
            ['Peter Ssali', 'action', 'bell', 'Action required', 'Scheduling Conference tomorrow at 9:30 AM — conference brief not yet prepared.', ['matter' => 'KSC-2026-0891', 'tab' => 'overview'], 'Today · 9:00 AM', false],
            ['Peter Ssali', 'action', 'list', 'Task assigned', 'Margaret has assigned you to prepare the authorities bundle for KSC-2026-0891.', ['matter' => 'KSC-2026-0891', 'tab' => 'work'], 'Today · 8:44 AM', false],
            ['Peter Ssali', 'info', 'info', 'Document sealed', 'Witness Statement (Draft v2) has been IRIS-sealed and is ready for partner approval.', $witness, 'Today · 8:30 AM', true],
            ['Peter Ssali', 'warn', 'alert', 'Legal rule — verify required', 'DEBT-R-005 (Mediation regime) is flagged [VERIFY]. Check current instrument before the Scheduling Conference.', ['matter' => 'KSC-2026-0891', 'tab' => 'ai'], 'Yesterday · 4:15 PM', true],
            ['Margaret Ssempebwa', 'critical', 'stop', 'Approval required', 'Witness statement (KSC-2026-0891) is waiting for your Class A partner approval — filing deadline 5:00 PM today.', $witness, 'Today · 9:30 AM', false],
            ['Tendo Mukasa', 'action', 'list', 'Task assigned', 'Peter Ssali assigned you: Prepare authorities bundle for Scheduling Conference — due 4:00 PM.', ['matter' => 'KSC-2026-0891', 'tab' => 'work'], 'Today · 10:25 AM', false],
            ['Grace Akello', 'warn', 'alert', 'Unassigned matters', 'KSC-2026-0987 and KSC-2026-0991 have no advocate assigned.', ['page' => 'adm-matters'], 'Today · 8:00 AM', false],
        ]) as [$recipient, $type, $icon, $label, $text, $link, $ts, $read]) {
            Notification::create([
                'recipient' => $recipient, 'type' => $type, 'icon' => $icon, 'type_label' => $label, 'text' => $text,
                'link' => $link, 'ts_label' => $ts, 'read_at' => $read ? now() : null,
            ]);
        }
    }
}
