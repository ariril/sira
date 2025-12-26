<?php

namespace App\Services\MultiRater;

use App\Models\User;

class AssessorTypeResolver
{
    public const TYPES = ['self', 'supervisor', 'peer', 'subordinate'];

    public static function resolve(User $assessor, User $assessee): string
    {
        if ((int) $assessor->id === (int) $assessee->id) {
            return 'self';
        }

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
