<?php
// Shared helpers: config, database, auth, slots, billing, notifications.

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

const SLOT_MINUTES = 30;       // length of one appointment slot
const LUNCH_START  = '12:00';  // no slots between lunch start/end
const LUNCH_END    = '13:00';
const HOLD_SECONDS = 300;      // a chosen slot is reserved for 5 minutes
const LEAD_MINUTES = 60;       // must book at least 1 hour ahead
const TZ           = 'Asia/Manila';
const NA_MESSAGE   = 'Not available — this date and time was already taken by another client. Please choose a different time.';

const SPECIES     = ['Dog', 'Cat', 'Bird', 'Rabbit', 'Hamster', 'Guinea Pig', 'Reptile', 'Fish', 'Other'];
const PAY_METHODS = ['Cash', 'GCash', 'Maya', 'Credit/Debit Card', 'Bank Transfer', 'Other'];
const DAY_NAMES   = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

/* ------------------------------------------------------------------ config */
function cfg(string $key, ?string $default = null): ?string
{
    $v = getenv($key);
    return ($v !== false && $v !== '') ? $v : $default;
}

function url(string $path = '/'): string
{
    return rtrim((string)cfg('APP_BASE', ''), '/') . '/' . ltrim($path, '/');
}

/* ---------------------------------------------------------------- database */
function db(): MongoDB\Database
{
    static $db = null;
    if ($db === null) {
        $client = new MongoDB\Client(
            cfg('MONGO_URI', 'mongodb://127.0.0.1:27017'),
            ['serverSelectionTimeoutMS' => 4000, 'connectTimeoutMS' => 4000],
            ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]
        );
        $db = $client->selectDatabase(cfg('MONGO_DB', 'petclinic'));
    }
    return $db;
}

function col(string $name): MongoDB\Collection
{
    return db()->selectCollection($name);
}

function all(string $coll, array $filter = [], array $opts = []): array
{
    return iterator_to_array(col($coll)->find($filter, $opts), false);
}

/** Fetch documents by a list of ids and return [idString => doc]. */
function lookup(string $coll, iterable $ids): array
{
    $seen = [];
    foreach ($ids as $i) {
        if ($i !== null) $seen[(string)$i] = $i;
    }
    if (!$seen) return [];
    $map = [];
    foreach (col($coll)->find(['_id' => ['$in' => array_values($seen)]]) as $d) {
        $map[(string)$d['_id']] = $d;
    }
    return $map;
}

function ensure_indexes(): void
{
    col('users')->createIndex(['email' => 1], ['unique' => true]);
    // THE slot lock: only one *active* appointment may own a slot_key.
    col('appointments')->createIndex(['slot_key' => 1], ['unique' => true, 'partialFilterExpression' => ['active' => true]]);
    col('appointments')->createIndex(['owner_id' => 1, 'starts_at' => -1]);
    col('appointments')->createIndex(['starts_at' => 1]);
    // Temporary reservation while someone is choosing a slot (auto-deleted by TTL).
    col('slot_holds')->createIndex(['slot_key' => 1], ['unique' => true]);
    col('slot_holds')->createIndex(['expires_at' => 1], ['expireAfterSeconds' => 0]);
    col('pets')->createIndex(['owner_id' => 1]);
    col('records')->createIndex(['pet_id' => 1, 'visit_date' => -1]);
    col('invoices')->createIndex(['owner_id' => 1]);
    col('notifications')->createIndex(['user_id' => 1, 'created_at' => -1]);
    col('feedback')->createIndex(['appointment_id' => 1], ['unique' => true]);
}

function next_seq(string $name): int
{
    $r = col('counters')->findOneAndUpdate(
        ['_id' => $name],
        ['$inc' => ['seq' => 1]],
        ['upsert' => true, 'returnDocument' => MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER]
    );
    return (int)$r['seq'];
}

function is_dup(Throwable $e): bool
{
    return $e->getCode() === 11000 || strpos($e->getMessage(), 'E11000') !== false;
}

/* ----------------------------------------------------------------- helpers */
function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

function oid($v): ?ObjectId
{
    if ($v === null || $v === '' || $v instanceof ObjectId) return $v instanceof ObjectId ? $v : null;
    try {
        return new ObjectId((string)$v);
    } catch (Throwable $e) {
        return null;
    }
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['t' => $type, 'm' => $msg];
}

function take_flash(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $t = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!hash_equals(csrf_token(), (string)$t)) {
        http_response_code(419);
        exit('Your session expired. Please go back, refresh the page and try again.');
    }
}

