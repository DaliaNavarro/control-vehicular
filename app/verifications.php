<?php
declare(strict_types=1);

/** Calendar supplied by the fleet administrator, repeated every calendar year. */
function verificationSchedule(array $vehicle, int $year): ?array {
    $permit = !empty($vehicle['verification_permit']);
    preg_match_all('/[0-9]/', (string)$vehicle['plates'], $digits);
    if (!$permit && !$digits[0]) return null;
    $digit = $permit ? null : (int)end($digits[0]);
    $startMonth = $permit ? 5 : match ($digit) { 5,6=>1, 7,8=>2, 3,4=>3, 1,2=>4, 9,0=>5 };
    $months = ['', 'enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    $periods = [];
    foreach ([1=>$startMonth, 2=>$startMonth+6] as $number=>$month) {
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = (new DateTimeImmutable($start))->modify('+2 months -1 day')->format('Y-m-d');
        $periods[$number] = ['number'=>$number,'start'=>$start,'end'=>$end,'label'=>ucfirst($months[$month]).'–'.$months[$month+1]];
    }
    return ['digit'=>$digit,'permit'=>$permit,'periods'=>$periods];
}

function verificationPlateKey(array $vehicle): string {
    return strtoupper(preg_replace('/[\s-]+/u', '', (string)$vehicle['plates'])).'|'.(int)!empty($vehicle['verification_permit']);
}

function verificationVehicleState(array $vehicle, array $records, string $today): array {
    $year = (int)substr($today,0,4);
    $schedule = verificationSchedule($vehicle,$year);
    $key = verificationPlateKey($vehicle);
    $result = ['vehicle'=>$vehicle,'year'=>$year,'plate_key'=>$key,'schedule'=>$schedule,'periods'=>[]];
    if (!$schedule) return $result;
    foreach ($schedule['periods'] as $number=>$period) {
        $record = null;
        foreach ($records as $r) {
            if ((int)$r['vehicle_id']===(int)$vehicle['id'] && (int)$r['verification_year']===$year && (int)$r['period']===$number && $r['plate_key']===$key) { $record=$r; break; }
        }
        $completed = !empty($record['completed_at']);
        $active = $today >= $period['start'] && $today <= $period['end'];
        $period += ['completed'=>$completed,'completed_at'=>$record['completed_at']??null,'version'=>(int)($record['version']??0),'active'=>$active,'due'=>$active&&!$completed,'future'=>$today<$period['start']];
        $period['status'] = $completed ? 'Atendido' : ($active ? 'Por verificar' : ($period['future'] ? 'Próximo' : 'Sin marcar'));
        $result['periods'][$number]=$period;
    }
    return $result;
}

function verificationSnapshot(?string $today=null): array {
    $today ??= date('Y-m-d');
    $year=(int)substr($today,0,4);
    $records=rows('SELECT * FROM vehicle_verifications WHERE verification_year=?',[$year]);
    $vehicles=[];$due=[];
    foreach (rows('SELECT * FROM vehicles ORDER BY economic_number') as $v) {
        $state=verificationVehicleState($v,$records,$today);
        $vehicles[(int)$v['id']]=$state;
        foreach ($state['periods'] as $p) if ($p['due']) $due[]=['vehicle'=>$v,'period'=>$p];
    }
    return ['date'=>$today,'year'=>$year,'vehicles'=>$vehicles,'due'=>$due];
}

function saveVerification(array $input, ?string $today=null): void {
    $today ??= date('Y-m-d');
    $vid=filter_var($input['vehicle_id']??null,FILTER_VALIDATE_INT);
    $year=filter_var($input['verification_year']??null,FILTER_VALIDATE_INT);
    $period=filter_var($input['period']??null,FILTER_VALIDATE_INT);
    $version=filter_var($input['version']??null,FILTER_VALIDATE_INT);
    if (!$vid || $vid<1 || $year!==(int)substr($today,0,4) || !in_array($period,[1,2],true) || $version===false || $version<0) throw new RuntimeException('El año o periodo ya no es válido. Recarga el panel.');
    if (isset($input['completed']) && $input['completed']!=='1') throw new RuntimeException('Estado de verificación inválido.');
    $completed=isset($input['completed']);
    db()->beginTransaction();
    try {
        $vehicle=row('SELECT * FROM vehicles WHERE id=? FOR UPDATE',[$vid]);
        if (!$vehicle) throw new RuntimeException('El vehículo ya no existe.');
        $key=verificationPlateKey($vehicle);
        if (!hash_equals($key,(string)($input['plate_key']??''))) throw new RuntimeException('Las placas o el permiso cambiaron. Recarga antes de marcar el cumplimiento.');
        $schedule=verificationSchedule($vehicle,$year);
        if (!$schedule) throw new RuntimeException('No se encontró un dígito en las placas. Revisa las placas o indica que circula con permiso en Vehículos.');
        if ($today<$schedule['periods'][$period]['start']) throw new RuntimeException('Este periodo de verificación todavía no inicia.');
        $old=row('SELECT * FROM vehicle_verifications WHERE vehicle_id=? AND verification_year=? AND period=? AND plate_key=? FOR UPDATE',[$vid,$year,$period,$key]);
        if ((int)($old['version']??0)!==$version) throw new RuntimeException('Otra persona actualizó esta casilla. Recarga para ver el estado actual.');
        $markedAt=$completed?date('Y-m-d H:i:s'):null;
        $actor=substr(getenv('APP_USER')?:'admin',0,150);
        if ($old) execute('UPDATE vehicle_verifications SET completed_at=?,recorded_by=?,version=version+1 WHERE id=?',[$markedAt,$actor,$old['id']]);
        else execute('INSERT INTO vehicle_verifications(vehicle_id,verification_year,period,plate_key,plates,by_permit,completed_at,recorded_by) VALUES (?,?,?,?,?,?,?,?)',[$vid,$year,$period,$key,$vehicle['plates'],(int)!empty($vehicle['verification_permit']),$markedAt,$actor]);
        db()->commit();
    } catch (Throwable $e) { if(db()->inTransaction())db()->rollBack(); throw $e; }
}
