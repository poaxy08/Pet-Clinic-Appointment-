<?php
require __DIR__ . '/../../app/bootstrap.php';
$u = require_role('admin');

if (is_post()) {
    csrf_verify();
    $act = $_POST['action'] ?? '';
    $id = oid($_POST['id'] ?? null);
    $a = $id ? col('appointments')->findOne(['_id' => $id]) : null;
    if (!$a) {
        flash('error', 'Appointment not found.');
        redirect('/admin/appointments.php');
    }
    $pet = col('pets')->findOne(['_id' => $a['pet_id']]);
    $label = ($pet['name'] ?? 'Your pet') . ' — ' . $a['service_name'] . ' on ' . fdate($a['date']) . ' at ' . ftime($a['time']);
    $closed = in_array($a['status'], ['completed', 'cancelled', 'rejected'], true);

    if ($act === 'confirm' && $a['status'] === 'pending') {
        col('appointments')->updateOne(['_id' => $id], ['$set' => ['status' => 'confirmed', 'confirmed_at' => utc_now()]]);
        notify($a['owner_id'], 'Appointment confirmed ✅', $label . ' is confirmed. See you then!', '/user/appointments.php', 'success');
        flash('success', 'Appointment confirmed and the client was notified.');
    } elseif ($act === 'reject' && !$closed) {
        col('appointments')->updateOne(['_id' => $id], ['$set' => ['status' => 'rejected', 'active' => false, 'admin_note' => trim($_POST['note'] ?? '')]]);
        notify($a['owner_id'], 'Appointment declined', $label . ' could not be accommodated. Please book another time.', '/user/book.php', 'info');
        flash('success', 'Appointment declined. The time slot is open again.');
    } elseif ($act === 'cancel' && !$closed) {
        col('appointments')->updateOne(['_id' => $id], ['$set' => ['status' => 'cancelled', 'active' => false, 'cancelled_at' => utc_now(), 'cancelled_by' => 'admin']]);
        notify($a['owner_id'], 'Appointment cancelled', $label . ' was cancelled by the clinic. Please book another time.', '/user/book.php', 'info');
        flash('success', 'Appointment cancelled. The time slot is open again.');
    } elseif ($act === 'complete' && !$closed) {
        col('appointments')->updateOne(['_id' => $id], ['$set' => ['status' => 'completed', 'completed_at' => utc_now()]]);
        notify($a['owner_id'], 'Visit completed', 'Thanks for visiting! How was ' . ($pet['name'] ?? 'your pet') . '\'s visit? Please rate it.', '/user/feedback.php?appointment=' . $a['_id'], 'success');
        flash('success', 'Marked as completed. You can now add a record and an invoice.');
    } elseif ($act === 'remind' && !$closed) {
        notify($a['owner_id'], 'Appointment reminder', 'Reminder: ' . $label . '.', '/user/appointments.php', 'reminder');
        flash('success', 'Reminder sent to the client.');
    } else {
        flash('error', 'That action is not available for this appointment.');
    }
    redirect('/admin/appointments.php?' . http_build_query(array_filter(['scope' => $_POST['scope'] ?? '', 'status' => $_POST['fstatus'] ?? '', 'date' => $_POST['fdate'] ?? ''])));
}

$scope = in_array($_GET['scope'] ?? '', ['upcoming', 'past', 'all'], true) ? $_GET['scope'] : 'upcoming';
$fstatus = in_array($_GET['status'] ?? '', ['pending', 'confirmed', 'completed', 'cancelled', 'rejected'], true) ? $_GET['status'] : '';
$fdateV = valid_date((string)($_GET['date'] ?? '')) ? $_GET['date'] : '';

$filter = [];
$sort = ['starts_at' => 1];
if ($scope === 'upcoming') {
    $filter['starts_at'] = ['$gte' => new MongoDB\BSON\UTCDateTime(strtotime('today') * 1000)];
} elseif ($scope === 'past') {
    $filter['starts_at'] = ['$lt' => new MongoDB\BSON\UTCDateTime(strtotime('today') * 1000)];
    $sort = ['starts_at' => -1];
} else {
    $sort = ['starts_at' => -1];
}
if ($fstatus) $filter['status'] = $fstatus;
if ($fdateV) $filter['date'] = $fdateV;

