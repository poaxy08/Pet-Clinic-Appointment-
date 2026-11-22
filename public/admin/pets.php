<?php
require __DIR__ . '/../../app/bootstrap.php';
$u = require_role('admin');

if (is_post()) {
    csrf_verify();
    if (($_POST['action'] ?? '') === 'delete') {
        $id = oid($_POST['id'] ?? null);
        $pet = $id ? col('pets')->findOne(['_id' => $id]) : null;
        if ($pet && col('appointments')->countDocuments(['pet_id' => $id, 'active' => true, 'status' => ['$in' => ['pending', 'confirmed']]])) {
            flash('error', 'This pet still has upcoming appointments. Cancel them first.');
        } elseif ($pet) {
            col('pets')->deleteOne(['_id' => $id]);
            flash('success', $pet['name'] . ' was removed. (Medical records are kept.)');
        }
    }
    redirect('/admin/pets.php');
}

$q = trim($_GET['q'] ?? '');
$filter = [];
if ($q !== '') {
    $rx = new MongoDB\BSON\Regex(preg_quote($q), 'i');
    $ownerIds = array_column(all('users', ['$or' => [['name' => $rx], ['email' => $rx]]], ['projection' => ['_id' => 1]]), '_id');
    $filter['$or'] = [['name' => $rx], ['breed' => $rx], ['species' => $rx], ['owner_id' => ['$in' => $ownerIds]]];
}
if ($oid = oid($_GET['owner'] ?? null)) $filter['owner_id'] = $oid;
$pets = all('pets', $filter, ['sort' => ['created_at' => -1], 'limit' => 300]);
$owners = lookup('users', array_column($pets, 'owner_id'));

page_start('Pets', 'admin');
page_header('Registered pets', 'All pets registered by clients.');
?>
<form class="filters card" method="get">
    <div class="field grow"><label>Search</label><input name="q" value="<?= e($q) ?>" placeholder="Pet name, species, breed or owner"></div>
    <button class="btn btn-primary">Search</button><a class="btn btn-ghost" href="?">Reset</a>
</form>
<?php if (!$pets): empty_state('🐾', 'No pets found'); else: ?>
    <div class="card flush"><div class="table-wrap"><table>
        <thead><tr><th>Pet</th><th>Owner</th><th>Details</th><th>Age</th><th>Weight</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($pets as $p): $o = $owners[(string)$p['owner_id']] ?? null; ?>
            <tr>
                <td><div class="cell-av"><span class="pet-av sm"><?= e(pet_emoji($p['species'])) ?></span><strong><?= e($p['name']) ?></strong></div></td>
                <td><?= $o ? '<a href="' . e(url('/admin/clients.php?id=' . $o['_id'])) . '">' . e($o['name']) . '</a>' : '—' ?></td>
                <td><?= e($p['species'] . ($p['breed'] ? ' · ' . $p['breed'] : '') . ' · ' . $p['sex']) ?><?= $p['notes'] ? '<br><span class="muted small">📝 ' . e($p['notes']) . '</span>' : '' ?></td>
                <td><?= e(pet_age($p['birthdate']) ?: '—') ?></td>
                <td><?= $p['weight'] ? e($p['weight']) . ' kg' : '—' ?></td>
                <td class="r"><div class="btn-group">
                    <a class="btn btn-sm btn-ghost" href="<?= e(url('/admin/records.php?pet=' . $p['_id'])) ?>">Records</a>
                    <a class="btn btn-sm btn-primary" href="<?= e(url('/admin/records.php?new=1&pet=' . $p['_id'])) ?>">+ Record</a>
                    <?= post_btn('Remove', ['action' => 'delete', 'id' => $p['_id']], 'btn btn-sm btn-danger-ghost', 'Remove this pet?') ?>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div></div>
<?php endif;
page_end('admin'); ?>
