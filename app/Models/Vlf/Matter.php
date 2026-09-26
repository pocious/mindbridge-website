<?php

namespace App\Models\Vlf;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Matter extends Model
{
    protected $table = 'vlf_matters';

    protected $guarded = [];

    protected $casts = ['instruction_date' => 'date'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function toClient(): array
    {
        return [
            'ref' => $this->ref,
            'clientId' => $this->client_id,
            'client' => $this->client?->name,
            'title' => $this->title,
            'opposingParty' => $this->opposing_party,
            'practiceArea' => $this->practice_area,
            'description' => $this->description,
            'court' => $this->court,
            'judge' => $this->judge,
            'advocate' => $this->advocate,
            'supervisor' => $this->supervisor,
            'feeArrangement' => $this->fee_arrangement,
            'instructionDate' => $this->instruction_date?->toDateString(),
            'stage' => $this->stage,
            'statusLabel' => $this->status_label,
            'statusLevel' => $this->status_level,
            'riskLevel' => $this->risk_level,
            'riskNote' => $this->risk_note,
        ];
    }
}
