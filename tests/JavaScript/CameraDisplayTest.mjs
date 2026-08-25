import assert from 'node:assert/strict';
import test from 'node:test';

import { cameraDisplayName } from '../../resources/js/shared/camera-display.js';

test('muestra el nombre operacional de la cámara sin exponer su código', () => {
    assert.equal(
        cameraDisplayName({ codigo: 'CAM-04', nombre: 'Bodega Principal' }),
        'Bodega Principal',
    );
});

test('no reutiliza el código cuando la cámara no tiene nombre', () => {
    assert.equal(
        cameraDisplayName({ codigo: 'CAM-04', nombre: '   ' }),
        'Cámara sin nombre',
    );
});
