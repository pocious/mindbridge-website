<?php

namespace App\Models\Vlf;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $table = 'vlf_documents';

    protected $guarded = [];

    protected $casts = ['data' => 'array'];
}
