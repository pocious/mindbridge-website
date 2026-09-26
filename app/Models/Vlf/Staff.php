<?php

namespace App\Models\Vlf;

use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    protected $table = 'vlf_staff';

    protected $guarded = [];

    protected $casts = ['active' => 'boolean', 'month_hours' => 'float'];

    public function toClient(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'initials' => $this->initials,
            'role' => $this->role,
            'email' => $this->email,
            'rate' => $this->rate,
            'status' => $this->status,
            'active' => $this->active,
            'monthHours' => $this->month_hours,
            'activeMatters' => Matter::where('advocate', $this->name)->orWhere('supervisor', $this->name)->count(),
        ];
    }
}
