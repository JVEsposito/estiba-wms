import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

import {
    buildOperatorPalletFacts,
    buildOperatorPhysicalContext,
} from '../../mobile/src/domain/operatorPhysicalContext.ts';

const panel = await readFile(
    new URL('../../mobile/src/components/operator/OperatorPhysicalContextPanel.tsx', import.meta.url),
    'utf8',
);
const execution = await readFile(
    new URL('../../mobile/src/components/operator/OperatorTaskExecution.tsx', import.meta.url),
    'utf8',
);
const resource = await readFile(
    new URL('../../app/Http/Resources/TareaMovimientoResource.php', import.meta.url),
    'utf8',
);

function endpoint(position, label = null) {
    return {
        camara: { id: 'cam-7', nombre: 'Cámara de tránsito 07' },
        posicion: {
            id: `pos-${position}`,
            etiqueta: label,
            banda: 8,
            posicion: position,
            nivel: 1,
        },
    };
}

function task() {
    return {
        id: 'task-target',
        estado: 'asumida',
        secuencia_maniobra: 1,
        tipo_movimiento: 'retiro',
        tipo_paso_maniobra: 'extraccion_temporal',
        contexto: {
            cliente: 'Exportadora Norte',
            exportadora: 'Exportadora Norte',
            producto: '',
            profundidad_resultante: 3,
        },
        folio: {
            id: 'folio-target',
            numero_folio: 'PAL-058321',
            tipo_bulto: 'pallet',
            variedad: 'Santina',
            calibre: '2J',
            marca: 'MACE',
            exportadora: 'Exportadora Norte',
            fecha_ingreso: '2026-09-11T09:15:00-03:00',
        },
        origen: endpoint(3, 'B08-P03-N1'),
        destino: null,
        maniobra: {
            id: 'maneuver-7',
            secuencia_actual: 1,
            pasos_totales: 3,
            pasos: [
                {
                    id: 'step-blocker',
                    secuencia: 1,
                    estado: 'asumida',
                    tipo_movimiento: 'retiro',
                    tipo_paso: 'extraccion_temporal',
                    folio: { id: 'folio-blocker', numero_folio: 'PAL-058300' },
                    origen: endpoint(4, 'B08-P04-N1'),
                    destino: null,
                    destino_logico: null,
                    instruccion: 'Extraer pallet bloqueador',
                },
                {
                    id: 'step-target',
                    secuencia: 2,
                    estado: 'bloqueada',
                    tipo_movimiento: 'traslado_entre_camaras',
                    tipo_paso: 'movimiento_permanente',
                    folio: { id: 'folio-target', numero_folio: 'PAL-058321' },
                    origen: endpoint(3, 'B08-P03-N1'),
                    destino: endpoint(7, 'B08-P07-N1'),
                    destino_logico: null,
                    instruccion: 'Mover pallet objetivo',
                },
                {
                    id: 'step-return',
                    secuencia: 3,
                    estado: 'bloqueada',
                    tipo_movimiento: 'ubicacion_inicial',
                    tipo_paso: 'retorno_banda',
                    folio: { id: 'folio-blocker', numero_folio: 'PAL-058300' },
                    origen: null,
                    destino: endpoint(4, 'B08-P04-N1'),
                    destino_logico: null,
                    instruccion: 'Retornar pallet bloqueador',
                },
            ],
            custodias_temporales: [],
        },
    };
}

test('la ficha prioriza atributos reales del folio y no duplica cliente/exportadora', () => {
    const facts = buildOperatorPalletFacts(task());
    const byKey = Object.fromEntries(facts.map((item) => [item.key, item.value]));

    assert.equal(byKey.cliente, 'Exportadora Norte');
    assert.equal(byKey.variedad, 'Santina');
    assert.equal(byKey.calibre, '2J');
    assert.equal(byKey.marca, 'MACE');
    assert.equal(byKey.fecha_ingreso, '11-09-2026');
    assert.equal(byKey.producto, undefined);
    assert.equal(byKey.exportadora, undefined);
});

test('la lectura física deriva bloqueador, objetivo y retorno solo de pasos reales', () => {
    const physical = buildOperatorPhysicalContext(task());

    assert.equal(physical.camera, 'Cámara de tránsito 07');
    assert.equal(physical.band, 8);
    assert.equal(physical.position, 3);
    assert.equal(physical.level, 1);
    assert.equal(physical.resultingDepth, 3);
    assert.deepEqual(physical.blockers.map((item) => item.folio), ['PAL-058300']);
    assert.equal(physical.target.folio, 'PAL-058321');
    assert.deepEqual(physical.returns.map((item) => item.folio), ['PAL-058300']);
    assert.equal(physical.blockers[0].state, 'current');
    assert.equal(physical.target.state, 'pending');
});

test('la pantalla explica ausencias y se integra dentro de Mi maniobra', () => {
    assert.match(panel, /CONTEXTO FÍSICO REAL/);
    assert.match(panel, /BLOQUEADOR/);
    assert.match(panel, /PALLET OBJETIVO DE LA MANIOBRA/);
    assert.match(panel, /ACCESO DIRECTO/);
    assert.match(panel, /Sin atributos complementarios publicados/);
    assert.match(panel, /no inventa producto, variedad, lote ni cliente/i);
    assert.match(execution, /<OperatorPhysicalContextPanel task=\{task\} \/>/);
});

test('el recurso publica los atributos verificables del folio sin migraciones nuevas', () => {
    assert.match(resource, /'variedad' => \$this->folio->variedad/);
    assert.match(resource, /'calibre' => \$this->folio->calibre/);
    assert.match(resource, /'marca' => \$this->folio->marca/);
    assert.match(resource, /'exportadora' => \$this->folio->exportadora/);
    assert.match(resource, /'fecha_ingreso' => \$this->folio->fecha_ingreso/);
});
