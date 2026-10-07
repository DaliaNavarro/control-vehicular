<?php
declare(strict_types=1);
function normalizeTripForm(array $p): array {
 $date=dateValue($p['request_date']??'','Fecha');
 $weekday=(int)(new DateTimeImmutable($date))->format('N');
 if($weekday>=6)throw new RuntimeException('No se pueden registrar bitácoras en sábado ni domingo.');
 $p['departure_date']=$p['arrival_date']=$date;return normalizeTrip($p);
}
function normalizeTrip(array $p): array {
 $request=dateValue($p['request_date']??'','Fecha');$start=dateValue($p['departure_date']??'','Fecha de salida');$end=dateValue($p['arrival_date']??'','Fecha de llegada');
 foreach(['departure_time','arrival_time'] as $f)if(!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',(string)($p[$f]??'')))throw new RuntimeException('Las horas deben usar HH:MM.');
 $s=$start.' '.$p['departure_time'].':00';$t=$end.' '.$p['arrival_time'].':00';if($t<=$s)throw new RuntimeException('La hora de llegada debe ser posterior a la salida.');
 $a=integerValue($p['km_start']??'','Km iniciales');$b=integerValue($p['km_end']??'','Km finales');if((float)$b<(float)$a)throw new RuntimeException('Los km finales no pueden ser menores que los iniciales.');
 $vid=filter_var($p['vehicle_id']??0,FILTER_VALIDATE_INT);if(!$vid||$vid<1)throw new RuntimeException('Selecciona un vehículo válido.');
 return ['vehicle_id'=>(int)$vid,'request_date'=>$request,'departure_at'=>$s,'arrival_at'=>$t,'km_start'=>$a,'km_end'=>$b,'driver'=>textValue($p['driver']??'','Conductor',150),'purpose'=>textValue($p['purpose']??'','Comisión',500),'requester'=>textValue($p['requester']??($p['driver']??''),'Solicitante',150),'observations'=>(function($x){$x=trim((string)$x);if($x==='' )return '';if(mb_strlen($x)>1000)throw new RuntimeException('Observaciones: máximo 1000 caracteres.');return mb_strtoupper($x,'UTF-8');})($p['observations']??'')];
}

function tripBlockingConflicts(array $t,array $others): array {
 $conflicts=[];
 $prior=array_values(array_filter($others,fn($r)=>$r['departure_at']<$t['departure_at']));
 usort($prior,fn($a,$b)=>[$a['departure_at'],(string)$a['id']]<=>[$b['departure_at'],(string)$b['id']]);
 foreach($prior as $r){
  if($t['departure_at']<$r['arrival_at']&&$t['arrival_at']>$r['departure_at']) $conflicts[]='El horario se cruza con la bitácora anterior #'.$r['id'].' ('.$r['departure_at'].' a '.$r['arrival_at'].').';
  // Un salto de odómetro ya no bloquea el registro. Solo se considera retroceso
  // cuando el nuevo inicio queda por debajo del inicio de un registro anterior;
  // los huecos/intermedios son válidos y se conservan como advertencias.
  if((float)$t['km_start'] < (float)$r['km_start']) {
   $conflicts[]='Los kilómetros retroceden respecto a la bitácora anterior #'.$r['id'].' (inicia en '.$r['km_start'].' km).';
  }
 }
 return array_values(array_unique($conflicts));
}

function tripWarnings(array $t,array $others,array $vehicle,array $periods,bool $checkContinuity=true): array {
 $w=[];$start=substr($t['departure_at'],0,10);$end=substr($t['arrival_at'],0,10);if((float)$t['km_start']==(float)$t['km_end'])$w[]='El recorrido tiene 0 kilómetros.';
 foreach($periods as $p)if($p['start_date']<=$end&&($p['end_date']===null||$p['end_date']>=$start))$w[]='Coincide con el periodo inactivo #'.$p['id'].' ('.$p['reason'].').';
 usort($others,fn($a,$b)=>[$a['departure_at'],(string)$a['id']]<=>[$b['departure_at'],(string)$b['id']]);$prev=null;$next=null;
 foreach($others as $r){if($t['departure_at']<$r['arrival_at']&&$t['arrival_at']>$r['departure_at'])$w[]='Cruce de horario con bitácora #'.$r['id'].'.';if($r['departure_at']<$t['departure_at'])$prev=$r;elseif($next===null)$next=$r;}
 if($checkContinuity&&$prev&&abs((float)$prev['km_end']-(float)$t['km_start'])>0.005)$w[]='No continúa la bitácora #'.$prev['id'].': termina en '.$prev['km_end'].' km.';
 if($checkContinuity&&!$prev&&$start>=$vehicle['baseline_date']&&abs((float)$vehicle['baseline_km']-(float)$t['km_start'])>0.005)$w[]='El primer registro no continúa la lectura de referencia del vehículo.';
 if($checkContinuity&&$next&&abs((float)$t['km_end']-(float)$next['km_start'])>0.005)$w[]='No enlaza con la siguiente bitácora #'.$next['id'].'.';
 return array_values(array_unique($w));
}
function normalizeFuel(array $p): array {
 $method=(string)($p['payment_method']??'');if(!in_array($method,['Efectivo','Tarjeta','Vale'],true))throw new RuntimeException('Selecciona una forma de pago válida.');
 $last=null;if($method==='Tarjeta'){$last=trim((string)($p['card_last5']??''));if(!preg_match('/^\d{5}$/D',$last))throw new RuntimeException('Para tarjeta escribe exactamente los últimos 5 dígitos.');}
 $vid=filter_var($p['vehicle_id']??0,FILTER_VALIDATE_INT);if(!$vid||$vid<1)throw new RuntimeException('Selecciona un vehículo válido.');
 return ['vehicle_id'=>(int)$vid,'fuel_date'=>dateValue($p['fuel_date']??'','Fecha de carga'),'liters'=>decimalValue($p['liters']??'','Litros',3,true),'amount'=>decimalValue($p['amount']??'','Monto total'),'payment_method'=>$method,'card_last5'=>$last];
}
function remember(array $t): void {foreach(['driver','purpose'] as $f)execute('INSERT IGNORE INTO suggestions(kind,value) VALUES (?,?)',[$f,$t[$f]]);}
function createTrip(array $t,string $key,?int $userId=null): int {execute('INSERT INTO trips(reference_key,vehicle_id,user_id,request_date,departure_at,arrival_at,km_start,km_end,driver,purpose,requester,observations) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',[$key,$t['vehicle_id'],$userId,$t['request_date'],$t['departure_at'],$t['arrival_at'],$t['km_start'],$t['km_end'],$t['driver'],$t['purpose'],$t['requester'],$t['observations']]);$id=(int)db()->lastInsertId();remember($t);return $id;}
function createFuel(array $f,string $key,?int $tripId=null): int {execute('INSERT INTO fuel_loads(reference_key,vehicle_id,trip_id,fuel_date,liters,amount,payment_method,card_last5) VALUES (?,?,?,?,?,?,?,?)',[$key,$f['vehicle_id'],$tripId,$f['fuel_date'],$f['liters'],$f['amount'],$f['payment_method'],$f['card_last5']]);return (int)db()->lastInsertId();}
function periodForDate(array $periods,string $date): ?array {foreach($periods as $p)if($p['start_date']<=$date&&($p['end_date']===null||$p['end_date']>=$date))return $p;return null;}
function monthSummary(array $v,string $month,?array $all=null): array {[$start,$end]=monthBounds($month);$all??=rows('SELECT * FROM trips WHERE vehicle_id=? ORDER BY departure_at,id',[$v['id']]);$in=[];$prev=null;foreach($all as $t){if($t['departure_at']<$start)$prev=$t;elseif($t['departure_at']<$end)$in[]=$t;}$initial=$prev?(float)$prev['km_end']:($v['baseline_date']<=$start?(float)$v['baseline_km']:null);if($initial===null&&$in)$initial=(float)$in[0]['km_start'];$final=$in?(float)$in[count($in)-1]['km_end']:$initial;$km=array_sum(array_map(fn($t)=>(float)$t['km_end']-(float)$t['km_start'],$in));$fuel=row('SELECT COALESCE(SUM(liters),0) liters,COALESCE(SUM(amount),0) amount FROM fuel_loads WHERE vehicle_id=? AND fuel_date>=? AND fuel_date<?',[$v['id'],$start,$end]);return ['initial'=>$initial,'final'=>$final,'km'=>$km,'odometer'=>$final!==null&&$initial!==null?$final-$initial:null,'liters'=>(float)$fuel['liters'],'amount'=>(float)$fuel['amount'],'count'=>count($in),'source'=>$prev?'Última lectura #'.$prev['id']:'Lectura de referencia','trips'=>$in];}
function missingRanges(array $v,array $all,string $month): array {[$start,$end]=monthBounds($month);$g=[];$prev=null;usort($all,fn($a,$b)=>[$a['departure_at'],$a['id']]<=>[$b['departure_at'],$b['id']]);foreach($all as $t){$prevDate=$prev?substr($prev['departure_at'],0,10):$v['baseline_date'];$prevKm=$prev?$prev['km_end']:$v['baseline_km'];$date=substr($t['departure_at'],0,10);if($date>=$v['baseline_date']&&$prevDate<$end&&$date>=$start){$delta=round((float)$t['km_start']-(float)$prevKm,2);if($delta!==0.0)$g[]=['type'=>$delta>0?'Faltante':'Retroceso / superposición','from'=>$prevKm,'to'=>$t['km_start'],'km'=>abs($delta),'before'=>$prev?'#'.$prev['id']:'Referencia','after'=>'#'.$t['id'],'dates'=>$prevDate.' → '.$date,'uncertain'=>substr($prevDate,0,7)!==substr($date,0,7)];}$prev=$t;}return $g;}
function warningToken(array $payload,array $warnings): string {return hash_hmac('sha256',json_encode([$payload,$warnings],JSON_UNESCAPED_UNICODE),$_SESSION['csrf']);}
function warningsConfirmed(array $payload,array $warnings): bool {return !$warnings||(!empty($_POST['accept_warnings'])&&hash_equals(warningToken($payload,$warnings),(string)($_POST['warning_token']??'')));}
function storePendingTrip(array $t,int $id,array $warnings): string {
 $token=warningToken([$t,$id],$warnings);
 $_SESSION['pending_trip']=['data'=>$t,'id'=>$id,'warnings'=>$warnings,'token'=>$token,'created_at'=>time()];
 return $token;
}
function pendingTrip(): ?array {
 $p=$_SESSION['pending_trip']??null;
 if(!is_array($p)||empty($p['data'])||empty($p['warnings'])||empty($p['token']))return null;
 if((int)($p['created_at']??0)<time()-1800){unset($_SESSION['pending_trip']);return null;}
 return $p;
}
function clearPendingTrip(): void {unset($_SESSION['pending_trip']);}
