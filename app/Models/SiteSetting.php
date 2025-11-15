<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SiteSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'short_name',
        'short_description',
        'address',
        'phone',
        'email',
        'logo_path',
        'favicon_path',
    'hero_path',
        'facebook_url',
        'instagram_url',
        'twitter_url',
        'youtube_url',
        'footer_text',
        'updated_by',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
