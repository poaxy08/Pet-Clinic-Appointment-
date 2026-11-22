<?php
// Shared by /user/notifications.php and /admin/notifications.php. Expects $area and $u.
$uid = $u['_id'];

if (isset($_GET['go'])) {                       // open a notification: mark read, then follow its link
    $nid = oid($_GET['go']);
    $n = $nid ? col('notifications')->findOne(['_id' => $nid, 'user_id' => $uid]) : null;
    if ($n) {
        col('notifications')->updateOne(['_id' => $nid], ['$set' => ['read' => true]]);
        redirect($n['link'] ?: '/' . $area . '/notifications.php');
    }
    redirect('/' . $area . '/notifications.php');
}

if (is_post()) {
    csrf_verify();
    $act = $_POST['action'] ?? '';
    if ($act === 'read_all') {
        col('notifications')->updateMany(['user_id' => $uid, 'read' => false], ['$set' => ['read' => true]]);
        flash('success', 'All notifications marked as read.');
    } elseif ($act === 'clear') {
        col('notifications')->deleteMany(['user_id' => $uid, 'read' => true]);
        flash('success', 'Read notifications cleared.');
    }
    redirect('/' . $area . '/notifications.php');
}

$list = all('notifications', ['user_id' => $uid], ['sort' => ['created_at' => -1], 'limit' => 100]);
page_start('Notifications', $area);
page_header('Notifications', 'Appointment reminders, confirmations and updates.',
    post_btn('Mark all read', ['action' => 'read_all'], 'btn btn-ghost') . post_btn('Clear read', ['action' => 'clear'], 'btn btn-ghost', 'Delete all read notifications?'));
if (!$list) {
    empty_state('🔔', 'You\'re all caught up', 'New notifications will appear here.');
} else { ?>
    <div class="card flush">
        <ul class="list notif">
            <?php foreach ($list as $n): ?>
                <li class="<?= $n['read'] ? '' : 'unread' ?>">
                    <span class="n-ico"><?= $n['type'] === 'reminder' ? '⏰' : ($n['type'] === 'success' ? '✅' : 'ℹ️') ?></span>
                    <a class="grow" href="?go=<?= e($n['_id']) ?>"><strong><?= e($n['title']) ?></strong><small class="muted"><?= e($n['message']) ?></small></a>
                    <span class="muted small nowrap"><?= e(dt($n['created_at'], 'M j · g:i A')) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php }
page_end($area);
