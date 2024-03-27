<?php

declare(strict_types=1);

namespace App\Tests;

use App\CacheTrait;
use PHPUnit\Framework\TestCase;

class CacheTraitTest extends TestCase
{
    public function testGetCacheKey(): void
    {
        $trait = new class {
            use CacheTrait {
                getCacheKey as public;
            }
        };
        $this->assertSame($trait->getCacheKey(
            'City-of-Helsinki',
            'drupal-helfi',
            'dev',
            'update-configuration',
        ), 'city-of-helsinki-drupal-helfi-dev-update-configuration');

        $this->assertSame($trait->getCacheKey('helfi', '123'), 'helfi-123');
    }
}
