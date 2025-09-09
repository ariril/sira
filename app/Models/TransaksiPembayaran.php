<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransaksiPembayaran extends Model
{
    use HasFactory;
    protected $table = 'transaksi_pembayaran';

    protected $fillable = [
        'id_pasien',
        'tanggal_transaksi',
        'jumlah_pembayaran',
        'metode_pembayaran',
        'status_pembayaran',
        'nomor_referensi_pembayaran',
    ];

    public function pasien()
    {
        return $this->belongsTo(Pasien::class, 'id_pasien', 'id_pasien');
    }
}
