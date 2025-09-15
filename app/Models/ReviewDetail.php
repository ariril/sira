<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Enums\MedicalStaffReviewRole;

class ReviewDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'review_id',
        'medical_staff_id',
        'role',
        'rating',
        'comment',
    ];

    protected $casts = [
        'rating' => 'integer',
        'role'   => MedicalStaffReviewRole::class,
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */
    public function review()
    {
        return $this->belongsTo(Review::class);
    }

    public function medicalStaff()
    {
        return $this->belongsTo(User::class, 'medical_staff_id');
    }
}
