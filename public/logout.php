<?php
require __DIR__ . '/../app/bootstrap.php';
if (is_post()) {
    csrf_verify();
    if ($u = current_user()) {
        col('slot_holds')->deleteMany(['user_id' => $u['_id']]); // free any slot they were holding
    }
    $_SESSION = [];
    session_destroy();
    session_start();
    flash('success', 'You have been signed out.');
}
redirect('/login.php');
