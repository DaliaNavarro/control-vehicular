<?php
/** All writes pass through CSRF, parameterized SQL, validation and transactions. */
function handleAction(): void {
 global $warnings,$warningPayload,$importPreview,$error;
 checkCsrf();$action=$_POST['action']??'';$id=(int)($_POST['id']??0);
 if($action==='logout'){$_SESSION=[];session_destroy();redirect('login');}
 if($action==='reservation_save'){
  saveReservation($_POST,$id);flash($id?'Apartado actualizado.':'Vehículo apartado.');redirect('reservations');
 }
 if($action==='reservation_cancel'){
  cancelReservation($id,(int)($_POST['version']??0));flash('Apartado cancelado.');redirect('reservations');
 }
 if($action==='vehicle_save'){
  $v=['brand'=>textValue($_POST['brand']??'','Marca',80),'model'=>textValue($_POST['model']??'','Modelo',100),'plates'=>strtoupper(textValue($_POST['plates']??'','Placas',30)),'economic_number'=>textValue($_POST['economic_number']??'','No. económico',30),'responsible'=>textValue($_POST['responsible']??'','Responsable',150),'baseline_date'=>dateValue($_POST['baseline_date']??'','Fecha de referencia'),'baseline_km'=>decimalValue($_POST['baseline_km']??'','Km de referencia')];
  db()->beginTransaction();if($id){$old=row('SELECT * FROM vehicles WHERE id=? FOR UPDATE',[$id]);if(!$old)throw new RuntimeException('El vehículo ya no existe.');if(!hash_equals(hash('sha256',json_encode($old)),(string)($_POST['vehicle_version']??'')))throw new RuntimeException('El vehículo cambió desde que abriste el formulario. Recarga y revisa los datos.');execute('UPDATE vehicles SET brand=?,model=?,plates=?,economic_number=?,responsible=?,baseline_date=?,baseline_km=? WHERE id=?',[...array_values($v),$id]);}else{execute('INSERT INTO vehicles(brand,model,plates,economic_number,responsible,baseline_date,baseline_km) VALUES (?,?,?,?,?,?,?)',array_values($v));$id=(int)db()->lastInsertId();}db()->commit();flash('Vehículo guardado.');redirect('vehicle',['id'=>$id]);
 }
 if($action==='vehicle_delete'){
  db()->beginTransaction();row('SELECT id FROM vehicles WHERE id=? FOR UPDATE',[$id]);$counts=row('SELECT (SELECT COUNT(*) FROM trips WHERE vehicle_id=?) trips,(SELECT COUNT(*) FROM inactive_periods WHERE vehicle_id=?) periods',[$id,$id]);
  if($counts['trips']||$counts['periods'])throw new RuntimeException('Este vehículo tiene historial. Para conservarlo, registra un periodo de inactividad. Solo se eliminan vehículos sin bitácoras ni periodos.');execute('DELETE FROM vehicles WHERE id=?',[$id]);db()->commit();flash('Vehículo eliminado.');redirect('vehicles');
 }
 if($action==='trip_save'){
  $t=normalizeTrip($_POST);$initial=null;if(!empty($_POST['add_fuel']))$initial=normalizeFuel($_POST);
  $before=$id?need('trips',$id):null;$lockIds=array_unique([$t['vehicle_id'],(int)($before['vehicle_id']??$t['vehicle_id'])]);sort($lockIds);
  db()->beginTransaction();foreach($lockIds as $lockId)row('SELECT id FROM vehicles WHERE id=? FOR UPDATE',[$lockId]);$vehicle=need('vehicles',$t['vehicle_id']);
  if($id){$old=need('trips',$id);if((int)$old['version']!==(int)($_POST['version']??0)||(int)$old['vehicle_id']!==(int)$before['vehicle_id'])throw new RuntimeException('Otra persona modificó esta bitácora. Recarga para revisar la versión actual.');}
  $others=rows('SELECT * FROM trips WHERE vehicle_id=? AND id<>? ORDER BY departure_at,id',[$t['vehicle_id'],$id]);$periods=rows('SELECT * FROM inactive_periods WHERE vehicle_id=?',[$t['vehicle_id']]);
  $warnings=tripWarnings($t,$others,$vehicle,$periods);if($id&&(int)$old['vehicle_id']!==$t['vehicle_id'])$warnings[]='Cambiar el vehículo de la bitácora #'.$id.' reasignará también sus cargas de gasolina al vehículo '.$vehicle['economic_number'].'.';if($initial)$warnings=[...$warnings,...fuelWarnings($initial,$t+['id'=>$id?:'nuevo'])];$warningPayload=[$t,$initial,$id];
  if(!warningsConfirmed($warningPayload,$warnings)){db()->rollBack();return;}
  if($id){execute('UPDATE trips SET vehicle_id=?,request_date=?,departure_at=?,arrival_at=?,km_start=?,km_end=?,driver=?,purpose=?,version=version+1 WHERE id=?',[...array_values($t),$id]);remember($t);}else $id=createTrip($t,keygen());
  if($initial)createFuel($id,$initial,keygen());db()->commit();flash('Bitácora #'.$id.' guardada.');redirect('trip',['id'=>$id]);
 }
 if($action==='trip_delete'){
  $t=need('trips',$id);db()->beginTransaction();row('SELECT id FROM vehicles WHERE id=? FOR UPDATE',[$t['vehicle_id']]);$t=need('trips',$id);if((int)$t['version']!==(int)($_POST['version']??0))throw new RuntimeException('La bitácora cambió. Recarga antes de eliminar.');execute('DELETE FROM trips WHERE id=?',[$id]);db()->commit();flash('Bitácora #'.$id.' y sus cargas eliminadas.');redirect('dashboard');
 }
 if($action==='fuel_save'||$action==='fuel_delete'){
  $tripId=(int)($_POST['trip_id']??0);$t=need('trips',$tripId);$lockedVehicle=(int)$t['vehicle_id'];db()->beginTransaction();row('SELECT id FROM vehicles WHERE id=? FOR UPDATE',[$lockedVehicle]);$t=need('trips',$tripId);if((int)$t['vehicle_id']!==$lockedVehicle)throw new RuntimeException('La bitácora cambió de vehículo. Recarga para revisar sus cargas.');
  if($id){$old=need('fuel_loads',$id);if((int)$old['trip_id']!==$tripId||(int)$old['version']!==(int)($_POST['version']??0))throw new RuntimeException('La carga cambió o no corresponde a esta bitácora. Recarga la página.');}
  if($action==='fuel_delete')execute('DELETE FROM fuel_loads WHERE id=?',[$id]);
  else{$f=normalizeFuel($_POST);$warnings=fuelWarnings($f,$t);$warningPayload=[$f,$tripId,$id];if(!warningsConfirmed($warningPayload,$warnings)){db()->rollBack();return;}
   if($id)execute('UPDATE fuel_loads SET fuel_date=?,liters=?,amount=?,card_last4=?,version=version+1 WHERE id=?',[...array_values($f),$id]);else createFuel($tripId,$f,keygen());
  }execute('UPDATE trips SET version=version+1 WHERE id=?',[$tripId]);db()->commit();flash('Carga de gasolina '.($action==='fuel_delete'?'eliminada.':'guardada.'));redirect('trip',['id'=>$tripId]);
 }
 if($action==='period_save'||$action==='period_delete'){
  $vid=(int)($_POST['vehicle_id']??0);db()->beginTransaction();$vehicle=row('SELECT * FROM vehicles WHERE id=? FOR UPDATE',[$vid]);if(!$vehicle)throw new RuntimeException('Vehículo no encontrado.');
  if($id){$old=need('inactive_periods',$id);if((int)$old['vehicle_id']!==$vid||(int)$old['version']!==(int)($_POST['version']??0))throw new RuntimeException('El periodo cambió. Recarga la página.');}
  if($action==='period_delete')execute('DELETE FROM inactive_periods WHERE id=?',[$id]);
  else{$a=dateValue($_POST['start_date']??'','Inicio de inactividad');$b=trim($_POST['end_date']??'')!==''?dateValue($_POST['end_date'],'Fin de inactividad'):null;if($b!==null&&$b<$a)throw new RuntimeException('La fecha final no puede ser anterior al inicio.');$reason=$_POST['reason']??'';if(!in_array($reason,['Descompuesto','En servicio','No disponible'],true))throw new RuntimeException('Estado inválido.');$notes=trim($_POST['notes']??'');if(strlen($notes)>2000)throw new RuntimeException('Notas demasiado largas.');
   $over=row('SELECT id FROM inactive_periods WHERE vehicle_id=? AND id<>? AND start_date<=? AND (end_date IS NULL OR end_date>=?)',[$vid,$id,$b??'9999-12-31',$a]);if($over)throw new RuntimeException('Se cruza con el periodo #'.$over['id'].'. Edita el periodo existente.');
   $conflicts=rows('SELECT id FROM trips WHERE vehicle_id=? AND departure_at<? AND arrival_at>=? ORDER BY id',[$vid,$b?date('Y-m-d',strtotime($b.' +1 day')):'9999-12-31',$a]);$warnings=$conflicts?['El periodo incluye bitácoras ya registradas: '.implode(', ',array_map(fn($t)=>'#'.$t['id'],$conflicts)).'.']:[];$warningPayload=[$vid,$id,$a,$b,$reason,$notes];if(!warningsConfirmed($warningPayload,$warnings)){db()->rollBack();return;}
   if($id)execute('UPDATE inactive_periods SET start_date=?,end_date=?,reason=?,notes=?,version=version+1 WHERE id=?',[$a,$b,$reason,$notes,$id]);else execute('INSERT INTO inactive_periods(vehicle_id,start_date,end_date,reason,notes) VALUES (?,?,?,?,?)',[$vid,$a,$b,$reason,$notes]);
  }db()->commit();flash('Periodo '.($action==='period_delete'?'eliminado.':'guardado. Las fechas inicial y final son inclusivas.'));redirect('vehicle',['id'=>$vid]);
 }
 if($action==='import_preview'){
  $file=$_FILES['xlsx']??null;if(!$file||$file['error']!==UPLOAD_ERR_OK)throw new RuntimeException('No se recibió el archivo completo. Máximo 8 MB.');if($file['size']>8*1024*1024||strtolower(pathinfo($file['name'],PATHINFO_EXTENSION))!=='xlsx')throw new RuntimeException('Selecciona un XLSX de hasta 8 MB.');
  $batch=parseImport($file['tmp_name']);$importPreview=evaluateImport($batch);$_SESSION['import_batch']=$batch;$_SESSION['import_review']=$importPreview;return;
 }
 if($action==='import_commit'){
  $batch=$_SESSION['import_batch']??null;if(!$batch)throw new RuntimeException('Primero revisa un archivo.');db()->beginTransaction();rows('SELECT id FROM vehicles ORDER BY id FOR UPDATE');$now=evaluateImport($batch);
  if($now!==($_SESSION['import_review']??null)){db()->rollBack();$_SESSION['import_review']=$now;$importPreview=$now;$error='Los datos cambiaron desde la revisión. Revisa de nuevo y confirma.';return;}
  if($now['warnings']&&empty($_POST['accept_import']))throw new RuntimeException('Confirma que revisaste las advertencias.');$result=evaluateImport($batch,true);db()->commit();unset($_SESSION['import_batch'],$_SESSION['import_review']);flash('Importación completa: '.$result['trips'].' bitácoras, '.$result['fuel'].' cargas; '.$result['skipped'].' registros idénticos omitidos.');redirect('import');
 }
 throw new RuntimeException('Acción no encontrada.');
}
