<?php

namespace App\Models\Vlf;

use Illuminate\Database\Eloquent\Model;

class CourtEvent extends Model
{
    protected $table = 'vlf_court_events';

    protected $guarded = [];

    protected $casts = ['date' => 'date', 'checklist' => 'array'];

    public function toClient(): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'matter' => $this->matter_ref,
            'title' => $this->title,
            'court' => $this->court,
            'judge' => $this->judge,
            'date' => $this->date->toDateString(),
            'dateLabel' => $this->date->format('l j F Y'),
            'time' => $this->time,
            'advocate' => $this->advocate,
            'level' => $this->level,
            'notes' => $this->notes,
            'checklist' => $this->checklist ?? [],
        ];
    }
}
