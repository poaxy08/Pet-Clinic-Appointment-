<?php
require __DIR__ . '/../../app/bootstrap.php';
$u = require_role('admin');

if (is_post()) {
    csrf_verify();
    $act = $_POST['action'] ?? '';

    if ($act === 'delete') {
        if ($rid = oid($_POST['id'] ?? null)) col('records')->deleteOne(['_id' => $rid]);
        flash('success', 'Record deleted.');
        redirect('/admin/records.php');
    }

    if ($act === 'save') {
        $petId = oid($_POST['pet_id'] ?? null);
        $pet = $petId ? col('pets')->findOne(['_id' => $petId]) : null;
        $visit = (string)($_POST['visit_date'] ?? '');
        if (!$pet || !valid_date($visit)) {
            flash('error', 'Please choose a pet and a valid visit date.');
            redirect('/admin/records.php?new=1');
        }
        $vetId = oid($_POST['vet_id'] ?? null);
        $apptId = oid($_POST['appointment_id'] ?? null);
        $data = [
            'pet_id' => $pet['_id'], 'owner_id' => $pet['owner_id'], 'vet_id' => $vetId, 'appointment_id' => $apptId,
            'visit_date' => $visit,
            'weight' => trim((string)($_POST['weight'] ?? '')) !== '' ? round((float)$_POST['weight'], 2) : '',
            'temperature' => trim((string)($_POST['temperature'] ?? '')) !== '' ? round((float)$_POST['temperature'], 1) : '',
            'diagnosis' => trim($_POST['diagnosis'] ?? ''),
            'treatment' => trim($_POST['treatment'] ?? ''),
            'medications' => trim($_POST['medications'] ?? ''),
            'vaccinations' => trim($_POST['vaccinations'] ?? ''),
            'follow_up' => valid_date((string)($_POST['follow_up'] ?? '')) ? $_POST['follow_up'] : '',
            'notes' => trim($_POST['notes'] ?? ''),
        ];
        $id = oid($_POST['id'] ?? null);
        if ($id) {
            col('records')->updateOne(['_id' => $id], ['$set' => $data + ['updated_at' => utc_now()]]);
            flash('success', 'Medical record updated.');
        } else {
            col('records')->insertOne($data + ['created_at' => utc_now()]);
            if ($data['weight'] !== '') col('pets')->updateOne(['_id' => $pet['_id']], ['$set' => ['weight' => $data['weight']]]);
            notify($pet['owner_id'], 'New medical record', 'A new medical record was added for ' . $pet['name'] . '.', '/user/records.php?pet=' . $pet['_id'], 'info');
            flash('success', 'Medical record saved and the owner was notified.');
        }
        redirect('/admin/records.php?pet=' . $pet['_id']);
    }
}

$edit = null;
$showForm = isset($_GET['new']);
if ($eid = oid($_GET['edit'] ?? null)) {
    $edit = col('records')->findOne(['_id' => $eid]);
    $showForm = (bool)$edit;
}

page_start('Medical Records', 'admin');

if ($showForm):
    $r = $edit ?: [
        'pet_id' => oid($_GET['pet'] ?? null), 'vet_id' => null, 'appointment_id' => oid($_GET['appointment'] ?? null),
        'visit_date' => date('Y-m-d'), 'weight' => '', 'temperature' => '', 'diagnosis' => '', 'treatment' => '',
        'medications' => '', 'vaccinations' => '', 'follow_up' => '', 'notes' => '',
    ];
    $allPets = all('pets', [], ['sort' => ['name' => 1], 'limit' => 500]);
    $ownerMap = lookup('users', array_column($allPets, 'owner_id'));
    $vets = all('vets', ['active' => true], ['sort' => ['name' => 1]]);
    // Pre-select the vet from the linked appointment
    if (!$edit && $r['appointment_id']) {
        $ap = col('appointments')->findOne(['_id' => $r['appointment_id']]);
        if ($ap) { $r['vet_id'] = $ap['vet_id']; $r['visit_date'] = $ap['date']; $r['diagnosis'] = ''; }
    }
    page_header($edit ? 'Edit medical record' : 'New medical record', 'The pet owner can view this record in their portal.');
    ?>
    <div class="card narrow">
        <form method="post" class="form">
            <?= csrf_field() ?><input type="hidden" name="action" value="save">
            <?php if ($edit): ?><input type="hidden" name="id" value="<?= e($edit['_id']) ?>"><?php endif; ?>
            <?php if ($r['appointment_id']): ?><input type="hidden" name="appointment_id" value="<?= e($r['appointment_id']) ?>"><?php endif; ?>
            <div class="form-row">
                <div class="field"><label>Pet *</label>
                    <select name="pet_id" required><option value="">Select pet…</option>
                        <?php foreach ($allPets as $p): ?><option value="<?= e($p['_id']) ?>" <?= $r['pet_id'] && (string)$r['pet_id'] === (string)$p['_id'] ? 'selected' : '' ?>><?= e($p['name'] . ' (' . $p['species'] . ') — ' . ($ownerMap[(string)$p['owner_id']]['name'] ?? '')) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="field"><label>Veterinarian</label>
                    <select name="vet_id"><option value="">—</option>
                        <?php foreach ($vets as $v): ?><option value="<?= e($v['_id']) ?>" <?= $r['vet_id'] && (string)$r['vet_id'] === (string)$v['_id'] ? 'selected' : '' ?>><?= e($v['name']) ?></option><?php endforeach; ?>
                    </select></div>
            </div>
            <div class="form-row three">
                <div class="field"><label>Visit date *</label><input type="date" name="visit_date" value="<?= e($r['visit_date']) ?>" required></div>
                <div class="field"><label>Weight (kg)</label><input type="number" step="0.01" min="0" name="weight" value="<?= e($r['weight']) ?>"></div>
                <div class="field"><label>Temperature (°C)</label><input type="number" step="0.1" min="0" name="temperature" value="<?= e($r['temperature']) ?>"></div>
            </div>
            <div class="field"><label>Diagnosis</label><input name="diagnosis" value="<?= e($r['diagnosis']) ?>" placeholder="e.g. Ear infection"></div>
            <div class="field"><label>Treatment</label><textarea name="treatment" rows="2"><?= e($r['treatment']) ?></textarea></div>
            <div class="form-row">
                <div class="field"><label>Medications / prescriptions</label><textarea name="medications" rows="2"><?= e($r['medications']) ?></textarea></div>
                <div class="field"><label>Vaccinations given</label><textarea name="vaccinations" rows="2"><?= e($r['vaccinations']) ?></textarea></div>
            </div>
            <div class="form-row">
                <div class="field"><label>Follow-up date</label><input type="date" name="follow_up" value="<?= e($r['follow_up']) ?>"></div>
                <div class="field"><label>Additional notes</label><input name="notes" value="<?= e($r['notes']) ?>"></div>
            </div>
            <div class="form-actions"><button class="btn btn-primary">Save record</button><a class="btn btn-ghost" href="<?= e(url('/admin/records.php')) ?>">Cancel</a></div>
        </form>
    </div>
