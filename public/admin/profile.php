<?php
require __DIR__ . '/../../app/bootstrap.php';
$u = require_role('admin');
$uid = $u['_id'];

if (is_post()) {
    csrf_verify();
    $act = $_POST['action'] ?? '';
    if ($act === 'profile') {
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Please enter a valid name and email.');
        } else {
            try {
                col('users')->updateOne(['_id' => $uid], ['$set' => ['name' => $name, 'email' => $email, 'phone' => trim($_POST['phone'] ?? '')]]);
                flash('success', 'Account updated.');
            } catch (Throwable $e) {
                if (is_dup($e)) flash('error', 'That email is already in use.');
                else throw $e;
            }
        }
    } elseif ($act === 'password') {
        $new = (string)($_POST['new'] ?? '');
        if (!password_verify((string)($_POST['current'] ?? ''), $u['password'])) flash('error', 'Your current password is incorrect.');
        elseif (strlen($new) < 8) flash('error', 'New password must be at least 8 characters.');
        elseif ($new !== (string)($_POST['new2'] ?? '')) flash('error', 'New passwords do not match.');
        else {
            col('users')->updateOne(['_id' => $uid], ['$set' => ['password' => password_hash($new, PASSWORD_DEFAULT)]]);
            flash('success', 'Password changed.');
        }
    }
    redirect('/admin/profile.php');
}

page_start('My Account', 'admin');
page_header('Administrator account', 'Update your details and change your password.');
?>
<div class="grid g2">
    <div class="card">
        <form method="post" class="form"><?= csrf_field() ?><input type="hidden" name="action" value="profile">
            <div class="field"><label>Name</label><input name="name" value="<?= e($u['name']) ?>" required></div>
            <div class="form-row">
                <div class="field"><label>Email (used to sign in)</label><input type="email" name="email" value="<?= e($u['email']) ?>" required></div>
                <div class="field"><label>Phone</label><input name="phone" value="<?= e($u['phone'] ?? '') ?>"></div>
            </div>
            <button class="btn btn-primary">Save</button>
        </form>
    </div>
    <div class="card">
        <h3>Change password</h3>
        <form method="post" class="form"><?= csrf_field() ?><input type="hidden" name="action" value="password">
            <div class="field"><label>Current password</label><input type="password" name="current" required></div>
            <div class="field"><label>New password</label><input type="password" name="new" minlength="8" required></div>
            <div class="field"><label>Confirm new password</label><input type="password" name="new2" minlength="8" required></div>
            <button class="btn btn-primary">Update password</button>
        </form>
    </div>
</div>
<?php page_end('admin'); ?>
