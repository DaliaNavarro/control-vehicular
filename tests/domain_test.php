<?php
require __DIR__.'/../app/bootstrap.php';
function check(bool $value,string $label): void {if(!$value)throw new RuntimeException('FALLÓ: '.$label);echo "OK: $label\n";}
function has(array $warnings,string $needle): bool {return (bool)array_filter($warnings,fn($w)=>str_contains($w,$needle));}
$v=['id'=>1,'baseline_date'=>'2026-08-01','baseline_km'=>'100.00'];
$p=['vehicle_id'=>1,'request_date'=>'2026-08-31','departure_date'=>'2026-08-31','departure_time'=>'09:00','arrival_date'=>'2026-08-31','arrival_time'=>'10:00','km_start'=>'100','km_end'=>'150','driver'=>'Ana','purpose'=>'Comisión'];$a=normalizeTrip($p)+['id'=>10];
$p['request_date']=$p['departure_date']=$p['arrival_date']='2026-09-01';$p['km_start']='150';$p['km_end']='180';$b=normalizeTrip($p)+['id'=>11];
check(tripWarnings($b,[$a],$v,[])===[],'Continuidad entre meses y extremo km compartido válidos');
$c=$b;$c['departure_at']='2026-08-31 09:30:00';$c['arrival_at']='2026-08-31 10:30:00';$c['km_start']='120';$c['km_end']='170';$w=tripWarnings($c,[$a],$v,[]);check(has($w,'horario con bitácora #10')&&has($w,'kilometraje')===false&&has($w,'Kilometraje'),'Cruces de horario y km muestran ID');
$c=$b;$c['km_start']='160';check(has(tripWarnings($c,[$a],$v,[]),'No continúa'),'Hueco entre meses');
$c=$b;$c['departure_at']='2026-09-05 09:00:00';$c['arrival_at']='2026-09-05 10:00:00';check(has(tripWarnings($c,[$a],$v,[]),'fin de semana'),'Fin de semana genera advertencia');
$period=['id'=>7,'start_date'=>'2026-09-01','end_date'=>'2026-09-01','reason'=>'En servicio'];check(has(tripWarnings($b,[$a],$v,[$period]),'periodo inactivo #7'),'Periodo con extremos incluidos');
check(periodForDate([$period],'2026-09-02')===null,'Reactivación al día siguiente del fin');
check(missingRanges($v,[$b,$a],'2026-09')===[],'Captura fuera de orden no crea falso faltante');
$c=$b;$c['km_start']='160';$g=missingRanges($v,[$c,$a],'2026-09');check(count($g)===1&&$g[0]['km']===10.0&&$g[0]['uncertain'],'Hueco intermensual sin asignación falsa');
$c=$b;$c['km_start']='140';check(missingRanges($v,[$a,$c],'2026-09')[0]['type']==='Retroceso / superposición','Retroceso no se cuenta como faltante positivo');
$ok=false;try{normalizeTrip(array_merge($p,['km_start'=>'999','km_end'=>'100']));}catch(RuntimeException){$ok=true;}check($ok,'Km finales menores se bloquean');
$ok=false;try{normalizeTrip(array_merge($p,['arrival_time'=>'08:00']));}catch(RuntimeException){$ok=true;}check($ok,'Llegada anterior se bloquea');
check(normalizeFuel(['fuel_date'=>'2026-09-01','liters'=>'12.345','amount'=>'321.50','card_last4'=>'0007'])['card_last4']==='0007','Tarjeta conserva ceros');
check(has(fuelWarnings(['fuel_date'=>'2026-10-01'],$b),'mes de la fecha de carga'),'Carga posterior se contabiliza por su propia fecha');
$_SESSION=['csrf'=>'test-secret'];$token=warningToken($b,['aviso']);$_POST=['accept_warnings'=>1,'warning_token'=>$token];check(warningsConfirmed($b,['aviso']),'Confirmación de advertencias válida');$b['km_end']='181';check(!warningsConfirmed($b,['aviso']),'Cambiar formulario invalida confirmación anterior');
echo "Todas las pruebas de reglas pasaron.\n";
