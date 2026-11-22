<?php
require __DIR__ . '/../../app/bootstrap.php';
$area = 'admin';
$u = require_role('admin');
require ROOT . '/app/notifications_page.php';
