<?php
// POST action=hold|release  -> first person to choose a slot gets it; everyone else sees "Not available".
require __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');

$u = current_user();
if (!$u || $u['role'] !== 'user') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Please sign in again.']);
    exit;
}
if (!is_post()) {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'POST required.']);
    exit;
}
csrf_verify();

$action = $_POST['action'] ?? 'hold';
if ($action === 'release') {
    col('slot_holds')->deleteMany(['user_id' => $u['_id']]);
    echo json_encode(['ok' => true]);
    exit;
}

$vetId = oid($_POST['vet_id'] ?? null);
$vet = $vetId ? col('vets')->findOne(['_id' => $vetId, 'active' => true]) : null;
$date = (string)($_POST['date'] ?? '');
$time = (string)($_POST['time'] ?? '');

if (!$vet || !valid_date($date) || !vet_works($vet, $date) || !in_array($time, slot_times($vet), true)
    || strtotime("$date $time") < time() + LEAD_MINUTES * 60) {
    echo json_encode(['ok' => false, 'message' => 'That time cannot be booked. Please pick another.']);
    exit;
}

$res = hold_slot(slot_key((string)$vet['_id'], $date, $time), $u['_id']);
if ($res !== 'ok') {
    echo json_encode(['ok' => false, 'message' => NA_MESSAGE]);
    exit;
}
echo json_encode(['ok' => true, 'expires_in' => HOLD_SECONDS]);
