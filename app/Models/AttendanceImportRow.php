<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceImportRow extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'row_no',
        'user_id',
        'employee_number',
        'raw_data',
        'parsed_data',
        'success',
        'error_code',
        'error_message',
    ];

    protected $casts = [
        'success' => 'boolean',
        'raw_data' => 'array',
        'parsed_data' => 'array',
    ];

    public function batch()
    {
        return $this->belongsTo(AttendanceImportBatch::class, 'batch_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
