<?php

namespace App\Models\Vlf;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use DisplaysTime;

    protected $table = 'vlf_messages';

    protected $guarded = [];

    protected $casts = ['mine' => 'boolean'];

    public function toClient(): array
    {
        return [
            'from' => $this->author_av,
            'name' => $this->author_name,
            'ts' => $this->displayTime($this->ts_label),
            'mine' => $this->mine,
            'text' => $this->text,
        ];
    }
}
