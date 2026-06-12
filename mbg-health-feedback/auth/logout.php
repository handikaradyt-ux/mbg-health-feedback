<?php
/**
 * auth/logout.php
 * Proses logout: hapus session dan redirect ke login
 */

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/auth/session.php';
require_once dirname(__DIR__) . '/helpers/audit_helper.php';

// Catat audit log sebelum session dihapus
if (isLoggedIn()) {
    $user = getCurrentUser();
    logAudit($user['user_id'], 'LOGOUT', 'users', $user['user_id'], 'Logout dari sistem');
}

// Hapus semua variabel session
$_SESSION = [];

// Hapus cookie session jika ada
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Hancurkan session
session_destroy();

// Redirect ke halaman login dengan pesan
header('Location: ' . BASE_URL . '/auth/login.php');
exit;