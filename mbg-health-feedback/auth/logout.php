<?php
/**
 * auth/logout.php
 * Proses logout: catat audit log, hancurkan session, redirect ke login
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../helpers/audit_helper.php';

startSecureSession();

// Catat audit sebelum session dihapus
if (isLoggedIn()) {
    $uid  = getCurrentUserId();
    $role = getCurrentRole();
    logAudit($uid, 'LOGOUT', 'users', $uid, "Logout dari sistem sebagai {$role}");
}

// Hancurkan session (fungsi dari session.php)
destroyUserSession();

// Redirect ke login
header('Location: ' . BASE_URL . '/auth/login.php');
exit;
