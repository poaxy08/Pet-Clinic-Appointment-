<?php
require __DIR__ . '/../../app/bootstrap.php';
$u = require_role('admin');

if (is_post()) {
    csrf_verify();
    $act = $_POST['action'] ?? '';
    $id = oid($_POST['id'] ?? null);

    if ($act === 'toggle' && $id) {
        $v = col('vets')->findOne(['_id' => $id]);
        if ($v) {
            col('vets')->updateOne(['_id' => $id], ['$set' => ['active' => !($v['active'] ?? true)]]);
            flash('success', $v['name'] . (($v['active'] ?? true) ? ' is now inactive (hidden from booking).' : ' is active again.'));
        }
        redirect('/admin/vets.php');
    }
    if ($act === 'delete' && $id) {
        if (col('appointments')->countDocuments(['vet_id' => $id, 'active' => true, 'status' => ['$in' => ['pending', 'confirmed']]])) {
            flash('error', 'This veterinarian has upcoming appointments. Set them to inactive instead, or cancel the appointments first.');
        } else {
            col('vets')->deleteOne(['_id' => $id]);
            flash('success', 'Veterinarian removed.');
        }
        redirect('/admin/vets.php');
    }
    if ($act === 'save') {
        $days = array_values(array_filter(array_map('intval', (array)($_POST['days'] ?? [])), fn($d) => $d >= 1 && $d <= 7));
        $start = (string)($_POST['start'] ?? '09:00');
        $end = (string)($_POST['end'] ?? '17:00');
        $data = [
            'name' => trim($_POST['name'] ?? ''), 'specialization' => trim($_POST['specialization'] ?? ''),
            'license_no' => trim($_POST['license_no'] ?? ''), 'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''), 'bio' => trim($_POST['bio'] ?? ''),
            'days' => $days, 'start' => $start, 'end' => $end, 'active' => !empty($_POST['active']),
        ];
        if ($data['name'] === '' || !$days || !preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end) || $start >= $end) {
            flash('error', 'Please enter a name, at least one working day, and valid working hours (start before end).');
            redirect('/admin/vets.php?' . ($id ? 'edit=' . $id : 'new=1'));
        }
        if ($id) {
            col('vets')->updateOne(['_id' => $id], ['$set' => $data]);
            flash('success', 'Veterinarian updated.');
        } else {
            col('vets')->insertOne($data + ['created_at' => utc_now()]);
            flash('success', 'Veterinarian added.');
        }
        redirect('/admin/vets.php');
    }
}

$edit = null;
$showForm = isset($_GET['new']);
if ($eid = oid($_GET['edit'] ?? null)) {
    $edit = col('vets')->findOne(['_id' => $eid]);
    $showForm = (bool)$edit;
}

page_start('Veterinarians', 'admin');
if ($showForm):
    $v = $edit ?: ['name' => '', 'specialization' => '', 'license_no' => '', 'email' => '', 'phone' => '', 'bio' => '', 'days' => [1, 2, 3, 4, 5, 6], 'start' => '09:00', 'end' => '17:00', 'active' => true];
    page_header($edit ? 'Edit veterinarian' : 'Add veterinarian', 'Working days and hours control which time slots clients can book.');
    ?>
    <div class="card narrow">
        <form method="post" class="form">
            <?= csrf_field() ?><input type="hidden" name="action" value="save">
            <?php if ($edit): ?><input type="hidden" name="id" value="<?= e($edit['_id']) ?>"><?php endif; ?>
            <div class="form-row">
                <div class="field"><label>Full name *</label><input name="name" value="<?= e($v['name']) ?>" required placeholder="Dr. Juan Dela Cruz"></div>
                <div class="field"><label>Specialization</label><input name="specialization" value="<?= e($v['specialization']) ?>" placeholder="e.g. Small animal medicine"></div>
            </div>
            <div class="form-row three">
                <div class="field"><label>PRC license no.</label><input name="license_no" value="<?= e($v['license_no']) ?>"></div>
                <div class="field"><label>Email</label><input type="email" name="email" value="<?= e($v['email']) ?>"></div>
                <div class="field"><label>Phone</label><input name="phone" value="<?= e($v['phone']) ?>"></div>
            </div>
            <div class="field"><label>Short bio</label><textarea name="bio" rows="2"><?= e($v['bio']) ?></textarea></div>
            <div class="field"><label>Working days *</label>
                <div class="chips"><?php foreach (DAY_NAMES as $n => $l): ?>
                    <label class="chip"><input type="checkbox" name="days[]" value="<?= $n ?>" <?= in_array($n, array_map('intval', (array)$v['days']), true) ? 'checked' : '' ?>><span><?= $l ?></span></label>
                <?php endforeach; ?></div></div>
            <div class="form-row">
                <div class="field"><label>Start time</label><input type="time" name="start" value="<?= e($v['start']) ?>" required></div>
                <div class="field"><label>End time</label><input type="time" name="end" value="<?= e($v['end']) ?>" required></div>
            </div>
            <p class="muted small">Slots are <?= SLOT_MINUTES ?> minutes long. Lunch break (<?= ftime(LUNCH_START) ?>–<?= ftime(LUNCH_END) ?>) is skipped automatically.</p>
            <label class="check"><input type="checkbox" name="active" value="1" <?= !empty($v['active']) ? 'checked' : '' ?>> Active (can be booked)</label>
            <div class="form-actions"><button class="btn btn-primary">Save veterinarian</button><a class="btn btn-ghost" href="<?= e(url('/admin/vets.php')) ?>">Cancel</a></div>
        </form>
    </div>
<?php else:
    $vets = all('vets', [], ['sort' => ['name' => 1]]);
    page_header('Veterinarian management', 'Manage the clinic\'s veterinary team and their schedules.', '<a class="btn btn-primary" href="' . e(url('/admin/vets.php?new=1')) . '">+ Add veterinarian</a>');
    if (!$vets):
        empty_state('🧑‍⚕️', 'No veterinarians yet', 'Add a veterinarian so clients can book appointments.');
    else: ?>
        <div class="grid g3">
            <?php foreach ($vets as $v): ?>
                <div class="card vet">
                    <div class="avatar xl"><?= e(initials($v['name'])) ?></div>
                    <h3><?= e($v['name']) ?></h3>
                    <p class="spec"><?= e($v['specialization'] ?: 'General practice') ?></p>
                    <?= ($v['active'] ?? true) ? badge('active') : badge('inactive') ?>
                    <p class="small"><strong>Days:</strong> <?= e(implode(', ', array_map(fn($d) => DAY_NAMES[(int)$d] ?? '', (array)$v['days']))) ?><br><strong>Hours:</strong> <?= e(ftime($v['start'])) ?> – <?= e(ftime($v['end'])) ?></p>
                    <p class="muted small"><?= e($v['license_no'] ? 'PRC ' . $v['license_no'] : '') ?> <?= e($v['phone']) ?><br><?= e($v['email']) ?></p>
                    <div class="card-actions">
                        <a class="btn btn-sm btn-ghost" href="?edit=<?= e($v['_id']) ?>">Edit</a>
                        <?= post_btn(($v['active'] ?? true) ? 'Set inactive' : 'Activate', ['action' => 'toggle', 'id' => $v['_id']], 'btn btn-sm btn-ghost') ?>
                        <?= post_btn('Delete', ['action' => 'delete', 'id' => $v['_id']], 'btn btn-sm btn-danger-ghost', 'Delete this veterinarian?') ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif;
endif;
page_end('admin'); ?>
