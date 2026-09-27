<?php

namespace App\Support;

use App\Models\User;
use App\Models\Vlf\Client;
use App\Models\Vlf\Notification;
use App\Models\Vlf\Setting;
use App\Models\Vlf\Staff;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Records a VLF notification for a person (by display name) and emails it when
 * that person has an address on file — staff email or a client contact email.
 * With MAIL_MAILER=log the email is written to storage/logs instead of sent.
 */
class VlfNotifier
{
    public const TYPES = [
        'critical' => ['⛔', 'Critical — Action required'],
        'action' => ['⚡', 'Action required'],
        'task' => ['📋', 'Task assigned'],
        'info' => ['✓', 'Update'],
        'warn' => ['⚠', 'Attention'],
    ];

    /**
     * @param  array{matter?: string, tab?: string, doc?: string, page?: string}|null  $link
     * @param  string|null  $setting  firm setting that can switch this kind of alert off, e.g. "notify_deadlines"
     */
    public static function notify(?string $recipient, string $type, string $text, ?array $link = null, ?string $label = null, ?string $setting = null): ?Notification
    {
        // Load the model (not ->value()) so the JSON cast turns the stored "false" into false.
        if (! $recipient || ($setting && Setting::where('key', $setting)->first()?->value === false)) {
            return null;
        }

        [$icon, $defaultLabel] = self::TYPES[$type] ?? self::TYPES['info'];

        $notification = Notification::create([
            'recipient' => $recipient,
            // The page styles critical / action / info / warn; a task is shown as an action.
            'type' => match (true) {
                $type === 'task' => 'action',
                isset(self::TYPES[$type]) => $type,
                default => 'info',
            },
            'icon' => $icon,
            'type_label' => $label ?? $defaultLabel,
            'text' => $text,
            'link' => $link,
        ]);

        $email = User::where('name', $recipient)->where('active', true)->value('email')
            ?? Staff::where('name', $recipient)->value('email')
            ?? Client::where('contact_name', $recipient)->value('contact_email');

        if ($email) {
            try {
                Mail::raw($text."\n\n— GAVEL.CO Virtual Law Firm", function ($message) use ($email, $label, $defaultLabel) {
                    $message->to($email)->subject('[VLF] '.($label ?? $defaultLabel));
                });
                $notification->update(['emailed_at' => now()]);
            } catch (Throwable $e) {
                Log::warning('VLF notification email failed', ['to' => $email, 'error' => $e->getMessage()]);
            }
        }

        return $notification;
    }
}
