<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

$currentUser = current_user();
if ($currentUser) {
    if ($currentUser['role'] === 'customer') {
        header("Location: " . base_url('customer/dashboard.php'));
    } else {
        header("Location: " . base_url('admin/index.php'));
    }
} else {
    header("Location: " . base_url('login.php'));
}
exit;
