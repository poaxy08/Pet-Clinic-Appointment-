<?php
require __DIR__ . '/../../app/bootstrap.php';
$u = require_role('user');
$uid = $u['_id'];

if (is_post()) {
    csrf_verify();
    $aid = oid($_POST['appointment_id'] ?? null);
    $a = $aid ? col('appointments')->findOne(['_id' => $aid, 'owner_id' => $uid, 'status' => 'completed']) : null;
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    if (!$a) {
        flash('error', 'You can only rate completed appointments.');
    } elseif ($rating < 1 || $rating > 5) {
        flash('error', 'Please choose a star rating from 1 to 5.');
    } elseif (mb_strlen($comment) < 3) {
        flash('error', 'Please write a short comment.');
    } else {
        try {
            col('feedback')->insertOne([
                'owner_id' => $uid, 'appointment_id' => $aid, 'vet_id' => $a['vet_id'], 'rating' => $rating,
                'comment' => mb_substr($comment, 0, 1000), 'public' => false, 'reply' => '', 'created_at' => utc_now(),
            ]);
            notify_admins('New feedback received', $u['name'] . ' rated their visit ' . $rating . '/5.', '/admin/feedback.php', 'info');
            flash('success', 'Thank you for your feedback!');
        } catch (Throwable $e) {
            if (is_dup($e)) flash('error', 'You already rated this appointment.');
            else throw $e;
        }
    }
    redirect('/user/feedback.php');
}

$done = [];
$mine = all('feedback', ['owner_id' => $uid], ['sort' => ['created_at' => -1]]);
foreach ($mine as $f) $done[(string)$f['appointment_id']] = true;
$eligible = array_values(array_filter(
    all('appointments', ['owner_id' => $uid, 'status' => 'completed'], ['sort' => ['starts_at' => -1]]),
    fn($a) => empty($done[(string)$a['_id']])
));
$pets = lookup('pets', array_merge(array_column($eligible, 'pet_id')));
$vetMap = lookup('vets', array_merge(array_column($eligible, 'vet_id'), array_column($mine, 'vet_id')));
$preselect = (string)($_GET['appointment'] ?? '');

page_start('Feedback', 'user');
page_header('Feedback & ratings', 'Tell us how your visit went — it helps us improve.');
?>
<div class="grid g2">
    <div class="card">
        <h3>Rate a visit</h3>
        <?php if (!$eligible): empty_state('⭐', 'Nothing to rate right now', 'Completed visits that you have not rated yet will appear here.'); else: ?>
            <form method="post" class="form">
                <?= csrf_field() ?>
                <div class="field"><label>Visit</label>
                    <select name="appointment_id" required>
                        <?php foreach ($eligible as $a): $p = $pets[(string)$a['pet_id']] ?? null; ?>
                            <option value="<?= e($a['_id']) ?>" <?= $preselect === (string)$a['_id'] ? 'selected' : '' ?>><?= e(fdate($a['date']) . ' — ' . ($p['name'] ?? 'Pet') . ' — ' . $a['service_name']) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="field"><label>Your rating</label>
                    <div class="rating">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" id="s<?= $i ?>" name="rating" value="<?= $i ?>" required><label for="s<?= $i ?>" title="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>">★</label>
                        <?php endfor; ?>
                    </div></div>
                <div class="field"><label>Comments</label><textarea name="comment" rows="4" required placeholder="What went well? What could be better?"></textarea></div>
                <button class="btn btn-primary">Submit feedback</button>
            </form>
        <?php endif; ?>
    </div>
    <div class="card">
        <h3>Your previous feedback</h3>
        <?php if (!$mine): empty_state('💬', 'No feedback yet'); else: ?>
            <ul class="list">
                <?php foreach ($mine as $f): ?>
                    <li class="col">
                        <div><?= stars((int)$f['rating']) ?> <span class="muted small"><?= e(dt($f['created_at'], 'M j, Y')) ?></span></div>
                        <p><?= e($f['comment']) ?></p>
                        <?php if (!empty($f['reply'])): ?><div class="reply"><b>Clinic reply:</b> <?= e($f['reply']) ?></div><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
<?php page_end('user'); ?>
