<?php

namespace App\Services\MultiRater;

use Illuminate\Support\Facades\DB;

class AssessorLevelResolver
{
    /**
     * Resolve supervisor level (L1/L2/...) from profession_reporting_lines.
     *
     * If an exact assessor profession mapping is not found and $fallbackToFirstLevel is true,
     * returns the smallest active supervisor level for the assessee profession.
     */
    public static function resolveSupervisorLevel(
        int $assesseeProfessionId,
        int $assessorProfessionId,
        bool $fallbackToFirstLevel = true
    ): ?int {
        $assesseeProfessionId = (int) $assesseeProfessionId;
        $assessorProfessionId = (int) $assessorProfessionId;

        if ($assesseeProfessionId <= 0 || $assessorProfessionId <= 0) {
            return null;
        }

        $level = DB::table('profession_reporting_lines')
            ->where('relation_type', 'supervisor')
            ->where('is_active', 1)
            ->where('assessee_profession_id', $assesseeProfessionId)
            ->where('assessor_profession_id', $assessorProfessionId)
            ->orderByRaw('CASE WHEN level IS NULL THEN 999999 ELSE level END ASC')
            ->value('level');

        if ($level !== null && (int) $level > 0) {
            return (int) $level;
        }

        if (!$fallbackToFirstLevel) {
            return null;
        }

        $fallback = DB::table('profession_reporting_lines')
            ->where('relation_type', 'supervisor')
            ->where('is_active', 1)
            ->where('assessee_profession_id', $assesseeProfessionId)
            ->whereNotNull('level')
            ->orderBy('level')
            ->value('level');

        return $fallback !== null && (int) $fallback > 0 ? (int) $fallback : null;
    }
}
