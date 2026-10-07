<?php
require __DIR__.'/../app/bootstrap.php';require __DIR__.'/../app/excel.php';
if(getenv('CV_TEST_DATABASE')!=='yes')die("Usa una base de pruebas y define CV_TEST_DATABASE=yes.\n");
function check11(bool $ok,string $message): void {if(!$ok)throw new RuntimeException('FALLÓ: '.$message);echo "OK: $message\n";}
function expectFailure(callable $fn,string $message): void {$failed=false;try{$fn();}catch(RuntimeException){$failed=true;}check11($failed,$message);}
function accountingSnapshot(): string {
 $data=[];foreach(['vehicles','trips','fuel_loads','inactive_periods','suggestions'] as $table)$data[$table]=rows('SELECT * FROM '.$table.' ORDER BY id');
 return hash('sha256',serialize($data));
}
$pdo=db();$pdo->beginTransaction();$tmp=tempnam(sys_get_temp_dir(),'cv11');
try {
 execute('INSERT INTO vehicles(brand,model,plates,economic_number,responsible,baseline_date,baseline_km) VALUES (?,?,?,?,?,?,?)',['Prueba','1.1','QA-'.substr(keygen(),0,12),'QA-'.substr(keygen(),0,10),'Prueba','2026-09-01',100]);$vid=(int)$pdo->lastInsertId();$vehicle=need('vehicles',$vid);
 $key=keygen();$simple=[$key,$vehicle['economic_number'],'2026-09-28','09:00','10:00',100,125,'Ana','Entrega'];
 Xlsx::write($tmp,[['name'=>'Bitacoras','rows'=>[TRIP_HEADERS,$simple]]]);$batch=parseImport($tmp);$trip=$batch['trips'][0]['data'];
 check11(count(TRIP_HEADERS)===9&&!in_array('fecha_llegada',TRIP_HEADERS)&&!in_array('fecha_salida',TRIP_HEADERS),'Plantilla de una sola fecha');
 check11($trip['request_date']==='2026-09-28'&&$trip['departure_at']==='2026-09-28 09:00:00'&&$trip['arrival_at']==='2026-09-28 10:00:00','La fecha única se aplica a solicitud, salida y llegada');
 evaluateImport($batch,true);$saved=row('SELECT * FROM trips WHERE reference_key=?',[$key]);
 check11($saved!==null&&$saved['km_end']==='125.00','Importación simplificada guarda valores correctos');
 $sheets=dataSheets(null,$vid);check11($sheets[0]['rows'][0]===TRIP_HEADERS&&count($sheets[0]['rows'][1])===9,'Exportación normal usa las nueve columnas');
 Xlsx::write($tmp,$sheets);$round=parseImport($tmp);check11(evaluateImport($round)['skipped']===1,'Exportación simplificada se reimporta sin duplicar');
 $legacy=[$key,$vehicle['economic_number'],'2026-09-28','2026-09-28','09:00','2026-09-28','10:00',100,125,'Ana','Entrega'];
 Xlsx::write($tmp,[['name'=>'Bitacoras','rows'=>[LEGACY_TRIP_HEADERS,$legacy]]]);check11(evaluateImport(parseImport($tmp))['skipped']===1,'Plantilla anterior sigue siendo compatible');
 $bad=$simple;$bad[3]='15:00';$bad[4]='09:00';Xlsx::write($tmp,[['name'=>'Bitacoras','rows'=>[TRIP_HEADERS,$bad]]]);expectFailure(fn()=>parseImport($tmp),'Llegada anterior a salida rechazada en plantilla de un día');
 // Exceptional old data must not be silently flattened when exporting.
 execute('UPDATE trips SET request_date=?,arrival_at=? WHERE id=?',['2026-09-27','2026-09-29 10:00:00',$saved['id']]);
 $sheets=dataSheets(null,$vid);check11($sheets[0]['rows'][0]===LEGACY_TRIP_HEADERS,'Datos antiguos con fechas diferentes conservan columnas necesarias');
 Xlsx::write($tmp,$sheets);check11(evaluateImport(parseImport($tmp))['skipped']===1,'Fechas antiguas se reimportan sin pérdida');
 $snapshot=accountingSnapshot();$summary=monthSummary($vehicle,'2026-09');
 $input=['vehicle_id'=>$vid,'scheduled_date'=>'2026-09-28','scheduled_time'=>'09:00'];$rid=saveReservation($input);
 check11(accountingSnapshot()===$snapshot&&monthSummary($vehicle,'2026-09')===$summary,'Apartar incluso sobre una bitácora no modifica registros ni totales');
 expectFailure(fn()=>saveReservation($input),'Apartado duplicado exacto rechazado');
 $input['scheduled_date']='2026-09-29';$input['scheduled_time']='12:30';$input['version']=1;saveReservation($input,$rid);
 check11(need('reservations',$rid)['scheduled_at']==='2026-09-29 12:30:00','Mover apartado conserva su ID');
 expectFailure(fn()=>saveReservation($input,$rid),'Edición con versión obsoleta rechazada');
 expectFailure(fn()=>cancelReservation($rid,1),'Cancelación con versión obsoleta rechazada');
 cancelReservation($rid,2);check11(row('SELECT id FROM reservations WHERE id=?',[$rid])===null,'Cancelar quita el apartado');
 check11(accountingSnapshot()===$snapshot,'Crear, mover y cancelar no altera ninguna tabla operativa');
 expectFailure(fn()=>saveReservation(['vehicle_id'=>$vid,'scheduled_date'=>'2026-02-31','scheduled_time'=>'09:00']),'Fecha programada inválida rechazada');
 expectFailure(fn()=>saveReservation(['vehicle_id'=>$vid,'scheduled_date'=>'2026-09-29','scheduled_time'=>'25:00']),'Hora programada inválida rechazada');
 echo "Pruebas 1.1 completas; cambios revertidos.\n";
} finally {$pdo->rollBack();unlink($tmp);}
