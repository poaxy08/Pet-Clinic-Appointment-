<?php
require __DIR__ . '/../app/bootstrap.php';
if ($cu = current_user()) redirect(home_for($cu));

$error = '';
$email = '';

if (is_post()) {
    csrf_verify();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pw = (string)($_POST['password'] ?? '');
    $user = $email !== '' ? col('users')->findOne(['email' => $email]) : null;
    $now = time();

    if ($user && !empty($user['locked_until']) && $user['locked_until']->toDateTime()->getTimestamp() > $now) {
        $error = 'Too many failed attempts. Please wait a few minutes and try again.';
    } elseif (!$user || !password_verify($pw, $user['password'])) {
        if ($user) {
            $f = (int)($user['failed'] ?? 0) + 1;
            $set = ['failed' => $f];
            if ($f >= 5) {
                $set = ['failed' => 0, 'locked_until' => new MongoDB\BSON\UTCDateTime(($now + 300) * 1000)];
            }
            col('users')->updateOne(['_id' => $user['_id']], ['$set' => $set]);
        }
        $error = 'Incorrect email or password.';
    } elseif (($user['active'] ?? true) === false) {
        $error = 'This account has been deactivated. Please contact the clinic.';
    } else {
        col('users')->updateOne(['_id' => $user['_id']], ['$set' => ['failed' => 0, 'last_login' => utc_now()], '$unset' => ['locked_until' => '']]);
        session_regenerate_id(true);
        $_SESSION['uid'] = (string)$user['_id'];
        flash('success', 'Welcome back, ' . explode(' ', $user['name'])[0] . '!');
        redirect(home_for($user));
    }
}

page_start('Sign in', 'auth');
?>
<div class="auth-box">
    <h1>Welcome back</h1>
    <p class="muted">Sign in to manage your pets and appointments. Clinic staff can sign in here too.</p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="form">
        <?= csrf_field() ?>
        <div class="field"><label>Email</label><input type="email" name="email" value="<?= e($email) ?>" required autofocus placeholder="you@email.com"></div>
        <div class="field"><label>Password</label><input type="password" name="password" required placeholder="Your password"></div>
        <button class="btn btn-primary btn-block btn-lg">Sign in</button>
    </form>
    <p class="center muted">New here? <a href="<?= e(url('/register.php')) ?>">Create an account</a></p>
</div>
<?php page_end('auth'); ?>
