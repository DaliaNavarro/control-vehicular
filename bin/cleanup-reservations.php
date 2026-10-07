<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../app/bootstrap.php';
try { $n=pruneReservations();if($n)echo 'Apartados vencidos eliminados: '.$n."\n"; }
catch(Throwable $error){fwrite(STDERR,'No se pudo limpiar la programación: '.$error->getMessage()."\n");exit(1);}
