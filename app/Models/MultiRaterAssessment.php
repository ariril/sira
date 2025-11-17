<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MultiRaterAssessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'assessee_id','assessor_id','assessor_type','assessment_period_id','status','submitted_at'
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function assessee() { return $this->belongsTo(User::class, 'assessee_id'); }
    public function assessor() { return $this->belongsTo(User::class, 'assessor_id'); }
    public function period() { return $this->belongsTo(AssessmentPeriod::class, 'assessment_period_id'); }
    public function details() { return $this->hasMany(MultiRaterAssessmentDetail::class); }
}
