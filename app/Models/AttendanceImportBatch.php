<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceImportBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_name',
        'imported_by',
        'imported_at',
        'total_rows',
        'success_rows',
        'failed_rows',
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

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'import_batch_id');
    }
}