function post_btn(string $label, array $fields, string $cls = 'btn btn-sm btn-ghost', string $confirm = ''): string
{
    $h = '<form method="post" class="inline"' . ($confirm !== '' ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field();
    foreach ($fields as $k => $v) {
        $h .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    }
    return $h . '<button class="' . e($cls) . '">' . e($label) . '</button></form>';
}

/* ------------------------------------------------------------ date + money */
function utc_now(): UTCDateTime
{
    return new UTCDateTime();
}

function dt($v, string $fmt = 'M j, Y g:i A'): string
{
    if ($v instanceof UTCDateTime) {
        $d = $v->toDateTime();
        $d->setTimezone(new DateTimeZone(TZ));
        return $d->format($fmt);
    }
    return '';
}

function fdate(string $ymd): string
{
    $d = DateTime::createFromFormat('Y-m-d', $ymd);
    return $d ? $d->format('D, M j, Y') : $ymd;
}

function ftime(string $hi): string
{
    $d = DateTime::createFromFormat('H:i', $hi);
    return $d ? $d->format('g:i A') : $hi;
}

function valid_date(string $d): bool
{
    $x = DateTime::createFromFormat('Y-m-d', $d);
    return $x && $x->format('Y-m-d') === $d;
}

function starts_at_utc(string $date, string $time): UTCDateTime
{
    $d = new DateTime("$date $time", new DateTimeZone(TZ));
    return new UTCDateTime($d->getTimestamp() * 1000);
}

function peso($n): string
{
    return '₱' . number_format((float)$n, 2);
}

function pet_age(string $birth): string
{
    if (!valid_date($birth)) return '';
    $diff = (new DateTime($birth))->diff(new DateTime('today'));
    if ($diff->y > 0) return $diff->y . ' yr' . ($diff->y > 1 ? 's' : '') . ($diff->m ? ' ' . $diff->m . ' mo' : '');
    return max(0, $diff->m) . ' mo';
}

function pet_emoji(string $species): string
{
    $m = ['Dog' => '🐶', 'Cat' => '🐱', 'Bird' => '🐦', 'Rabbit' => '🐰', 'Hamster' => '🐹', 'Guinea Pig' => '🐹', 'Reptile' => '🦎', 'Fish' => '🐠'];
    return $m[$species] ?? '🐾';
}

function initials(string $name): string
{
    $p = preg_split('/\s+/', trim($name));
    $s = mb_strtoupper(mb_substr($p[0] ?? '', 0, 1) . mb_substr($p[count($p) - 1] ?? '', 0, 1));
    return $s !== '' ? $s : '?';
}

function badge(string $s): string
{
    return '<span class="badge b-' . e($s) . '">' . e(ucfirst($s)) . '</span>';
}

function stars(int $n): string
{
    return '<span class="stars" title="' . $n . ' / 5">' . str_repeat('★', $n) . '<i>' . str_repeat('★', 5 - $n) . '</i></span>';
}

/* -------------------------------------------------------------------- auth */
function current_user(): ?array
{
    static $u = false;
    if ($u !== false) return $u;
    $u = null;
    if (!empty($_SESSION['uid'])) {
        $id = oid($_SESSION['uid']);
        if ($id) {
            $found = col('users')->findOne(['_id' => $id, 'active' => ['$ne' => false]]);
            $u = $found ?: null;
        }
        if (!$u) unset($_SESSION['uid']);
    }
    return $u;
}

function home_for(array $u): string
{
    return $u['role'] === 'admin' ? '/admin/dashboard.php' : '/user/dashboard.php';
}

function require_role(string $role): array
{
    $u = current_user();
    if (!$u) {
        flash('error', 'Please sign in to continue.');
        redirect('/login.php');
    }
    if ($u['role'] !== $role) redirect(home_for($u));
    return $u;
}

/* ----------------------------------------------------------- notifications */
function notify(ObjectId $uid, string $title, string $msg, string $link = '', string $type = 'info'): void
{
    col('notifications')->insertOne([
        'user_id' => $uid, 'title' => $title, 'message' => $msg, 'link' => $link,
        'type' => $type, 'read' => false, 'created_at' => utc_now(),
    ]);
}

function notify_admins(string $title, string $msg, string $link = '', string $type = 'info'): void
{
    foreach (col('users')->find(['role' => 'admin', 'active' => ['$ne' => false]]) as $a) {
        notify($a['_id'], $title, $msg, $link, $type);
    }
}

function unread_count(ObjectId $uid): int
{
    return col('notifications')->countDocuments(['user_id' => $uid, 'read' => false]);
}

/**
 * Creates an in-app reminder for every active appointment starting within the next 24 hours.
 * Runs automatically (at most every 5 minutes) while people use the site, and can also be run
 * from cron: php cron/send_reminders.php
 */
function run_reminders(bool $force = false): int
{
    $meta = col('meta');
    $nowMs = (int)(microtime(true) * 1000);
    $last = $meta->findOne(['_id' => 'reminders']);
    if (!$force && $last && ($nowMs - (int)$last['ts']) < 300000) return 0;
    $meta->updateOne(['_id' => 'reminders'], ['$set' => ['ts' => $nowMs]], ['upsert' => true]);

    $n = 0;
    $due = col('appointments')->find([
        'active' => true,
        'status' => ['$in' => ['pending', 'confirmed']],
        'reminded' => ['$ne' => true],
        'starts_at' => ['$gt' => new UTCDateTime($nowMs), '$lte' => new UTCDateTime($nowMs + 86400000)],
    ]);
    foreach ($due as $a) {
        $pet = col('pets')->findOne(['_id' => $a['pet_id']]);
        notify($a['owner_id'], 'Appointment reminder',
            'Reminder: ' . ($pet['name'] ?? 'Your pet') . ' has a ' . $a['service_name'] . ' visit on '
            . fdate($a['date']) . ' at ' . ftime($a['time']) . '.', '/user/appointments.php', 'reminder');
        col('appointments')->updateOne(['_id' => $a['_id']], ['$set' => ['reminded' => true]]);
        $n++;
    }
    return $n;
}

/* ------------------------------------------------------------------- slots */
function slot_key(string $vetId, string $date, string $time): string
{
    return cfg('SLOT_MODE', 'vet') === 'clinic' ? "$date|$time" : "$vetId|$date|$time";
}

function vet_works(array $vet, string $date): bool
{
    $dow = (int)date('N', strtotime($date));
    return in_array($dow, array_map('intval', (array)($vet['days'] ?? [1, 2, 3, 4, 5, 6])), true);
}

/** A vet with no services selected can do every service (keeps older vets working). */
function vet_offers(array $vet, $serviceId): bool
{
    $ids = array_map('strval', (array)($vet['service_ids'] ?? []));
    return !$ids || in_array((string)$serviceId, $ids, true);
}

function slot_times(array $vet): array
{
    $start = strtotime('2000-01-01 ' . ($vet['start'] ?? '09:00'));
    $end = strtotime('2000-01-01 ' . ($vet['end'] ?? '17:00'));
    $ls = strtotime('2000-01-01 ' . LUNCH_START);
    $le = strtotime('2000-01-01 ' . LUNCH_END);
    $out = [];
    for ($t = $start; $t < $end; $t += SLOT_MINUTES * 60) {
        if ($t >= $ls && $t < $le) continue;
        $out[] = date('H:i', $t);
    }
    return $out;
}

/** State of every slot for a vet on a date: free | booked | held | mine | past */
function slot_states(array $vet, string $date, ?ObjectId $me): array
{
    $times = vet_works($vet, $date) ? slot_times($vet) : [];
    $vid = (string)$vet['_id'];
    $keys = array_map(fn($t) => slot_key($vid, $date, $t), $times);

    $booked = [];
    foreach (col('appointments')->find(['slot_key' => ['$in' => $keys], 'active' => true], ['projection' => ['slot_key' => 1]]) as $a) {
        $booked[$a['slot_key']] = true;
    }
    $held = [];
    foreach (col('slot_holds')->find(['slot_key' => ['$in' => $keys], 'expires_at' => ['$gt' => utc_now()]]) as $h) {
        $held[$h['slot_key']] = (string)$h['user_id'];
    }

    $out = [];
    foreach ($times as $t) {
        $k = slot_key($vid, $date, $t);
        $state = 'free';
        if (strtotime("$date $t") < time() + LEAD_MINUTES * 60) $state = 'past';
        elseif (isset($booked[$k])) $state = 'booked';
        elseif (isset($held[$k])) $state = ($me && $held[$k] === (string)$me) ? 'mine' : 'held';
        $out[] = ['time' => $t, 'label' => ftime($t), 'state' => $state];
    }
    return $out;
}

/** Try to reserve a slot for $uid for HOLD_SECONDS. Returns ok | booked | held */
function hold_slot(string $key, ObjectId $uid): string
{
    if (col('appointments')->countDocuments(['slot_key' => $key, 'active' => true]) > 0) return 'booked';
    $now = utc_now();
    col('slot_holds')->deleteMany(['expires_at' => ['$lte' => $now]]);
    col('slot_holds')->deleteMany(['user_id' => $uid, 'slot_key' => ['$ne' => $key]]);
    $exp = new UTCDateTime((int)((time() + HOLD_SECONDS) * 1000));
    try {
        col('slot_holds')->insertOne(['slot_key' => $key, 'user_id' => $uid, 'expires_at' => $exp, 'created_at' => $now]);
        return 'ok';
    } catch (Throwable $e) {
        if (!is_dup($e)) throw $e;
        $h = col('slot_holds')->findOne(['slot_key' => $key]);
        if ($h && (string)$h['user_id'] === (string)$uid) {
            col('slot_holds')->updateOne(['_id' => $h['_id']], ['$set' => ['expires_at' => $exp]]);
            return 'ok';
        }
        return 'held';
    }
}

/* ----------------------------------------------------------------- billing */
function inv_calc(array $inv): array
{
    $sub = 0.0;
    foreach (($inv['items'] ?? []) as $it) $sub += (float)$it['qty'] * (float)$it['price'];
    $disc = (float)($inv['discount'] ?? 0);
    $total = max(0.0, round($sub - $disc, 2));
    $paid = 0.0;
    foreach (($inv['payments'] ?? []) as $p) $paid += (float)$p['amount'];
    $paid = round($paid, 2);
    $bal = round(max(0.0, $total - $paid), 2);
    $status = ($paid >= $total) ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');
    return ['subtotal' => round($sub, 2), 'discount' => $disc, 'total' => $total, 'paid' => $paid, 'balance' => $bal, 'status' => $status];
}

function inv_number(array $inv): string
{
    return 'INV-' . str_pad((string)($inv['number'] ?? 0), 5, '0', STR_PAD_LEFT);
}

function render_invoice(array $inv, array $owner, ?array $pet, bool $admin): void
{
    $c = inv_calc($inv);
    ?>
    <div class="card invoice" id="invoice">
        <div class="inv-head">
            <div>
                <div class="brand-line">🐾 <strong><?= e(cfg('CLINIC_NAME', 'PetCare Veterinary Clinic')) ?></strong></div>
                <p class="muted small"><?= e(cfg('CLINIC_ADDRESS', '')) ?><br><?= e(cfg('CLINIC_PHONE', '')) ?> · <?= e(cfg('CLINIC_EMAIL', '')) ?></p>
            </div>
            <div class="inv-meta">
                <h2><?= e(inv_number($inv)) ?></h2>
                <p class="muted small">Issued <?= e(dt($inv['created_at'], 'M j, Y')) ?></p>
                <?= badge($c['status']) ?>
            </div>
        </div>
        <div class="inv-bill">
            <div><span class="label">Billed to</span><strong><?= e($owner['name'] ?? '') ?></strong><br><span class="muted small"><?= e($owner['email'] ?? '') ?><br><?= e($owner['phone'] ?? '') ?></span></div>
            <?php if ($pet): ?>
                <div><span class="label">Patient</span><strong><?= e(pet_emoji($pet['species'] ?? '') . ' ' . ($pet['name'] ?? '')) ?></strong><br><span class="muted small"><?= e(($pet['species'] ?? '') . ' · ' . ($pet['breed'] ?? '')) ?></span></div>
            <?php endif; ?>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Description</th><th class="r">Qty</th><th class="r">Unit price</th><th class="r">Amount</th></tr></thead>
                <tbody>
                <?php foreach ($inv['items'] as $it): ?>
                    <tr><td><?= e($it['description']) ?></td><td class="r"><?= e(rtrim(rtrim(number_format((float)$it['qty'], 2), '0'), '.')) ?></td><td class="r"><?= peso($it['price']) ?></td><td class="r"><?= peso((float)$it['qty'] * (float)$it['price']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="inv-totals">
            <div><span>Subtotal</span><span><?= peso($c['subtotal']) ?></span></div>
            <?php if ($c['discount'] > 0): ?><div><span>Discount</span><span>- <?= peso($c['discount']) ?></span></div><?php endif; ?>
            <div class="grand"><span>Total</span><span><?= peso($c['total']) ?></span></div>
            <div><span>Paid</span><span><?= peso($c['paid']) ?></span></div>
            <div class="due"><span>Balance due</span><span><?= peso($c['balance']) ?></span></div>
        </div>
        <?php if (!empty($inv['payments'])): ?>
            <h4>Payment history</h4>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Date</th><th>Method</th><th>Reference</th><th class="r">Amount</th></tr></thead>
                    <tbody>
                    <?php foreach ($inv['payments'] as $p): ?>
                        <tr><td><?= e(dt($p['paid_at'], 'M j, Y g:i A')) ?></td><td><?= e($p['method']) ?></td><td><?= e($p['reference'] ?: '—') ?></td><td class="r"><?= peso($p['amount']) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <?php if (!empty($inv['notes'])): ?><p class="muted small"><strong>Notes:</strong> <?= e($inv['notes']) ?></p><?php endif; ?>
    </div>
    <?php
}