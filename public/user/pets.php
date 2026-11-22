<?php
require __DIR__ . '/../../app/bootstrap.php';
$u = require_role('user');
$uid = $u['_id'];

if (is_post()) {
    csrf_verify();
    $act = $_POST['action'] ?? '';

    if ($act === 'delete') {
        $id = oid($_POST['id'] ?? null);
        $pet = $id ? col('pets')->findOne(['_id' => $id, 'owner_id' => $uid]) : null;
        if (!$pet) {
            flash('error', 'Pet not found.');
        } elseif (col('appointments')->countDocuments(['pet_id' => $id, 'active' => true, 'status' => ['$in' => ['pending', 'confirmed']]])) {
            flash('error', 'Cancel ' . $pet['name'] . '\'s upcoming appointments before removing this pet.');
        } else {
            col('pets')->deleteOne(['_id' => $id]);
            flash('success', $pet['name'] . ' was removed.');
        }
        redirect('/user/pets.php');
    }

    if ($act === 'save') {
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'species' => in_array($_POST['species'] ?? '', SPECIES, true) ? $_POST['species'] : '',
            'breed' => trim($_POST['breed'] ?? ''),
            'sex' => in_array($_POST['sex'] ?? '', ['Male', 'Female', 'Unknown'], true) ? $_POST['sex'] : 'Unknown',
            'birthdate' => valid_date((string)($_POST['birthdate'] ?? '')) ? $_POST['birthdate'] : '',
            'weight' => max(0, round((float)($_POST['weight'] ?? 0), 2)),
            'color' => trim($_POST['color'] ?? ''),
            'microchip' => trim($_POST['microchip'] ?? ''),
            'notes' => trim($_POST['notes'] ?? ''),
        ];
        if ($data['name'] === '' || $data['species'] === '') {
            flash('error', 'Pet name and species are required.');
            redirect('/user/pets.php?new=1');
        }
        $id = oid($_POST['id'] ?? null);
        if ($id) {
            col('pets')->updateOne(['_id' => $id, 'owner_id' => $uid], ['$set' => $data + ['updated_at' => utc_now()]]);
            flash('success', $data['name'] . '\'s details were updated.');
        } else {
            col('pets')->insertOne($data + ['owner_id' => $uid, 'created_at' => utc_now()]);
            flash('success', $data['name'] . ' was registered! You can now book an appointment.');
        }
        redirect('/user/pets.php');
    }
}

$edit = null;
$showForm = isset($_GET['new']);
if (isset($_GET['edit'])) {
    $eid = oid($_GET['edit']);
    $edit = $eid ? col('pets')->findOne(['_id' => $eid, 'owner_id' => $uid]) : null;
    $showForm = (bool)$edit;
}
$pets = all('pets', ['owner_id' => $uid], ['sort' => ['created_at' => -1]]);
$p = $edit ?: ['name' => '', 'species' => 'Dog', 'breed' => '', 'sex' => 'Unknown', 'birthdate' => '', 'weight' => '', 'color' => '', 'microchip' => '', 'notes' => ''];

page_start('My Pets', 'user');

if ($showForm):
    page_header($edit ? 'Edit pet' : 'Register a pet', 'Tell us about your companion.');
    ?>
    <div class="card narrow">
        <form method="post" class="form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <?php if ($edit): ?><input type="hidden" name="id" value="<?= e($edit['_id']) ?>"><?php endif; ?>
            <div class="form-row">
                <div class="field"><label>Pet name *</label><input name="name" value="<?= e($p['name']) ?>" required placeholder="e.g. Bantay"></div>
                <div class="field"><label>Species *</label>
                    <select name="species" required><?php foreach (SPECIES as $s): ?><option <?= $p['species'] === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="form-row">
                <div class="field"><label>Breed</label><input name="breed" value="<?= e($p['breed']) ?>" placeholder="e.g. Aspin, Shih Tzu, Puspin"></div>
                <div class="field"><label>Sex</label>
                    <select name="sex"><?php foreach (['Male', 'Female', 'Unknown'] as $s): ?><option <?= $p['sex'] === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="form-row">
                <div class="field"><label>Date of birth</label><input type="date" name="birthdate" max="<?= date('Y-m-d') ?>" value="<?= e($p['birthdate']) ?>"></div>
                <div class="field"><label>Weight (kg)</label><input type="number" step="0.01" min="0" name="weight" value="<?= e($p['weight']) ?>"></div>
            </div>
            <div class="form-row">
                <div class="field"><label>Color / markings</label><input name="color" value="<?= e($p['color']) ?>"></div>
                <div class="field"><label>Microchip no.</label><input name="microchip" value="<?= e($p['microchip']) ?>"></div>
            </div>
            <div class="field"><label>Allergies, conditions, notes</label><textarea name="notes" rows="3" placeholder="Anything the vet should know"><?= e($p['notes']) ?></textarea></div>
            <div class="form-actions">
                <button class="btn btn-primary"><?= $edit ? 'Save changes' : 'Register pet' ?></button>
                <a class="btn btn-ghost" href="<?= e(url('/user/pets.php')) ?>">Cancel</a>
            </div>
        </form>
    </div>
<?php else:
    page_header('My pets', 'Everyone in your family, in one place.', '<a class="btn btn-primary" href="' . e(url('/user/pets.php?new=1')) . '">+ Register pet</a>');
    if (!$pets) {
        empty_state('🐶', 'No pets yet', 'Register your first pet to book appointments and keep medical records.', '<a class="btn btn-primary" href="' . e(url('/user/pets.php?new=1')) . '">Register a pet</a>');
    } else { ?>
        <div class="grid g3">
            <?php foreach ($pets as $pt): ?>
                <div class="card pet">
                    <div class="pet-top"><div class="pet-av"><?= e(pet_emoji($pt['species'])) ?></div>
                        <div><h3><?= e($pt['name']) ?></h3><span class="muted"><?= e($pt['species']) ?><?= $pt['breed'] ? ' · ' . e($pt['breed']) : '' ?></span></div></div>
                    <dl class="kv">
                        <div><dt>Sex</dt><dd><?= e($pt['sex']) ?></dd></div>
                        <div><dt>Age</dt><dd><?= e(pet_age($pt['birthdate']) ?: '—') ?></dd></div>
                        <div><dt>Weight</dt><dd><?= $pt['weight'] ? e($pt['weight']) . ' kg' : '—' ?></dd></div>
                        <div><dt>Color</dt><dd><?= e($pt['color'] ?: '—') ?></dd></div>
                    </dl>
                    <?php if ($pt['notes']): ?><p class="muted small">📝 <?= e($pt['notes']) ?></p><?php endif; ?>
                    <div class="card-actions">
                        <a class="btn btn-primary btn-sm" href="<?= e(url('/user/book.php?pet=' . $pt['_id'])) ?>">Book</a>
                        <a class="btn btn-ghost btn-sm" href="<?= e(url('/user/records.php?pet=' . $pt['_id'])) ?>">Records</a>
                        <a class="btn btn-ghost btn-sm" href="<?= e(url('/user/pets.php?edit=' . $pt['_id'])) ?>">Edit</a>
                        <?= post_btn('Remove', ['action' => 'delete', 'id' => $pt['_id']], 'btn btn-sm btn-danger-ghost', 'Remove ' . $pt['name'] . '? This cannot be undone.') ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php }
endif;
page_end('user'); ?>
