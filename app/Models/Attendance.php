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
        'shift_name',
        'scheduled_in',
        'scheduled_out',
        'late_minutes',
        'early_leave_minutes',
        'work_duration_minutes',
        'break_duration_minutes',
        'extra_break_minutes',
        'overtime_end',
        'holiday_public',
        'holiday_regular',
        'overtime_shift',
        'attendance_status',
        'note',
        'overtime_note',
        'source',
        'import_batch_id',
    ];

    protected $casts = [
        'attendance_date'   => 'date',
        // check_in/check_out are TIME columns; keep raw string (no cast)
        'holiday_public'    => 'boolean',
        'holiday_regular'   => 'boolean',
        'overtime_shift'    => 'boolean',
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
