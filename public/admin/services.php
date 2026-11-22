<?php
require __DIR__ . '/../../app/bootstrap.php';
$u = require_role('admin');

if (is_post()) {
    csrf_verify();
    $act = $_POST['action'] ?? '';
    $id = oid($_POST['id'] ?? null);

    if ($act === 'toggle' && $id) {
        $s = col('services')->findOne(['_id' => $id]);
        if ($s) {
            col('services')->updateOne(['_id' => $id], ['$set' => ['active' => !($s['active'] ?? true)]]);
            flash('success', $s['name'] . (($s['active'] ?? true) ? ' is now hidden from booking.' : ' is available for booking.'));
        }
        redirect('/admin/services.php');
    }
    if ($act === 'delete' && $id) {
        col('services')->deleteOne(['_id' => $id]);
        flash('success', 'Service deleted. (Past appointments keep their original service name and price.)');
        redirect('/admin/services.php');
    }
    if ($act === 'save') {
        $price = round((float)($_POST['price'] ?? 0), 2);
        $data = [
            'name' => trim($_POST['name'] ?? ''), 'category' => trim($_POST['category'] ?? ''),
            'description' => trim($_POST['description'] ?? ''), 'price' => $price,
            'duration' => max(5, (int)($_POST['duration'] ?? 30)), 'active' => !empty($_POST['active']),
        ];
        if ($data['name'] === '' || $price < 0) {
            flash('error', 'Please enter a service name and a valid price in pesos.');
            redirect('/admin/services.php?' . ($id ? 'edit=' . $id : 'new=1'));
        }
        if ($id) {
            col('services')->updateOne(['_id' => $id], ['$set' => $data]);
            flash('success', 'Service updated.');
        } else {
            col('services')->insertOne($data + ['created_at' => utc_now()]);
            flash('success', 'Service added.');
        }
        redirect('/admin/services.php');
    }
}

$edit = null;
$showForm = isset($_GET['new']);
if ($eid = oid($_GET['edit'] ?? null)) {
    $edit = col('services')->findOne(['_id' => $eid]);
    $showForm = (bool)$edit;
}

page_start('Services', 'admin');
if ($showForm):
    $s = $edit ?: ['name' => '', 'category' => '', 'description' => '', 'price' => '', 'duration' => 30, 'active' => true];
    page_header($edit ? 'Edit service' : 'Add service', 'Prices are in Philippine pesos (₱).');
    ?>
    <div class="card narrow">
        <form method="post" class="form">
            <?= csrf_field() ?><input type="hidden" name="action" value="save">
            <?php if ($edit): ?><input type="hidden" name="id" value="<?= e($edit['_id']) ?>"><?php endif; ?>
            <div class="form-row">
                <div class="field"><label>Service name *</label><input name="name" value="<?= e($s['name']) ?>" required placeholder="e.g. Anti-rabies vaccination"></div>
                <div class="field"><label>Category</label><input name="category" value="<?= e($s['category'] ?? '') ?>" placeholder="e.g. Preventive care"></div>
            </div>
            <div class="field"><label>Description</label><textarea name="description" rows="2"><?= e($s['description']) ?></textarea></div>
            <div class="form-row">
                <div class="field"><label>Price (₱) *</label><div class="input-prefix"><span>₱</span><input type="number" step="0.01" min="0" name="price" value="<?= e($s['price']) ?>" required></div></div>
                <div class="field"><label>Duration (minutes)</label><input type="number" min="5" step="5" name="duration" value="<?= e($s['duration']) ?>"></div>
            </div>
            <label class="check"><input type="checkbox" name="active" value="1" <?= !empty($s['active']) ? 'checked' : '' ?>> Available for booking</label>
            <div class="form-actions"><button class="btn btn-primary">Save service</button><a class="btn btn-ghost" href="<?= e(url('/admin/services.php')) ?>">Cancel</a></div>
        </form>
    </div>
<?php else:
    $services = all('services', [], ['sort' => ['name' => 1]]);
    page_header('Veterinary services', 'Everything the clinic offers, with prices in Philippine pesos.', '<a class="btn btn-primary" href="' . e(url('/admin/services.php?new=1')) . '">+ Add service</a>');
    if (!$services):
        empty_state('💉', 'No services yet', 'Add the services your clinic offers.');
    else: ?>
        <div class="card flush"><div class="table-wrap"><table>
            <thead><tr><th>Service</th><th>Category</th><th class="r">Price</th><th>Duration</th><th>Status</th><th class="r">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($services as $s): ?>
                <tr>
                    <td><strong><?= e($s['name']) ?></strong><br><span class="muted small"><?= e($s['description']) ?></span></td>
                    <td><?= e($s['category'] ?? '') ?: '—' ?></td>
                    <td class="r"><strong><?= peso($s['price']) ?></strong></td>
                    <td><?= (int)$s['duration'] ?> min</td>
                    <td><?= ($s['active'] ?? true) ? badge('active') : badge('inactive') ?></td>
                    <td class="r"><div class="btn-group">
                        <a class="btn btn-sm btn-ghost" href="?edit=<?= e($s['_id']) ?>">Edit</a>
                        <?= post_btn(($s['active'] ?? true) ? 'Hide' : 'Show', ['action' => 'toggle', 'id' => $s['_id']], 'btn btn-sm btn-ghost') ?>
                        <?= post_btn('Delete', ['action' => 'delete', 'id' => $s['_id']], 'btn btn-sm btn-danger-ghost', 'Delete this service?') ?>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div></div>
    <?php endif;
endif;
page_end('admin'); ?>
