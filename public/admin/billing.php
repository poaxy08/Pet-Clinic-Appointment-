<?php
require __DIR__ . '/../../app/bootstrap.php';
$u = require_role('admin');

if (is_post() && ($_POST['action'] ?? '') === 'create') {
    csrf_verify();
    $apptId = oid($_POST['appointment_id'] ?? null);
    $appt = $apptId ? col('appointments')->findOne(['_id' => $apptId]) : null;
    $ownerId = $appt ? $appt['owner_id'] : oid($_POST['owner_id'] ?? null);
    $owner = $ownerId ? col('users')->findOne(['_id' => $ownerId, 'role' => 'user']) : null;

    $items = [];
    $descs = (array)($_POST['desc'] ?? []);
    $qtys = (array)($_POST['qty'] ?? []);
    $prices = (array)($_POST['price'] ?? []);
    foreach ($descs as $i => $d) {
        $d = trim((string)$d);
        $q = (float)($qtys[$i] ?? 0);
        $p = (float)($prices[$i] ?? 0);
        if ($d !== '' && $q > 0 && $p >= 0) $items[] = ['description' => $d, 'qty' => $q, 'price' => round($p, 2)];
    }
    $discount = max(0, round((float)($_POST['discount'] ?? 0), 2));

    if (!$owner) {
        flash('error', 'Please choose a client or an appointment.');
        redirect('/admin/billing.php?new=1');
    }
    if (!$items) {
        flash('error', 'Add at least one line item with a description, quantity and price.');
        redirect('/admin/billing.php?new=1');
    }
    if ($appt && col('invoices')->countDocuments(['appointment_id' => $apptId])) {
        flash('error', 'That appointment already has an invoice.');
        redirect('/admin/billing.php');
    }
    $sub = 0.0;
    foreach ($items as $it) $sub += $it['qty'] * $it['price'];
    if ($discount > $sub) {
        flash('error', 'The discount cannot be larger than the subtotal.');
        redirect('/admin/billing.php?new=1');
    }

    $inv = [
        'number' => next_seq('invoice'), 'owner_id' => $owner['_id'], 'appointment_id' => $appt ? $apptId : null,
        'pet_id' => $appt ? $appt['pet_id'] : null, 'items' => $items, 'discount' => $discount,
        'payments' => [], 'notes' => trim($_POST['notes'] ?? ''), 'created_at' => utc_now(),
    ];
    $newId = col('invoices')->insertOne($inv)->getInsertedId();
    if ($appt) col('appointments')->updateOne(['_id' => $apptId], ['$set' => ['invoiced' => true]]);
    $c = inv_calc($inv);
    notify($owner['_id'], 'New invoice ' . inv_number($inv), 'Amount due: ' . peso($c['total']) . '. You can view it under Billing.', '/user/invoice.php?id=' . $newId, 'info');
    flash('success', 'Invoice ' . inv_number($inv) . ' created for ' . peso($c['total']) . '.');
    redirect('/admin/invoice.php?id=' . $newId);
}

page_start('Billing', 'admin');

if (isset($_GET['new'])):
    $owners = all('users', ['role' => 'user', 'active' => ['$ne' => false]], ['sort' => ['name' => 1], 'limit' => 500]);
    $uninvoiced = all('appointments', ['status' => ['$in' => ['completed', 'confirmed']], 'invoiced' => ['$ne' => true]], ['sort' => ['starts_at' => -1], 'limit' => 200]);
    $pets = lookup('pets', array_column($uninvoiced, 'pet_id'));
    $ownerMap = lookup('users', array_column($uninvoiced, 'owner_id'));
    $pre = oid($_GET['appointment'] ?? null);
    page_header('Create invoice', 'Pick an appointment to pre-fill the service fee, or bill a client directly. Amounts are in ₱.');
    ?>
    <form method="post" class="card narrow form">
        <?= csrf_field() ?><input type="hidden" name="action" value="create">
        <div class="form-row">
            <div class="field"><label>Appointment <span class="muted">(optional)</span></label>
                <select name="appointment_id" id="apptSel">
                    <option value="">— No appointment (walk-in / product sale) —</option>
                    <?php foreach ($uninvoiced as $a): $p = $pets[(string)$a['pet_id']] ?? null; $o = $ownerMap[(string)$a['owner_id']] ?? null; ?>
                        <option value="<?= e($a['_id']) ?>" data-owner="<?= e($a['owner_id']) ?>" data-service="<?= e($a['service_name']) ?>" data-price="<?= e($a['price']) ?>" <?= $pre && (string)$pre === (string)$a['_id'] ? 'selected' : '' ?>>
                            <?= e(fdate($a['date']) . ' — ' . ($o['name'] ?? '') . ' / ' . ($p['name'] ?? '') . ' — ' . $a['service_name']) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="field"><label>Client</label>
                <select name="owner_id" id="ownerSel">
                    <option value="">Select client…</option>
                    <?php foreach ($owners as $o): ?><option value="<?= e($o['_id']) ?>"><?= e($o['name']) ?> — <?= e($o['email']) ?></option><?php endforeach; ?>
                </select></div>
        </div>
        <label>Line items</label>
        <div id="invItems"></div>
        <template id="invRowTpl">
            <div class="inv-row">
                <input name="desc[]" placeholder="Description (e.g. Vaccination, Medication)">
                <input name="qty[]" type="number" step="0.01" min="0.01" value="1" title="Quantity">
                <div class="input-prefix"><span>₱</span><input name="price[]" type="number" step="0.01" min="0" placeholder="0.00" title="Unit price"></div>
                <button type="button" class="btn btn-sm btn-danger-ghost rm" title="Remove">✕</button>
            </div>
        </template>
        <button type="button" class="btn btn-ghost btn-sm" id="addRow">+ Add line</button>
        <div class="form-row">
            <div class="field"><label>Discount (₱)</label><div class="input-prefix"><span>₱</span><input type="number" step="0.01" min="0" name="discount" value="0"></div></div>
            <div class="field"><label>Notes</label><input name="notes" placeholder="Optional note on the invoice"></div>
        </div>
        <div class="total-bar"><span>Invoice total</span><strong id="invTotal">₱0.00</strong></div>
        <div class="form-actions"><button class="btn btn-primary">Create invoice</button><a class="btn btn-ghost" href="<?= e(url('/admin/billing.php')) ?>">Cancel</a></div>
    </form>
