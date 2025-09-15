<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;

class PaymentTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'visit_id',
        'queue_id',
        'transaction_date',
        'amount',
        'payment_method',
        'channel',
        'payment_status',
        'paid_at',
        'payment_reference_number',
        'created_by',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'paid_at'          => 'datetime',
        'amount'           => 'decimal:2',
        'channel'          => PaymentChannel::class,
        'payment_status'   => PaymentStatus::class,
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

    public function queue()
    {
        return $this->belongsTo(PatientQueue::class, 'queue_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
