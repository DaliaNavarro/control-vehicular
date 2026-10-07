<?php
function verificationNoticeHtml(array $snapshot): string {
    if (!$snapshot['due']) return '';
    $html='<div id="verification-notice" class="notice warning verification-alert"><strong>Verificación vehicular pendiente · '.$snapshot['year'].'</strong><p>Estos vehículos están en periodo de verificación. Marca su casilla cuando hayan sido atendidos.</p><ul>';
    foreach ($snapshot['due'] as $item) {
        $v=$item['vehicle'];$p=$item['period'];
        $html.='<li><a href="'.e(url('dashboard')).'#vehicle-'.(int)$v['id'].'">Vehículo '.e($v['economic_number']).' · '.e($v['plates']).'</a> — '.e($p['label']).' (hasta '.e(date('d/m/Y',strtotime($p['end']))).')</li>';
    }
    return $html.'</ul></div>';
}

function verificationCardHtml(array $state): string {
    $v=$state['vehicle'];
    $html='<h4>Verificaciones '.$state['year'].'</h4>';
    if (!$state['schedule']) return $html.'<p class="verification-hint">No se encontró un dígito en las placas. <a href="'.e(url('vehicle',['id'=>$v['id']])).'">Revisar placas o permiso</a>.</p>';
    $html.='<div class="verification-group">';
    foreach ($state['periods'] as $n=>$p) {
        $id='verification-'.$v['id'].'-'.$n;
        $html.='<form method="post" action="'.e(url('dashboard')).'" class="verification-form '.($p['completed']?'is-complete':($p['due']?'is-due':'')).'">'.csrf();
        foreach (['action'=>'verification_save','vehicle_id'=>$v['id'],'verification_year'=>$state['year'],'period'=>$n,'plate_key'=>$state['plate_key'],'version'=>$p['version']] as $name=>$value) $html.='<input type="hidden" name="'.e($name).'" value="'.e($value).'">';
        $html.='<label for="'.$id.'"><input type="checkbox" id="'.$id.'" name="completed" value="1" '.($p['completed']?'checked ':'').($p['future']?'disabled ':'').' aria-label="Marcar atendido: vehículo '.e($v['economic_number']).', periodo '.$n.' de '.$state['year'].'"><span><strong>'.($n===1?'1.er':'2.º').' periodo</strong><small>'.e($p['label']).'</small><span class="verification-status">'.e($p['status']).'</span></span></label>';
        if (!$p['future']) $html.='<button class="button secondary small verification-save" type="submit">Guardar</button>';
        $html.='</form>';
    }
    return $html.'</div><p class="verification-hint">'.($state['schedule']['permit']?'Calendario de permisos.':'Último dígito: '.$state['schedule']['digit'].'.').' <a href="'.e(url('vehicle',['id'=>$v['id']])).'#verification-history">Ver historial</a></p>';
}

function verificationResponseData(): array {
    $snapshot=verificationSnapshot();$cards=[];
    foreach ($snapshot['vehicles'] as $id=>$state) $cards[(string)$id]=verificationCardHtml($state);
    return ['date'=>$snapshot['date'],'year'=>$snapshot['year'],'notice'=>verificationNoticeHtml($snapshot),'cards'=>$cards];
}

function verificationHistoryView(int $vehicleId): void {
    $history=rows('SELECT * FROM vehicle_verifications WHERE vehicle_id=? ORDER BY verification_year DESC,period DESC,updated_at DESC',[$vehicleId]);
    echo '<section class="panel" id="verification-history"><h2>Historial de verificaciones</h2><p class="help">Cada casilla corresponde a un periodo y año. La fecha indica cuándo se marcó atendido en el sistema.</p><div class="table-wrap"><table><thead><tr><th>Año</th><th>Periodo</th><th>Placas</th><th>Estado</th><th>Marcado atendido</th></tr></thead><tbody>';
    foreach ($history as $r) {$schedule=verificationSchedule(['plates'=>$r['plates'],'verification_permit'=>$r['by_permit']],(int)$r['verification_year']);echo '<tr><td>'.(int)$r['verification_year'].'</td><td>'.e($schedule['periods'][(int)$r['period']]['label']??'').'</td><td>'.e($r['plates']).'</td><td>'.($r['completed_at']?'Atendido':'Sin marcar').'</td><td>'.e($r['completed_at']??'—').'</td></tr>';}
    if (!$history) echo '<tr><td colspan="5" class="empty">Todavía no se ha marcado ningún periodo.</td></tr>';
    echo '</tbody></table></div></section>';
}
