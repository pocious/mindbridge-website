<?php

namespace App\Models\Vlf;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'vlf_settings';

    protected $guarded = [];

    protected $casts = ['value' => 'json'];
}
