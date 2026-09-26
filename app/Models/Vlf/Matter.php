<?php

namespace App\Models\Vlf;

use Illuminate\Database\Eloquent\Model;

class Matter extends Model
{
    protected $table = 'vlf_matters';

    protected $guarded = [];

    public function toClient(): array
    {
        return [
            'ref' => $this->ref,
            'title' => $this->title,
            'court' => $this->court,
            'judge' => $this->judge,
            'advocate' => $this->advocate,
            'stage' => $this->stage,
            'riskLevel' => $this->risk_level,
            'riskNote' => $this->risk_note,
        ];
    }
}
