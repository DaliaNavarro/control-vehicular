<?php
declare(strict_types=1);
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'America/Mexico_City');
function db(): PDO {
    static $pdo;
    if (!$pdo) $pdo = new PDO('mysql:host='.(getenv('DB_HOST') ?: 'db').';dbname='.(getenv('DB_NAME') ?: 'vehicular').';charset=utf8mb4',getenv('DB_USER') ?: 'vehicular',getenv('DB_PASSWORD') ?: '',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    return $pdo;
}
function rows(string $sql,array $params=[]): array {$q=db()->prepare($sql);$q->execute($params);return $q->fetchAll();}
function row(string $sql,array $params=[]): ?array {return rows($sql,$params)[0]??null;}
function execute(string $sql,array $params=[]): int {$q=db()->prepare($sql);$q->execute($params);return $q->rowCount();}
function e(mixed $v): string {return htmlspecialchars((string)($v??''),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function url(string $page,array $params=[]): string {return 'index.php?'.http_build_query(['page'=>$page]+$params);}
function redirect(string $page,array $params=[]): never {header('Location: '.url($page,$params));exit;}
function flash(string $s): void {$_SESSION['flash']=$s;}
function keygen(): string {return bin2hex(random_bytes(16));}
function csrf(): string {$_SESSION['csrf']??=keygen();return '<input type="hidden" name="csrf" value="'.e($_SESSION['csrf']).'">';}
function checkCsrf(): void {if (!hash_equals($_SESSION['csrf']??'!',(string)($_POST['csrf']??''))) throw new RuntimeException('La sesión del formulario venció. Recarga la página.');}
function need(string $table,int $id): array {if(!in_array($table,['vehicles','trips','fuel_loads','inactive_periods','reservations','users'],true))throw new LogicException('Tabla inválida');return row("SELECT * FROM $table WHERE id=?",[$id])??throw new RuntimeException('El registro ya no existe.');}
function dateValue(mixed $v,string $label='Fecha'): string {
 $s=trim((string)$v);
 foreach(['Y-m-d','d/m/Y','d-m-Y'] as $fmt){$d=DateTimeImmutable::createFromFormat('!'.$fmt,$s);if($d&&$d->format($fmt)===$s){$out=$d->format('Y-m-d');if($out>='1900-01-01'&&$out<='2100-12-31')return $out;}}
 throw new RuntimeException("$label: usa el formato DD/MM/AAAA.");
}
function monthValue(mixed $v,string $label='Mes'): string {
 $s=trim((string)$v);foreach(['Y-m','m/Y'] as $fmt){$d=DateTimeImmutable::createFromFormat('!'.$fmt,$s);if($d&&$d->format($fmt)===$s){$out=$d->format('Y-m');if($out>='1900-01'&&$out<='2100-12')return $out;}}
 throw new RuntimeException("$label: usa el formato MM/AAAA.");
}
function displayDate(mixed $v): string {if(!$v)return '';try{return (new DateTimeImmutable((string)$v))->format('d/m/Y');}catch(Throwable $e){return (string)$v;}}
function displayMonth(mixed $v): string {if(!$v)return '';try{return (new DateTimeImmutable((string)$v.'-01'))->format('m/Y');}catch(Throwable $e){return (string)$v;}}

function textValue(mixed $v,string $label,int $max): string {$s=trim((string)$v);if($s===''||preg_match_all('/./us',$s)>$max)throw new RuntimeException("$label es obligatorio; máximo $max caracteres.");return mb_strtoupper($s,'UTF-8');}
function integerValue(mixed $v,string $label,bool $positive=false): string {
 $s=trim((string)$v);if(!preg_match('/^\d{1,9}$/D',$s)||($positive&&(int)$s<=0))throw new RuntimeException("$label: ingresa un número entero ".($positive?'mayor que cero':'no negativo').'.');return (string)(int)$s;
}
function decimalValue(mixed $v,string $label,int $places=2,bool $positive=false): string {
 $s=trim((string)$v);if(!preg_match('/^\d{1,9}(?:\.\d{1,'.$places.'})?$/D',$s)||($positive&&(float)$s<=0))throw new RuntimeException("$label: ingresa un número ".($positive?'mayor que cero':'no negativo')." con hasta $places decimales (punto decimal, sin separadores).");return number_format((float)$s,$places,'.','');
}
function monthBounds(string $month): array {if(!preg_match('/^(19|20|21)\d{2}-(0[1-9]|1[0-2])$/D',$month))throw new RuntimeException('Mes inválido.');$start=$month.'-01';return [$start,date('Y-m-d',strtotime($start.' +1 month'))];}
function num(mixed $n,int $dec=2): string {return $n===null?'Sin dato':number_format((float)$n,$dec,'.',',');}
require_once __DIR__.'/domain.php';
require_once __DIR__.'/auth.php';
require_once __DIR__.'/reservations.php';

require_once __DIR__.'/verifications.php';
