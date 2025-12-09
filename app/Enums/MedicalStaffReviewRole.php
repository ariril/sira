<?php

namespace App\Enums;

enum MedicalStaffReviewRole: string
{
    case DOKTER = 'dokter';
    case PERAWAT = 'perawat';
    case LAINNYA = 'lainnya';

    /**
     * Derive enum value from a profession name string.
     */
    public static function guessFromProfession(?string $profession): self
    {
        if (!$profession) {
            return self::LAINNYA;
        }

        $name = strtolower($profession);

        if (str_contains($name, 'dokter')) {
            return self::DOKTER;
        }

        if (str_contains($name, 'perawat')) {
            return self::PERAWAT;
        }

        return self::LAINNYA;
    }
}
