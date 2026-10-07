# Control vehicular V2

Segunda versión construida sobre la arquitectura PHP/HTML/CSS + MariaDB + Docker de la V1.

## Actualización desde V1

1. Conserva tu volumen `vehicular_data`.
2. Reemplaza el código por esta versión.
3. Ejecuta `docker compose up -d --build`.
4. El contenedor ejecuta `bin/migrate.php` al iniciar y aplica `003_v2.sql` sin borrar las bitácoras existentes.

## Accesos

- Administrador: conserva `APP_USER` / `APP_PASSWORD` del `.env`.
- Usuarios externos: se registran desde el mismo formulario de acceso con nombre de conductor, número de licencia y vencimiento.
- La vista pública anterior queda deshabilitada.

## Plantillas

Las plantillas originales están en `app/templates/` y se rellenan sobre una copia para conservar formato, imágenes, combinaciones y distribución.

## Cambios V2 principales

Usuarios externos con perfil de conductor y bloqueo por licencia vencida; bitácoras con solicitante y observaciones; combustible independiente de bitácoras; departamento/año en vehículos; programaciones por usuario; consulta de registros con permisos; XLSX individual de bitácora; concentrado mensual de combustible; concentrado anual de importes y litros; importación histórica de combustible; panel de usuarios externos.

## V2.4

Cambios de esta revisión:
- Corrección de persistencia de casillas de verificación vehicular: cada periodo se guarda de manera independiente y permite marcar/desmarcar sin congelar las demás casillas.
- Los errores AJAX de verificación regresan JSON para que el estado visual no quede desincronizado.
- El concentrado mensual de gasolina usa `plantilla_concentradogas.xlsx`, con cuatro bloques semanales (1–7, 8–14, 15–21 y 22–fin), km inicial/final y km recorrido por semana.
- Se incluyen todas las cargas del mes; cuando exceden los renglones disponibles, las restantes se conservan como detalle multilinea en el último renglón sin afectar los totales.
- El concentrado mensual muestra los totales de km, litros y dinero.
- El concentrado anual agrega una fila `TOTAL MES` con la suma de todos los vehículos para cada mes, además del total anual por vehículo.


## V2.9
- Fechas visibles: DD/MM/AAAA y filtros mensuales MM/AAAA.
- Kilometraje entero, sin decimales.
- Usuarios externos bloqueados durante periodos de inactividad.
- Razones de inactividad normalizadas.
- Concentrado mensual: mes en formato Mes-AAAA y destino solo cuando la inactividad cubre todo el mes.
