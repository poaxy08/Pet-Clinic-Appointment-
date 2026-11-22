<?php
require __DIR__ . '/../../app/bootstrap.php';
$u = require_role('admin');

$today = date('Y-m-d');
$todayList = all('appointments', ['date' => $today, 'status' => ['$in' => ['pending', 'confirmed', 'completed']]], ['sort' => ['time' => 1]]);
$pending = col('appointments')->countDocuments(['status' => 'pending', 'active' => true]);
$clients = col('users')->countDocuments(['role' => 'user']);
$petCount = col('pets')->countDocuments([]);

// Revenue per month (last 6 months), from recorded payments
$monthStart = new DateTime('first day of this month 00:00:00', new DateTimeZone(TZ));
$from = (clone $monthStart)->modify('-5 months');
$agg = col('invoices')->aggregate([
    ['$unwind' => '$payments'],
    ['$match' => ['payments.paid_at' => ['$gte' => new MongoDB\BSON\UTCDateTime($from->getTimestamp() * 1000)]]],
    ['$group' => ['_id' => ['$dateToString' => ['format' => '%Y-%m', 'date' => '$payments.paid_at', 'timezone' => TZ]], 'total' => ['$sum' => '$payments.amount']]],
]);
$rev = [];
foreach ($agg as $row) $rev[$row['_id']] = (float)$row['total'];
$months = [];
for ($i = 0; $i < 6; $i++) {
    $d = (clone $from)->modify("+$i months");
    $months[$d->format('Y-m')] = ['label' => $d->format('M'), 'v' => $rev[$d->format('Y-m')] ?? 0.0];
}
$maxRev = max(1.0, max(array_column($months, 'v')));
$thisMonth = $months[$monthStart->format('Y-m')]['v'];

$outstanding = 0.0;
foreach (all('invoices') as $inv) $outstanding += inv_calc($inv)['balance'];

$fb = col('feedback')->aggregate([['$group' => ['_id' => null, 'avg' => ['$avg' => '$rating'], 'n' => ['$sum' => 1]]]])->toArray();
$avg = $fb ? round((float)$fb[0]['avg'], 1) : null;

$recent = all('appointments', [], ['sort' => ['created_at' => -1], 'limit' => 6]);
$pets = lookup('pets', array_merge(array_column($todayList, 'pet_id'), array_column($recent, 'pet_id')));
$owners = lookup('users', array_merge(array_column($todayList, 'owner_id'), array_column($recent, 'owner_id')));
$vets = lookup('vets', array_merge(array_column($todayList, 'vet_id'), array_column($recent, 'vet_id')));

page_start('Dashboard', 'admin');
?>
<div class="welcome card-grad">
    <div><h2>Good day, <?= e(explode(' ', $u['name'])[0]) ?> 👋</h2><p><?= e(date('l, F j, Y')) ?> · <?= count($todayList) ?> appointment<?= count($todayList) === 1 ? '' : 's' ?> today</p></div>
    <a class="btn btn-accent" href="<?= e(url('/admin/appointments.php')) ?>">Manage appointments</a>
</div>

<div class="grid g4 stats">
    <div class="stat <?= $pending ? 'warn' : '' ?>"><span class="s-ico">⏳</span><div><b><?= $pending ?></b><small>Pending approval</small></div></div>
    <div class="stat"><span class="s-ico">👥</span><div><b><?= $clients ?></b><small>Clients</small></div></div>
    <div class="stat"><span class="s-ico">🐾</span><div><b><?= $petCount ?></b><small>Registered pets</small></div></div>
    <div class="stat"><span class="s-ico">⭐</span><div><b><?= $avg !== null ? $avg . ' / 5' : '—' ?></b><small>Avg. rating (<?= $fb ? (int)$fb[0]['n'] : 0 ?>)</small></div></div>
</div>
<div class="grid g3 stats">
    <div class="stat"><span class="s-ico">💰</span><div><b><?= peso($thisMonth) ?></b><small>Collected this month</small></div></div>
    <div class="stat <?= $outstanding > 0 ? 'warn' : '' ?>"><span class="s-ico">🧾</span><div><b><?= peso($outstanding) ?></b><small>Outstanding balance</small></div></div>
    <div class="stat"><span class="s-ico">📅</span><div><b><?= count($todayList) ?></b><small>Appointments today</small></div></div>
</div>

<div class="grid g2">
    <div class="card">
        <div class="card-head"><h3>Today's schedule</h3><a href="<?= e(url('/admin/appointments.php?scope=all&date=' . $today)) ?>">Open</a></div>
        <?php if (!$todayList): empty_state('☀️', 'No appointments today'); else: ?>
            <ul class="list">
                <?php foreach ($todayList as $a): $p = $pets[(string)$a['pet_id']] ?? null; $o = $owners[(string)$a['owner_id']] ?? null; $v = $vets[(string)$a['vet_id']] ?? null; ?>
                    <li><div class="date-chip"><b><?= e(date('g:i', strtotime($a['time']))) ?></b><small><?= e(date('A', strtotime($a['time']))) ?></small></div>
                        <div class="grow"><strong><?= e(($p ? pet_emoji($p['species']) . ' ' . $p['name'] : 'Pet') . ' · ' . $a['service_name']) ?></strong>
                            <small class="muted"><?= e($o['name'] ?? '') ?> · <?= e($v['name'] ?? '') ?></small></div><?= badge($a['status']) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <div class="card">
        <div class="card-head"><h3>Revenue — last 6 months</h3><span class="muted small">₱ collected</span></div>
        <div class="bars">
            <?php foreach ($months as $m): ?>
                <div class="bar"><span class="val"><?= $m['v'] > 0 ? '₱' . number_format($m['v'], 0) : '' ?></span><i style="height:<?= max(4, round($m['v'] / $maxRev * 100)) ?>%"></i><em><?= e($m['label']) ?></em></div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-head"><h3>Latest booking requests</h3><a href="<?= e(url('/admin/appointments.php')) ?>">View all</a></div>
    <?php if (!$recent): empty_state('📭', 'No bookings yet'); else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>When</th><th>Client</th><th>Pet</th><th>Service</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $a): $p = $pets[(string)$a['pet_id']] ?? null; $o = $owners[(string)$a['owner_id']] ?? null; ?>
                <tr><td><?= e(fdate($a['date'])) ?> · <?= e(ftime($a['time'])) ?></td><td><?= e($o['name'] ?? '—') ?></td><td><?= e($p['name'] ?? '—') ?></td><td><?= e($a['service_name']) ?></td><td><?= badge($a['status']) ?></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
    <?php endif; ?>
</div>
<?php page_end('admin'); ?>
