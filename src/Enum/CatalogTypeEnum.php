<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Onglets du catalogue (Films / Séries / Animés).
 * Un animé est un média (film ou série) de la catégorie « Animation ».
 */
enum CatalogTypeEnum: string
{
    case MOVIE = 'movie';
    case SERIE = 'serie';
    case ANIME = 'anime';

    public const ANIMATION_CATEGORY = 'Animation';

    public function label(): string
    {
        return match ($this) {
            self::MOVIE => 'Films',
            self::SERIE => 'Séries',
            self::ANIME => 'Animés',
        };
    }
}
