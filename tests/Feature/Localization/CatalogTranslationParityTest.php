<?php

namespace Tests\Feature\Localization;

use Tests\TestCase;

final class CatalogTranslationParityTest extends TestCase
{
    public function test_en_and_ar_catalog_keys_are_identical(): void
    {
        $en = require base_path('app/Modules/Integrations/Lang/en/catalog.php');
        $ar = require base_path('app/Modules/Integrations/Lang/ar/catalog.php');

        $enPaths = $this->flatten($en);
        $arPaths = $this->flatten($ar);

        sort($enPaths);
        sort($arPaths);

        $missingInAr = array_values(array_diff($enPaths, $arPaths));
        $missingInEn = array_values(array_diff($arPaths, $enPaths));

        $this->assertSame(
            [],
            $missingInAr,
            'Keys present in en/catalog.php but missing in ar/catalog.php: '.implode(', ', $missingInAr),
        );
        $this->assertSame(
            [],
            $missingInEn,
            'Keys present in ar/catalog.php but missing in en/catalog.php: '.implode(', ', $missingInEn),
        );
    }

    public function test_every_integration_definition_key_resolves_in_both_locales(): void
    {
        $en = require base_path('app/Modules/Integrations/Lang/en/catalog.php');
        $ar = require base_path('app/Modules/Integrations/Lang/ar/catalog.php');

        $referenced = [
            'integrations::catalog.google_gmail.name',
            'integrations::catalog.google_gmail.description',
            'integrations::catalog.google_calendar.name',
            'integrations::catalog.google_sheets.name',
            'integrations::catalog.google_docs.name',
            'integrations::catalog.google_analytics.name',
            'integrations::catalog.google_drive.name',
        ];

        $flatEn = $this->flatten($en);
        $flatAr = $this->flatten($ar);

        foreach ($referenced as $key) {
            $short = preg_replace('/^integrations::catalog\./', '', $key);
            $this->assertContains($short, $flatEn, "Missing in en: {$key}");
            $this->assertContains($short, $flatAr, "Missing in ar: {$key}");
        }
    }

    /**
     * @return array<int, string>
     */
    private function flatten(array $tree, string $prefix = ''): array
    {
        $paths = [];
        foreach ($tree as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($value)) {
                $paths = array_merge($paths, $this->flatten($value, $path));
            } else {
                $paths[] = $path;
            }
        }

        return $paths;
    }
}
