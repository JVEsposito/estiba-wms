# Existencias en Excel

## Alcance

La oficina `/oficina/existencias` entrega tres inventarios independientes:

1. **Producto terminado:** una fila por folio activo de pallet o saldo.
2. **Materiales:** una fila por folio material, conservando cantidad inicial, actual, reservada, disponible y unidad de medida.
3. **Materia prima:** una fila por lote vigente, con trazabilidad, pesos, hidrocooler y cámara asignada.

El servidor define qué registros constituyen existencia. Excel no reconstruye estados ni suma unidades incompatibles.

## Modalidades

### Corte estático XLSX

Genera un libro con:

- fecha y hora de corte;
- usuario que realizó la descarga;
- temporada consultada;
- encabezados congelados;
- autofiltros;
- valores numéricos almacenados como números.

El archivo no cambia después de descargarlo y sirve como evidencia histórica.

### Consulta conectada para Excel

Genera un archivo `.iqy` que contiene una URL de consulta de solo lectura. Al abrirlo, Excel descarga la existencia vigente desde el WMS.

Flujo recomendado para el usuario:

1. abrir el `.iqy` con Excel;
2. aceptar la conexión externa;
3. guardar el libro como `.xlsx`;
4. usar **Datos → Actualizar todo**;
5. en las propiedades de la conexión, activar **Actualizar al abrir el archivo** cuando corresponda.

No requiere instalar un controlador ODBC ni entregar credenciales de MySQL al usuario final.

## Seguridad

Cada conexión:

- pertenece a un único usuario;
- está limitada a una sola existencia;
- conserva los permisos actuales del usuario;
- vence después de un año;
- puede revocarse desde la oficina de Existencias;
- deja de funcionar inmediatamente si el usuario se desactiva o pierde el permiso correspondiente.

El token se guarda cifrado mediante hash SHA-256; el servidor no conserva el token en texto plano.

## Permisos

- Producto terminado: perfiles autorizados para producto, cargas, Prefrío o consulta gerencial.
- Materiales: perfiles con consulta de inventario y despachos de materiales.
- Materia prima: perfiles con consulta de lotes de materia prima.

La API vuelve a comprobar el permiso en cada actualización del archivo conectado.

## Rutas

- `GET /api/existencias`
- `GET /api/existencias/{tipo}/corte`
- `POST /api/existencias/{tipo}/conexion-excel`
- `GET /api/existencias/{tipo}/consulta?token=...`
- `POST /api/existencias/conexiones/{conexion}/revocar`

Tipos válidos:

- `producto-terminado`
- `materiales`
- `materia-prima`
