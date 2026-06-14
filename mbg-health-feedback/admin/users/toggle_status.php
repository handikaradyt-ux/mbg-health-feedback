<?php
/**
 * admin/users/toggle_status.php
 * Mengaktifkan/menonaktifkan akun pengguna (toggle is_active)
 */

require_once dirname(__DIR__, 2) . '/auth/session.php';
require_once dirname(__DIR__, 2) . '/helpers/user_model.php';
require_once dirname(__DIR__, 2) . '/helpers/audit_helper.php';

requireRole([ROLE_ADMIN]);
startSecureSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/users/index.php');
    exit;
}

$userId = (int) ($_POST['user_id'] ?? 0);

$redirectParams = [
    'page'   => (int) ($_POST['redirect_page'] ?? 1),
    'search' => $_POST['redirect_search'] ?? '',
    'role'   => $_POST['redirect_role'] ?? '',
];
$redirectUrl = BASE_URL . '/admin/users/index.php?' . http_build_query($redirectParams);

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Token keamanan tidak valid.'];
    header('Location: ' . $redirectUrl);
    exit;
}

$user = getUserById($userId);
if (!$user) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Pengguna tidak ditemukan.'];
    header('Location: ' . $redirectUrl);
    exit;
}

// Admin tidak dapat menonaktifkan akun sendiri
if ($userId === getCurrentUserId()) {
    $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Anda tidak dapat mengubah status akun Anda sendiri.'];
    header('Location: ' . $redirectUrl);
    exit;
}

$newStatus = (int) $user['is_active'] === 1 ? false : true;
$success = setUserActiveStatus($userId, $newStatus);

if ($success) {
    $actionType = $newStatus ? 'ACTIVATE' : 'DEACTIVATE';
    $statusText = $newStatus ? 'mengaktifkan' : 'menonaktifkan';

    logAudit(
        getCurrentUserId(),
        $actionType,
        'users',
        $userId,
        "Berhasil {$statusText} pengguna '{$user['username']}'"
    );

    $_SESSION['flash'] = [
        'type'    => 'success',
        'message' => "Pengguna '{$user['full_name']}' berhasil " . ($newStatus ? 'diaktifkan.' : 'dinonaktifkan.'),
    ];
} else {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Gagal mengubah status pengguna.'];
}

header('Location: ' . $redirectUrl);
exit;