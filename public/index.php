<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off']);session_start();
header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header('Referrer-Policy: same-origin');header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");header('Cache-Control: no-store');
$_SESSION['csrf']??=keygen();$page=(string)($_GET['page']??'dashboard');$error='';$warnings=[];$warningPayload=[];$importPreview=null;
if(empty($_SESSION['authenticated'])){
 $page='login';if($_SERVER['REQUEST_METHOD']==='POST')try{checkCsrf();$pass=getenv('APP_PASSWORD')?:'';if($pass==='')throw new RuntimeException('Configura APP_PASSWORD en el archivo .env y vuelve a crear el contenedor app.');if(($_SESSION['login_after']??0)>time())throw new RuntimeException('Espera unos segundos antes de volver a intentar.');
 if(!hash_equals(getenv('APP_USER')?:'admin',(string)($_POST['username']??''))||!hash_equals($pass,(string)($_POST['password']??''))){$_SESSION['login_after']=time()+3;throw new RuntimeException('Usuario o contraseña incorrectos.');}session_regenerate_id(true);$_SESSION['authenticated']=true;$_SESSION['csrf']=keygen();redirect('dashboard');}catch(Throwable $e){$error=$e->getMessage();}
}else{
 require __DIR__.'/../app/excel.php';require __DIR__.'/../app/actions.php';
 try{
  if($_SERVER['REQUEST_METHOD']==='POST')handleAction();
  if($page==='export')exportExcel((string)($_GET['kind']??'general'),(string)($_GET['month']??date('Y-m')),(int)($_GET['vehicle_id']??0));
  if($page==='suggest'){$kind=$_GET['kind']??'';if(!in_array($kind,['driver','purpose'],true))throw new RuntimeException('Tipo inválido.');$q=trim((string)($_GET['q']??''));header('Content-Type: application/json; charset=utf-8');echo json_encode(array_column(rows('SELECT value FROM suggestions WHERE kind=? AND value LIKE ? ORDER BY value LIMIT 15',[$kind,'%'.substr($q,0,100).'%']),'value'),JSON_UNESCAPED_UNICODE);exit;}
 }catch(Throwable $e){try{if(db()->inTransaction())db()->rollBack();}catch(Throwable $ignored){}if($e instanceof PDOException){error_log((string)$e);$error=$e->getCode()==='23000'?'Ya existe esa placa, número económico o clave; también puede haber registros relacionados que impiden eliminar.':'No fue posible guardar o consultar los datos. Revisa la conexión a la base de datos.';}else $error=$e->getMessage();}
}
require __DIR__.'/../app/views.php';
renderHeader($page);
if($error)echo '<div class="notice error" role="alert">'.e($error).'</div>';
if(isset($_SESSION['flash'])){echo '<div class="notice success" role="status">'.e($_SESSION['flash']).'</div>';unset($_SESSION['flash']);}
try{switch($page){case 'login':loginView();break;case 'dashboard':dashboardView();break;case 'vehicles':vehiclesView();break;case 'vehicle':vehicleView();break;case 'trip_form':tripFormView();break;case 'trip':tripView();break;case 'fuel':fuelView();break;case 'period':periodView();break;case 'reservations':reservationsView();break;case 'reservation_form':reservationFormView();break;case 'reports':reportsView();break;case 'gaps':gapsView();break;case 'import':importView();break;default:http_response_code(404);echo '<section class="panel"><h1>Página no encontrada</h1><a href="'.e(url('dashboard')).'">Volver al inicio</a></section>';}}catch(Throwable $e){if($e instanceof PDOException){error_log((string)$e);echo '<div class="notice error">No se pudo conectar con la base de datos. Ejecuta <code>docker compose ps</code> y revisa que db esté saludable.</div>';}else echo '<div class="notice error">'.e($e->getMessage()).'</div>';}
renderFooter();
