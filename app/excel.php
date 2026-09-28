<?php
require_once __DIR__.'/Xlsx.php';
const TRIP_HEADERS=['clave','vehiculo_no_economico','fecha_solicitud','hora_salida','hora_llegada','km_inicial','km_final','conductor','comision'];
const LEGACY_TRIP_HEADERS=['clave','vehiculo_no_economico','fecha_solicitud','fecha_salida','hora_salida','fecha_llegada','hora_llegada','km_inicial','km_final','conductor','comision'];
const FUEL_HEADERS=['clave_carga','bitacora_clave','fecha_carga','litros','importe_total','tarjeta_ultimos4'];
function dataSheets(?string $month=null,int $vehicle=0): array {
 $where=[];$args=[];if($month){[$a,$b]=monthBounds($month);$where[]='((t.departure_at>=? AND t.departure_at<?) OR EXISTS(SELECT 1 FROM fuel_loads f WHERE f.trip_id=t.id AND f.fuel_date>=? AND f.fuel_date<?))';$args=[$a,$b,$a,$b];}if($vehicle){$where[]='t.vehicle_id=?';$args[]=$vehicle;}
 $ts=rows('SELECT t.*,v.economic_number FROM trips t JOIN vehicles v ON v.id=t.vehicle_id'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY t.departure_at,t.id',$args);
 // Preserve exceptional legacy trips across dates without silently changing their history.
 $legacy=(bool)array_filter($ts,fn($t)=>$t['request_date']!==substr($t['departure_at'],0,10)||$t['request_date']!==substr($t['arrival_at'],0,10));
 $tr=[$legacy?LEGACY_TRIP_HEADERS:TRIP_HEADERS];$fu=[FUEL_HEADERS];foreach($ts as $t){
 $record=[$t['reference_key'],$t['economic_number'],$t['request_date']];
 if($legacy)$record[]=substr($t['departure_at'],0,10);
 $record[]=substr($t['departure_at'],11,5);
 if($legacy)$record[]=substr($t['arrival_at'],0,10);
 array_push($record,substr($t['arrival_at'],11,5),(float)$t['km_start'],(float)$t['km_end'],$t['driver'],$t['purpose']);$tr[]=$record;foreach(rows('SELECT * FROM fuel_loads WHERE trip_id=? ORDER BY fuel_date,id',[$t['id']]) as $f)$fu[]=[$f['reference_key'],$t['reference_key'],$f['fuel_date'],['value'=>(float)$f['liters'],'style'=>5],(float)$f['amount'],$f['card_last4']];}
 return [['name'=>'Bitacoras','rows'=>$tr,'filter'=>true],['name'=>'Cargas','rows'=>$fu,'filter'=>true]];
}
function instructionsSheet(): array {return ['name'=>'Instrucciones','widths'=>[32,110],'rows'=>[['Campo / regla','Instrucción'],['Uso','Llena Bitacoras y Cargas. Crea primero los vehículos en la aplicación. Máximo 2000 filas por hoja.'],['clave / clave_carga','Texto único de hasta 64 caracteres: por ejemplo HIST-2025-001. No reutilices una clave para otra entrada.'],['vehiculo_no_economico','Debe coincidir exactamente con un vehículo existente. Formatea como Texto si incluye ceros iniciales.'],['bitacora_clave','En Cargas: clave de una bitácora de este archivo o ya registrada. Puedes importar solo cargas.'],['Fecha única','En Bitacoras captura solo fecha_solicitud (AAAA-MM-DD). La salida y la llegada se registran ese mismo día.'],['Horas','hora_salida y hora_llegada en HH:MM de 24 horas. La llegada debe ser posterior a la salida, dentro del mismo día. También se aceptan fechas/horas numéricas de Excel.'],['Compatibilidad','Se aceptan archivos v1.0 con fecha_salida y fecha_llegada. Si un historial antiguo usa fechas diferentes, su exportación conserva esas columnas para no perder información.'],['Kilómetros','Números no negativos. Km final >= inicial. Hasta 2 decimales. Sin separador de miles.'],['litros / importe_total','Litros positivos (3 decimales); importe total pagado en MXN (2 decimales). Precio se interpreta como total, no precio por litro.'],['tarjeta_ultimos4','Exactamente 4 dígitos. Formatea esta columna como Texto para conservar 0007.'],['Actualizaciones','La importación agrega; no sobreescribe. Claves idénticas con los mismos valores se omiten. Si cambian los valores se rechazan. Edita en la aplicación.'],['Relación','Bitácoras y cargas tienen claves independientes. Una bitácora admite varias cargas.'],['Mes','Los recorridos se agrupan por fecha de salida. Las cargas por fecha real de carga, aunque se capturen después.'],['Advertencias','Se revisan cruces, continuidad, fines de semana e inactividad antes de confirmar. Las referencias fila N son filas del mismo archivo.'],['Exportación','Los reportes incluyen hojas de datos para reimportar. Las hojas de informe no se importan. Incluyen cargas vinculadas de otros meses, sin sumarlas al mes equivocado.'],['Fórmulas','Pega como valores antes de importar. No se ejecutan fórmulas del archivo.'],['Inactividad','Se administra en Vehículos; los informes incluyen periodos, pero no los importan.'],['Totales','Km registrados suma recorridos; diferencia de odómetro resta cierre e inicio. Huecos o superposiciones pueden producir diferencias.']]];}
function exportExcel(string $kind,string $month,int $vehicle): never {
 if($kind==='template')Xlsx::download([['name'=>'Bitacoras','rows'=>[TRIP_HEADERS]],['name'=>'Cargas','rows'=>[FUEL_HEADERS]],instructionsSheet()],'plantilla-historicos.xlsx');
 if($kind==='data')Xlsx::download([...dataSheets(),instructionsSheet()],'registros-completos.xlsx');
 [$start,$end]=monthBounds($month);$vs=$vehicle?[need('vehicles',$vehicle)]:rows('SELECT * FROM vehicles ORDER BY economic_number');$sheets=[];
 if($kind==='general'){
  $rr=[['No. económico','Vehículo','Placas','Mes','Km inicio','Km cierre','Km registrados','Diferencia odómetro','Litros','Total pagado MXN','Bitácoras','Origen lectura inicial']];
  foreach($vs as $v){$s=monthSummary($v,$month);$rr[]=[$v['economic_number'],$v['brand'].' '.$v['model'],$v['plates'],$month,$s['initial'],$s['final'],$s['km'],$s['odometer'],['value'=>$s['liters'],'style'=>5],$s['amount'],$s['count'],$s['source']];}
  $last=count($rr);$tot=['TOTALES','','','','','',0,'',0,0,0,''];foreach([6,8,9,10] as $col){$letter=[6=>'G',8=>'I',9=>'J',10=>'K'][$col];$sum=array_sum(array_map(fn($r)=>is_array($r[$col])?$r[$col]['value']:(float)$r[$col],array_slice($rr,1)));$tot[$col]=['formula'=>$last>1?'SUM('.$letter.'2:'.$letter.$last.')':'0','value'=>$sum,'style'=>$col===8?6:4];}$rr[]=$tot;
  $sheets[]=['name'=>'General','rows'=>$rr,'styles'=>[count($rr)-1=>4],'widths'=>[20,28,18,16,20,20,20,22,18,22,16,45]];
 }else{
  if(!$vehicle)throw new RuntimeException('Selecciona un vehículo para el concentrado individual.');$v=$vs[0];$s=monthSummary($v,$month);$periods=rows('SELECT * FROM inactive_periods WHERE vehicle_id=? ORDER BY start_date',[$vehicle]);
  $fuels=rows('SELECT f.*,t.id trip_number FROM fuel_loads f JOIN trips t ON t.id=f.trip_id WHERE t.vehicle_id=? AND f.fuel_date>=? AND f.fuel_date<? ORDER BY f.fuel_date,f.id',[$vehicle,$start,$end]);
  $rr=[['Vehículo','Placas','No. económico','Fecha','Día','ID bitácora','Solicitud','Salida','Llegada','Km inicial','Km final','Km recorridos','Conductor','Comisión','Litros','Importe MXN','Últimos 4','Estado / observación']];$styles=[];$kmSum=0;$litSum=0;$amountSum=0;
  for($d=$start;$d<$end;$d=date('Y-m-d',strtotime($d.' +1 day'))){$week=(int)date('N',strtotime($d));$state=periodForDate($periods,$d);$base=[$v['brand'].' '.$v['model'],$v['plates'],$v['economic_number'],$d,['','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'][$week]];$any=false;
   foreach($s['trips'] as $t){if(substr($t['departure_at'],0,10)!==$d)continue;$any=true;$km=(float)$t['km_end']-(float)$t['km_start'];$rr[]=[...$base,$t['id'],$t['request_date'],$t['departure_at'],$t['arrival_at'],(float)$t['km_start'],(float)$t['km_end'],['formula'=>'K'.(count($rr)+1).'-J'.(count($rr)+1),'value'=>$km],$t['driver'],$t['purpose'],'','','',$state?$state['reason']:'Disponible'];$kmSum+=$km;if($week>=6)$styles[count($rr)-1]=2;}
   foreach($fuels as $f){if($f['fuel_date']!==$d)continue;$any=true;$rr[]=[...$base,$f['trip_number'],'','','','','','','Carga #'.$f['id'],'Carga de gasolina',['value'=>(float)$f['liters'],'style'=>$week>=6?7:5],(float)$f['amount'],$f['card_last4'],$state?$state['reason']:'Disponible'];$litSum+=(float)$f['liters'];$amountSum+=(float)$f['amount'];if($week>=6)$styles[count($rr)-1]=2;}
   if(!$any){$rr[]=[...$base,'','','','','','','','','','','','',$state?$state['reason']:'Sin registros'];if($week>=6)$styles[count($rr)-1]=2;}
  }
  $last=count($rr);$rr[]=['TOTAL DEL MES','','','','','','','','','','',['formula'=>'SUM(L2:L'.$last.')','value'=>$kmSum],'','',['formula'=>'SUM(O2:O'.$last.')','value'=>$litSum,'style'=>6],['formula'=>'SUM(P2:P'.$last.')','value'=>$amountSum],'',''];$styles[count($rr)-1]=4;
  $sheets[]=['name'=>'Concentrado','rows'=>$rr,'styles'=>$styles,'widths'=>[26,18,18,16,16,16,16,23,23,18,18,18,28,50,16,20,15,30]];
  $sheets[]=['name'=>'Resumen','rows'=>[['Concepto','Valor'],['Vehículo',$v['brand'].' '.$v['model']],['Placas',$v['plates']],['Responsable',$v['responsible']],['Mes',$month],['Km inicio',$s['initial']],['Km cierre',$s['final']],['Km registrados',$s['km']],['Diferencia de odómetro',$s['odometer']],['Origen del inicio',$s['source']],['Litros',['value'=>$s['liters'],'style'=>5]],['Total pagado MXN',$s['amount']],['Regla de contabilización','Recorridos por mes de salida; gasolina por fecha de carga. Cada carga se muestra una sola vez.']],'widths'=>[35,90]];
 }
 $pp=[['No. económico','Vehículo','Placas','ID periodo','Inicio original','Fin original','Desde en este mes','Hasta en este mes','Estado','Notas']];
 foreach($vs as $v)foreach(rows('SELECT * FROM inactive_periods WHERE vehicle_id=? AND start_date<? AND (end_date IS NULL OR end_date>=?) ORDER BY start_date',[$v['id'],$end,$start]) as $p)$pp[]=[$v['economic_number'],$v['brand'].' '.$v['model'],$v['plates'],$p['id'],$p['start_date'],$p['end_date']??'Abierto',max($start,$p['start_date']),min(date('Y-m-d',strtotime($end.' -1 day')),$p['end_date']??$end),$p['reason'],$p['notes']];
 $sheets[]=['name'=>'Inactividad','rows'=>$pp,'widths'=>[20,28,18,16,18,18,22,22,24,60],'filter'=>true];
 Xlsx::download([...$sheets,...dataSheets($month,$vehicle),instructionsSheet()],($kind==='general'?'concentrado-general-':'concentrado-vehiculo-'.$vehicle.'-').$month.'.xlsx');
}
function excelDate(string $v,bool $date1904): string {if(is_numeric($v)){if((float)$v<0||(float)$v>80000)throw new RuntimeException('Fecha numérica Excel fuera de rango.');return (new DateTimeImmutable($date1904?'1904-01-01':'1899-12-30'))->modify('+'.(int)$v.' days')->format('Y-m-d');}return $v;}
function excelTime(string $v): string {if(is_numeric($v)){if((float)$v<0||(float)$v>=1)throw new RuntimeException('Hora numérica Excel fuera de rango.');$minutes=(int)round((float)$v*1440)%1440;return sprintf('%02d:%02d',intdiv($minutes,60),$minutes%60);}return preg_match('/^\d{2}:\d{2}:00$/D',$v)?substr($v,0,5):$v;}
function importKey(string $v): string {if(!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$v))throw new RuntimeException('La clave debe tener de 1 a 64 letras, números, guiones o guiones bajos.');return $v;}
function parseImport(string $path): array {
 $books=Xlsx::read($path);$out=['trips'=>[],'fuel'=>[]];if(!$books)throw new RuntimeException('Faltan las hojas Bitacoras o Cargas. Descarga la plantilla.');
 foreach($books as $name=>$sheet){$data=$sheet['data'];if(!$data)continue;$header=array_shift($data);unset($header['_row']);$expected=$name==='Bitacoras'?TRIP_HEADERS:FUEL_HEADERS;
  if($name==='Bitacoras'&&array_values($header)===LEGACY_TRIP_HEADERS)$expected=LEGACY_TRIP_HEADERS;
  if(array_values($header)!==$expected)throw new RuntimeException('Los encabezados de '.$name.' no coinciden con la plantilla.');
  foreach($data as $r){$n=$r['_row'];try{$p=[];foreach($expected as $i=>$h)$p[$h]=trim((string)($r[$i]??''));
   if($name==='Bitacoras'){$v=row('SELECT id FROM vehicles WHERE economic_number=?',[$p['vehiculo_no_economico']]);if(!$v)throw new RuntimeException('No existe el vehículo '.$p['vehiculo_no_economico']);$t=normalizeTrip(['vehicle_id'=>$v['id'],'request_date'=>excelDate($p['fecha_solicitud'],$sheet['date1904']),'departure_date'=>excelDate($p['fecha_salida']??$p['fecha_solicitud'],$sheet['date1904']),'departure_time'=>excelTime($p['hora_salida']),'arrival_date'=>excelDate($p['fecha_llegada']??$p['fecha_solicitud'],$sheet['date1904']),'arrival_time'=>excelTime($p['hora_llegada']),'km_start'=>$p['km_inicial'],'km_end'=>$p['km_final'],'driver'=>$p['conductor'],'purpose'=>$p['comision']]);$out['trips'][]=['key'=>importKey($p['clave']),'row'=>$n,'data'=>$t];}
   else{$f=normalizeFuel(['fuel_date'=>excelDate($p['fecha_carga'],$sheet['date1904']),'liters'=>$p['litros'],'amount'=>$p['importe_total'],'card_last4'=>$p['tarjeta_ultimos4']]);$out['fuel'][]=['key'=>importKey($p['clave_carga']),'trip_key'=>importKey($p['bitacora_clave']),'row'=>$n,'data'=>$f];}
  }catch(Throwable $e){throw new RuntimeException($name.' fila '.$n.': '.$e->getMessage());}}
 }
 if(!$out['trips']&&!$out['fuel'])throw new RuntimeException('El archivo no contiene registros.');return $out;
}
function sameFields(array $db,array $data): bool {foreach($data as $k=>$v)if((string)$db[$k] !== (string)$v)return false;return true;}
function evaluateImport(array $batch,bool $commit=false): array {
 $warnings=[];$new=[];$keys=[];$skipped=0;$newFuel=[];$fuelKeys=[];$vehicles=[];$existing=[];$periods=[];
 foreach($batch['trips'] as $r){$key=$r['key'];if(isset($keys[$key]))throw new RuntimeException('Clave de bitácora repetida dentro del archivo: '.$key);$keys[$key]=true;
  $old=row('SELECT * FROM trips WHERE reference_key=?',[$key]);if($old){if(!sameFields($old,$r['data']))throw new RuntimeException('La clave '.$key.' ya existe como #'.$old['id'].' con valores diferentes. No se sobreescribió.');$skipped++;continue;}$new[]=$r;
 }
 foreach($new as $r){$vid=$r['data']['vehicle_id'];$vehicles[$vid]??=need('vehicles',$vid);$existing[$vid]??=rows('SELECT * FROM trips WHERE vehicle_id=? ORDER BY departure_at,id',[$vid]);$periods[$vid]??=rows('SELECT * FROM inactive_periods WHERE vehicle_id=?',[$vid]);}
 foreach($new as $r){$vid=$r['data']['vehicle_id'];$others=$existing[$vid];foreach($new as $other)if($other['key']!==$r['key']&&$other['data']['vehicle_id']==$vid)$others[]=$other['data']+['id'=>'fila '.$other['row']];foreach(tripWarnings($r['data'],$others,$vehicles[$vid],$periods[$vid]) as $w)$warnings[]='Bitacoras fila '.$r['row'].': '.$w;}
 foreach($batch['fuel'] as $r){if(isset($fuelKeys[$r['key']]))throw new RuntimeException('Clave de carga repetida dentro del archivo: '.$r['key']);$fuelKeys[$r['key']]=true;
  $trip=row('SELECT * FROM trips WHERE reference_key=?',[$r['trip_key']]);if(!$trip)foreach($new as $t)if($t['key']===$r['trip_key']){$trip=$t['data']+['id'=>'fila '.$t['row']];break;}if(!$trip)throw new RuntimeException('Cargas fila '.$r['row'].': no existe bitácora con clave '.$r['trip_key']);
  $old=row('SELECT f.*,t.reference_key trip_key FROM fuel_loads f JOIN trips t ON t.id=f.trip_id WHERE f.reference_key=?',[$r['key']]);if($old){if(!sameFields($old,$r['data'])||$old['trip_key']!==$r['trip_key'])throw new RuntimeException('La carga '.$r['key'].' ya existe con valores diferentes.');$skipped++;continue;}
  foreach(fuelWarnings($r['data'],$trip) as $w)$warnings[]='Cargas fila '.$r['row'].': '.$w;$newFuel[]=$r;
 }
 if($commit){foreach($new as $r)createTrip($r['data'],$r['key']);foreach($newFuel as $r){$t=row('SELECT id FROM trips WHERE reference_key=?',[$r['trip_key']]);createFuel((int)$t['id'],$r['data'],$r['key']);}}
 return ['warnings'=>$warnings,'trips'=>count($new),'fuel'=>count($newFuel),'skipped'=>$skipped];
}