<?php else:
    $petId = oid($_GET['pet'] ?? null);
    $filter = $petId ? ['pet_id' => $petId] : [];
    $records = all('records', $filter, ['sort' => ['visit_date' => -1, 'created_at' => -1], 'limit' => 200]);
    $petMap = lookup('pets', array_column($records, 'pet_id'));
    $ownerMap = lookup('users', array_column($records, 'owner_id'));
    $vetMap = lookup('vets', array_column($records, 'vet_id'));
    $actions = '<a class="btn btn-primary" href="' . e(url('/admin/records.php?new=1' . ($petId ? '&pet=' . $petId : ''))) . '">+ New record</a>';
    page_header('Veterinary medical records', $petId ? 'Showing one pet\'s history.' : 'Latest records across all pets.', $actions);
    if ($petId): ?><div class="tabs"><a href="?">← All records</a></div><?php endif;
    if (!$records): empty_state('🩺', 'No medical records yet', 'Create a record after a consultation.');
    else: ?>
        <div class="timeline">
            <?php foreach ($records as $r): $p = $petMap[(string)$r['pet_id']] ?? null; $o = $ownerMap[(string)$r['owner_id']] ?? null; $v = $vetMap[(string)($r['vet_id'] ?? '')] ?? null; ?>
                <div class="card tl-item">
                    <div class="tl-head">
                        <div><h3><?= e($p ? pet_emoji($p['species']) . ' ' . $p['name'] : 'Pet') ?> — <?= e($r['diagnosis'] ?: 'Check-up') ?></h3>
                            <span class="muted small"><?= e(fdate($r['visit_date'])) ?> · Owner: <?= e($o['name'] ?? '—') ?><?= $v ? ' · ' . e($v['name']) : '' ?></span></div>
                        <div class="btn-group">
                            <a class="btn btn-sm btn-ghost" href="?edit=<?= e($r['_id']) ?>">Edit</a>
                            <?= post_btn('Delete', ['action' => 'delete', 'id' => $r['_id']], 'btn btn-sm btn-danger-ghost', 'Delete this medical record?') ?>
                        </div>
                    </div>
                    <div class="vitals">
                        <?php if ($r['weight'] !== ''): ?><span>⚖️ <?= e($r['weight']) ?> kg</span><?php endif; ?>
                        <?php if ($r['temperature'] !== ''): ?><span>🌡️ <?= e($r['temperature']) ?> °C</span><?php endif; ?>
                        <?php if ($r['follow_up']): ?><span class="pill warn">Follow-up: <?= e(fdate($r['follow_up'])) ?></span><?php endif; ?>
                    </div>
                    <dl class="rec">
                        <?php if ($r['treatment']): ?><div><dt>Treatment</dt><dd><?= nl2br(e($r['treatment'])) ?></dd></div><?php endif; ?>
                        <?php if ($r['medications']): ?><div><dt>Medications</dt><dd><?= nl2br(e($r['medications'])) ?></dd></div><?php endif; ?>
                        <?php if ($r['vaccinations']): ?><div><dt>Vaccinations</dt><dd><?= nl2br(e($r['vaccinations'])) ?></dd></div><?php endif; ?>
                        <?php if ($r['notes']): ?><div><dt>Notes</dt><dd><?= nl2br(e($r['notes'])) ?></dd></div><?php endif; ?>
                    </dl>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif;
endif;
page_end('admin'); ?>
