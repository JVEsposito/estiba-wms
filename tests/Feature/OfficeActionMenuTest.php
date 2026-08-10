<?php

namespace Tests\Feature;

use Tests\TestCase;

class OfficeActionMenuTest extends TestCase
{
    public function test_office_record_actions_are_upgraded_to_select_menus(): void
    {
        $script = file_get_contents(resource_path('js/office-navigation.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString('office-action-select', $script);
        $this->assertStringContainsString('Seleccionar acción', $script);

        foreach ([
            '.admin-season-actions',
            '.material-reception-actions',
            '.validation-row',
            '.annulment-card',
            '.repa-history-card',
            '.tunnel-card__footer',
            '.guide-actions',
            '.lot-actions',
            '.process-delivery-actions',
            '.legacy-card__actions',
            '.result-card',
        ] as $actionHost) {
            $this->assertStringContainsString($actionHost, $script);
        }
    }

    public function test_material_inventory_keeps_its_dedicated_action_menu(): void
    {
        $navigation = file_get_contents(resource_path('js/office-navigation.js'));
        $inventory = file_get_contents(resource_path('js/office-material-inventory-actions.js'));

        $this->assertIsString($navigation);
        $this->assertIsString($inventory);
        $this->assertStringNotContainsString('#materialsInventoryBody td:last-child', $navigation);
        $this->assertStringContainsString('material-inventory-action-toggle', $inventory);

        foreach ([
            'data-direct-dispatch',
            'data-correct-material',
            'data-block-material',
            'data-release-material',
        ] as $action) {
            $this->assertStringContainsString($action, $inventory);
        }
    }

    public function test_action_menu_styles_and_hidden_sources_are_shared_by_all_offices(): void
    {
        $styles = file_get_contents(resource_path('css/office-corporate.css'));

        $this->assertIsString($styles);
        $this->assertStringContainsString('.office-action-select', $styles);
        $this->assertStringContainsString('.office-action-sources[hidden]', $styles);
    }

    public function test_action_selects_isolate_interaction_and_refresh_dynamic_options(): void
    {
        $script = file_get_contents(resource_path('js/office-navigation.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString('event.stopPropagation()', $script);
        $this->assertStringContainsString('syncOfficeActionSelect', $script);
        $this->assertStringContainsString('characterData: true', $script);
        $this->assertStringContainsString('attributes: true', $script);
        $this->assertStringNotContainsString(
            "if (host.dataset.officeActionMenuReady === 'true') return;",
            $script,
        );

        foreach ([
            'pointerdown',
            'mousedown',
            'touchstart',
            'click',
            'input',
            'keydown',
        ] as $eventName) {
            $this->assertStringContainsString("'{$eventName}'", $script);
        }
    }

    public function test_legacy_returns_render_real_actions_inside_the_action_field(): void
    {
        $script = file_get_contents(resource_path('js/office-raw-material-returns.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString('legacy-action-fact', $script);
        $this->assertStringContainsString('data-office-action-menu', $script);
        $this->assertStringContainsString('data-migrate-legacy', $script);
        $this->assertStringContainsString('data-discard-legacy', $script);
        $this->assertStringContainsString("can('puede_entregar_fruta_proceso')", $script);
        $this->assertStringContainsString("can('puede_corregir_entregas_fruta_proceso')", $script);
        $this->assertStringNotContainsString("can('puede_anular_entregas_fruta_proceso')", $script);
        $this->assertStringNotContainsString('<span>ACCIÓN</span><strong>', $script);
    }
}
