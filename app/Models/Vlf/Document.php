<?php

namespace App\Models\Vlf;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $table = 'vlf_documents';

    protected $guarded = [];

    protected $casts = ['data' => 'array'];

    /**
     * The page's document object, plus download details when a file was uploaded.
     */
    public function toClient(): array
    {
        $data = $this->data;

        if ($this->file_path) {
            $data['file'] = [
                'name' => $this->file_name,
                'mime' => $this->file_mime,
                'size' => $this->file_size,
                'sha256' => $this->file_sha256,
                'url' => 'api/vlf/documents/'.$this->key.'/file',
            ];
        }

        return $data;
    }
}
