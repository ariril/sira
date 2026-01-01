<?php

namespace App\Services\CriteriaEngine\Collectors;

use App\Enums\ReviewStatus;
use App\Models\AssessmentPeriod;
use App\Services\CriteriaEngine\Contracts\CriteriaCollector;
use Illuminate\Support\Facades\DB;

class RatingCollector implements CriteriaCollector
{
    public function key(): string { return 'rating'; }

    public function label(): string { return 'Rating'; }

    public function type(): string { return 'benefit'; }

    public function source(): string { return 'system'; }

    public function collect(AssessmentPeriod $period, int $unitId, array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $start = $period->start_date;
        $end = $period->end_date;

        // Excel rule for TOTAL_UNIT normalization (rating):
        // - raw rating per staff is conceptually AVG(rating)
        // - but normalization uses AVG(rating) * jumlah_rater (i.e., SUM(rating)) as the aggregated value.
        // Since the engine consumes a single numeric "raw" value, we collect SUM(rating) per staff.
        $query = DB::table('review_details')
            ->join('reviews', 'reviews.id', '=', 'review_details.review_id')
            ->selectRaw('review_details.medical_staff_id as user_id, SUM(review_details.rating) as rating_sum')
            ->whereNotNull('review_details.rating')
            ->where('reviews.status', ReviewStatus::APPROVED)
            ->where('reviews.unit_id', $unitId)
            ->whereIn('review_details.medical_staff_id', $userIds)
            ->groupBy('review_details.medical_staff_id');

        if ($start) {
            $query->whereDate('reviews.decided_at', '>=', $start);
        }
        if ($end) {
            $query->whereDate('reviews.decided_at', '<=', $end);
        }

        return $query
            ->pluck('rating_sum', 'user_id')
            ->map(fn($v) => (float) $v)
            ->all();
    }

    public function readiness(AssessmentPeriod $period, int $unitId): array
    {
        if ($unitId <= 0) {
            return ['status' => 'missing_data', 'message' => 'Unit belum valid.'];
        }

        $start = $period->start_date;
        $end = $period->end_date;

        $q = DB::table('reviews')
            ->where('unit_id', $unitId)
            ->where('status', ReviewStatus::APPROVED);

        if ($start) {
            $q->whereDate('decided_at', '>=', $start);
        }
        if ($end) {
            $q->whereDate('decided_at', '<=', $end);
        }

        $count = (int) $q->count();

        return $count > 0
            ? ['status' => 'ready', 'message' => null]
            : ['status' => 'missing_data', 'message' => 'Belum ada review/rating APPROVED pada periode ini.'];
    }
}
