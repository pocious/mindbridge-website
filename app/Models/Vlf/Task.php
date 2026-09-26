<?php

namespace App\Models\Vlf;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $table = 'vlf_tasks';

    protected $guarded = [];

    protected $casts = ['billable' => 'boolean'];

    public function toClient(): array
    {
        return [
            'id' => $this->code,
            'matter' => $this->matter_ref,
            'matterTitle' => $this->matter_title,
            'class' => $this->class,
            'title' => $this->title,
            'desc' => $this->description,
            'priority' => $this->priority,
            'assignedTo' => $this->assigned_to,
            'assignedBy' => $this->assigned_by,
            'deadline' => $this->deadline,
            'status' => $this->status,
            'blockedBy' => $this->blocked_by,
            'estimatedTime' => $this->estimated_time,
            'billable' => $this->billable,
            'relatedDoc' => $this->related_doc,
        ];
    }
}
