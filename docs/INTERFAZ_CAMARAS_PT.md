# Interfaz operacional de Cámaras PT

## Propósito

`/oficina/frigorifico/camaras` concentra el estado vigente de las cámaras de producto terminado en una vista corporativa de consulta. La configuración física continúa separada en `/oficina/administracion/camaras` y conserva sus formularios y permisos anteriores.

## Información mostrada

- ocupación comprometida, capacidad efectiva, reservas y posiciones disponibles;
- plano operacional por bandas y posiciones, sin representar coordenadas métricas;
- estado, modo, usos permitidos y afinidad calculada de cada banda;
- folios ubicados y reservas físicas vigentes;
- último control ambiental horario, cuando el perfil puede consultarlo;
- movimientos recientes relacionados con la cámara seleccionada.

La interfaz no crea temperaturas, ubicaciones, afinidades ni estados. Ante datos ausentes presenta mensajes explícitos como `SIN REGISTRO`, `Sin afinidad activa` o `Sin movimientos recientes`.

## Contratos consumidos

- `GET /api/camaras`
- `GET /api/camaras/{camara}/plano`
- `GET /api/movimientos/recientes?camara_id={camara}&limite=8`
- `GET /api/control-ambiental/estado?camara_id={camara}`

No se agregan endpoints ni acciones de negocio en este cambio.

## Apariencia y contraste

La vista utiliza los tokens corporativos vigentes para los temas Claro profesional, Oscuro industrial y las variantes naturales/cálidas. Las cabeceras navy declaran `data-estiba-contrast="navy"`, un contrato compartido que mantiene texto claro y legible aunque las reglas tipográficas temáticas se carguen después del módulo.
