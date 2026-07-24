# Proveedores y categorías de Materiales

## Regla operacional

La habilitación de abastecimiento se define por la combinación:

```text
proveedor + cliente + categoría de catálogo
```

El proveedor no se relaciona manualmente con cada ítem. Al habilitar una categoría para un cliente, quedan seleccionables todos los ítems activos de esa categoría dentro de la temporada operacional vigente.

Ejemplo:

```text
Corupac + AG-001 La Aguada + ABSORPAD
```

permite recibir todos los ítems activos de `ABSORPAD` pertenecientes a La Aguada, pero no habilita automáticamente esa categoría para otro cliente asociado al mismo proveedor.

## Configuración en oficina

En **Oficina de Materiales → Proveedores**:

1. Se registra o edita el proveedor.
2. Se seleccionan los clientes asociados.
3. Para cada cliente se selecciona al menos una categoría disponible en el catálogo de la temporada elegida.
4. La interfaz informa cuántos ítems activos habilita cada categoría.

Un cliente no puede mantenerse asociado sin categorías habilitadas.

## Recepción en tablet

El flujo de selección es:

```text
cliente → proveedor autorizado → ítems de categorías habilitadas
```

Cambiar el cliente o proveedor limpia los ítems previamente seleccionados para evitar combinaciones obsoletas.

## Validación de seguridad

La interfaz filtra las opciones, pero Laravel valida nuevamente la relación al crear el borrador y al confirmar la recepción. Una solicitud que intente usar un ítem cuya categoría no está habilitada para ese proveedor y cliente es rechazada.

## Compatibilidad

La migración inicial asigna a cada vínculo activo existente todas las categorías activas que actualmente posee su cliente. Esto evita bloquear recepciones ya configuradas y permite que la restricción se refine posteriormente desde Oficina de Materiales.

`categoria` es la clasificación comercial del catálogo, como `ABSORPAD`, `CAJAS` o `ETIQUETAS`. No reemplaza a `categoria_operacional`, que continúa distinguiendo `insumo`, `material_mp` y `material_pt`.

## Cobertura automatizada

Las pruebas verifican la creación de proveedores con categorías por cliente, la publicación del catálogo para tablet, el rechazo de ítems no autorizados, la compatibilidad con Transformación de Materiales y la presencia de los controles correspondientes en oficina y móvil.
