<?php

namespace Tests\Feature;

use Tests\TestCase;

class OfficeThemeTest extends TestCase
{
    public function test_office_assets_publish_dark_and_light_themes(): void
    {
        $css = file_get_contents(resource_path('css/office-corporate.css'));
        $javascript = file_get_contents(resource_path('js/office-navigation.js'));

        $this->assertIsString($css);
        $this->assertIsString($javascript);

        $this->assertStringContainsString(
            ':root[data-office-theme="dark-industrial"]',
            $css,
        );
        $this->assertStringContainsString(
            ':root[data-office-theme="light-professional"]',
            $css,
        );
        $this->assertStringContainsString(
            ':root[data-office-theme="light-natural"]',
            $css,
        );
        $this->assertStringContainsString(
            ':root[data-office-theme="light-warm"]',
            $css,
        );
        $this->assertStringContainsString(
            "const defaultTheme = 'dark-industrial';",
            $javascript,
        );
        $this->assertStringContainsString(
            "const themeKey = 'estiba_wms_office_theme';",
            $javascript,
        );
        $this->assertStringContainsString(
            'id="officeThemeSelector"',
            $javascript,
        );
        $this->assertStringContainsString(
            'localStorage.setItem(themeKey, normalized)',
            $javascript,
        );
    }
}
