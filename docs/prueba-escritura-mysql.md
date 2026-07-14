# Prueba de escritura desde Expo Go hacia MySQL

Esta prueba confirma el circuito completo: **Expo Go → API Laravel → MySQL → plano actualizado**. Que la interfaz funcione en modo demo no valida este circuito.

## 1. Preparar Laravel y MySQL

En el `.env` de la raíz, configura una base de datos exclusiva para esta prueba:

```dotenv
APP_ENV=local
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=estiba_wms
DB_USERNAME=root
DB_PASSWORD=
```

Después de modificar el archivo, limpia la configuración almacenada:

```bash
php artisan config:clear
```

Si la base es nueva o contiene solamente datos descartables de prueba:

```bash
php artisan migrate:fresh --seed
```

> `migrate:fresh` elimina todas las tablas de la base configurada. No debe utilizarse sobre información que se quiera conservar.

Inicia Laravel para que sea visible desde la tablet:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

## 2. Configurar el cliente móvil

Copia `mobile/.env.example` como `mobile/.env` y reemplaza la IP por la IPv4 del computador:

```dotenv
EXPO_PUBLIC_DEMO_MODE=false
EXPO_PUBLIC_API_URL=http://192.168.1.100:8000
```

No uses `localhost`: desde Expo Go representaría a la tablet, no al computador.

Reinicia Expo después de cualquier cambio del archivo:

```bash
cd mobile
npm ci
npm run start:clear
```

## 3. Confirmar que no es una demostración

En Expo Go:

1. La pantalla de acceso debe mostrar `API · http://...`.
2. Inicia el turno con `operador@estiba.local`, contraseña `password` y dispositivo `TABLET-01`.
3. El encabezado operacional debe mostrar **API conectada**.

Si aparece **Demo local**, detén Expo, revisa `mobile/.env` y vuelve a ejecutar `npm run start:clear`.

## 4. Ejecutar una escritura

1. Selecciona `CAM-01`.
2. Abre la estiba.
3. Selecciona una posición libre.
4. Ubica un folio único, por ejemplo `MYSQL-PRUEBA-001`.
5. Espera la confirmación **guardado en el servidor**.
6. Comprueba que el folio aparezca en la posición y en movimientos recientes.

Luego muévelo a otra posición libre. La versión del plano y el historial deben actualizarse nuevamente.

## 5. Comprobar directamente en MySQL

Ejecuta esta consulta en HeidiSQL, phpMyAdmin o el cliente MySQL:

```sql
SELECT
    f.numero_folio,
    f.tipo_bulto,
    f.estado_operacional,
    c.codigo AS camara,
    p.etiqueta AS posicion,
    ua.ubicado_at
FROM folios AS f
JOIN ubicaciones_actuales AS ua ON ua.folio_id = f.id
JOIN posiciones AS p ON p.id = ua.posicion_id
JOIN camaras AS c ON c.id = p.camara_id
WHERE f.numero_folio = 'MYSQL-PRUEBA-001';
```

La trazabilidad también debe contener el movimiento:

```sql
SELECT
    f.numero_folio,
    m.tipo_movimiento,
    origen.codigo AS camara_origen,
    destino.codigo AS camara_destino,
    m.recibido_servidor_at
FROM movimientos AS m
JOIN folios AS f ON f.id = m.folio_id
LEFT JOIN camaras AS origen ON origen.id = m.camara_origen_id
LEFT JOIN camaras AS destino ON destino.id = m.camara_destino_id
WHERE f.numero_folio = 'MYSQL-PRUEBA-001'
ORDER BY m.created_at DESC;
```

## Resultado esperado

- El folio existe una sola vez en `folios`.
- Existe una sola ubicación vigente en `ubicaciones_actuales`.
- Cada ubicación o traslado agrega un registro en `movimientos`.
- La cámara queda bloqueada mientras la estiba está abierta y disponible después de cerrarla.
- La aplicación impide finalizar el turno mientras mantenga estibas abiertas.
