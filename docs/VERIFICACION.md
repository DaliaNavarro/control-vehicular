# Verificación de la entrega

## Actualización 1.1 — 28 de septiembre de 2026

Se comprobaron la plantilla de una sola fecha, la compatibilidad con archivos anteriores, la exportación/reimportación sin pérdida de fechas antiguas y la migración repetible. Las pruebas de programación incluyen alta, cambio de fecha/hora, cancelación, duplicados exactos, versiones obsoletas y CSRF.

Se compararon las cinco tablas operativas y los totales antes y después de crear, mover y cancelar apartados: permanecieron idénticos. También se comprobó por HTTP que el panel y el concentrado no cambian por una programación. La descarga de la plantilla contiene exactamente nueve columnas, una sola fecha y las dos horas.

Se revisaron la tabla y el formulario de programación en navegador de escritorio y a 390 px de ancho, incluyendo crear, editar y cancelar. Las tablas utilizan desplazamiento horizontal interno en celular para conservar legibilidad. No hubo errores JavaScript ni advertencias PHP en los flujos comprobados.

Pruebas nuevas: `tests/update_1_1_test.php`, solo en una base de pruebas separada con `CV_TEST_DATABASE=yes`. También pasaron las pruebas anteriores de reglas e integración.

Se conservan las limitaciones sobre Docker, Microsoft Excel de escritorio y teléfono físico indicadas más abajo. Las capturas de programación contienen datos ficticios.

## Verificación base — 24 de septiembre de 2026

## Ejecutado

- Revisión de sintaxis de todos los archivos PHP con PHP 8.3.6 y del JavaScript de interfaz.
- 15 comprobaciones de reglas: continuidad entre meses, extremos compartidos válidos, horarios superpuestos, ID de conflictos, huecos, fines de semana, periodos inclusivos, captura fuera de orden, retrocesos, formatos inválidos, conservación de ceros en tarjeta, fechas de gasolina y confirmaciones de advertencias ligadas al formulario revisado.
- Pruebas de integración con MariaDB 10.11.14 en una base temporal: esquema con llaves foráneas y restricciones, varias cargas por bitácora, totales por mes, fechas de carga independientes, exportación/reimportación XLSX, duplicados, relaciones, borrado de cargas en cascada, sugerencias y rechazo de fórmulas de importación.
- Pruebas HTTP: inicio/cierre de sesión; alta, edición y eliminación de vehículos/bitácoras/cargas/periodos; bloqueo de edición obsoleta; reasignación de vehículo conservando ID y cargas; búsquedas por ID, fechas, km, conductor y vehículo; autollenado; advertencias con confirmación; generación de los cuatro tipos de Excel; colores de fin de semana y totales numéricos; importación repetida sin duplicados; protección CSRF y protección de exportaciones sin sesión.
- Caso de captura desordenada: se registró un recorrido que dejaba 20 km pendientes, se comprobó su aparición en la tabla y luego se capturó la bitácora intermedia; el hueco desapareció.
- Navegador Chromium: panel, tarjetas, formulario, cálculo de distancia, carga inicial opcional y sugerencias. Revisión a 1440 px y 390 px; panel, formulario, vehículos, concentrados, pendientes e importación sin desbordamiento horizontal de la página. Las tablas y la navegación móvil permiten desplazamiento interno.
- Revisión visual de capturas de escritorio y celular; sin errores de JavaScript ni advertencias PHP en los flujos comprobados.

## Límites de la verificación

No había un motor Docker disponible en el entorno de entrega. Por ello **no se ejecutó `docker compose up --build` ni la imagen objetivo MariaDB 11.4**. La aplicación sí se ejecutó con PHP y MariaDB reales mediante procesos locales de prueba. El arranque de los contenedores debe comprobarse en el equipo destino.

Los archivos XLSX fueron revisados como paquetes OOXML, incluyendo XML, estilos de fines de semana, fórmulas con resultados almacenados y reimportación a la aplicación. No se abrieron en Microsoft Excel de escritorio.

No se hicieron pruebas de carga masiva, publicación en Internet ni acceso desde un teléfono físico. Las pruebas móviles utilizaron un navegador con tamaño de pantalla de celular. No hay funcionamiento sin conexión.

## Pruebas incluidas

`tests/domain_test.php` no requiere base de datos ni modifica registros:

```powershell
docker compose exec app php tests/domain_test.php
```

`tests/integration_test.php` requiere una **base de pruebas separada con el esquema cargado** y `CV_TEST_DATABASE=yes`. Aunque revierte sus inserciones, puede avanzar contadores de ID; no la ejecutes contra la base operativa. Configura `DB_HOST`, `DB_NAME`, `DB_USER` y `DB_PASSWORD` para esa base antes de ejecutarla.

Las capturas incluidas muestran datos ficticios usados en las pruebas. Esos vehículos y registros no se insertan en la instalación entregada.
