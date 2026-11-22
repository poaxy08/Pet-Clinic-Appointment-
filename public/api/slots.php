<?php
// GET ?vet_id=..&date=YYYY-MM-DD  ->  live state of every time slot.
require __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');

$u = current_user();
if (!$u) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Please sign in again.']);
    exit;
}

$vetId = oid($_GET['vet_id'] ?? null);
$vet = $vetId ? col('vets')->findOne(['_id' => $vetId, 'active' => true]) : null;
$date = (string)($_GET['date'] ?? '');

if (!$vet || !valid_date($date)) {
    echo json_encode(['ok' => false, 'message' => 'Choose a veterinarian and a date.', 'slots' => []]);
    exit;
}
if ($date < date('Y-m-d')) {
    echo json_encode(['ok' => false, 'message' => 'That date has already passed.', 'slots' => []]);
    exit;
}

$works = vet_works($vet, $date);
$slots = $works ? slot_states($vet, $date, $u['_id']) : [];

$expires = null;
foreach ($slots as $s) {
    if ($s['state'] === 'mine') {
        $h = col('slot_holds')->findOne(['user_id' => $u['_id'], 'expires_at' => ['$gt' => utc_now()]]);
        if ($h) $expires = max(0, $h['expires_at']->toDateTime()->getTimestamp() - time());
        break;
    }
}

echo json_encode([
    'ok' => true,
    'works' => $works,
    'message' => $works ? '' : 'This veterinarian does not take appointments on that day.',
    'slots' => $slots,
    'hold_expires_in' => $expires,
]);