<?php else:
    $status = in_array($_GET['status'] ?? '', ['unpaid', 'partial', 'paid'], true) ? $_GET['status'] : '';
    $invs = all('invoices', [], ['sort' => ['created_at' => -1], 'limit' => 300]);
    $ownerMap = lookup('users', array_column($invs, 'owner_id'));
    $petMap = lookup('pets', array_column($invs, 'pet_id'));
    $tot = ['billed' => 0.0, 'paid' => 0.0, 'due' => 0.0];
    $rows = [];
    foreach ($invs as $i) {
        $c = inv_calc($i);
        $tot['billed'] += $c['total']; $tot['paid'] += $c['paid']; $tot['due'] += $c['balance'];
        if (!$status || $status === $c['status']) $rows[] = [$i, $c];
    }
    page_header('Billing & payments', 'Create invoices and record payments in Philippine pesos (₱).', '<a class="btn btn-primary" href="' . e(url('/admin/billing.php?new=1')) . '">+ New invoice</a>');
    ?>
    <div class="grid g3 stats">
        <div class="stat"><span class="s-ico">🧾</span><div><b><?= peso($tot['billed']) ?></b><small>Total billed</small></div></div>
        <div class="stat"><span class="s-ico">✅</span><div><b><?= peso($tot['paid']) ?></b><small>Total collected</small></div></div>
        <div class="stat <?= $tot['due'] > 0 ? 'warn' : '' ?>"><span class="s-ico">⏳</span><div><b><?= peso($tot['due']) ?></b><small>Outstanding</small></div></div>
    </div>
    <div class="tabs">
        <?php foreach ([''=> 'All', 'unpaid' => 'Unpaid', 'partial' => 'Partial', 'paid' => 'Paid'] as $k => $l): ?><a class="<?= $status === $k ? 'active' : '' ?>" href="?status=<?= $k ?>"><?= $l ?></a><?php endforeach; ?>
    </div>
    <?php if (!$rows): empty_state('🧾', 'No invoices to show', 'Create an invoice after a completed appointment.');
    else: ?>
        <div class="card flush"><div class="table-wrap"><table>
            <thead><tr><th>Invoice</th><th>Date</th><th>Client / Pet</th><th class="r">Total</th><th class="r">Paid</th><th class="r">Balance</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as [$i, $c]): $o = $ownerMap[(string)$i['owner_id']] ?? null; $p = $petMap[(string)($i['pet_id'] ?? '')] ?? null; ?>
                <tr>
                    <td><strong><?= e(inv_number($i)) ?></strong></td>
                    <td><?= e(dt($i['created_at'], 'M j, Y')) ?></td>
                    <td><?= e($o['name'] ?? '—') ?><?= $p ? '<br><span class="muted small">' . e(pet_emoji($p['species']) . ' ' . $p['name']) . '</span>' : '' ?></td>
                    <td class="r"><?= peso($c['total']) ?></td><td class="r"><?= peso($c['paid']) ?></td><td class="r"><?= peso($c['balance']) ?></td>
                    <td><?= badge($c['status']) ?></td>
                    <td class="r"><a class="btn btn-sm btn-primary" href="<?= e(url('/admin/invoice.php?id=' . $i['_id'])) ?>"><?= $c['balance'] > 0 ? 'Open / Pay' : 'Open' ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div></div>
    <?php endif;
endif;
page_end('admin'); ?>
