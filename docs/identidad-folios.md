# Identidad FoliOS

**FoliOS** es el nombre visible de la plataforma operacional. La escritura oficial conserva
`Foli` en peso semibold y destaca `OS` en frost y peso extra, para comunicar a la vez los
folios trazables y el sistema operativo de la cadena de frío.

## Recursos oficiales

Los SVG canónicos están en `public/brand/folios/` y ofrecen símbolo, composición horizontal
y composición apilada, cada una con variante clara y para fondo oscuro.

- La composición horizontal es la opción preferida en cabeceras y accesos.
- La composición apilada se reserva para espacios verticales o piezas institucionales.
- El símbolo se usa en iconos de aplicación y superficies donde el nombre ya está presente.
- Las variantes `on-dark` se usan sobre navy u otros fondos oscuros; no incorporan fondo propio.

## Compatibilidad técnica

El cambio de marca no renombra tablas, contratos API, namespaces, eventos, claves de
almacenamiento, variables `ESTIBA_*`, el slug de Expo ni los identificadores de paquete Android.
Esos nombres son contratos técnicos existentes y se mantienen para no interrumpir sesiones,
instalaciones o integraciones.

El nombre e icono que Android muestra en el lanzador forman parte del binario nativo. La
versión productiva se eleva a `1.3.0` (`versionCode` 5) y la demo a `1.1.0` (`versionCode` 2):
para recibir esa parte de la identidad se debe generar e instalar una APK nueva; una
actualización OTA solo puede cambiar las superficies internas de la aplicación.
