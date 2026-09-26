<?php

namespace App\Models\Vlf;

use Illuminate\Database\Eloquent\Model;

class Deadline extends Model
{
    protected $table = 'vlf_deadlines';

    protected $guarded = [];

    protected $casts = ['due_date' => 'date', 'done_at' => 'datetime'];

    public function toClient(): array
    {
        return [
            'id' => $this->id,
            'matter' => $this->matter_ref,
            'title' => $this->title,
            'dueDate' => $this->due_date->toDateString(),
            'dueLabel' => $this->due_date->format('D j M Y'),
            'dueTime' => $this->due_time,
            'owner' => $this->owner,
            'severity' => $this->severity,
            'done' => $this->done_at !== null,
        ];
    }
}
