<?php

namespace Divido\DividoFinancing\Test\Unit\Helper\Data;

use PHPUnit\Framework\TestCase;

class LanguagesTest extends TestHelper
{
    // Dumb test to see the data instance is as we think it should be
    public function test_ShouldHaveLanguages(): void
    {
        self::assertIsArray(
            $this->dataInstance::WIDGET_LANGUAGES
        );

        $expectedLanguages = ["en", "fi" , "no", "es", "da", "fr", "de", "pe"];

        self::assertEquals(
            sizeof($this->dataInstance::WIDGET_LANGUAGES),
            sizeof($expectedLanguages)
        );

        foreach ($expectedLanguages as $languageCode ) {
            self::assertContains($languageCode, $this->dataInstance::WIDGET_LANGUAGES);
        }
    }
}
