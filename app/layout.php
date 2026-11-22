<?php
// Page chrome: public website, auth screens, and the user/admin dashboards.

function nav_items(string $area): array
{
    if ($area === 'admin') {
        return [
            ['/admin/dashboard.php', 'Dashboard', '📊', []],
            ['/admin/appointments.php', 'Appointments', '📅', []],
            ['/admin/clients.php', 'Clients', '👥', []],
            ['/admin/pets.php', 'Pets', '🐾', []],
            ['/admin/records.php', 'Medical Records', '🩺', []],
            ['/admin/vets.php', 'Veterinarians', '🧑‍⚕️', []],
            ['/admin/services.php', 'Services', '💉', []],
            ['/admin/billing.php', 'Billing', '💳', ['/admin/invoice.php']],
            ['/admin/feedback.php', 'Feedback', '⭐', []],
            ['/admin/notifications.php', 'Notifications', '🔔', []],
            ['/admin/profile.php', 'My Account', '⚙️', []],
        ];
    }
    return [
        ['/user/dashboard.php', 'Dashboard', '🏠', []],
        ['/user/pets.php', 'My Pets', '🐾', []],
        ['/user/book.php', 'Book Appointment', '📅', []],
        ['/user/appointments.php', 'My Appointments', '🗓️', []],
        ['/user/records.php', 'Medical Records', '🩺', []],
        ['/user/billing.php', 'Billing', '💳', ['/user/invoice.php']],
        ['/user/feedback.php', 'Feedback', '⭐', []],
        ['/user/notifications.php', 'Notifications', '🔔', []],
        ['/user/profile.php', 'My Profile', '⚙️', []],
    ];
}

function render_flash(): void
{
    echo '<div class="toasts" id="toasts">';
    foreach (take_flash() as $f) {
        echo '<div class="toast t-' . e($f['t']) . '">' . e($f['m']) . '<button type="button" class="x" aria-label="Close">×</button></div>';
    }
    echo '</div>';
}

function page_start(string $title, string $area = 'public'): void
{
    $u = current_user();
    $clinic = cfg('CLINIC_NAME', 'PetCare Veterinary Clinic');
    if ($u && ($area === 'user' || $area === 'admin')) run_reminders();
    $v = @filemtime(ROOT . '/public/assets/style.css') ?: 1;
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= e($clinic) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('/assets/style.css')) ?>?v=<?= $v ?>">
<script>window.APP={base:<?= json_encode(rtrim((string)cfg('APP_BASE', ''), '/')) ?>,csrf:<?= json_encode(csrf_token()) ?>};</script>
</head>
<body class="area-<?= e($area) ?>">
<?php render_flash(); ?>
<?php if ($area === 'public'): ?>
    <header class="site-nav" id="siteNav">
        <div class="container nav-in">
            <a class="brand" href="<?= e(url('/')) ?>"><span class="logo">🐾</span><span><?= e($clinic) ?></span></a>
            <button class="burger" id="burger" aria-label="Menu">☰</button>
            <nav class="links" id="navLinks">
                <a href="<?= e(url('/')) ?>#services">Services</a>
                <a href="<?= e(url('/')) ?>#vets">Veterinarians</a>
                <a href="<?= e(url('/')) ?>#how">How it works</a>
                <a href="<?= e(url('/')) ?>#reviews">Reviews</a>
                <?php if ($u): ?>
                    <a class="btn btn-primary btn-sm" href="<?= e(url(home_for($u))) ?>">Go to dashboard</a>
                <?php else: ?>
                    <a class="btn btn-ghost btn-sm" href="<?= e(url('/login.php')) ?>">Sign in</a>
                    <a class="btn btn-primary btn-sm" href="<?= e(url('/register.php')) ?>">Create account</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <main>
<?php elseif ($area === 'auth'): ?>
    <div class="auth">
        <aside class="auth-art">
            <a class="brand light" href="<?= e(url('/')) ?>"><span class="logo">🐾</span><span><?= e($clinic) ?></span></a>
            <div class="auth-copy">
                <h2>Care that wags,<br>purrs and chirps.</h2>
                <p>Book visits, keep medical records and manage billing — all in one place.</p>
                <ul class="ticks">
                    <li>Real-time slot availability</li>
                    <li>Appointment reminders</li>
                    <li>Medical history for every pet</li>
                </ul>
            </div>
            <div class="paws" aria-hidden="true">🐾 🐾 🐾</div>
        </aside>
        <section class="auth-form">
