<?php
require __DIR__ . '/../../app/bootstrap.php';
$u = require_role('user');

$id = oid($_GET['id'] ?? null);
$inv = $id ? col('invoices')->findOne(['_id' => $id, 'owner_id' => $u['_id']]) : null;
if (!$inv) {
    flash('error', 'Invoice not found.');
    redirect('/user/billing.php');
}
$pet = !empty($inv['pet_id']) ? col('pets')->findOne(['_id' => $inv['pet_id']]) : null;

page_start('Invoice', 'user');
page_header(inv_number($inv), '', '<a class="btn btn-ghost" href="' . e(url('/user/billing.php')) . '">← Back</a><button class="btn btn-primary" onclick="window.print()">🖨️ Print</button>');
render_invoice($inv, $u, $pet, false);
page_end('user'); ?>
