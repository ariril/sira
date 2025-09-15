<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\AnnouncementCategory;
use App\Enums\AnnouncementLabel;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'summary',
        'content',
        'category',
        'label',
        'is_featured',
        'published_at',
        'expired_at',
        'attachments',
        'author_id',
    ];

    protected $casts = [
        'attachments'   => 'array',
        'published_at'  => 'datetime',
        'expired_at'    => 'datetime',
        'is_featured'   => 'boolean',
        'category'      => AnnouncementCategory::class,
        'label'         => AnnouncementLabel::class,
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
