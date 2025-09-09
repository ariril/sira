<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class HalamanTentang extends Model
{
    use HasFactory;

    protected $table = 'halaman_tentang';
    protected $fillable = [
        'type','title','content','image_path','attachments_json',
        'published_at','is_active','author_id'
    ];

    protected $casts = [
        'attachments_json' => 'array',
        'published_at'     => 'datetime',
        'is_active'        => 'boolean',
    ];

    public const TYPE_VISI      = 'visi';
    public const TYPE_MISI      = 'misi';
    public const TYPE_STRUKTUR  = 'struktur';
    public const TYPE_PROFIL_RS = 'profil_rs';
    public const TYPE_TUGAS     = 'tugas_fungsi';

    public function scopeType($q, string $type) { return $q->where('type', $type); }
}

