<?php

namespace App\Models\Vlf;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use DisplaysTime;

    protected $table = 'vlf_messages';

    protected $guarded = [];

    protected $casts = ['mine' => 'boolean'];

    /**
     * @param  User|null  $viewer  "mine" is true when the viewer wrote it (seeded rows keep their stored flag)
     */
    public function toClient(?User $viewer = null): array
    {
        return [
            'from' => $this->author_av,
            'name' => $this->author_name,
            'ts' => $this->displayTime($this->ts_label),
            'mine' => $this->user_id !== null && $viewer !== null ? $this->user_id === $viewer->id : $this->mine,
            'text' => $this->text,
        ];
    }
}
