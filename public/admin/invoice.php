<?php
require __DIR__ . '/../../app/bootstrap.php';
$u = require_role('admin');

$id = oid($_GET['id'] ?? null);
$inv = $id ? col('invoices')->findOne(['_id' => $id]) : null;
if (!$inv) {
    flash('error', 'Invoice not found.');
    redirect('/admin/billing.php');
}

if (is_post()) {
    csrf_verify();
    $act = $_POST['action'] ?? '';
    $c = inv_calc($inv);

    if ($act === 'pay') {
        $amount = round((float)($_POST['amount'] ?? 0), 2);
        $method = in_array($_POST['method'] ?? '', PAY_METHODS, true) ? $_POST['method'] : 'Cash';
        if ($amount <= 0) {
            flash('error', 'Enter a payment amount greater than ₱0.00.');
        } elseif ($amount > $c['balance'] + 0.001) {
            flash('error', 'That amount is more than the balance due (' . peso($c['balance']) . ').');
        } else {
            col('invoices')->updateOne(['_id' => $id], ['$push' => ['payments' => [
                'amount' => $amount, 'method' => $method, 'reference' => trim($_POST['reference'] ?? ''),
                'paid_at' => utc_now(), 'recorded_by' => $u['_id'],
            ]]]);
            $inv = col('invoices')->findOne(['_id' => $id]);
            $n = inv_calc($inv);
            notify($inv['owner_id'], 'Payment received', 'We received ' . peso($amount) . ' via ' . $method . ' for ' . inv_number($inv) . '. '
                . ($n['balance'] > 0 ? 'Remaining balance: ' . peso($n['balance']) . '.' : 'This invoice is fully paid. Thank you!'), '/user/invoice.php?id=' . $id, 'success');
            flash('success', 'Payment of ' . peso($amount) . ' recorded.');
        }
    } elseif ($act === 'delete') {
        if (!empty($inv['payments'])) {
            flash('error', 'An invoice with recorded payments cannot be deleted.');
        } else {
            col('invoices')->deleteOne(['_id' => $id]);
            if (!empty($inv['appointment_id'])) col('appointments')->updateOne(['_id' => $inv['appointment_id']], ['$set' => ['invoiced' => false]]);
            flash('success', 'Invoice deleted.');
            redirect('/admin/billing.php');
        }
    }
    redirect('/admin/invoice.php?id=' . $id);
}

$owner = col('users')->findOne(['_id' => $inv['owner_id']]) ?: ['name' => 'Unknown client'];
$pet = !empty($inv['pet_id']) ? col('pets')->findOne(['_id' => $inv['pet_id']]) : null;
$c = inv_calc($inv);

page_start('Invoice', 'admin');
page_header(inv_number($inv), '', '<a class="btn btn-ghost" href="' . e(url('/admin/billing.php')) . '">← Back</a><button class="btn btn-primary" onclick="window.print()">🖨️ Print</button>');
?>
<div class="grid inv-layout">
    <div><?php render_invoice($inv, $owner, $pet, true); ?></div>
    <div class="stack no-print">
        <div class="card">
            <h3>Record a payment</h3>
            <?php if ($c['balance'] <= 0): ?>
                <div class="alert alert-success">✅ This invoice is fully paid.</div>
            <?php else: ?>
                <p class="muted small">Balance due: <strong><?= peso($c['balance']) ?></strong></p>
                <form method="post" class="form">
                    <?= csrf_field() ?><input type="hidden" name="action" value="pay">
                    <div class="field"><label>Amount (₱)</label><div class="input-prefix"><span>₱</span><input type="number" step="0.01" min="0.01" max="<?= e($c['balance']) ?>" name="amount" value="<?= e(number_format($c['balance'], 2, '.', '')) ?>" required></div></div>
                    <div class="field"><label>Payment method</label>
                        <select name="method"><?php foreach (PAY_METHODS as $m): ?><option><?= e($m) ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label>Reference no. <span class="muted">(GCash / bank / card)</span></label><input name="reference" placeholder="Optional"></div>
                    <button class="btn btn-primary btn-block">Record payment</button>
                </form>
                <p class="muted small">This records a payment you received (cash, GCash, card terminal, etc.). It does not process money online.</p>
            <?php endif; ?>
        </div>
        <?php if (empty($inv['payments'])): ?>
            <div class="card"><?= post_btn('Delete invoice', ['action' => 'delete'], 'btn btn-danger-ghost btn-block', 'Delete this invoice?') ?></div>
        <?php endif; ?>
    </div>
</div>
<?php page_end('admin'); ?>
