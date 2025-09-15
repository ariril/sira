<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'visit_id',
        'overall_rating',
        'comment',
        'patient_name',
        'contact',
        'client_ip',
        'user_agent',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */
    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }

    public function details()
    {
        return $this->hasMany(ReviewDetail::class);
    }
}
