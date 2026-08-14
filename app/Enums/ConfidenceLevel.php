<?php

namespace App\Enums;

enum ConfidenceLevel: string
{
    case HIGH = 'HIGH';
    case MEDIUM = 'MEDIUM';
    case LOW = 'LOW';
    case NONE = 'NONE';
}
