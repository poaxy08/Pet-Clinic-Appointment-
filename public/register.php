<?php
require __DIR__ . '/../app/bootstrap.php';
if ($cu = current_user()) redirect(home_for($cu));

$errors = [];
$old = ['name' => '', 'email' => '', 'phone' => '', 'address' => ''];

if (is_post()) {
    csrf_verify();
    $old = [
        'name' => trim($_POST['name'] ?? ''),
        'email' => strtolower(trim($_POST['email'] ?? '')),
        'phone' => trim($_POST['phone'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
    ];
    $pw = (string)($_POST['password'] ?? '');
    $pw2 = (string)($_POST['password2'] ?? '');

    if (mb_strlen($old['name']) < 2) $errors[] = 'Please enter your full name.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $old['phone'])) $errors[] = 'Please enter a valid mobile number (e.g. 0917 123 4567).';
    if (strlen($pw) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($pw !== $pw2) $errors[] = 'Passwords do not match.';
    if (empty($_POST['terms'])) $errors[] = 'Please accept the terms to continue.';

    if (!$errors) {
        try {
            $id = col('users')->insertOne([
                'name' => $old['name'], 'email' => $old['email'], 'phone' => $old['phone'],
                'address' => $old['address'], 'password' => password_hash($pw, PASSWORD_DEFAULT),
                'role' => 'user', 'active' => true, 'created_at' => utc_now(),
            ])->getInsertedId();
        } catch (Throwable $e) {
            if (is_dup($e)) $errors[] = 'That email is already registered. Try signing in instead.';
            else throw $e;
        }
    }
    if (!$errors) {
        session_regenerate_id(true);
        $_SESSION['uid'] = (string)$id;
        notify($id, 'Welcome to ' . cfg('CLINIC_NAME', 'PetCare') . '! 🐾', 'Start by registering your pet, then book your first visit.', '/user/pets.php?new=1', 'info');
        notify_admins('New client registered', $old['name'] . ' just created an account.', '/admin/clients.php', 'info');
        flash('success', 'Account created. Welcome, ' . explode(' ', $old['name'])[0] . '!');
        redirect('/user/pets.php?new=1');
    }
}

page_start('Create account', 'auth');
?>
<div class="auth-box">
    <h1>Create your account</h1>
    <p class="muted">Pet owners can register pets, book visits and view records.</p>
    <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
    <form method="post" class="form" autocomplete="on">
        <?= csrf_field() ?>
        <div class="field"><label>Full name</label><input name="name" value="<?= e($old['name']) ?>" required placeholder="Juan Dela Cruz"></div>
        <div class="form-row">
            <div class="field"><label>Email</label><input type="email" name="email" value="<?= e($old['email']) ?>" required placeholder="you@email.com"></div>
            <div class="field"><label>Mobile number</label><input name="phone" value="<?= e($old['phone']) ?>" required placeholder="0917 123 4567"></div>
        </div>
        <div class="field"><label>Address <span class="muted">(optional)</span></label><input name="address" value="<?= e($old['address']) ?>" placeholder="Street, Barangay, City"></div>
        <div class="form-row">
            <div class="field"><label>Password</label><input type="password" name="password" minlength="8" required placeholder="At least 8 characters"></div>
            <div class="field"><label>Confirm password</label><input type="password" name="password2" minlength="8" required></div>
        </div>
        <label class="check"><input type="checkbox" name="terms" value="1"> I agree to the clinic's terms and privacy policy.</label>
        <button class="btn btn-primary btn-block btn-lg">Create account</button>
    </form>
    <p class="center muted">Already have an account? <a href="<?= e(url('/login.php')) ?>">Sign in</a></p>
</div>
<?php page_end('auth'); ?>
