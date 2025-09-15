<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\AttendanceStatus;
use App\Enums\AttendanceSource;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'attendance_date',
        'check_in',
        'check_out',
        'attendance_status',
        'overtime_note',
        'source',
        'import_batch_id',
    ];

    protected $casts = [
        'attendance_date'   => 'date',
        'check_in'          => 'datetime:H:i',
        'check_out'         => 'datetime:H:i',
        'attendance_status' => AttendanceStatus::class,
        'source'            => AttendanceSource::class,
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function importBatch()
    {
        return $this->belongsTo(AttendanceImportBatch::class, 'import_batch_id');
    }
}
