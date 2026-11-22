<?php
require __DIR__ . '/../../app/bootstrap.php';
$u = require_role('admin');

if (is_post()) {
    csrf_verify();
    $id = oid($_POST['id'] ?? null);
    $c = $id ? col('users')->findOne(['_id' => $id, 'role' => 'user']) : null;
    if ($c && ($_POST['action'] ?? '') === 'toggle') {
        $new = !($c['active'] ?? true);
        col('users')->updateOne(['_id' => $id], ['$set' => ['active' => $new]]);
        flash('success', $c['name'] . ($new ? ' was reactivated.' : ' was deactivated and can no longer sign in.'));
    }
    redirect('/admin/clients.php' . ($id ? '?id=' . $id : ''));
}

$detail = null;
if ($did = oid($_GET['id'] ?? null)) $detail = col('users')->findOne(['_id' => $did, 'role' => 'user']);

page_start('Clients', 'admin');

if ($detail):
    $pets = all('pets', ['owner_id' => $detail['_id']]);
    $appts = all('appointments', ['owner_id' => $detail['_id']], ['sort' => ['starts_at' => -1], 'limit' => 20]);
    $balance = 0.0;
    foreach (all('invoices', ['owner_id' => $detail['_id']]) as $i) $balance += inv_calc($i)['balance'];
    page_header($detail['name'], 'Client profile', '<a class="btn btn-ghost" href="' . e(url('/admin/clients.php')) . '">← All clients</a>'
        . post_btn(($detail['active'] ?? true) ? 'Deactivate account' : 'Reactivate account', ['action' => 'toggle', 'id' => $detail['_id']], 'btn btn-danger-ghost', 'Change this account\'s access?'));
    ?>
    <div class="grid g2">
        <div class="card">
            <div class="profile-top"><span class="avatar xl"><?= e(initials($detail['name'])) ?></span><div><h3><?= e($detail['name']) ?></h3><?= ($detail['active'] ?? true) ? badge('active') : badge('inactive') ?></div></div>
            <dl class="kv wide">
                <div><dt>Email</dt><dd><?= e($detail['email']) ?></dd></div>
                <div><dt>Mobile</dt><dd><?= e($detail['phone'] ?? '—') ?></dd></div>
                <div><dt>Alt. phone</dt><dd><?= e($detail['alt_phone'] ?? '—') ?></dd></div>
                <div><dt>Emergency contact</dt><dd><?= e($detail['emergency_contact'] ?? '—') ?></dd></div>
                <div><dt>Address</dt><dd><?= e($detail['address'] ?? '—') ?></dd></div>
                <div><dt>Joined</dt><dd><?= e(dt($detail['created_at'], 'M j, Y')) ?></dd></div>
                <div><dt>Balance due</dt><dd><strong><?= peso($balance) ?></strong></dd></div>
            </dl>
        </div>
        <div class="card">
            <h3>Pets (<?= count($pets) ?>)</h3>
            <?php if (!$pets): ?><p class="muted">No pets registered.</p><?php else: ?>
                <ul class="list"><?php foreach ($pets as $p): ?>
                    <li><span class="n-ico"><?= e(pet_emoji($p['species'])) ?></span><div class="grow"><strong><?= e($p['name']) ?></strong><small class="muted"><?= e($p['species'] . ($p['breed'] ? ' · ' . $p['breed'] : '')) ?></small></div>
                        <a class="btn btn-sm btn-ghost" href="<?= e(url('/admin/records.php?pet=' . $p['_id'])) ?>">Records</a></li>
                <?php endforeach; ?></ul>
            <?php endif; ?>
        </div>
    </div>
    <div class="card">
        <h3>Recent appointments</h3>
        <?php if (!$appts): ?><p class="muted">No appointments yet.</p><?php else: ?>
            <div class="table-wrap"><table><thead><tr><th>When</th><th>Service</th><th>Status</th></tr></thead><tbody>
                <?php foreach ($appts as $a): ?><tr><td><?= e(fdate($a['date'])) ?> · <?= e(ftime($a['time'])) ?></td><td><?= e($a['service_name']) ?></td><td><?= badge($a['status']) ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </div>
<?php else:
    $q = trim($_GET['q'] ?? '');
    $filter = ['role' => 'user'];
    if ($q !== '') {
        $rx = new MongoDB\BSON\Regex(preg_quote($q), 'i');
        $filter['$or'] = [['name' => $rx], ['email' => $rx], ['phone' => $rx]];
    }
    $clients = all('users', $filter, ['sort' => ['created_at' => -1], 'limit' => 200]);
    $petCounts = [];
    foreach (col('pets')->aggregate([['$group' => ['_id' => '$owner_id', 'n' => ['$sum' => 1]]]]) as $r) $petCounts[(string)$r['_id']] = $r['n'];
    page_header('Clients', 'Pet owners registered on the website (owner profile management).');
    ?>
    <form class="filters card" method="get">
        <div class="field grow"><label>Search</label><input name="q" value="<?= e($q) ?>" placeholder="Name, email or phone"></div>
        <button class="btn btn-primary">Search</button><a class="btn btn-ghost" href="?">Reset</a>
    </form>
    <?php if (!$clients): empty_state('👥', 'No clients found'); else: ?>
        <div class="card flush"><div class="table-wrap"><table>
            <thead><tr><th>Client</th><th>Contact</th><th>Pets</th><th>Joined</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($clients as $c): ?>
                <tr>
                    <td><div class="cell-av"><span class="avatar"><?= e(initials($c['name'])) ?></span><strong><?= e($c['name']) ?></strong></div></td>
                    <td><?= e($c['email']) ?><br><span class="muted small"><?= e($c['phone'] ?? '') ?></span></td>
                    <td><?= (int)($petCounts[(string)$c['_id']] ?? 0) ?></td>
                    <td><?= e(dt($c['created_at'], 'M j, Y')) ?></td>
                    <td><?= ($c['active'] ?? true) ? badge('active') : badge('inactive') ?></td>
                    <td class="r"><a class="btn btn-sm btn-ghost" href="?id=<?= e($c['_id']) ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div></div>
    <?php endif;
endif;
page_end('admin'); ?>
