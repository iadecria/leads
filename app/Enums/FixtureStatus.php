<?php

namespace App\Enums;

enum FixtureStatus: string
{
    case SCHEDULED = 'SCHEDULED';
    case IN_PLAY = 'IN_PLAY';
    case PAUSED = 'PAUSED';
    case FINISHED = 'FINISHED';
    case POSTPONED = 'POSTPONED';
    case CANCELLED = 'CANCELLED';
    case UNKNOWN = 'UNKNOWN';
}
