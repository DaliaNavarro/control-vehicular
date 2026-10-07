<?php
declare(strict_types=1);
require_once __DIR__.'/views.php';

function publicUrl(string $page='trip'): string { return 'public.php?'.http_build_query(['page'=>$page]); }
function publicRedirect(string $page): never { header('Location: '.publicUrl($page));exit; }
function publicFormKey(): string {
    $now=time();
    foreach($_SESSION['public_forms']??[] as $key=>$created) if($created<$now-86400) unset($_SESSION['public_forms'][$key]);
    $key=(string)($_POST['submission_key']??'');
    if(!isset($_SESSION['public_forms'][$key])) {
        $key=keygen();$_SESSION['public_forms'][$key]=$now;
        if(count($_SESSION['public_forms'])>30) array_shift($_SESSION['public_forms']);
    }
    return $key;
}
/** Public creation only; no supplied ID can turn this operation into an edit. */
function savePublicTrip(array $input): ?int {
    global $warnings,$warningPayload;
    if(!empty($input['id'])) throw new RuntimeException('La vista pública solo permite registrar bitácoras nuevas.');
    $key=(string)($input['submission_key']??'');
    if(!preg_match('/^[a-f0-9]{32}$/D',$key)||!isset($_SESSION['public_forms'][$key])||$_SESSION['public_forms'][$key]<time()-86400)
        throw new RuntimeException('El formulario venció. Recarga la página e intenta de nuevo.');
    $input['driver'] = mb_strtoupper(
    trim((string)($input['driver'] ?? '')),
    'UTF-8'
);

$input['purpose'] = mb_strtoupper(
    trim((string)($input['purpose'] ?? '')),
    'UTF-8'
);

$t = normalizeTripForm($input);
$fuel = !empty($input['add_fuel']) ? normalizeFuel($input) : null;
    db()->beginTransaction();
    try {
        $vehicle=row('SELECT * FROM vehicles WHERE id=? FOR UPDATE',[$t['vehicle_id']]);
        if(!$vehicle) throw new RuntimeException('Selecciona un vehículo existente.');
        $existing=row('SELECT * FROM trips WHERE reference_key=?',[$key]);
        if($existing) {
            foreach($t as $field=>$value) if((string)$existing[$field] !== (string)$value)
                throw new RuntimeException('Este formulario ya fue guardado. Abre un registro nuevo.');
            $savedFuel=row('SELECT * FROM fuel_loads WHERE trip_id=? AND reference_key=?',[$existing['id'],$key]);
            if((bool)$fuel !== (bool)$savedFuel) throw new RuntimeException('Este formulario ya fue guardado. Abre un registro nuevo.');
            if($fuel) foreach($fuel as $field=>$value) if((string)$savedFuel[$field] !== (string)$value)
                throw new RuntimeException('Este formulario ya fue guardado. Abre un registro nuevo.');
            db()->commit();return (int)$existing['id'];
        }
        $others=rows('SELECT id,departure_at,arrival_at,km_start,km_end FROM trips WHERE vehicle_id=? ORDER BY departure_at,id',[$t['vehicle_id']]);
        $periods=rows('SELECT * FROM inactive_periods WHERE vehicle_id=?',[$t['vehicle_id']]);
        $warnings=tripWarnings($t,$others,$vehicle,$periods,false);
        if($fuel) $warnings=[...$warnings,...fuelWarnings($fuel,$t)];
        $warningPayload=['public',$key,$t,$fuel];
        if(!warningsConfirmed($warningPayload,$warnings)){db()->rollBack();return null;}
        $id=createTrip($t,$key);
        if($fuel) createFuel($id,$fuel,$key);
        db()->commit();return $id;
    } catch(Throwable $error) { if(db()->inTransaction())db()->rollBack();throw $error; }
}
function handlePublicAction(string $page): void {
    checkCsrf();
    $action=(string)($_POST['action']??'');
    if($page==='trip'&&$action==='public_trip_save') {
        $id=savePublicTrip($_POST);
        if($id!==null){$_SESSION['public_flash']='Bitácora #'.$id.' registrada correctamente. Conserva este folio para cualquier aclaración.';publicRedirect('trip');}
        return;
    }
    if($page==='reservations'&&$action==='public_reservation_save') {
        if(!empty($_POST['id'])) throw new RuntimeException('Desde esta vista solo puedes crear apartados nuevos.');
        pruneReservations();saveReservation($_POST);
        $_SESSION['public_flash']='Vehículo apartado. La programación ya es visible para todos.';publicRedirect('reservations');
    }
    http_response_code(403);throw new RuntimeException('Esta acción no está disponible en la vista pública.');
}
function publicHeader(string $page): void {
    ?><!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $page==='reservations'?'Programación':'Registrar bitácora' ?> · Control vehicular</title><link rel="icon" href="assets/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="assets/app.css?v=1.3"><script src="assets/app.js?v=1.3" defer></script></head><body><div class="public-shell"><header class="public-header"><a class="brand" href="public.php"><span class="brand-mark">CV</span><span>Control vehicular</span></a><nav aria-label="Opciones públicas"><a class="<?=$page==='trip'?'active':''?>" href="<?=e(publicUrl())?>">Registrar bitácora</a><a class="<?=$page==='reservations'?'active':''?>" href="<?=e(publicUrl('reservations'))?>">Programación</a></nav></header><main id="main"><?php
}
function publicFooter(): void {echo '</main><footer class="public-footer"><span>Control vehicular</span><a href="index.php?page=login">Acceso de administración</a></footer></div></body></html>';}
function publicTripView(): void {
    $v=$_SERVER['REQUEST_METHOD']==='POST'?$_POST:['request_date'=>date('Y-m-d')];
    pageTitle('Registrar bitácora','Selecciona el vehículo que utilizaste y captura los datos de tu recorrido.');
    echo '<form method="post" class="panel" id="trip-form" action="'.e(publicUrl()).'">'.csrf();
    hidden('action','public_trip_save');hidden('submission_key',publicFormKey());warningBox();
    echo '<div class="form-grid">';vehicleSelect($v['vehicle_id']??0);inputField('request_date','Fecha de solicitud',$v['request_date']??'','date','required');
    echo '</div><p class="help">No olvides verificar tus datos.</p><h2 class="form-section">Recorrido</h2><div class="form-grid">';
    inputField('departure_time','Hora de salida',$v['departure_time']??'','time','required');inputField('arrival_time','Hora de llegada',$v['arrival_time']??'','time','required');
    inputField('km_start','Km iniciales',$v['km_start']??'','number','required min="0" step="0.01"');inputField('km_end','Km finales',$v['km_end']??'','number','required min="0" step="0.01"');
    echo '<div class="field"><span>Km recorridos</span><output id="km-distance">—</output></div>';
    inputField('driver','Nombre del conductor',$v['driver']??'','text','required maxlength="150" autocomplete="name"');
    echo '</div><label class="field public-purpose"><span>Comisión</span><textarea name="purpose" rows="3" maxlength="500" required placeholder="Describe brevemente el motivo del recorrido">'.e($v['purpose']??'').'</textarea></label>';
    echo '<h2 class="form-section">Gasolina <span class="subtle">opcional</span></h2><label class="check"><input type="checkbox" name="add_fuel" id="add-fuel" value="1" '.(!empty($v['add_fuel'])?'checked':'').'> Se cargó gasolina durante este recorrido</label><div id="initial-fuel" class="form-grid">';fuelFields($v,false);echo '</div>';
    echo '<div class="form-actions"><button class="button">'.(!empty($GLOBALS['warnings'])?'Confirmar y guardar':'Revisar y guardar').'</button></div></form>';
}
function publicScheduleHtml(): string {
    pruneReservations();
    $all=rows('SELECT r.id,r.scheduled_at,v.economic_number,v.brand,v.model,v.plates FROM reservations r JOIN vehicles v ON v.id=r.vehicle_id WHERE r.scheduled_at>=? ORDER BY r.scheduled_at,r.id',[date('Y-m-d H:i:s')]);
    $html='<div class="section-heading"><h2>Vehículos apartados</h2><span>'.count($all).' apartado(s)</span></div><div class="table-wrap"><table class="schedule-table"><thead><tr><th>Vehículo</th><th>Fecha programada</th><th>Hora</th></tr></thead><tbody>';
    foreach($all as $r) $html.='<tr><td><strong>'.e($r['economic_number'].' · '.$r['brand'].' '.$r['model']).'</strong><small>'.e($r['plates']).'</small></td><td>'.e(date('d/m/Y',strtotime($r['scheduled_at']))).'</td><td>'.e(substr($r['scheduled_at'],11,5)).'</td></tr>';
    if(!$all)$html.='<tr><td colspan="3" class="empty">No hay apartados próximos.</td></tr>';
    return $html.'</tbody></table></div>';
}
function publicReservationsView(): void {
    pageTitle('Programación','Reserva una fecha y hora.');
    echo '<section class="panel" id="schedule-live" data-schedule-source="public">'.publicScheduleHtml().'</section><p class="help">Los apartados vencidos se eliminan una vez que pasé el tiempo registrado.</p>';
    $v=$_SERVER['REQUEST_METHOD']==='POST'?$_POST:[];
    echo '<form method="post" class="panel" action="'.e(publicUrl('reservations')).'"><h2>Apartar vehículo</h2>'.csrf();hidden('action','public_reservation_save');
    echo '<div class="form-grid">';vehicleSelect($v['vehicle_id']??0);inputField('scheduled_date','Fecha programada',$v['scheduled_date']??date('Y-m-d'),'date','required min="'.e(date('Y-m-d')).'"');inputField('scheduled_time','Hora programada',$v['scheduled_time']??'','time','required');
    echo '</div><p class="help">Para mover o cancelar un apartado antes de su vencimiento, comunícate con administración.</p><button class="button">Guardar apartado</button></form>';
}
