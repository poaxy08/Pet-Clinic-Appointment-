<?php
require __DIR__ . '/../../app/bootstrap.php';
$u = require_role('user');
$uid = $u['_id'];

$pets = all('pets', ['owner_id' => $uid], ['sort' => ['name' => 1]]);
$services = all('services', ['active' => true], ['sort' => ['name' => 1]]);
$vets = all('vets', ['active' => true], ['sort' => ['name' => 1]]);

$old = [
    'pet_id' => (string)($_GET['pet'] ?? ''),
    'service_id' => (string)($_GET['service'] ?? ''),
    'vet_id' => (string)($_GET['vet'] ?? ''),
    'date' => '',
    'notes' => '',
];

if (is_post()) {
    csrf_verify();
    foreach (['pet_id', 'service_id', 'vet_id', 'date', 'notes'] as $k) $old[$k] = trim((string)($_POST[$k] ?? ''));
    $time = trim((string)($_POST['time'] ?? ''));
    $errors = [];

    $petId = oid($old['pet_id']);
    $pet = $petId ? col('pets')->findOne(['_id' => $petId, 'owner_id' => $uid]) : null;
    $svcId = oid($old['service_id']);
    $svc = $svcId ? col('services')->findOne(['_id' => $svcId, 'active' => true]) : null;
    $vetId = oid($old['vet_id']);
    $vet = $vetId ? col('vets')->findOne(['_id' => $vetId, 'active' => true]) : null;

    if (!$pet) $errors[] = 'Please choose one of your registered pets.';
    if (!$svc) $errors[] = 'Please choose a service.';
    if (!$vet) $errors[] = 'Please choose a veterinarian.';
    if ($vet && $svc && !vet_offers($vet, $svc['_id'])) {
        $errors[] = $vet['name'] . ' does not perform ' . $svc['name'] . '. Please choose another veterinarian.';
    } elseif (!valid_date($old['date'])) {
        $errors[] = 'Please choose a valid date.';
    } elseif ($vet) {
        if ($old['date'] > date('Y-m-d', strtotime('+120 days'))) $errors[] = 'Appointments can be booked up to 120 days ahead.';
        elseif (!vet_works($vet, $old['date'])) $errors[] = 'That veterinarian does not take appointments on the selected day.';
        elseif (!in_array($time, slot_times($vet), true)) $errors[] = 'Please choose one of the available times.';
        elseif (strtotime($old['date'] . ' ' . $time) < time() + LEAD_MINUTES * 60) $errors[] = 'Please choose a time at least 1 hour from now.';
    }

    $key = '';
    if (!$errors) {
        $key = slot_key((string)$vet['_id'], $old['date'], $time);
        // Someone else is currently holding / choosing this exact slot?
        $heldByOther = col('slot_holds')->findOne([
            'slot_key' => $key, 'expires_at' => ['$gt' => utc_now()], 'user_id' => ['$ne' => $uid],
        ]);
        if ($heldByOther) $errors[] = NA_MESSAGE;
    }

    if (!$errors) {
        try {
            // The UNIQUE partial index on slot_key makes this atomic: if two people submit
            // at the same moment, MongoDB lets exactly one win and rejects the other.
            col('appointments')->insertOne([
                'owner_id' => $uid, 'pet_id' => $pet['_id'], 'vet_id' => $vet['_id'], 'service_id' => $svc['_id'],
                'service_name' => $svc['name'], 'price' => (float)$svc['price'],
                'date' => $old['date'], 'time' => $time, 'starts_at' => starts_at_utc($old['date'], $time),
                'notes' => $old['notes'], 'status' => 'pending', 'active' => true, 'slot_key' => $key,
                'reminded' => false, 'invoiced' => false, 'created_at' => utc_now(),
            ]);
        } catch (Throwable $e) {
            if (is_dup($e)) $errors[] = NA_MESSAGE;
            else throw $e;
        }
    }

    if (!$errors) {
        col('slot_holds')->deleteMany(['user_id' => $uid]);
        notify($uid, 'Appointment requested', $pet['name'] . ' — ' . $svc['name'] . ' on ' . fdate($old['date']) . ' at ' . ftime($time) . '. We will confirm it shortly.', '/user/appointments.php', 'success');
        notify_admins('New appointment request', $u['name'] . ' booked ' . $svc['name'] . ' for ' . $pet['name'] . ' on ' . fdate($old['date']) . ' at ' . ftime($time) . '.', '/admin/appointments.php', 'info');
        flash('success', 'Your appointment is booked! Status: pending confirmation.');
        redirect('/user/appointments.php');
    }
    foreach ($errors as $er) flash('error', $er);
}

page_start('Book Appointment', 'user');
page_header('Book an appointment', 'Pick a time that works for you. The first person to choose a slot gets it — others will see it as “Not available”.');

if (!$pets) {
    empty_state('🐶', 'Register a pet first', 'You need at least one pet on your account before booking.', '<a class="btn btn-primary" href="' . e(url('/user/pets.php?new=1')) . '">Register a pet</a>');
    page_end('user');
    exit;
}
if (!$services || !$vets) {
    empty_state('🛠️', 'Booking is not available yet', 'The clinic has not set up services or veterinarians yet. Please check back soon.');
    page_end('user');
    exit;
}

