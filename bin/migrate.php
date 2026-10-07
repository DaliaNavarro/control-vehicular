<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../app/bootstrap.php';
try {
    foreach (['001_programacion.sql','002_verificaciones.sql','003_v2.sql','004_v2_changes.sql','005_formatos_v27.sql'] as $file) {
        $sql = file_get_contents(__DIR__.'/../database/migrations/'.$file);
        if ($sql === false) throw new RuntimeException('No se encontró la migración '.$file);
        db()->exec($sql);
    }
    echo "Base de datos lista para la versión 2: programación y verificaciones disponibles. Los registros existentes se conservaron.\n";
} catch (Throwable $error) {
    fwrite(STDERR, "No se pudo aplicar la actualización: ".$error->getMessage()."\n");
    exit(1);
}
