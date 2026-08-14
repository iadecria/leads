<?php

namespace App\Enums;

enum RankingType: string
{
    case TOP3 = 'TOP3';
    case TOP5 = 'TOP5';
    case WATCHLIST = 'WATCHLIST';
    case REJECTED = 'REJECTED';
}