$svcName = [];
foreach ($services as $s) $svcName[(string)$s['_id']] = $s['name'];
$vetData = [];
foreach ($vets as $v) $vetData[(string)$v['_id']] = [
    'days' => array_map('intval', (array)($v['days'] ?? [])), 'name' => $v['name'],
    'services' => array_map('strval', (array)($v['service_ids'] ?? [])),   // empty = all services
];
?>
<form method="post" id="bookForm" class="book-grid">
    <?= csrf_field() ?>
    <div class="stack">
        <div class="card">
            <h3><span class="step-n">1</span> Choose your pet</h3>
            <div class="pet-pick">
                <?php foreach ($pets as $i => $p): $sel = $old['pet_id'] === (string)$p['_id'] || ($old['pet_id'] === '' && $i === 0); ?>
                    <label class="choice"><input type="radio" name="pet_id" value="<?= e($p['_id']) ?>" <?= $sel ? 'checked' : '' ?> required>
                        <span><b><?= e(pet_emoji($p['species'])) ?> <?= e($p['name']) ?></b><small><?= e($p['species']) ?><?= !empty($p['breed']) ? ' · ' . e($p['breed']) : '' ?></small></span></label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card">
            <h3><span class="step-n">2</span> Choose a service</h3>
            <div class="svc-pick">
                <?php foreach ($services as $s): ?>
                    <label class="choice"><input type="radio" name="service_id" value="<?= e($s['_id']) ?>" <?= $old['service_id'] === (string)$s['_id'] ? 'checked' : '' ?> required>
                        <span><b><?= e($s['name']) ?></b><small><?= peso($s['price']) ?> · ~<?= (int)($s['duration'] ?? 30) ?> min</small></span></label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card">
            <h3><span class="step-n">3</span> Choose a veterinarian &amp; date</h3>
            <div class="vet-pick">
                <?php foreach ($vets as $v):
                    $vn = array_values(array_filter(array_map(fn($i) => $svcName[(string)$i] ?? null, (array)($v['service_ids'] ?? []))));
                    $days = implode(', ', array_map(fn($d) => DAY_NAMES[(int)$d] ?? '', (array)($v['days'] ?? []))); ?>
                    <label class="choice vet-card" data-vet="<?= e($v['_id']) ?>">
                        <input type="radio" name="vet_id" value="<?= e($v['_id']) ?>" <?= $old['vet_id'] === (string)$v['_id'] ? 'checked' : '' ?> required>
                        <span>
                            <span class="vc-top"><i class="avatar"><?= e(initials($v['name'])) ?></i><b><?= e($v['name']) ?></b></span>
                            <em class="vc-spec"><?= e($v['specialization'] ?: 'General practice') ?></em>
                            <?php if (!empty($v['bio'])): ?><small><?= e($v['bio']) ?></small><?php endif; ?>
                            <small>🗓️ <?= e($days) ?> · <?= e(ftime($v['start'] ?? '09:00')) ?> – <?= e(ftime($v['end'] ?? '17:00')) ?></small>
                            <small>💉 <?= $vn ? e(implode(', ', $vn)) : 'All services' ?></small>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
            <p id="vetNone" class="hint" hidden>No veterinarian is available for this service yet. Please pick another service.</p>
            <div class="field vet-date"><label>Date</label>
                <input type="date" name="date" value="<?= e($old['date']) ?>" min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+120 days')) ?>" required>
            </div>
        </div>

        <div class="card">
            <h3><span class="step-n">4</span> Pick a time</h3>
            <div class="slot-legend"><span><i class="dot free"></i>Available</span><span><i class="dot sel"></i>Your choice</span><span><i class="dot na"></i>Not available</span></div>
            <div id="slotGrid" class="slot-grid"><p class="muted">Select a veterinarian and a date to see open times.</p></div>
            <p id="slotHint" class="hint"></p>
            <input type="hidden" name="time" id="timeInput" value="">
        </div>

        <div class="card">
            <h3><span class="step-n">5</span> Anything we should know?</h3>
            <div class="field"><textarea name="notes" rows="3" placeholder="Symptoms, behaviour, medications, special requests…"><?= e($old['notes']) ?></textarea></div>
            <button class="btn btn-primary btn-lg" id="bookSubmit" disabled>Confirm booking</button>
        </div>
    </div>

    <aside class="card side-note">
        <h3>How booking works</h3>
        <ol class="plain">
            <li>Pick a pet, service, veterinarian and date.</li>
            <li>Tap a time. It is <b>reserved for you for 5 minutes</b> so nobody else can take it while you finish.</li>
            <li>Other clients will see that time as <b>Not available</b>.</li>
            <li>Press <b>Confirm booking</b>. The clinic will confirm your visit and you will get a notification.</li>
        </ol>
        <p class="muted small">Bookings must be made at least 1 hour ahead. Clinic hours: <?= e(cfg('CLINIC_HOURS', '')) ?>.</p>
    </aside>
</form>
<script>window.BOOK = {vets: <?= json_encode($vetData) ?>};</script>
<?php page_end('user', ['book.js']); ?>