$appts = all('appointments', $filter, ['sort' => $sort, 'limit' => 300]);
$pets = lookup('pets', array_column($appts, 'pet_id'));
$owners = lookup('users', array_column($appts, 'owner_id'));
$vets = lookup('vets', array_column($appts, 'vet_id'));
$invMap = [];
foreach (all('invoices', ['appointment_id' => ['$in' => array_column($appts, '_id')]], ['projection' => ['appointment_id' => 1]]) as $i) $invMap[(string)$i['appointment_id']] = $i['_id'];

page_start('Appointments', 'admin');
page_header('Appointment management', 'Confirm, decline, complete or cancel client bookings.');
?>
<form class="filters card" method="get">
    <div class="field"><label>Show</label>
        <select name="scope"><?php foreach (['upcoming' => 'Today & upcoming', 'past' => 'Past', 'all' => 'All'] as $k => $l): ?><option value="<?= $k ?>" <?= $scope === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Status</label>
        <select name="status"><option value="">Any status</option><?php foreach (['pending', 'confirmed', 'completed', 'cancelled', 'rejected'] as $s): ?><option value="<?= $s ?>" <?= $fstatus === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Date</label><input type="date" name="date" value="<?= e($fdateV) ?>"></div>
    <button class="btn btn-primary">Filter</button>
    <a class="btn btn-ghost" href="?">Reset</a>
</form>

<?php if (!$appts): empty_state('📭', 'No appointments match', 'Try changing the filters.'); else: ?>
    <div class="card flush"><div class="table-wrap"><table>
        <thead><tr><th>When</th><th>Client / Pet</th><th>Service</th><th>Veterinarian</th><th>Status</th><th class="r">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($appts as $a):
            $p = $pets[(string)$a['pet_id']] ?? null; $o = $owners[(string)$a['owner_id']] ?? null; $v = $vets[(string)$a['vet_id']] ?? null;
            $closed = in_array($a['status'], ['completed', 'cancelled', 'rejected'], true);
            $keep = ['scope' => $scope, 'fstatus' => $fstatus, 'fdate' => $fdateV]; ?>
            <tr>
                <td><strong><?= e(fdate($a['date'])) ?></strong><br><span class="muted small"><?= e(ftime($a['time'])) ?></span></td>
                <td><strong><?= e($o['name'] ?? '—') ?></strong><br><span class="muted small"><?= e($p ? pet_emoji($p['species']) . ' ' . $p['name'] : '—') ?> · <?= e($o['phone'] ?? '') ?></span></td>
                <td><?= e($a['service_name']) ?><br><span class="muted small"><?= peso($a['price']) ?></span></td>
                <td><?= e($v['name'] ?? '—') ?></td>
                <td><?= badge($a['status']) ?></td>
                <td class="r"><div class="btn-group">
                    <?php if ($a['status'] === 'pending'): ?><?= post_btn('Confirm', ['action' => 'confirm', 'id' => $a['_id']] + $keep, 'btn btn-sm btn-primary') ?><?php endif; ?>
                    <?php if (!$closed): ?>
                        <?= post_btn('Complete', ['action' => 'complete', 'id' => $a['_id']] + $keep, 'btn btn-sm btn-ghost') ?>
                        <?= post_btn('Remind', ['action' => 'remind', 'id' => $a['_id']] + $keep, 'btn btn-sm btn-ghost') ?>
                        <?= post_btn($a['status'] === 'pending' ? 'Decline' : 'Cancel', ['action' => $a['status'] === 'pending' ? 'reject' : 'cancel', 'id' => $a['_id']] + $keep, 'btn btn-sm btn-danger-ghost', 'Free this time slot and notify the client?') ?>
                    <?php endif; ?>
                    <?php if ($a['status'] === 'completed'): ?>
                        <a class="btn btn-sm btn-ghost" href="<?= e(url('/admin/records.php?new=1&pet=' . $a['pet_id'] . '&appointment=' . $a['_id'])) ?>">+ Record</a>
                        <?php if (!empty($invMap[(string)$a['_id']])): ?>
                            <a class="btn btn-sm btn-ghost" href="<?= e(url('/admin/invoice.php?id=' . $invMap[(string)$a['_id']])) ?>">Invoice</a>
                        <?php else: ?>
                            <a class="btn btn-sm btn-accent" href="<?= e(url('/admin/billing.php?new=1&appointment=' . $a['_id'])) ?>">+ Invoice</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div></td>
            </tr>
            <?php if (!empty($a['notes'])): ?><tr class="sub"><td colspan="6" class="muted small">📝 Client note: <?= e($a['notes']) ?></td></tr><?php endif; ?>
        <?php endforeach; ?>
        </tbody></table></div></div>
<?php endif;
page_end('admin'); ?>
