<?php

namespace App\Enums;

enum DataQualityLevel: string
{
    case EXCELLENT = 'EXCELLENT'; // 90-100
    case HIGH = 'HIGH';           // 75-89
    case MEDIUM = 'MEDIUM';       // 60-74
    case LOW = 'LOW';             // 40-59
    case INSUFFICIENT = 'INSUFFICIENT'; // 0-39

    public static function fromScore(int $score): self
    {
        if ($score >= 90) {
            return self::EXCELLENT;
        }
        if ($score >= 75) {
            return self::HIGH;
        }
        if ($score >= 60) {
            return self::MEDIUM;
        }
        if ($score >= 40) {
            return self::LOW;
        }

        return self::INSUFFICIENT;
    }
}
