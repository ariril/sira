<?php

namespace App\Services\MultiRater;

use App\Models\ProfessionReportingLine;
use App\Models\User;

class AssessorTypeResolver
{
    public const TYPES = ['self', 'supervisor', 'peer', 'subordinate'];

    public static function resolveByIds(
        int $assessorId,
        ?int $assessorProfessionId,
        int $assesseeId,
        ?int $assesseeProfessionId,
        bool $assessorHasKepalaPoliklinikRole = false
    ): string {
        if ($assessorId === $assesseeId) {
            return 'self';
        }

        if ($assesseeProfessionId && $assessorProfessionId) {
            $relationType = ProfessionReportingLine::query()
                ->where('assessee_profession_id', (int) $assesseeProfessionId)
                ->where('assessor_profession_id', (int) $assessorProfessionId)
                ->where('is_active', true)
                ->pluck('relation_type')
                ->unique()
                ->values();

            if ($relationType->contains('supervisor')) {
                return 'supervisor';
            }
            if ($relationType->contains('peer')) {
                return 'peer';
            }
            if ($relationType->contains('subordinate')) {
                return 'subordinate';
            }
        }

        if ($assessorHasKepalaPoliklinikRole) {
            return 'supervisor';
        }

        return 'peer';
    }

    /**
     * Resolve assessor relationship type for 360.
     *
     * IMPORTANT: $assessorProfessionId enables multi-role/multi-profession context.
     */
    public static function resolve(User $assessor, User $assessee, ?int $assessorProfessionId = null): string
    {
        if ((int) $assessor->id === (int) $assessee->id) {
            return 'self';
        }

        $assesseeProfessionId = $assessee->profession_id ? (int) $assessee->profession_id : null;
        $assessorProfessionId = $assessorProfessionId ?: ($assessor->profession_id ? (int) $assessor->profession_id : null);

        if ($assesseeProfessionId && $assessorProfessionId) {
            return self::resolveByIds(
                (int) $assessor->id,
                (int) $assessorProfessionId,
                (int) $assessee->id,
                (int) $assesseeProfessionId,
                $assessor->hasRole('kepala_poliklinik')
            );
        }

        // Fallback: keep legacy heuristic to avoid breaking when hierarchy table is incomplete.
        // Leadership roles are treated as supervisor relationship.
        if ($assessor->hasRole('kepala_poliklinik')) {
            return 'supervisor';
        }

        $assessorLevel = self::professionLevel($assessor);
        $assesseeLevel = self::professionLevel($assessee);

        if ($assessorLevel > $assesseeLevel) {
            return 'supervisor';
        }

        if ($assessorLevel < $assesseeLevel) {
            return 'subordinate';
        }

        return 'peer';
    }

    private static function professionLevel(User $user): int
    {
        // Default: treat unknown professions as baseline.
        $code = $user->profession?->code;
        if (!$code) {
            return 1;
        }

        // Simple hierarchy: doctors > nurses. Can be refined later.
        if (str_starts_with($code, 'DOK')) {
            return 2;
        }

        if ($code === 'PRW') {
            return 1;
        }

        return 1;
    }
}
