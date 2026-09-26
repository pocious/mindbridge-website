<?php

namespace App\Models\Vlf;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use DisplaysTime;

    protected $table = 'vlf_notifications';

    protected $guarded = [];

    protected $casts = ['link' => 'array', 'read_at' => 'datetime', 'emailed_at' => 'datetime'];

    public function toClient(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'icon' => $this->icon,
            'typeLabel' => $this->type_label,
            'text' => $this->text,
            'link' => $this->link,
            'ts' => $this->displayTime($this->ts_label),
            'unread' => $this->read_at === null,
            'emailed' => $this->emailed_at !== null,
        ];
    }
}
