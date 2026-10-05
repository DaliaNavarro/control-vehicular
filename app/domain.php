<?php
declare(strict_types=1);
// The web form has a single date; legacy imports retain their explicit dates.
function normalizeTripForm(array $p): array {
 $date=dateValue($p['request_date']??'','Fecha de solicitud');
 $p['departure_date']=$p['arrival_date']=$date;
 return normalizeTrip($p);
}
function normalizeTrip(array $p): array {
 $request=dateValue($p['request_date']??'','Fecha de solicitud');
 $start=dateValue($p['departure_date']??'','Fecha de salida');$end=dateValue($p['arrival_date']??'','Fecha de llegada');
 foreach(['departure_time','arrival_time'] as $f)if(!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',(string)($p[$f]??'')))throw new RuntimeException('Las horas deben usar el formato HH:MM de 24 horas.');
 $s=$start.' '.$p['departure_time'].':00';$t=$end.' '.$p['arrival_time'].':00';
 if($t<=$s)throw new RuntimeException($start===$end?'La hora de llegada debe ser posterior a la hora de salida dentro del mismo día.':'La llegada debe ser posterior a la salida.');
 $a=decimalValue($p['km_start']??'','Km iniciales');$b=decimalValue($p['km_end']??'','Km finales');
 if((float)$b<(float)$a)throw new RuntimeException('Los km finales no pueden ser menores que los iniciales.');
 $vid=filter_var($p['vehicle_id']??0,FILTER_VALIDATE_INT);if(!$vid||$vid<1)throw new RuntimeException('Selecciona un vehículo válido.');
 return ['vehicle_id'=>$vid,'request_date'=>$request,'departure_at'=>$s,'arrival_at'=>$t,'km_start'=>$a,'km_end'=>$b,'driver'=>textValue($p['driver']??'','Conductor',150),'purpose'=>textValue($p['purpose']??'','Comisión',500)];
}
function tripWarnings(array $t,array $others,array $vehicle,array $periods): array {
 $w=[];$label=fn($r)=>'#'.$r['id'];$start=substr($t['departure_at'],0,10);$end=substr($t['arrival_at'],0,10);
 $weekend=false;for($d=new DateTimeImmutable($start);$d<=new DateTimeImmutable($end);$d=$d->modify('+1 day')){if((int)$d->format('N')>=6){$weekend=true;break;}}
 if($weekend||(int)date('N',strtotime($t['request_date']))>=6)$w[]='La solicitud o el recorrido incluye un fin de semana.';
 if($t['request_date']>$start)$w[]='La fecha de solicitud es posterior a la salida.';
 if((float)$t['km_start']==(float)$t['km_end'])$w[]='El recorrido tiene 0 kilómetros.';
 if($start<$vehicle['baseline_date'])$w[]='El recorrido es anterior a la lectura de referencia del vehículo. Revisa esa lectura para completar el historial.';
 foreach($periods as $p)if($p['start_date']<=$end&&($p['end_date']===null||$p['end_date']>=$start))$w[]='Coincide con el periodo inactivo #'.$p['id'].' ('.$p['reason'].', '.$p['start_date'].' a '.($p['end_date']??'sin fecha de fin').').';
 usort($others,fn($a,$b)=>[$a['departure_at'],(string)$a['id']]<=>[$b['departure_at'],(string)$b['id']]);
 $prev=null;$next=null;
 foreach($others as $r){
  if($t['departure_at']<$r['arrival_at']&&$t['arrival_at']>$r['departure_at'])$w[]='Cruce de horario con bitácora '.$label($r).' ('.$r['departure_at'].' a '.$r['arrival_at'].').';
  $overlap=max((float)$t['km_start'],(float)$r['km_start'])<min((float)$t['km_end'],(float)$r['km_end']);
  $duplicate=(float)$t['km_start']==(float)$r['km_start']&&(float)$t['km_end']==(float)$r['km_end'];
  if($overlap||$duplicate)$w[]='Kilometraje superpuesto o repetido con bitácora '.$label($r).' ('.$r['km_start'].'–'.$r['km_end'].' km).';
  if($r['departure_at']<$t['departure_at'])$prev=$r;
  elseif($next===null)$next=$r;
 }
 if($prev&&abs((float)$prev['km_end']-(float)$t['km_start'])>0.005)$w[]='No continúa la bitácora '.$label($prev).': termina en '.$prev['km_end'].' km y esta inicia en '.$t['km_start'].' km (incluye continuidad entre meses).';
 if(!$prev&&$start>=$vehicle['baseline_date']&&abs((float)$vehicle['baseline_km']-(float)$t['km_start'])>0.005)$w[]='El primer registro no continúa la lectura de referencia: '.$vehicle['baseline_km'].' km al '.$vehicle['baseline_date'].'.';
 if($next&&abs((float)$t['km_end']-(float)$next['km_start'])>0.005)$w[]='No enlaza con bitácora '.$label($next).': esta termina en '.$t['km_end'].' km y la siguiente inicia en '.$next['km_start'].' km.';
 return array_values(array_unique($w));
}
function normalizeFuel(array $p): array {
 $last=trim((string)($p['card_last4']??''));if(!preg_match('/^\d{4}$/D',$last))throw new RuntimeException('Escribe exactamente los últimos 4 dígitos de la tarjeta.');
 return ['fuel_date'=>dateValue($p['fuel_date']??'','Fecha de carga'),'liters'=>decimalValue($p['liters']??'','Litros',3,true),'amount'=>decimalValue($p['amount']??'','Importe pagado'),'card_last4'=>$last];
}
function fuelWarnings(array $f,array $t): array {return $f['fuel_date']<substr($t['departure_at'],0,10)||$f['fuel_date']>substr($t['arrival_at'],0,10)?['La fecha de carga está fuera de las fechas del recorrido #'.($t['id']??'nuevo').'. Se contabilizará en el mes de la fecha de carga.']:[];}
function remember(array $t): void {foreach(['driver','purpose'] as $f)execute('INSERT IGNORE INTO suggestions(kind,value) VALUES (?,?)',[$f,$t[$f]]);}
function createTrip(array $t,string $key): int {
 execute('INSERT INTO trips(reference_key,vehicle_id,request_date,departure_at,arrival_at,km_start,km_end,driver,purpose) VALUES (?,?,?,?,?,?,?,?,?)',[$key,...array_values($t)]);
 $id=(int)db()->lastInsertId();remember($t);return $id;
}
function createFuel(int $trip,array $f,string $key): int {execute('INSERT INTO fuel_loads(reference_key,trip_id,fuel_date,liters,amount,card_last4) VALUES (?,?,?,?,?,?)',[$key,$trip,...array_values($f)]);return (int)db()->lastInsertId();}
function periodForDate(array $periods,string $date): ?array {foreach($periods as $p)if($p['start_date']<=$date&&($p['end_date']===null||$p['end_date']>=$date))return $p;return null;}
function monthSummary(array $v,string $month,?array $all=null): array {
 [$start,$end]=monthBounds($month);$all??=rows('SELECT * FROM trips WHERE vehicle_id=? ORDER BY departure_at,id',[$v['id']]);
 $in=[];$prev=null;foreach($all as $t){if($t['departure_at']<$start)$prev=$t;elseif($t['departure_at']<$end)$in[]=$t;}
 $initial=$prev?(float)$prev['km_end']:($v['baseline_date']<=$start?(float)$v['baseline_km']:null);
 $source=$prev?'Última lectura #'.$prev['id'].' ('.substr($prev['arrival_at'],0,10).')':($initial!==null?'Lectura de referencia':'Sin cierre previo');
 if($initial===null&&$in){$initial=(float)$in[0]['km_start'];$source='Primer registro del mes; falta cierre previo';}
 $final=$in?(float)$in[count($in)-1]['km_end']:$initial;
 $km=array_sum(array_map(fn($t)=>(float)$t['km_end']-(float)$t['km_start'],$in));
 $fuel=row('SELECT COALESCE(SUM(f.liters),0) liters,COALESCE(SUM(f.amount),0) amount FROM fuel_loads f JOIN trips t ON t.id=f.trip_id WHERE t.vehicle_id=? AND f.fuel_date>=? AND f.fuel_date<?',[$v['id'],$start,$end]);
 return ['initial'=>$initial,'final'=>$final,'km'=>$km,'odometer'=>$final!==null&&$initial!==null?$final-$initial:null,'liters'=>(float)$fuel['liters'],'amount'=>(float)$fuel['amount'],'count'=>count($in),'source'=>$source,'trips'=>$in];
}
function missingRanges(array $v,array $all,string $month): array {
 [$start,$end]=monthBounds($month);$gaps=[];$prev=null;
 usort($all,fn($a,$b)=>[$a['departure_at'],$a['id']]<=>[$b['departure_at'],$b['id']]);
 foreach($all as $t){
  $prevDate=$prev?substr($prev['departure_at'],0,10):$v['baseline_date'];$prevKm=$prev?$prev['km_end']:$v['baseline_km'];
  $date=substr($t['departure_at'],0,10);
  if($date>=$v['baseline_date']&&$prevDate<$end&&$date>=$start){
   $delta=round((float)$t['km_start']-(float)$prevKm,2);
   if($delta!==0.0)$gaps[]=['type'=>$delta>0?'Faltante':'Retroceso / superposición','from'=>$prevKm,'to'=>$t['km_start'],'km'=>abs($delta),'before'=>$prev?'#'.$prev['id']:'Referencia','after'=>'#'.$t['id'],'dates'=>$prevDate.' → '.$date,'uncertain'=>substr($prevDate,0,7)!==substr($date,0,7)];
  }
  $prev=$t;
 }
 return $gaps;
}
function warningToken(array $payload,array $warnings): string {return hash_hmac('sha256',json_encode([$payload,$warnings],JSON_UNESCAPED_UNICODE),$_SESSION['csrf']);}
function warningsConfirmed(array $payload,array $warnings): bool {return !$warnings||(!empty($_POST['accept_warnings'])&&hash_equals(warningToken($payload,$warnings),(string)($_POST['warning_token']??'')));}
