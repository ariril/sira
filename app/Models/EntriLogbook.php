<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntriLogbook extends Model
{
    protected $table = 'entri_logbook';
    protected $casts = [
        'lampiran_json' => 'array',
        'tanggal' => 'date',
        'disetujui_pada' => 'datetime',
    ];
}
