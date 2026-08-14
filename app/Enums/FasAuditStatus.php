<?php

namespace App\Enums;

enum FasAuditStatus: string
{
    case PENDING = 'PENDING';
    case HIT = 'HIT';
    case MISS = 'MISS';
    case VOID = 'VOID';
    case UNAVAILABLE = 'UNAVAILABLE';
}
