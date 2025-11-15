<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\CriteriaProposalStatus;

class CriteriaProposal extends Model
{
    use HasFactory;

    protected $fillable = [
        'name','description','suggested_weight','status','unit_head_id','approved_by','approved_at'
    ];

    protected $casts = [
        'suggested_weight' => 'decimal:2',
        'status' => CriteriaProposalStatus::class,
        'approved_at' => 'datetime',
    ];

    public function unitHead()
    {
        return $this->belongsTo(User::class, 'unit_head_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
