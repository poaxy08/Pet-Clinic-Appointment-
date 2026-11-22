<?php
require __DIR__ . '/../../app/bootstrap.php';
$area = 'user';
$u = require_role('user');
require ROOT . '/app/notifications_page.php';
