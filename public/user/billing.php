<?php
require __DIR__ . '/../../app/bootstrap.php';
$u = require_role('user');
$uid = $u['_id'];

$invs = all('invoices', ['owner_id' => $uid], ['sort' => ['created_at' => -1], 'limit' => 200]);
$pets = lookup('pets', array_column($invs, 'pet_id'));
$billed = $paid = $due = 0.0;
foreach ($invs as $i) {
    $c = inv_calc($i);
    $billed += $c['total'];
    $paid += $c['paid'];
    $due += $c['balance'];
}

page_start('Billing', 'user');
page_header('Billing & payments', 'All amounts are in Philippine pesos (₱).');
?>
<div class="grid g3 stats">
    <div class="stat"><span class="s-ico">🧾</span><div><b><?= peso($billed) ?></b><small>Total billed</small></div></div>
    <div class="stat"><span class="s-ico">✅</span><div><b><?= peso($paid) ?></b><small>Total paid</small></div></div>
    <div class="stat <?= $due > 0 ? 'warn' : '' ?>"><span class="s-ico">⏳</span><div><b><?= peso($due) ?></b><small>Balance due</small></div></div>
</div>

<?php if (!$invs): empty_state('💳', 'No invoices yet', 'Invoices appear here after your visit.'); else: ?>
    <div class="card flush">
        <div class="table-wrap">
            <table>
                <thead><tr><th>Invoice</th><th>Date</th><th>Pet</th><th class="r">Total</th><th class="r">Paid</th><th class="r">Balance</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($invs as $i): $c = inv_calc($i); $p = $pets[(string)($i['pet_id'] ?? '')] ?? null; ?>
                    <tr>
                        <td><strong><?= e(inv_number($i)) ?></strong></td>
                        <td><?= e(dt($i['created_at'], 'M j, Y')) ?></td>
                        <td><?= e($p ? pet_emoji($p['species']) . ' ' . $p['name'] : '—') ?></td>
                        <td class="r"><?= peso($c['total']) ?></td><td class="r"><?= peso($c['paid']) ?></td><td class="r"><?= peso($c['balance']) ?></td>
                        <td><?= badge($c['status']) ?></td>
                        <td class="r"><a class="btn btn-sm btn-ghost" href="<?= e(url('/user/invoice.php?id=' . $i['_id'])) ?>">View</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
<div class="card note">
    <h4>How to pay</h4>
    <p class="muted">Payments are settled at the clinic. We accept <?= e(implode(', ', PAY_METHODS)) ?>. After the clinic records your payment, this page updates automatically and you will receive a notification.</p>
</div>
<?php page_end('user'); ?>
