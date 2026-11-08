<?php
require_once __DIR__ . '/config/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear all session variables
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Restart new session just to flash logout message
session_start();
set_flash('info', 'คุณได้ออกจากระบบเรียบร้อยแล้ว');
header("Location: " . base_url('login.php'));
exit;
