<?php
require __DIR__ . '/../../app/bootstrap.php';
$u = require_role('user');
$uid = $u['_id'];

if (is_post()) {
    csrf_verify();
    if (($_POST['action'] ?? '') === 'cancel') {
        $id = oid($_POST['id'] ?? null);
        $a = $id ? col('appointments')->findOne(['_id' => $id, 'owner_id' => $uid]) : null;
        if ($a && in_array($a['status'], ['pending', 'confirmed'], true)) {
            // active=false frees the slot so another client can book it.
            col('appointments')->updateOne(['_id' => $id], ['$set' => ['status' => 'cancelled', 'active' => false, 'cancelled_at' => utc_now(), 'cancelled_by' => 'owner']]);
            $pet = col('pets')->findOne(['_id' => $a['pet_id']]);
            notify_admins('Appointment cancelled', $u['name'] . ' cancelled ' . $a['service_name'] . ' for ' . ($pet['name'] ?? 'a pet') . ' on ' . fdate($a['date']) . ' at ' . ftime($a['time']) . '.', '/admin/appointments.php', 'info');
            flash('success', 'Your appointment was cancelled.');
        } else {
            flash('error', 'This appointment can no longer be cancelled.');
        }
        redirect('/user/appointments.php');
    }
}

$tab = in_array($_GET['tab'] ?? '', ['upcoming', 'past', 'all'], true) ? $_GET['tab'] : 'upcoming';
$filter = ['owner_id' => $uid];
$sort = ['starts_at' => -1];
if ($tab === 'upcoming') {
    $filter += ['starts_at' => ['$gte' => utc_now()], 'status' => ['$in' => ['pending', 'confirmed']]];
    $sort = ['starts_at' => 1];
} elseif ($tab === 'past') {
    $filter['$or'] = [['starts_at' => ['$lt' => utc_now()]], ['status' => ['$in' => ['completed', 'cancelled', 'rejected']]]];
}
$appts = all('appointments', $filter, ['sort' => $sort, 'limit' => 200]);
$pets = lookup('pets', array_column($appts, 'pet_id'));
$vets = lookup('vets', array_column($appts, 'vet_id'));
$fbDone = [];
foreach (all('feedback', ['owner_id' => $uid], ['projection' => ['appointment_id' => 1]]) as $f) $fbDone[(string)$f['appointment_id']] = true;
$invs = [];
foreach (all('invoices', ['owner_id' => $uid], ['projection' => ['appointment_id' => 1]]) as $i) if (!empty($i['appointment_id'])) $invs[(string)$i['appointment_id']] = $i['_id'];

page_start('My Appointments', 'user');
page_header('My appointments', 'Track, review and cancel your visits.', '<a class="btn btn-primary" href="' . e(url('/user/book.php')) . '">+ Book appointment</a>');
?>
<div class="tabs">
    <?php foreach (['upcoming' => 'Upcoming', 'past' => 'Past & closed', 'all' => 'All'] as $k => $l): ?>
        <a class="<?= $tab === $k ? 'active' : '' ?>" href="?tab=<?= $k ?>"><?= $l ?></a>
    <?php endforeach; ?>
</div>
<?php if (!$appts): empty_state('📅', 'No appointments here', 'When you book a visit it will appear in this list.', '<a class="btn btn-primary btn-sm" href="' . e(url('/user/book.php')) . '">Book now</a>'); else: ?>
    <div class="card flush">
        <div class="table-wrap">
            <table>
                <thead><tr><th>When</th><th>Pet</th><th>Service</th><th>Veterinarian</th><th>Fee</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($appts as $a): $p = $pets[(string)$a['pet_id']] ?? null; $v = $vets[(string)$a['vet_id']] ?? null; $aid = (string)$a['_id']; ?>
                    <tr>
                        <td><strong><?= e(fdate($a['date'])) ?></strong><br><span class="muted small"><?= e(ftime($a['time'])) ?></span></td>
                        <td><?= e($p ? pet_emoji($p['species']) . ' ' . $p['name'] : '—') ?></td>
                        <td><?= e($a['service_name']) ?></td>
                        <td><?= e($v['name'] ?? '—') ?></td>
                        <td><?= peso($a['price']) ?></td>
                        <td><?= badge($a['status']) ?></td>
                        <td class="r nowrap">
                            <?php if (in_array($a['status'], ['pending', 'confirmed'], true)): ?>
                                <?= post_btn('Cancel', ['action' => 'cancel', 'id' => $a['_id']], 'btn btn-sm btn-danger-ghost', 'Cancel this appointment?') ?>
                            <?php endif; ?>
                            <?php if ($a['status'] === 'completed' && empty($fbDone[$aid])): ?>
                                <a class="btn btn-sm btn-accent" href="<?= e(url('/user/feedback.php?appointment=' . $aid)) ?>">Rate</a>
                            <?php endif; ?>
                            <?php if (!empty($invs[$aid])): ?>
                                <a class="btn btn-sm btn-ghost" href="<?= e(url('/user/invoice.php?id=' . $invs[$aid])) ?>">Invoice</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if (!empty($a['notes'])): ?><tr class="sub"><td colspan="7" class="muted small">📝 <?= e($a['notes']) ?></td></tr><?php endif; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif;
page_end('user'); ?>
