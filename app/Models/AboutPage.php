<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Enums\AboutPageType;

class AboutPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'title',
        'content',
        'image_path',
        'attachments',
        'published_at',
        'is_active',
    ];

    protected $casts = [
        'type'         => AboutPageType::class,
        'is_active'    => 'boolean',
        'published_at' => 'datetime',
        'attachments'  => 'array',
    ];

    // Optional: default nilai aktif (ikut default DB = 1, ini hanya jaga-jaga saat mass create)
    protected $attributes = [
        'is_active' => true,
    ];

    public const TYPE_VISI      = 'visi';
    public const TYPE_MISI      = 'misi';
    public const TYPE_STRUKTUR  = 'struktur';
    public const TYPE_PROFIL_RS = 'profil_rs';
    public const TYPE_TUGAS     = 'tugas_fungsi';

    public function scopeType($q, string $type)
    {
        return $q->where('type', $type);
    }
}
