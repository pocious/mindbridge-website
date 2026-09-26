<?php

namespace App\Models\Vlf;

use Illuminate\Database\Eloquent\Model;

class TimeEntry extends Model
{
    use DisplaysTime;

    protected $table = 'vlf_time_entries';

    protected $guarded = [];

    protected $casts = ['billable' => 'boolean'];

    public function toClient(): array
    {
        return [
            'id' => $this->code,
            'matter' => $this->matter_ref,
            'task' => $this->task_code,
            'invoice' => $this->invoice_code,
            'desc' => $this->description,
            'advocate' => $this->advocate,
            'date' => $this->displayTime($this->date_label),
            'duration' => $this->duration,
            'durationMins' => $this->duration_mins,
            'billable' => $this->billable,
            'rate' => $this->rate,
            'amount' => $this->amount,
        ];
    }
}
