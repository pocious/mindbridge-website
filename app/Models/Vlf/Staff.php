<?php

namespace App\Models\Vlf;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Staff extends Model
{
    protected $table = 'vlf_staff';

    protected $guarded = [];

    protected $casts = ['active' => 'boolean', 'month_hours' => 'float'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toClient(): array
    {
        return [
            'id' => $this->id,
            'userId' => $this->user_id,
            'name' => $this->name,
            'initials' => $this->initials,
            'role' => $this->role,
            'email' => $this->email,
            'rate' => $this->rate,
            'status' => $this->status,
            'active' => $this->active,
            'monthHours' => $this->month_hours,
            'hasLogin' => $this->user_id !== null,
            'activeMatters' => Matter::where('advocate', $this->name)->orWhere('supervisor', $this->name)->count(),
        ];
    }
}
