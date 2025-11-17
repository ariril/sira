<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceImportBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_name',
        'assessment_period_id',
        'imported_by',
        'imported_at',
        'total_rows',
        'success_rows',
        'failed_rows',
        'is_superseded',
        'previous_batch_id',
    ];

    protected $casts = [
        'imported_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */
    public function importer()
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function period()
    {
        return $this->belongsTo(\App\Models\AssessmentPeriod::class, 'assessment_period_id');
    }

    public function previous()
    {
        return $this->belongsTo(self::class, 'previous_batch_id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'import_batch_id');
    }

    public function rows()
    {
        return $this->hasMany(AttendanceImportRow::class, 'batch_id');
    }
}
