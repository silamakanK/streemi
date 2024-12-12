<?php

declare(strict_types=1);

namespace App\Enum;

enum MediaTypeEnum: string
{
    case MOVIE = 'movie';
    case SERIE = 'serie';
}