<?php

namespace App\Enums;

enum ConfidenceLevel: string
{
    case HIGH = 'HIGH';
    case VERY_HIGH = 'VERY_HIGH';
    case MEDIUM = 'MEDIUM';
    case LOW = 'LOW';
    case VERY_LOW = 'VERY_LOW';
    case NONE = 'NONE';
}
