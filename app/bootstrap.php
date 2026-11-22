<?php
// Loaded by every page. Sets up config, sessions, MongoDB and helpers.

define('ROOT', dirname(__DIR__));

// ---- tiny .env loader --------------------------------------------------
$envFile = ROOT . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v, " \t\"'");
        if (getenv($k) === false) putenv("$k=$v");
    }
}

date_default_timezone_set('Asia/Manila');

function setup_error(string $title, string $html): void
{
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><title>Setup needed</title>'
        . '<body style="font-family:system-ui,sans-serif;max-width:640px;margin:12vh auto;padding:0 20px;line-height:1.6">'
        . '<h2>' . $title . '</h2>' . $html . '</body>';
    exit;
}

if (!extension_loaded('mongodb')) {
    setup_error('PHP MongoDB extension is not enabled',
        '<p>Install/enable <code>ext-mongodb</code> and restart PHP. See README.md &rarr; Step 2.</p>');
}
if (!is_file(ROOT . '/vendor/autoload.php')) {
    setup_error('Composer packages are not installed',
        '<p>Open a terminal in the project folder and run:</p><pre>composer install</pre><p>See README.md &rarr; Step 3.</p>');
}
require ROOT . '/vendor/autoload.php';
require ROOT . '/app/lib.php';
require ROOT . '/app/layout.php';

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

set_exception_handler(function (Throwable $e) {
    http_response_code(500);
    $db = $e instanceof MongoDB\Driver\Exception\ConnectionException
        || $e instanceof MongoDB\Driver\Exception\AuthenticationException;
    $msg = $db
        ? 'Cannot connect to MongoDB. Make sure MongoDB is running (README &rarr; Step 4) and MONGO_URI in .env is correct.'
        : 'Something went wrong. Please try again.';
    if (cfg('APP_DEBUG', '0') === '1') $msg .= ' [' . get_class($e) . ': ' . $e->getMessage() . ']';
    if (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => strip_tags($msg)]);
        exit;
    }
    echo '<!doctype html><meta charset="utf-8"><title>Error</title>'
        . '<body style="font-family:system-ui,sans-serif;max-width:640px;margin:12vh auto;padding:0 20px;line-height:1.6">'
        . '<h2>Oops</h2><p>' . $msg . '</p></body>';
    exit;
});

// Create indexes once per browser session (cheap + idempotent).
if (empty($_SESSION['idx'])) {
    ensure_indexes();
    $_SESSION['idx'] = 1;
}
