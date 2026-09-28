<?php
declare(strict_types=1);
/** Informational appointments only: never writes trips, fuel, availability or suggestions. */
function normalizeReservation(array $input): array {
    $vehicle = filter_var($input['vehicle_id'] ?? 0, FILTER_VALIDATE_INT);
    if (!$vehicle || $vehicle < 1) throw new RuntimeException('Selecciona un vehículo válido.');
    $date = dateValue($input['scheduled_date'] ?? '', 'Fecha programada');
    $time = trim((string)($input['scheduled_time'] ?? ''));
    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $time)) {
        throw new RuntimeException('La hora programada debe usar el formato HH:MM de 24 horas.');
    }
    return ['vehicle_id' => $vehicle, 'scheduled_at' => $date.' '.$time.':00'];
}
function saveReservation(array $input, int $id = 0): int {
    $data = normalizeReservation($input);
    need('vehicles', $data['vehicle_id']);
    try {
        if ($id) {
            $count = execute('UPDATE reservations SET vehicle_id=?,scheduled_at=?,version=version+1 WHERE id=? AND version=?',
                [$data['vehicle_id'], $data['scheduled_at'], $id, (int)($input['version'] ?? 0)]);
            if ($count !== 1) throw new RuntimeException('El apartado cambió o fue cancelado. Recarga la página antes de editarlo.');
        } else {
            execute('INSERT INTO reservations(vehicle_id,scheduled_at) VALUES (?,?)', array_values($data));
            $id = (int)db()->lastInsertId();
        }
    } catch (PDOException $error) {
        if ((int)($error->errorInfo[1] ?? 0) === 1062) {
            throw new RuntimeException('Este vehículo ya tiene un apartado para esa fecha y hora. Edita el apartado existente o elige otra hora.');
        }
        throw $error;
    }
    return $id;
}
function cancelReservation(int $id, int $version): void {
    if (execute('DELETE FROM reservations WHERE id=? AND version=?', [$id, $version]) !== 1) {
        throw new RuntimeException('El apartado cambió o ya fue cancelado. Recarga la página.');
    }
}
