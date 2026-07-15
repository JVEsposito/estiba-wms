# Prueba GUI de la oficina del despachador

La oficina de cargas está disponible en `/oficina/cargas` y no requiere código de tablet.

## Acceso local

- Usuario: `despachador@estiba.local`
- Contraseña: `password`

## Recorrido principal

1. Ingresar a `/oficina/cargas`.
2. Crear una orden con número externo, prioridad, cámara objetivo opcional y observación.
3. Confirmar que se genere un código `CAR-000001` y quede en estado `Borrador`.
4. Pegar uno o varios folios ubicados y disponibles en el bloque **Agregar folios**.
   - Repetir un folio dentro del mismo texto y confirmar que la interfaz lo cuente una sola vez.
   - Superar los cupos restantes y confirmar que la interfaz advierta el límite antes de enviar.
5. Confirmar que la distribución muestre la cámara y posición actual de cada folio.
6. Intentar un lote que contenga un folio inválido y comprobar que ninguno sea incorporado.
7. Publicar la orden y confirmar que cambie a estado `Pendiente`.
8. Editar el encabezado o los folios mientras la separación no haya comenzado.
9. Cancelar una orden de prueba y comprobar que quede en modo consulta.

## Conflicto de versiones

1. Abrir la misma orden en dos navegadores o pestañas.
2. Guardar un cambio en la primera.
3. Sin actualizar la segunda, intentar guardar otro cambio.
4. La segunda pantalla debe informar que la orden cambió, recargar la versión vigente y no sobrescribir el primer cambio.

## Permisos

El despachador puede gestionar cargas, pero el enlace a cámaras no se muestra. Supervisores y administradores pueden navegar entre ambos módulos.
