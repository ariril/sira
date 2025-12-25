<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetricImportBatch extends Model
{
    use HasFactory;

    protected $table = 'metric_import_batches';

    protected $fillable = [
        'file_name',
        'assessment_period_id',
        'imported_by',
        'status',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriod::class, 'assessment_period_id');
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function values(): HasMany
    {
        return $this->hasMany(CriteriaMetric::class, 'import_batch_id');
    }
}
