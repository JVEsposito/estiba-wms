<?php

namespace Tests\Feature;

use Tests\TestCase;

class InterfazNotificacionesOperacionalesTest extends TestCase
{
    public function test_tablet_sondea_el_resumen_y_carga_el_feed_solo_al_abrirlo(): void
    {
        $componente = file_get_contents(
            base_path('mobile/src/components/NotificationCenter.tsx'),
        );
        $api = file_get_contents(base_path('mobile/src/services/estibaApi.ts'));

        $this->assertStringContainsString(
            'api.getOperationalNotificationSummary(auth.token)',
            $componente,
        );
        $this->assertStringContainsString(
            '() => visibleRef.current ? refreshFeed(true) : refreshSummary()',
            $componente,
        );
        $this->assertStringContainsString(
            'void refreshFeed(false)',
            $componente,
        );
        $this->assertStringContainsString(
            "'/api/notificaciones-operacionales/resumen'",
            $api,
        );
    }
}
