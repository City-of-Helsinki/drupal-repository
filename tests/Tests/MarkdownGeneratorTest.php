<?php

namespace App\Tests;

use App\MarkdownProcessorTrait;
use PHPUnit\Framework\TestCase;

class MarkdownGeneratorTest extends TestCase
{

    /**
     * @dataProvider markdownData
     */
    public function testProcessMarkdown(string $markdown, string $expected): void
    {
        $trait = new class {
            use MarkdownProcessorTrait {
                processMarkdown as public;
            }
        };
        $processed = $trait->processMarkdown($markdown);
        $this->assertSame($expected, $processed);
    }

    public function markdownData(): array
    {
        // @codingStandardsIgnoreStart
        return [
            [
                // Given markdown.
                "## [city-of-helsinki/drupal-hdbt](https://github.com/city-of-helsinki/drupal-hdbt): 4.3.0 to 4.3.2
### What's Changed
* Uhf x minor bug fixes2 by @Arkkimaagi in https://github.com/City-of-Helsinki/drupal-hdbt/pull/486
* UHF-7444: Add check for empty array item for metadata wrapper template by @teroelonen in https://github.com/City-of-Helsinki/drupal-hdbt/pull/484


**Full Changelog**: https://github.com/City-of-Helsinki/drupal-module-helfi-tpr/compare/2.1.3...2.1.4
## [city-of-helsinki/drupal-module-helfi-tunnistamo](https://github.com/city-of-helsinki/drupal-module-helfi-tunnistamo): 2.2.2 to 2.2.3
### What's Changed
* UHF-7565: Tunnistamo empty email by @ltpk in https://github.com/City-of-Helsinki/drupal-module-helfi-tunnistamo/pull/17

### New Contributors
* @ltpk made their first contribution in https://github.com/City-of-Helsinki/drupal-module-helfi-tunnistamo/pull/17

**Full Changelog**: https://github.com/City-of-Helsinki/drupal-module-helfi-tunnistamo/compare/2.2.2...2.2.3",
                // Expected markdown.
                "## [city-of-helsinki/drupal-hdbt](https://github.com/city-of-helsinki/drupal-hdbt): 4.3.0 to 4.3.2
### What's Changed
* Uhf x minor bug fixes2 in https://github.com/City-of-Helsinki/drupal-hdbt/pull/486
* [UHF-7444](https://helsinkisolutionoffice.atlassian.net/browse/UHF-7444): Add check for empty array item for metadata wrapper template in https://github.com/City-of-Helsinki/drupal-hdbt/pull/484


**Full Changelog**: https://github.com/City-of-Helsinki/drupal-module-helfi-tpr/compare/2.1.3...2.1.4
## [city-of-helsinki/drupal-module-helfi-tunnistamo](https://github.com/city-of-helsinki/drupal-module-helfi-tunnistamo): 2.2.2 to 2.2.3
### What's Changed
* [UHF-7565](https://helsinkisolutionoffice.atlassian.net/browse/UHF-7565): Tunnistamo empty email in https://github.com/City-of-Helsinki/drupal-module-helfi-tunnistamo/pull/17

**Full Changelog**: https://github.com/City-of-Helsinki/drupal-module-helfi-tunnistamo/compare/2.2.2...2.2.3",
            ],
            [
                // Given markdown.
                "## [city-of-helsinki/drupal-hdbt](https://github.com/city-of-helsinki/drupal-hdbt): 4.3.2 to 4.3.3
### What's Changed
* UHF-7264: Fixed issue with empty tags appearing when the tag was unpu… by @teroelonen in https://github.com/City-of-Helsinki/drupal-hdbt/pull/488


**Full Changelog**: https://github.com/City-of-Helsinki/drupal-hdbt/compare/4.3.2...4.3.3
## [city-of-helsinki/drupal-helfi-platform-config](https://github.com/city-of-helsinki/drupal-helfi-platform-config): 2.15.0 to 2.15.1
### What's Changed
* UHF-7419: Accessibility changes to chat button in https://github.com/City-of-Helsinki/drupal-helfi-platform-config/pull/404
* UHF-7417: Check cookie category before setting. by @Arskiainen in https://github.com/City-of-Helsinki/drupal-helfi-platform-config/pull/401


**Full Changelog**: https://github.com/City-of-Helsinki/drupal-helfi-platform-config/compare/2.15.0...2.15.1",
                // Expected markdown.
                "## [city-of-helsinki/drupal-hdbt](https://github.com/city-of-helsinki/drupal-hdbt): 4.3.2 to 4.3.3
### What's Changed
* [UHF-7264](https://helsinkisolutionoffice.atlassian.net/browse/UHF-7264): Fixed issue with empty tags appearing when the tag was unpu… in https://github.com/City-of-Helsinki/drupal-hdbt/pull/488


**Full Changelog**: https://github.com/City-of-Helsinki/drupal-hdbt/compare/4.3.2...4.3.3
## [city-of-helsinki/drupal-helfi-platform-config](https://github.com/city-of-helsinki/drupal-helfi-platform-config): 2.15.0 to 2.15.1
### What's Changed
* [UHF-7419](https://helsinkisolutionoffice.atlassian.net/browse/UHF-7419): Accessibility changes to chat button in https://github.com/City-of-Helsinki/drupal-helfi-platform-config/pull/404
* [UHF-7417](https://helsinkisolutionoffice.atlassian.net/browse/UHF-7417): Check cookie category before setting. in https://github.com/City-of-Helsinki/drupal-helfi-platform-config/pull/401


**Full Changelog**: https://github.com/City-of-Helsinki/drupal-helfi-platform-config/compare/2.15.0...2.15.1",
            ],
        ];
        // @codingStandardsIgnoreEnd
    }
}
