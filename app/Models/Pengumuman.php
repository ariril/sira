<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pengumuman extends Model
{

    protected $table = 'pengumuman';
    protected $casts = [
        'lampiran_json' => 'array',
        'dipublikasikan_pada' => 'datetime',
        'kedaluwarsa_pada' => 'datetime',
        'disorot' => 'boolean',
    ];
}
