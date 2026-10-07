<?php
require_once __DIR__.'/Xlsx.php';require_once __DIR__.'/template_xlsx.php';
const TRIP_HEADERS=['clave','vehiculo_no_economico','fecha','hora_salida','hora_llegada','km_inicial','km_final','conductor','comision','solicitante','observaciones'];
const FUEL_HEADERS=['clave_carga','vehiculo_no_economico','fecha_carga','litros','importe_total','forma_pago','tarjeta_ultimos5'];
function spanishMonthYear(string $month): string {$months=[1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];$d=new DateTimeImmutable($month.'-01');return $months[(int)$d->format('n')].'-'.$d->format('Y');}
function fullMonthInactiveReason(int $vehicle,string $start,string $end): ?string {$p=row('SELECT reason FROM inactive_periods WHERE vehicle_id=? AND start_date<=? AND (end_date IS NULL OR end_date>=?) ORDER BY start_date LIMIT 1',[$vehicle,$start,$end]);return $p['reason']??null;}
function exportExcel(string $kind,string $month='',int $vehicle=0): never {
 if($kind==='trip_template'){$id=(int)($_GET['id']??0);$t=need('trips',$id);if(!canViewTrip($t)|| (isExternal() && (int)$t['user_id']!==currentUserId()))throw new RuntimeException('Solo puedes descargar tus propias bitácoras.');$v=need('vehicles',(int)$t['vehicle_id']);$u=$t['user_id']?row('SELECT * FROM users WHERE id=?',[$t['user_id']]):null;$license=$u['license_number']??'';$expires=$u['license_expires']??'';$cells=['B5'=>['excel_date'=>$t['request_date']],'F5'=>'HORA DE SALIDA:  '.substr($t['departure_at'],11,5),'H5'=>'HORA DE LLEGADA: '.substr($t['arrival_at'],11,5),'A8'=>$t['driver'],'E8'=>$license,'G8'=>($expires!==''?['excel_date'=>$expires]:''),'B10'=>$v['brand'].' '.$v['model'],'E10'=>'MODELO  '.$v['model_year'],'G10'=>' PLACAS :   '.$v['plates'],'I10'=>'N° ECONOMICO  '.$v['economic_number'],'A11'=>' KM SALIDA:  '.num($t['km_start'],0),'D11'=>'KM ENTRADA: '.num($t['km_end'],0),'A14'=>$t['purpose'],'A22'=>$t['observations'],'A25'=>$t['requester']."\n(nombre y firma)",'H25'=>$t['driver']."\n(nombre y firma)"];TemplateXlsx::download(__DIR__.'/templates/plantilla_bitacora.xlsx','POINTER 04 BARRA',$cells,'bitacora-'.$id.'.xlsx');}
 requireAdmin();
 if($kind==='gas_month'){
  $month=$month?:date('Y-m');$v=need('vehicles',$vehicle);[$a,$b]=monthBounds($month);
  $trips=rows('SELECT * FROM trips WHERE vehicle_id=? AND departure_at>=? AND departure_at<? ORDER BY departure_at,id',[$vehicle,$a,$b]);
  $fuel=rows('SELECT * FROM fuel_loads WHERE vehicle_id=? AND fuel_date>=? AND fuel_date<? ORDER BY fuel_date,id',[$vehicle,$a,$b]);
  $cells=['C10'=>strtoupper($v['department']),'C11'=>strtoupper($v['brand'].' '.$v['model'].' '.$v['model_year']),'F11'=>spanishMonthYear($month),'C12'=>strtoupper($v['plates']),'D15'=>''];$inactiveReason=fullMonthInactiveReason($vehicle,$a,date('Y-m-d',strtotime($b.' -1 day')));if($inactiveReason)$cells['D15']=$inactiveReason;
  // La plantilla tiene cuatro renglones para las semanas del mes: 1-7, 8-14, 15-21 y 22-fin.
  $weekRanges=[
   [$a,date('Y-m-d',strtotime($a.' +7 days'))],
   [date('Y-m-d',strtotime($a.' +7 days')),date('Y-m-d',strtotime($a.' +14 days'))],
   [date('Y-m-d',strtotime($a.' +14 days')),date('Y-m-d',strtotime($a.' +21 days'))],
   [date('Y-m-d',strtotime($a.' +21 days')),$b]
  ];
  $totalKm=0.0;
  foreach($weekRanges as $i=>[$ws,$we]){
   $wk=array_values(array_filter($trips,fn($t)=>substr($t['departure_at'],0,10)>=$ws&&substr($t['departure_at'],0,10)<$we));
   $r=15+$i;
   if($wk){$initial=(float)$wk[0]['km_start'];$final=(float)$wk[count($wk)-1]['km_end'];$km=max(0,$final-$initial);$cells['B'.$r]=(int)round($initial);$cells['C'.$r]=(int)round($final);$cells['E'.$r]=(int)round($km);$totalKm+=$km;}
  }
  // Las columnas G/H/I son independientes del bloque semanal y permiten mostrar las cargas del mes.
  // Se usan hasta ocho renglones de la plantilla; si existen más, se agregan al último renglón como detalle multilinea.
  foreach(array_slice($fuel,0,7) as $i=>$f){$r=15+$i;$cells['G'.$r]=(float)$f['liters'];$cells['H'.$r]=(float)$f['amount'];$cells['I'.$r]=displayDate($f['fuel_date']);}
  if(count($fuel)>=8){
   $last=array_slice($fuel,7);
   $cells['G22']=implode("\n",array_map(fn($f)=>(string)$f['liters'],$last));$cells['H22']=implode("\n",array_map(fn($f)=>(string)$f['amount'],$last));$cells['I22']=implode("\n",array_column($last,'fuel_date'));
  }
  $totalLiters=array_sum(array_column($fuel,'liters'));$totalAmount=array_sum(array_column($fuel,'amount'));
  $cells['E23']=(int)round($totalKm);$cells['G23']=$totalLiters;$cells['H23']=$totalAmount;
  TemplateXlsx::download(__DIR__.'/templates/plantilla_concentradogas.xlsx','06',$cells,'concentrado-gas-'.$v['economic_number'].'-'.$month.'.xlsx');
 }
 if($kind==='annual'){
  $year=(int)($_GET['year']??date('Y'));if($year<2000||$year>2100)throw new RuntimeException('Año inválido.');
  $vs=rows('SELECT * FROM vehicles ORDER BY economic_number');$months=['ENE','FEB','MAR','ABR','MAY','JUN','JUL','AGO','SEP','OCT','NOV','DIC'];
  $money=[array_merge(['Vehículo'],$months,['TOTAL'])];$liters=[array_merge(['Vehículo'],$months,['TOTAL'])];
  $monthMoney=array_fill(1,12,0.0);$monthLiters=array_fill(1,12,0.0);$grandMoney=0.0;$grandLiters=0.0;
  foreach($vs as $v){
   $mr=[$v['economic_number'].' · '.$v['brand'].' '.$v['model']];$lr=[$v['economic_number'].' · '.$v['brand'].' '.$v['model']];$mt=$lt=0.0;
   for($m=1;$m<=12;$m++){$a=sprintf('%04d-%02d-01',$year,$m);$b=date('Y-m-d',strtotime($a.' +1 month'));$x=row('SELECT COALESCE(SUM(amount),0) a,COALESCE(SUM(liters),0) l FROM fuel_loads WHERE vehicle_id=? AND fuel_date>=? AND fuel_date<?',[$v['id'],$a,$b]);$amount=(float)$x['a'];$litersValue=(float)$x['l'];$mr[]=$amount;$lr[]=$litersValue;$mt+=$amount;$lt+=$litersValue;$monthMoney[$m]+=$amount;$monthLiters[$m]+=$litersValue;}
   $mr[]=$mt;$lr[]=$lt;$money[]=$mr;$liters[]=$lr;$grandMoney+=$mt;$grandLiters+=$lt;
  }
  // Totales mensuales de todos los vehículos, además del total anual por vehículo.
  $moneyTotal=['TOTAL MES'];$litersTotal=['TOTAL MES'];for($m=1;$m<=12;$m++){$moneyTotal[]=$monthMoney[$m];$litersTotal[]=$monthLiters[$m];}$moneyTotal[]=$grandMoney;$litersTotal[]=$grandLiters;$money[]=$moneyTotal;$liters[]=$litersTotal;
  Xlsx::download([['name'=>'Dinero MXN','rows'=>$money,'widths'=>array_fill(0,14,15)],['name'=>'Litros','rows'=>$liters,'widths'=>array_fill(0,14,15)]],'concentrado-anual-gasolina-'.$year.'.xlsx');
 }
 if($kind==='template'){Xlsx::download([['name'=>'Bitacoras','rows'=>[TRIP_HEADERS,['HIST-001','ECO-01',date('Y-m-d'),'08:00','10:00',1000,1050,'NOMBRE','COMISIÓN','NOMBRE','']], 'filter'=>true],['name'=>'Gasolina','rows'=>[FUEL_HEADERS,['GAS-001','ECO-01',date('Y-m-d'),20,500,'Tarjeta','12345']], 'filter'=>true]],'plantilla-importacion-v2.xlsx');}
 $rows=[['No. económico','Vehículo','Departamento','Mes','Km inicial','Km final','Km recorridos','Litros','Importe MXN']];$month=$month?:date('Y-m');foreach(rows('SELECT * FROM vehicles ORDER BY economic_number') as $v){if($vehicle&&(int)$v['id']!==$vehicle)continue;$s=monthSummary($v,$month);$rows[]=[$v['economic_number'],$v['brand'].' '.$v['model'],$v['department'],displayMonth($month),$s['initial']===null?null:(int)round($s['initial']),$s['final']===null?null:(int)round($s['final']),(int)round($s['km']),$s['liters'],$s['amount']];}Xlsx::download([['name'=>'Concentrado','rows'=>$rows,'filter'=>true]],'concentrado-'.$month.'.xlsx');
}
function excelDate(string $v,bool $date1904): string {if(is_numeric($v))return (new DateTimeImmutable($date1904?'1904-01-01':'1899-12-30'))->modify('+'.(int)$v.' days')->format('Y-m-d');return $v;}
function excelTime(string $v): string {if(is_numeric($v)){$min=(int)round((float)$v*1440)%1440;return sprintf('%02d:%02d',intdiv($min,60),$min%60);}return preg_match('/^\d{2}:\d{2}:00$/',$v)?substr($v,0,5):$v;}
function parseImport(string $path): array {$books=Xlsx::read($path,['Bitacoras','Gasolina']);$out=['trips'=>[],'fuel'=>[]];foreach($books as $name=>$sheet){$data=$sheet['data'];if(!$data)continue;$header=array_shift($data);unset($header['_row']);$expected=$name==='Bitacoras'?TRIP_HEADERS:FUEL_HEADERS;if(array_values($header)!==$expected)throw new RuntimeException('Encabezados inválidos en '.$name);foreach($data as $r){$p=[];foreach($expected as $i=>$h)$p[$h]=trim((string)($r[$i]??''));$v=row('SELECT id FROM vehicles WHERE economic_number=?',[$p['vehiculo_no_economico']]);if(!$v)throw new RuntimeException('No existe vehículo '.$p['vehiculo_no_economico']);if($name==='Bitacoras'){$out['trips'][]=['key'=>$p['clave'],'data'=>normalizeTrip(['vehicle_id'=>$v['id'],'request_date'=>excelDate($p['fecha'],$sheet['date1904']),'departure_date'=>excelDate($p['fecha'],$sheet['date1904']),'arrival_date'=>excelDate($p['fecha'],$sheet['date1904']),'departure_time'=>excelTime($p['hora_salida']),'arrival_time'=>excelTime($p['hora_llegada']),'km_start'=>$p['km_inicial'],'km_end'=>$p['km_final'],'driver'=>$p['conductor'],'purpose'=>$p['comision'],'requester'=>$p['solicitante'],'observations'=>$p['observaciones']])];}else{$out['fuel'][]=['key'=>$p['clave_carga'],'data'=>normalizeFuel(['vehicle_id'=>$v['id'],'fuel_date'=>excelDate($p['fecha_carga'],$sheet['date1904']),'liters'=>$p['litros'],'amount'=>$p['importe_total'],'payment_method'=>$p['forma_pago'],'card_last5'=>$p['tarjeta_ultimos5']])];}}}return $out;}
function evaluateImport(array $batch,bool $commit=false): array {$tn=$fn=$skip=0;foreach($batch['trips'] as $r){if(row('SELECT id FROM trips WHERE reference_key=?',[$r['key']])){$skip++;continue;}$tn++;if($commit)createTrip($r['data'],$r['key'],null);}foreach($batch['fuel'] as $r){if(row('SELECT id FROM fuel_loads WHERE reference_key=?',[$r['key']])){$skip++;continue;}$fn++;if($commit)createFuel($r['data'],$r['key']);}return ['warnings'=>[],'trips'=>$tn,'fuel'=>$fn,'skipped'=>$skip];}
