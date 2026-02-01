<?php

namespace App\Services\MultiRater;

use App\Models\Profession;
use App\Models\User;

class AssessorProfessionResolver
{
    /**
     * Resolve the assessor profession context based on the active rater role.
     * This enables one user to submit separate 360 assessments under different roles.
     */
    public static function resolve(User $assessor, ?string $raterRole): ?int
    {
        $raterRole = $raterRole ? trim($raterRole) : null;

        if ($raterRole === 'kepala_unit') {
            return self::professionIdByCode('KPL-UNIT') ?? $assessor->profession_id;
        }

        if ($raterRole === 'kepala_poliklinik') {
            return self::professionIdByCode('KPL-POLI') ?? $assessor->profession_id;
        }

        // Default: use the user's base profession.
        return $assessor->profession_id;
    }

    private static function professionIdByCode(string $code): ?int
    {
        static $cache = [];
        if (array_key_exists($code, $cache)) {
            return $cache[$code];
        }

        $id = Profession::query()->where('code', $code)->value('id');
        $cache[$code] = $id ? (int) $id : null;
        return $cache[$code];
    }
}
