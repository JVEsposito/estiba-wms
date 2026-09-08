<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class OfficeThemeTest extends TestCase
{
    public function test_office_assets_publish_the_single_operational_appearance(): void
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
        $this->assertStringContainsString("const officeTheme = 'light-professional';", $javascript);
        $this->assertStringContainsString('document.documentElement.dataset.officeTheme = officeTheme;', $javascript);
        $this->assertStringNotContainsString('officeThemeSelector', $javascript);
        $this->assertStringNotContainsString('estiba_wms_office_theme', $javascript);
    }

    public function test_every_office_with_shared_navigation_is_inside_the_visual_scope(): void
    {
        foreach (File::allFiles(resource_path('views/office')) as $file) {
            $view = $file->getContents();

            if (! str_contains($view, '<x-office.navigation')) {
                continue;
            }

            $this->assertMatchesRegularExpression(
                '/<main\b[^>]*class="[^"]*\boffice-app\b[^"]*"[^>]*>/',
                $view,
                $file->getRelativePathname(),
            );
        }
    }
}
