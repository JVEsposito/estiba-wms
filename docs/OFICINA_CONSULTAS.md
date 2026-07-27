# Oficina de consultas

La ruta `/oficina/consultas` centraliza búsquedas de folios, lotes de materia
prima, productores CSG y recepciones de romana sin limitar los resultados a la
temporada activa.

## Consulta SAG

La integración se ejecuta desde Laravel contra el buscador público del Sistema
de Registro Agrícola del SAG. El navegador nunca consulta el sitio externo de
forma directa.

- Pueden consultar administradores, supervisores de frío y digitadores de
  materia prima.
- Cada intento queda auditado con usuario, búsqueda, resultado y duración.
- Solo se guardan los datos estructurados del resultado; no se almacena el HTML.
- Un CSG encontrado se crea o actualiza en `productores_csg`.
- Un productor nuevo queda `pendiente_cliente`; la consulta no lo activa en el
  catálogo de una temporada.
- Si ya existe el mismo código en `csg_validacion`, se enlaza al productor sin
  modificar su estado `activo`.
- Solo administradores y supervisores de frío pueden asociar un productor a un
  cliente.
- Si SAG no está disponible o cambia el formato de respuesta, se responde con
  HTTP 503, se audita el error y no se crean ni modifican productores.

La ruta externa y los campos del formulario están encapsulados en
`ServicioConsultaSag` para que un cambio futuro del sitio público se corrija en
un único lugar.
