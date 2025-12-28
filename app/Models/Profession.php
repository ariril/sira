<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Profession extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'code', 'description', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */
    public function users()
    {
        return $this->hasMany(User::class, 'profession_id');
    }

    public function reportingLinesAsAssessee()
    {
        return $this->hasMany(ProfessionReportingLine::class, 'assessee_profession_id');
    }

    public function reportingLinesAsAssessor()
    {
        return $this->hasMany(ProfessionReportingLine::class, 'assessor_profession_id');
    }
}
