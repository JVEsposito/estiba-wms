-- Banco de volumen: SOLO en una base de pruebas (nunca en producción).
-- Crea 150.000 folios PT en la temporada activa y 150.000 en otra temporada, su trazabilidad
-- y 500.000 operaciones sincronizadas. Uso: docs/operacion-rendimiento.md.
-- Protección: el cliente mysql se detiene en el primer error. Solo continúa si el nombre de la
-- base contiene bench, prueba o test.
CREATE TEMPORARY TABLE guardia_banco (permitido TINYINT NOT NULL);
INSERT INTO guardia_banco VALUES (IF(DATABASE() REGEXP 'bench|prueba|test', 1, NULL));

SET @activa = (SELECT id FROM temporadas WHERE activa = 1 LIMIT 1);
SET @anterior = (SELECT id FROM temporadas WHERE activa = 0 LIMIT 1);
SET @origen = (SELECT id FROM origenes_validacion LIMIT 1);
SET @usuario = (SELECT id FROM users LIMIT 1);
SET @dispositivo = (SELECT id FROM dispositivos LIMIT 1);
DROP TABLE IF EXISTS bench_n;
CREATE TABLE bench_n (n INT PRIMARY KEY);
INSERT INTO bench_n (n)
SELECT a.d + b.d*10 + c.d*100 + d.d*1000 + e.d*10000 + f.d*100000
FROM (SELECT 0 d UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a,
     (SELECT 0 d UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b,
     (SELECT 0 d UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) c,
     (SELECT 0 d UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) d,
     (SELECT 0 d UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) e,
     (SELECT 0 d UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) f
WHERE a.d + b.d*10 + c.d*100 + d.d*1000 + e.d*10000 + f.d*100000 < 500000;

-- 150.000 folios por temporada (activa y anterior) con composición realista en datos_externos.
INSERT INTO folios (id, temporada_id, numero_folio, tipo_bulto, estado_operacional, activo, fecha_ingreso, variedad, calibre, marca, exportadora, datos_externos, created_at, updated_at)
SELECT UUID(), IF(n < 150000, @activa, @anterior), CONCAT(IF(n < 150000, 'PT-2026-', 'PT-2025-'), LPAD(n % 150000, 7, '0')), 'pallet', 'disponible', n < 150000,
       NOW() - INTERVAL (n % 150000) MINUTE, ELT(1 + n % 4, 'SANTINA', 'LAPINS', 'REGINA', 'KORDIA'), ELT(1 + n % 3, '2J', '3J', 'XL'), 'ATLAS', ELT(1 + n % 5, 'Exportadora Norte', 'Exportadora Sur', 'Frutas Centro', 'Agro Andes', 'Valle Export'),
       JSON_OBJECT('csg', '105410', 'predio', 'Fundo El Olmo', 'fecha_embalaje', '2026-01-15', 'especie', 'Cereza', 'composicion', JSON_ARRAY(
           JSON_OBJECT('origen_validacion_id', @origen, 'csg', '105410', 'predio', 'Fundo El Olmo', 'cantidad_cajas', 70, 'lote_materia_prima', CONCAT('L-', n % 4000), 'proceso_packing', CONCAT('P-', n % 900)),
           JSON_OBJECT('origen_validacion_id', @origen, 'csg', '105411', 'predio', 'Fundo La Palma', 'cantidad_cajas', 50, 'lote_materia_prima', CONCAT('L-', (n + 7) % 4000), 'proceso_packing', CONCAT('P-', n % 900)))),
       NOW(), NOW()
FROM bench_n WHERE n < 300000;

-- Proyección de trazabilidad equivalente (dos líneas por folio).
INSERT INTO trazabilidad_folio_origenes (id, folio_id, temporada_id, csg, numero_lote_materia_prima, numero_proceso_packing, cantidad_cajas, created_at, updated_at)
SELECT UUID(), f.id, f.temporada_id, '105410', JSON_UNQUOTE(JSON_EXTRACT(f.datos_externos, '$.composicion[0].lote_materia_prima')), JSON_UNQUOTE(JSON_EXTRACT(f.datos_externos, '$.composicion[0].proceso_packing')), 70, NOW(), NOW()
FROM folios f WHERE f.numero_folio LIKE 'PT-202%';
INSERT INTO trazabilidad_folio_origenes (id, folio_id, temporada_id, csg, numero_lote_materia_prima, numero_proceso_packing, cantidad_cajas, created_at, updated_at)
SELECT UUID(), f.id, f.temporada_id, '105411', JSON_UNQUOTE(JSON_EXTRACT(f.datos_externos, '$.composicion[1].lote_materia_prima')), JSON_UNQUOTE(JSON_EXTRACT(f.datos_externos, '$.composicion[1].proceso_packing')), 50, NOW(), NOW()
FROM folios f WHERE f.numero_folio LIKE 'PT-202%';

-- 500.000 operaciones sincronizadas (varias por folio a lo largo de la temporada).
INSERT INTO operaciones_sincronizacion (id, user_id, dispositivo_id, tipo, estado, payload_hash, payload, resultado, generada_dispositivo_at, recibida_servidor_at, procesada_at, created_at, updated_at)
SELECT UUID(), @usuario, @dispositivo, ELT(1 + n % 3, 'ubicar_folio', 'mover_folio', 'validacion_pallet'), 'aceptada', SHA2(n, 256),
       JSON_OBJECT('folio', CONCAT('PT-2026-', LPAD(n % 150000, 7, '0')), 'posicion', 'CAM-07-B12-P05', 'observacion', REPEAT('x', 300)),
       JSON_OBJECT('ok', true), NOW() - INTERVAL (n DIV 3) MINUTE, NOW() - INTERVAL (n DIV 3) MINUTE, NOW() - INTERVAL (n DIV 3) MINUTE, NOW(), NOW()
FROM bench_n;
ANALYZE TABLE folios, trazabilidad_folio_origenes, operaciones_sincronizacion;
SELECT (SELECT COUNT(*) FROM folios) folios, (SELECT COUNT(*) FROM trazabilidad_folio_origenes) trazabilidad, (SELECT COUNT(*) FROM operaciones_sincronizacion) operaciones;
DROP TABLE bench_n;
