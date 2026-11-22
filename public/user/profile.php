<?php
require __DIR__ . '/../../app/bootstrap.php';
$u = require_role('user');
$uid = $u['_id'];

if (is_post()) {
    csrf_verify();
    $act = $_POST['action'] ?? '';

    if ($act === 'profile') {
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');
        $err = '';
        if (mb_strlen($name) < 2) $err = 'Please enter your full name.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Please enter a valid email address.';
        elseif (!preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) $err = 'Please enter a valid mobile number.';
        if (!$err) {
            try {
                col('users')->updateOne(['_id' => $uid], ['$set' => [
                    'name' => $name, 'email' => $email, 'phone' => $phone,
                    'alt_phone' => trim($_POST['alt_phone'] ?? ''), 'address' => trim($_POST['address'] ?? ''),
                    'emergency_contact' => trim($_POST['emergency_contact'] ?? ''), 'updated_at' => utc_now(),
                ]]);
                flash('success', 'Your profile was updated.');
            } catch (Throwable $e) {
                if (is_dup($e)) $err = 'That email is already used by another account.';
                else throw $e;
            }
        }
        if ($err) flash('error', $err);
        redirect('/user/profile.php');
    }

    if ($act === 'password') {
        $cur = (string)($_POST['current'] ?? '');
        $new = (string)($_POST['new'] ?? '');
        $new2 = (string)($_POST['new2'] ?? '');
        if (!password_verify($cur, $u['password'])) flash('error', 'Your current password is incorrect.');
        elseif (strlen($new) < 8) flash('error', 'New password must be at least 8 characters.');
        elseif ($new !== $new2) flash('error', 'New passwords do not match.');
        else {
            col('users')->updateOne(['_id' => $uid], ['$set' => ['password' => password_hash($new, PASSWORD_DEFAULT)]]);
            flash('success', 'Password changed.');
        }
        redirect('/user/profile.php');
    }
}

page_start('My Profile', 'user');
page_header('Owner profile', 'Keep your contact details up to date so we can reach you about appointments.');
?>
<div class="grid g2">
    <div class="card">
        <div class="profile-top"><span class="avatar xl"><?= e(initials($u['name'])) ?></span>
            <div><h3><?= e($u['name']) ?></h3><span class="muted">Member since <?= e(dt($u['created_at'], 'M Y')) ?></span></div></div>
        <form method="post" class="form">
            <?= csrf_field() ?><input type="hidden" name="action" value="profile">
            <div class="field"><label>Full name</label><input name="name" value="<?= e($u['name']) ?>" required></div>
            <div class="form-row">
                <div class="field"><label>Email</label><input type="email" name="email" value="<?= e($u['email']) ?>" required></div>
                <div class="field"><label>Mobile</label><input name="phone" value="<?= e($u['phone'] ?? '') ?>" required></div>
            </div>
            <div class="form-row">
                <div class="field"><label>Alternate phone</label><input name="alt_phone" value="<?= e($u['alt_phone'] ?? '') ?>"></div>
                <div class="field"><label>Emergency contact</label><input name="emergency_contact" value="<?= e($u['emergency_contact'] ?? '') ?>" placeholder="Name · phone"></div>
            </div>
            <div class="field"><label>Home address</label><input name="address" value="<?= e($u['address'] ?? '') ?>"></div>
            <button class="btn btn-primary">Save profile</button>
        </form>
    </div>
    <div class="card">
        <h3>Change password</h3>
        <form method="post" class="form">
            <?= csrf_field() ?><input type="hidden" name="action" value="password">
            <div class="field"><label>Current password</label><input type="password" name="current" required></div>
            <div class="field"><label>New password</label><input type="password" name="new" minlength="8" required></div>
            <div class="field"><label>Confirm new password</label><input type="password" name="new2" minlength="8" required></div>
            <button class="btn btn-primary">Update password</button>
        </form>
    </div>
</div>
<?php page_end('user'); ?>
