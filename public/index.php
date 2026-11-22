<?php
require __DIR__ . '/../app/bootstrap.php';

$services = all('services', ['active' => true], ['sort' => ['name' => 1], 'limit' => 9]);
$vets = all('vets', ['active' => true], ['sort' => ['name' => 1], 'limit' => 6]);
$reviews = all('feedback', ['public' => true], ['sort' => ['created_at' => -1], 'limit' => 3]);
$owners = lookup('users', array_column($reviews, 'owner_id'));
$agg = col('feedback')->aggregate([['$group' => ['_id' => null, 'avg' => ['$avg' => '$rating'], 'n' => ['$sum' => 1]]]])->toArray();
$avg = $agg ? round((float)$agg[0]['avg'], 1) : null;
$n = $agg ? (int)$agg[0]['n'] : 0;
$u = current_user();

page_start('Veterinary care, made simple', 'public');
?>
<section class="hero" id="top">
    <div class="container hero-in">
        <div class="hero-copy">
            <span class="pill">🐾 Online appointment booking</span>
            <h1>Gentle care for the<br><span class="grad">whole family</span> — paws included.</h1>
            <p class="lead">Register your pets, book a visit in seconds, and keep every medical record, reminder and receipt in one secure place.</p>
            <div class="cta-row">
                <?php if ($u): ?>
                    <a class="btn btn-accent btn-lg" href="<?= e(url($u['role'] === 'admin' ? '/admin/dashboard.php' : '/user/book.php')) ?>"><?= $u['role'] === 'admin' ? 'Open admin console' : 'Book an appointment' ?></a>
                <?php else: ?>
                    <a class="btn btn-accent btn-lg" href="<?= e(url('/register.php')) ?>">Create free account</a>
                    <a class="btn btn-outline-light btn-lg" href="<?= e(url('/login.php')) ?>">Sign in</a>
                <?php endif; ?>
            </div>
            <ul class="hero-points">
                <li>✔ Live slot availability</li>
                <li>✔ Automatic reminders</li>
                <li>✔ Prices in Philippine pesos (₱)</li>
            </ul>
        </div>
        <div class="hero-card" aria-hidden="true">
            <div class="mock">
                <div class="mock-top"><span></span><span></span><span></span></div>
                <h4>Book your visit</h4>
                <div class="mock-row"><span>🐶 Pet</span><b>Choose your pet</b></div>
                <div class="mock-row"><span>💉 Service</span><b>Vaccination</b></div>
                <div class="mock-slots">
                    <i>9:00 AM</i><i class="x">9:30 AM<small>Not available</small></i><i class="on">10:00 AM</i><i>10:30 AM</i>
                </div>
                <div class="mock-btn">Confirm booking</div>
            </div>
            <div class="float f1">🔔 Reminder sent</div>
            <div class="float f2">🩺 Records updated</div>
        </div>
    </div>
</section>

<section class="section" id="how">
    <div class="container">
        <div class="sec-head"><span class="eyebrow">How it works</span><h2>From sign-up to check-up in three steps</h2></div>
        <div class="grid g3">
            <div class="card step"><div class="num">1</div><h3>Create your account</h3><p class="muted">Sign up with your email and mobile number, then register your pets with their breed, age and notes.</p></div>
            <div class="card step"><div class="num">2</div><h3>Pick a service &amp; time</h3><p class="muted">Choose a service and veterinarian, then see which times are open. Taken times are clearly marked “Not available”.</p></div>
            <div class="card step"><div class="num">3</div><h3>Visit, pay &amp; follow up</h3><p class="muted">Get reminders, view medical records and invoices online, and rate your experience afterwards.</p></div>
        </div>
    </div>
</section>

<section class="section alt" id="services">
    <div class="container">
        <div class="sec-head"><span class="eyebrow">Services</span><h2>Everything your pet needs</h2><p class="muted">Transparent pricing in Philippine pesos.</p></div>
        <?php if (!$services): ?>
            <p class="muted center">Services will appear here once the clinic adds them.</p>
        <?php else: ?>
            <div class="grid g3">
                <?php foreach ($services as $s): ?>
                    <div class="card svc">
                        <div class="svc-ico">💉</div>
                        <h3><?= e($s['name']) ?></h3>
                        <p class="muted"><?= e($s['description'] ?? '') ?></p>
                        <div class="svc-foot"><strong><?= peso($s['price']) ?></strong><span class="muted small">~<?= (int)($s['duration'] ?? 30) ?> min</span></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section" id="vets">
    <div class="container">
        <div class="sec-head"><span class="eyebrow">Our team</span><h2>Meet the veterinarians</h2></div>
        <?php if (!$vets): ?>
            <p class="muted center">Our veterinarians will be listed here soon.</p>
        <?php else: ?>
            <div class="grid g3">
                <?php foreach ($vets as $v): ?>
                    <div class="card vet">
                        <div class="avatar xl"><?= e(initials($v['name'])) ?></div>
                        <h3><?= e($v['name']) ?></h3>
                        <p class="spec"><?= e($v['specialization'] ?? 'General practice') ?></p>
                        <p class="muted small"><?= e($v['bio'] ?? '') ?></p>
                        <p class="small"><strong>Available:</strong> <?= e(implode(', ', array_map(fn($d) => DAY_NAMES[(int)$d] ?? '', (array)($v['days'] ?? [])))) ?> · <?= e(ftime($v['start'] ?? '09:00')) ?> – <?= e(ftime($v['end'] ?? '17:00')) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section alt" id="reviews">
    <div class="container">
        <div class="sec-head">
            <span class="eyebrow">Reviews</span><h2>What pet owners say</h2>
            <?php if ($avg !== null): ?><p class="muted"><?= stars((int)round($avg)) ?> <?= $avg ?> / 5 average from <?= $n ?> rating<?= $n === 1 ? '' : 's' ?></p><?php endif; ?>
        </div>
        <?php if (!$reviews): ?>
            <p class="muted center">No public reviews yet — be the first to share your experience after your visit.</p>
        <?php else: ?>
            <div class="grid g3">
                <?php foreach ($reviews as $r): $o = $owners[(string)$r['owner_id']] ?? null; ?>
                    <div class="card review">
                        <?= stars((int)$r['rating']) ?>
                        <p>“<?= e($r['comment']) ?>”</p>
                        <div class="by"><span class="avatar"><?= e(initials($o['name'] ?? 'Pet Owner')) ?></span><strong><?= e(explode(' ', $o['name'] ?? 'Pet Owner')[0]) ?></strong></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="cta-band">
            <div><h2>Ready to book your pet's next visit?</h2><p>It takes less than two minutes.</p></div>
            <a class="btn btn-accent btn-lg" href="<?= e(url($u ? ($u['role'] === 'admin' ? '/admin/dashboard.php' : '/user/book.php') : '/register.php')) ?>"><?= $u ? 'Continue' : 'Get started' ?></a>
        </div>
    </div>
</section>
<?php page_end('public'); ?>
