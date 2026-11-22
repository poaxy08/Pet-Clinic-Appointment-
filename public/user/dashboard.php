<?php
require __DIR__ . '/../../app/bootstrap.php';
$u = require_role('user');
$uid = $u['_id'];

$petCount = col('pets')->countDocuments(['owner_id' => $uid]);
$upcoming = all('appointments', [
    'owner_id' => $uid, 'active' => true, 'status' => ['$in' => ['pending', 'confirmed']], 'starts_at' => ['$gte' => utc_now()],
], ['sort' => ['starts_at' => 1], 'limit' => 5]);
$pets = lookup('pets', array_column($upcoming, 'pet_id'));
$vets = lookup('vets', array_column($upcoming, 'vet_id'));

$balance = 0.0;
foreach (all('invoices', ['owner_id' => $uid]) as $inv) $balance += inv_calc($inv)['balance'];

$unread = unread_count($uid);
$recent = all('notifications', ['user_id' => $uid], ['sort' => ['created_at' => -1], 'limit' => 4]);

$pendingFeedback = 0;
foreach (all('appointments', ['owner_id' => $uid, 'status' => 'completed'], ['projection' => ['_id' => 1]]) as $a) {
    if (!col('feedback')->countDocuments(['appointment_id' => $a['_id']])) $pendingFeedback++;
}

$soon = null;
if ($upcoming && $upcoming[0]['starts_at']->toDateTime()->getTimestamp() - time() < 86400) $soon = $upcoming[0];

page_start('Dashboard', 'user');
?>
<div class="welcome card-grad">
    <div>
        <h2>Hello, <?= e(explode(' ', $u['name'])[0]) ?> 👋</h2>
        <p>Here's what's happening with your pets today.</p>
    </div>
    <a class="btn btn-accent" href="<?= e(url('/user/book.php')) ?>">+ Book appointment</a>
</div>

<?php if ($soon): $sp = $pets[(string)$soon['pet_id']] ?? null; ?>
    <div class="alert alert-warn">⏰ <b>Coming up soon:</b> <?= e($sp['name'] ?? 'Your pet') ?> — <?= e($soon['service_name']) ?> on <?= e(fdate($soon['date'])) ?> at <?= e(ftime($soon['time'])) ?>.</div>
<?php endif; ?>

<div class="grid g4 stats">
    <div class="stat"><span class="s-ico">🐾</span><div><b><?= $petCount ?></b><small>Registered pets</small></div></div>
    <div class="stat"><span class="s-ico">📅</span><div><b><?= count($upcoming) ?></b><small>Upcoming visits</small></div></div>
    <div class="stat"><span class="s-ico">💳</span><div><b><?= peso($balance) ?></b><small>Balance due</small></div></div>
    <div class="stat"><span class="s-ico">🔔</span><div><b><?= $unread ?></b><small>Unread notifications</small></div></div>
</div>

<div class="grid g2">
    <div class="card">
        <div class="card-head"><h3>Upcoming appointments</h3><a href="<?= e(url('/user/appointments.php')) ?>">View all</a></div>
        <?php if (!$upcoming): empty_state('📅', 'No upcoming visits', 'Book an appointment to get started.', '<a class="btn btn-primary btn-sm" href="' . e(url('/user/book.php')) . '">Book now</a>'); else: ?>
            <ul class="list">
                <?php foreach ($upcoming as $a): $p = $pets[(string)$a['pet_id']] ?? null; $v = $vets[(string)$a['vet_id']] ?? null; ?>
                    <li>
                        <div class="date-chip"><b><?= e(date('d', strtotime($a['date']))) ?></b><small><?= e(date('M', strtotime($a['date']))) ?></small></div>
                        <div class="grow"><strong><?= e(($p ? pet_emoji($p['species']) . ' ' . $p['name'] : 'Pet') . ' · ' . $a['service_name']) ?></strong>
                            <small class="muted"><?= e(ftime($a['time'])) ?> with <?= e($v['name'] ?? 'Veterinarian') ?></small></div>
                        <?= badge($a['status']) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <div class="card">
        <div class="card-head"><h3>Recent notifications</h3><a href="<?= e(url('/user/notifications.php')) ?>">View all</a></div>
        <?php if (!$recent): empty_state('🔔', 'Nothing yet', 'Reminders and updates will show up here.'); else: ?>
            <ul class="list">
                <?php foreach ($recent as $n): ?>
                    <li class="<?= $n['read'] ? '' : 'unread' ?>"><span class="n-ico"><?= $n['type'] === 'reminder' ? '⏰' : ($n['type'] === 'success' ? '✅' : 'ℹ️') ?></span>
                        <div class="grow"><strong><?= e($n['title']) ?></strong><small class="muted"><?= e($n['message']) ?></small></div></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php if ($pendingFeedback > 0): ?>
    <div class="alert alert-info">⭐ You have <?= $pendingFeedback ?> completed visit<?= $pendingFeedback > 1 ? 's' : '' ?> waiting for your rating. <a href="<?= e(url('/user/feedback.php')) ?>">Leave feedback</a></div>
<?php endif; ?>
<?php page_end('user'); ?>
