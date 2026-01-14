<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\AssessmentValidationStatus;

class PerformanceAssessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'assessment_period_id',
        'assessment_date',
        'total_wsm_score',
        'total_wsm_value_score',
        'validation_status',
        'supervisor_comment',
    ];

    protected $casts = [
        'assessment_date'   => 'date',
        'total_wsm_score'   => 'decimal:2',
        'total_wsm_value_score' => 'decimal:2',
        'validation_status' => AssessmentValidationStatus::class,
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    /** Pegawai yang dinilai */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Periode penilaian */
    public function assessmentPeriod()
    {
        return $this->belongsTo(AssessmentPeriod::class);
    }

    /** Detail skor per kriteria */
    public function details()
    {
        return $this->hasMany(PerformanceAssessmentDetail::class, 'performance_assessment_id');
    }

    /** Alur persetujuan multi-level */
    public function approvals()
    {
        return $this->hasMany(AssessmentApproval::class, 'performance_assessment_id');
    }

    /**
     * Remunerasi hasil penilaian.
     * Catatan: tabel remunerations TIDAK menyimpan FK ke performance_assessments.
     * Hubungkan via (user_id, assessment_period_id).
     */
    public function remuneration()
    {
        return $this->hasOne(Remuneration::class, 'user_id', 'user_id')
            ->whereColumn('remunerations.assessment_period_id', 'performance_assessments.assessment_period_id');
    }
}
