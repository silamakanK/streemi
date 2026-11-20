<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\CatalogTypeEnum;
use PHPUnit\Framework\TestCase;

class CatalogTypeEnumTest extends TestCase
{
    public function testLabels(): void
    {
        $this->assertSame('Films', CatalogTypeEnum::MOVIE->label());
        $this->assertSame('Séries', CatalogTypeEnum::SERIE->label());
        $this->assertSame('Animés', CatalogTypeEnum::ANIME->label());
    }

    public function testUnknownTypeFromTheUrlIsIgnored(): void
    {
        $this->assertNull(CatalogTypeEnum::tryFrom('nimporte'));
        $this->assertSame(CatalogTypeEnum::ANIME, CatalogTypeEnum::tryFrom('anime'));
    }
}
