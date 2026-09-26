<?php

namespace App\Models\Vlf;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $table = 'vlf_invoices';

    protected $guarded = [];

    protected $casts = ['lines' => 'array'];

    public function toClient(): array
    {
        return [
            'id' => $this->code,
            'matter' => $this->matter_ref,
            'clientId' => $this->client_id,
            'client' => $this->client,
            'status' => $this->status,
            'issueDate' => $this->issue_date,
            'dueDate' => $this->due_date,
            'paidDate' => $this->paid_date,
            'lines' => $this->lines,
            'total' => $this->total,
            'paid' => $this->paid,
        ];
    }
}
