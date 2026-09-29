<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Vlf\Client;
use App\Models\Vlf\Staff;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'client_id', 'phone', 'active', 'signup_status', 'signup_as', 'signup_organisation'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Roles and the parts of the VLF app each may open.
     * Class A (filing, approvals) needs a partner; Class B an associate; Class C is junior work.
     */
    public const ROLES = [
        'partner' => ['label' => 'Partner', 'shells' => ['adv', 'adm']],
        'associate' => ['label' => 'Associate', 'shells' => ['adv']],
        'junior' => ['label' => 'Junior Associate', 'shells' => ['adv']],
        'clerk' => ['label' => 'Clerk', 'shells' => ['adv']],
        'admin' => ['label' => 'Firm Administrator', 'shells' => ['adm']],
        'client' => ['label' => 'Client', 'shells' => ['cli']],
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function staff(): HasOne
    {
        return $this->hasOne(Staff::class, 'user_id');
    }

    /** Asked for an account from the sign-in page and not yet approved. */
    public function isPendingSignup(): bool
    {
        return $this->signup_status === 'pending';
    }

    public function isClient(): bool
    {
        return $this->role === 'client';
    }

    public function isStaff(): bool
    {
        return ! $this->isClient();
    }

    public function isPartner(): bool
    {
        return $this->role === 'partner';
    }

    /** Can manage staff, settings and firm-wide billing. */
    public function isFirmAdmin(): bool
    {
        return in_array($this->role, ['admin', 'partner'], true);
    }

    /** @return list<string> */
    public function shells(): array
    {
        return self::ROLES[$this->role]['shells'] ?? [];
    }

    public function initials(): string
    {
        return strtoupper(collect(preg_split('/\s+/', trim($this->name)))->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode(''));
    }

    /**
     * What the page needs to know about the signed-in person.
     */
    public function toClient(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'roleLabel' => self::ROLES[$this->role]['label'] ?? $this->role,
            'initials' => $this->initials(),
            'shells' => $this->shells(),
            'clientId' => $this->client_id,
            'clientName' => $this->client?->name,
        ];
    }
}
