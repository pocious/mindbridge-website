<?php

namespace App\Models\Vlf;

use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use DisplaysTime;

    protected $table = 'vlf_comments';

    protected $guarded = [];

    protected $casts = ['replies' => 'array'];

    public function toClient(): array
    {
        return [
            'id' => 'c'.$this->id,
            'author' => $this->author,
            'av' => $this->author_av,
            'ts' => $this->displayTime($this->ts_label),
            'context' => $this->context,
            'text' => $this->text,
            'replies' => $this->replies ?? [],
        ];
    }
}
