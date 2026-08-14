<?php

namespace App\Enums;

enum FasEventType: string
{
    case HOME_WIN = 'HOME_WIN';
    case AWAY_WIN = 'AWAY_WIN';
    case DRAW = 'DRAW';
    case OVER_1_5 = 'OVER_1_5';
    case OVER_2_5 = 'OVER_2_5';
    case BTTS = 'BTTS';
    case FIRST_HALF_GOAL = 'FIRST_HALF_GOAL';
    case OVER_CORNERS = 'OVER_CORNERS';
    case OVER_CARDS = 'OVER_CARDS';
}
