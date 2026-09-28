<?php
require __DIR__.'/../app/bootstrap.php';
try{db()->query('SELECT 1 FROM vehicles LIMIT 1');echo 'OK';}catch(Throwable $e){http_response_code(503);echo 'Unavailable';}
