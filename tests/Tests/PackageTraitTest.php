<?php

declare(strict_types=1);

namespace App\Tests;

use App\PackageTrait;
use PHPUnit\Framework\TestCase;

class PackageTraitTest extends TestCase
{
    public function testGetPackageName(): void
    {
        $trait = new class {
            use PackageTrait {
                getPackageName as public;
            }

            public function __construct()
            {
                $this->packages = [
                    'drupal/helfi_api_base' => (object) [
                        'url' => 'https://github.com/city-of-helsinki/drupal-module-helfi-api-base',
                        'name' => 'drupal/helfi_api_base',
                    ],
                ];
            }

        };
        $this->assertEquals('drupal/helfi_api_base', $trait->getPackageName('drupal/helfi_api_base'));
        $this->assertEquals('drupal/helfi_api_base', $trait->getPackageName('https://github.com/city-of-helsinki/drupal-module-helfi-api-base'));
    }
}
