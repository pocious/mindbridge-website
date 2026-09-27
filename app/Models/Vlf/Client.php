<?php

namespace App\Models\Vlf;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $table = 'vlf_clients';

    protected $guarded = [];

    protected $casts = ['verified' => 'boolean'];

    public function matters(): HasMany
    {
        return $this->hasMany(Matter::class, 'client_id');
    }

    public function toClient(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'tin' => $this->tin,
            'type' => $this->type,
            'contactName' => $this->contact_name,
            'contactEmail' => $this->contact_email,
            'contactPhone' => $this->contact_phone,
            'address' => $this->address,
            'verified' => $this->verified,
            'notes' => $this->notes,
            'matters' => $this->matters->pluck('ref')->values(),
            'portalUsers' => User::where('client_id', $this->id)->pluck('email')->values(),
        ];
    }
}