<?php else:
    $items = nav_items($area);
    $self = $_SERVER['SCRIPT_NAME'] ?? '';
    $unread = $u ? unread_count($u['_id']) : 0;
    ?>
    <div class="app">
        <aside class="sidebar" id="sidebar">
            <a class="brand light" href="<?= e(url('/')) ?>">
                <span class="logo">🐾</span>
                <span>PetCare<small><?= $area === 'admin' ? 'Admin Console' : 'Pet Owner Portal' ?></small></span>
            </a>
            <nav>
                <?php foreach ($items as [$path, $label, $icon, $extra]):
                    $active = (url($path) === $self) || in_array($self, array_map('url', $extra), true); ?>
                    <a href="<?= e(url($path)) ?>" class="<?= $active ? 'active' : '' ?>">
                        <span class="ico"><?= $icon ?></span><?= e($label) ?>
                        <?php if ($label === 'Notifications' && $unread > 0): ?><em class="count"><?= $unread ?></em><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="side-foot">
                <a href="<?= e(url('/')) ?>">← Back to website</a>
                <form method="post" action="<?= e(url('/logout.php')) ?>"><?= csrf_field() ?><button class="btn btn-block btn-onDark">Sign out</button></form>
            </div>
        </aside>
        <div class="content">
            <header class="topbar">
                <button class="burger" id="burger" aria-label="Menu">☰</button>
                <h1><?= e($title) ?></h1>
                <div class="top-right">
                    <a class="bell" href="<?= e(url('/' . $area . '/notifications.php')) ?>" title="Notifications">🔔<?php if ($unread > 0): ?><em><?= $unread ?></em><?php endif; ?></a>
                    <div class="who"><span class="avatar"><?= e(initials($u['name'] ?? '')) ?></span><span class="who-t"><strong><?= e($u['name'] ?? '') ?></strong><small><?= $area === 'admin' ? 'Administrator' : 'Pet owner' ?></small></span></div>
                </div>
            </header>
            <main class="main">
<?php endif;
}

function page_end(string $area = 'public', array $scripts = []): void
{
    $clinic = cfg('CLINIC_NAME', 'PetCare Veterinary Clinic');
    if ($area === 'public'): ?>
    </main>
    <footer class="site-foot">
        <div class="container foot-grid">
            <div>
                <a class="brand light" href="<?= e(url('/')) ?>"><span class="logo">🐾</span><span><?= e($clinic) ?></span></a>
                <p>Compassionate veterinary care for your furry, feathered and scaly family members.</p>
            </div>
            <div>
                <h5>Explore</h5>
                <a href="<?= e(url('/')) ?>#services">Services</a>
                <a href="<?= e(url('/')) ?>#vets">Veterinarians</a>
                <a href="<?= e(url('/register.php')) ?>">Create account</a>
                <a href="<?= e(url('/login.php')) ?>">Sign in</a>
            </div>
            <div>
                <h5>Visit us</h5>
                <p><?= e(cfg('CLINIC_ADDRESS', '')) ?></p>
                <p><?= e(cfg('CLINIC_HOURS', '')) ?></p>
                <p><?= e(cfg('CLINIC_PHONE', '')) ?><br><?= e(cfg('CLINIC_EMAIL', '')) ?></p>
            </div>
            <div>
                <h5>Staff</h5>
                <a href="<?= e(url('/admin/dashboard.php')) ?>">Admin portal</a>
            </div>
        </div>
        <div class="container foot-base">© <?= date('Y') ?> <?= e($clinic) ?>. All rights reserved.</div>
    </footer>
<?php elseif ($area === 'auth'): ?>
        </section>
    </div>
<?php else: ?>
            </main>
        </div>
    </div>
<?php endif; ?>
<script src="<?= e(url('/assets/app.js')) ?>?v=<?= @filemtime(ROOT . '/public/assets/app.js') ?: 1 ?>"></script>
<?php foreach ($scripts as $s): ?><script src="<?= e(url('/assets/' . $s)) ?>?v=<?= @filemtime(ROOT . '/public/assets/' . $s) ?: 1 ?>"></script><?php endforeach; ?>
</body>
</html>
<?php
}

function page_header(string $title, string $sub = '', string $actions = ''): void
{
    echo '<div class="page-head"><div><h2>' . e($title) . '</h2>' . ($sub !== '' ? '<p class="muted">' . e($sub) . '</p>' : '') . '</div>'
        . ($actions !== '' ? '<div class="actions">' . $actions . '</div>' : '') . '</div>';
}

function empty_state(string $icon, string $title, string $text = '', string $cta = ''): void
{
    echo '<div class="empty"><div class="big">' . $icon . '</div><h3>' . e($title) . '</h3>'
        . ($text !== '' ? '<p class="muted">' . e($text) . '</p>' : '') . $cta . '</div>';
}
