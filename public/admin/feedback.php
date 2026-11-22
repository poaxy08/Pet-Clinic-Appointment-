<?php
require __DIR__ . '/../../app/bootstrap.php';
$u = require_role('admin');

if (is_post()) {
    csrf_verify();
    $act = $_POST['action'] ?? '';
    $id = oid($_POST['id'] ?? null);
    $f = $id ? col('feedback')->findOne(['_id' => $id]) : null;
    if ($f) {
        if ($act === 'publish') {
            $new = empty($f['public']);
            col('feedback')->updateOne(['_id' => $id], ['$set' => ['public' => $new]]);
            flash('success', $new ? 'Review is now shown on the website.' : 'Review removed from the website.');
        } elseif ($act === 'reply') {
            $reply = trim($_POST['reply'] ?? '');
            col('feedback')->updateOne(['_id' => $id], ['$set' => ['reply' => mb_substr($reply, 0, 1000)]]);
            if ($reply !== '') notify($f['owner_id'], 'The clinic replied to your feedback', $reply, '/user/feedback.php', 'info');
            flash('success', 'Reply saved.');
        } elseif ($act === 'delete') {
            col('feedback')->deleteOne(['_id' => $id]);
            flash('success', 'Feedback deleted.');
        }
    }
    redirect('/admin/feedback.php');
}

$list = all('feedback', [], ['sort' => ['created_at' => -1], 'limit' => 200]);
$owners = lookup('users', array_column($list, 'owner_id'));
$vets = lookup('vets', array_column($list, 'vet_id'));
$appts = lookup('appointments', array_column($list, 'appointment_id'));
$n = count($list);
$avg = $n ? round(array_sum(array_column($list, 'rating')) / $n, 1) : null;
$dist = array_fill(1, 5, 0);
foreach ($list as $f) $dist[(int)$f['rating']]++;

page_start('Feedback', 'admin');
page_header('Customer feedback & ratings', 'Reply to clients and choose which reviews appear on the public website.');
?>
<div class="grid g2">
    <div class="card"><div class="score"><b><?= $avg !== null ? $avg : '—' ?></b><div><?= $avg !== null ? stars((int)round($avg)) : '' ?><br><span class="muted small"><?= $n ?> rating<?= $n === 1 ? '' : 's' ?></span></div></div></div>
    <div class="card dist">
        <?php for ($i = 5; $i >= 1; $i--): ?>
            <div class="dist-row"><span><?= $i ?> ★</span><div class="meter"><i style="width:<?= $n ? round($dist[$i] / $n * 100) : 0 ?>%"></i></div><span><?= $dist[$i] ?></span></div>
        <?php endfor; ?>
    </div>
</div>
<?php if (!$list): empty_state('⭐', 'No feedback yet', 'Clients can rate completed visits.'); else: ?>
    <div class="stack">
        <?php foreach ($list as $f): $o = $owners[(string)$f['owner_id']] ?? null; $v = $vets[(string)($f['vet_id'] ?? '')] ?? null; $a = $appts[(string)$f['appointment_id']] ?? null; ?>
            <div class="card">
                <div class="tl-head">
                    <div class="cell-av"><span class="avatar"><?= e(initials($o['name'] ?? '?')) ?></span>
                        <div><strong><?= e($o['name'] ?? 'Client') ?></strong><br><span class="muted small"><?= $a ? e($a['service_name'] . ' · ' . fdate($a['date'])) : '' ?><?= $v ? ' · ' . e($v['name']) : '' ?></span></div></div>
                    <div><?= stars((int)$f['rating']) ?> <?= !empty($f['public']) ? '<span class="pill">On website</span>' : '' ?></div>
                </div>
                <p>“<?= e($f['comment']) ?>”</p>
                <form method="post" class="reply-form">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($f['_id']) ?>"><input type="hidden" name="action" value="reply">
                    <input name="reply" value="<?= e($f['reply'] ?? '') ?>" placeholder="Write a reply to the client…">
                    <button class="btn btn-sm btn-primary">Save reply</button>
                </form>
                <div class="btn-group">
                    <?= post_btn(!empty($f['public']) ? 'Hide from website' : 'Show on website', ['action' => 'publish', 'id' => $f['_id']], 'btn btn-sm btn-ghost') ?>
                    <?= post_btn('Delete', ['action' => 'delete', 'id' => $f['_id']], 'btn btn-sm btn-danger-ghost', 'Delete this feedback?') ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif;
page_end('admin'); ?>
