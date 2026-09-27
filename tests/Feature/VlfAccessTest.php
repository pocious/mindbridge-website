<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vlf\Client;
use App\Models\Vlf\Document;
use App\Models\Vlf\Invoice;
use App\Models\Vlf\Matter;
use App\Models\Vlf\Notification;
use App\Models\Vlf\Staff;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The rules that make the VLF a real system: who can sign in, what each person can
 * see, and which actions need which authority. The acting person always comes from
 * the signed-in account, never from the request.
 */
class VlfAccessTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $name, string $role, int $rate = 450000): User
    {
        $user = User::factory()->create(['name' => $name, 'role' => $role]);
        Staff::create(['user_id' => $user->id, 'name' => $name, 'initials' => $user->initials(), 'role' => ucfirst($role), 'email' => $user->email, 'rate' => $rate]);

        return $user;
    }

    private function clientWithMatter(string $clientName, string $ref, string $advocate = 'Peter Ssali'): array
    {
        $client = Client::create(['name' => $clientName, 'contact_name' => $clientName.' Contact', 'contact_email' => strtolower(str_replace(' ', '', $clientName)).'@example.com']);
        $matter = Matter::create(['ref' => $ref, 'client_id' => $client->id, 'title' => $clientName.' v. Someone', 'advocate' => $advocate, 'supervisor' => 'Margaret Ssempebwa']);
        $user = User::factory()->create(['name' => $client->contact_name, 'email' => $client->contact_email, 'role' => 'client', 'client_id' => $client->id]);

        return [$client, $matter, $user];
    }

    public function test_guests_are_sent_to_sign_in_and_the_api_refuses_them(): void
    {
        $this->get('/app')->assertRedirect('/login');
        $this->getJson('/api/vlf/state')->assertUnauthorized();
    }

    public function test_the_app_page_is_not_in_the_public_folder(): void
    {
        $this->assertFileDoesNotExist(public_path('vlf-fixed.html'));
    }

    public function test_active_users_can_sign_in_and_inactive_users_cannot(): void
    {
        $user = User::factory()->create(['email' => 'a@example.com', 'password' => 'secret-pass-1', 'role' => 'associate']);
        $this->post('/login', ['email' => 'a@example.com', 'password' => 'secret-pass-1'])->assertRedirect('/app');
        $this->assertAuthenticatedAs($user);

        $this->post('/logout');
        $user->update(['active' => false]);
        $this->post('/login', ['email' => 'a@example.com', 'password' => 'secret-pass-1'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_a_client_cannot_see_another_clients_matter(): void
    {
        $this->staff('Peter Ssali', 'associate');
        [, , $alice] = $this->clientWithMatter('Alpha Ltd', 'KSC-2026-0001');
        $this->clientWithMatter('Beta Ltd', 'KSC-2026-0002');

        $state = $this->actingAs($alice)->getJson('/api/vlf/state')->assertOk()->json();

        $this->assertSame(['KSC-2026-0001'], array_keys($state['matters']));
        $this->assertSame([], $state['tasks']);
        $this->assertSame([], $state['deadlines']);
        $this->assertSame([], $state['staff']);
    }

    public function test_a_client_cannot_download_privileged_documents(): void
    {
        Storage::fake();
        $peter = $this->staff('Peter Ssali', 'associate');
        [, , $client] = $this->clientWithMatter('Alpha Ltd', 'KSC-2026-0001');

        $key = $this->actingAs($peter)->post('/api/vlf/documents/upload', [
            'file' => UploadedFile::fake()->createWithContent('note.txt', 'strategy'),
            'title' => 'Strategy', 'matter' => 'KSC-2026-0001', 'folder' => 'Notes', 'visibility' => 'PRIVILEGED',
        ], ['Accept' => 'application/json'])->assertCreated()->json('key');

        $this->actingAs($client)->get("/api/vlf/documents/{$key}/file")->assertForbidden();
        $this->assertArrayNotHasKey($key, $this->actingAs($client)->getJson('/api/vlf/state')->json('documents'));
    }

    public function test_clients_cannot_use_staff_endpoints_or_internal_channels(): void
    {
        [, , $client] = $this->clientWithMatter('Alpha Ltd', 'KSC-2026-0001');

        $this->actingAs($client)->postJson('/api/vlf/tasks', ['matter' => 'KSC-2026-0001'])->assertForbidden();
        $this->actingAs($client)->postJson('/api/vlf/messages', ['channel' => 'matter-KSC-2026-0001', 'text' => 'hi'])->assertForbidden();
        $this->actingAs($client)->postJson('/api/vlf/messages', ['channel' => 'client-KSC-2026-0002', 'text' => 'hi'])->assertForbidden();
        $this->actingAs($client)->postJson('/api/vlf/messages', ['channel' => 'client-KSC-2026-0001', 'text' => 'hi'])->assertCreated();
    }

    public function test_only_a_partner_can_approve_an_invoice(): void
    {
        $peter = $this->staff('Peter Ssali', 'associate');
        $margaret = $this->staff('Margaret Ssempebwa', 'partner');
        $this->clientWithMatter('Alpha Ltd', 'KSC-2026-0001');
        $invoice = Invoice::create(['code' => 'KSC-INV-2026-0001', 'matter_ref' => 'KSC-2026-0001', 'client' => 'Alpha Ltd', 'status' => 'DRAFT', 'lines' => [['desc' => 'Fees', 'amount' => 100]], 'total' => 100]);

        // Claiming to be the partner in the request makes no difference.
        $this->actingAs($peter)->postJson("/api/vlf/invoices/{$invoice->code}/transition", ['action' => 'approve', 'actor' => 'Margaret Ssempebwa'])->assertForbidden();
        $this->actingAs($margaret)->postJson("/api/vlf/invoices/{$invoice->code}/transition", ['action' => 'approve'])->assertOk()->assertJsonPath('status', 'APPROVED');
    }

    public function test_filing_is_class_a_and_reviewers_cannot_approve_their_own_work(): void
    {
        $tendo = $this->staff('Tendo Mukasa', 'junior');
        $peter = $this->staff('Peter Ssali', 'associate');
        $margaret = $this->staff('Margaret Ssempebwa', 'partner');
        $this->clientWithMatter('Alpha Ltd', 'KSC-2026-0001');

        $doc = ['title' => 'Witness statement', 'matterId' => 'KSC-2026-0001', 'history' => [], 'approvalChain' => []];
        $this->actingAs($tendo)->putJson('/api/vlf/documents/ws-1', ['data' => $doc])->assertOk();
        $this->actingAs($tendo)->postJson('/api/vlf/documents/ws-1/transition', ['action' => 'submit'])->assertOk()->assertJsonPath('data.reviewer', 'Peter Ssali');

        $this->actingAs($tendo)->postJson('/api/vlf/documents/ws-1/transition', ['action' => 'approve'])->assertForbidden();
        // A plain save cannot sneak the status to "approved".
        $this->actingAs($tendo)->putJson('/api/vlf/documents/ws-1', ['data' => $doc + ['currentStatus' => 'APPROVED']])->assertStatus(422);

        $this->actingAs($peter)->postJson('/api/vlf/documents/ws-1/transition', ['action' => 'approve'])->assertOk();
        $this->actingAs($peter)->postJson('/api/vlf/documents/ws-1/transition', ['action' => 'file'])->assertForbidden();
        $this->actingAs($margaret)->postJson('/api/vlf/documents/ws-1/transition', ['action' => 'file'])->assertOk()->assertJsonPath('data.currentStatus', 'FILED');
    }

    public function test_time_is_logged_as_the_signed_in_user_at_their_own_rate(): void
    {
        $peter = $this->staff('Peter Ssali', 'associate', 450000);
        $this->clientWithMatter('Alpha Ltd', 'KSC-2026-0001');

        $this->actingAs($peter)->postJson('/api/vlf/time-entries', [
            'matter' => 'KSC-2026-0001', 'desc' => 'Drafting', 'durationMins' => 60, 'billable' => true,
            'advocate' => 'Someone Else', 'rate' => 99999999,
        ])->assertCreated()->assertJsonPath('advocate', 'Peter Ssali')->assertJsonPath('rate', 450000)->assertJsonPath('amount', 450000);
    }

    public function test_people_only_see_and_mark_their_own_notifications(): void
    {
        $peter = $this->staff('Peter Ssali', 'associate');
        $this->staff('Margaret Ssempebwa', 'partner');
        $theirs = Notification::create(['recipient' => 'Margaret Ssempebwa', 'type' => 'info', 'type_label' => 'Update', 'text' => 'For Margaret']);

        $this->actingAs($peter)->getJson('/api/vlf/notifications?recipient=Margaret%20Ssempebwa')->assertOk()->assertJsonCount(0);
        $this->actingAs($peter)->postJson("/api/vlf/notifications/{$theirs->id}/read")->assertForbidden();
    }

    public function test_only_partners_and_the_administrator_manage_staff_and_new_staff_get_an_invite(): void
    {
        NotificationFacade::fake();
        $peter = $this->staff('Peter Ssali', 'associate');
        $admin = $this->staff('Grace Akello', 'admin', 0);

        $this->actingAs($peter)->postJson('/api/vlf/staff', ['name' => 'Rita Nakato', 'role' => 'Associate'])->assertForbidden();
        $this->actingAs($admin)->postJson('/api/vlf/staff', ['name' => 'Rita Nakato', 'role' => 'Pupil Advocate', 'email' => 'rita@example.com'])
            ->assertCreated()->assertJsonPath('hasLogin', true);

        $rita = User::where('email', 'rita@example.com')->first();
        $this->assertSame('junior', $rita->role);
        NotificationFacade::assertSentTo($rita, ResetPassword::class);
    }
}
