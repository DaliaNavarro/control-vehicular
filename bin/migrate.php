<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../app/bootstrap.php';
try {
    $sql = file_get_contents(__DIR__.'/../database/migrations/001_programacion.sql');
    if ($sql === false) throw new RuntimeException('No se encontró la migración de programación.');
    db()->exec($sql);
    echo "Actualización 1.1 aplicada: tabla de programación disponible. Las bitácoras existentes se conservaron.\n";
} catch (Throwable $error) {
    fwrite(STDERR, "No se pudo aplicar la actualización: ".$error->getMessage()."\n");
    exit(1);
}
