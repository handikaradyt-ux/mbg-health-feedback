<?php
/**
 * Helper: cek sesi, cek peran, redirect
 */

require_once __DIR__ . '/../config/app.php';

/**
 * Memulai session jika belum dimulai, dengan konfigurasi aman
 */
function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
    }
}

/**
 * Mengecek apakah user sudah login
 * @return bool
 */
function isLoggedIn(): bool {
    startSecureSession();
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

/**
 * Mengambil role user yang sedang login
 * @return string|null
 */
function getCurrentRole(): ?string {
    startSecureSession();
    return $_SESSION['role'] ?? null;
}

/**
 * Mengambil user_id user yang sedang login
 * @return int|null
 */
function getCurrentUserId(): ?int {
    startSecureSession();
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

/**
 * Mengambil nama lengkap user yang sedang login
 * @return string|null
 */
function getCurrentFullName(): ?string {
    startSecureSession();
    return $_SESSION['full_name'] ?? null;
}

/**
 * Memaksa user untuk login. Jika belum login, redirect ke halaman login.
 * @return void
 */
function requireLogin(): void {
    startSecureSession();
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
}

/**
 * Memaksa user memiliki salah satu role tertentu. Jika tidak, tolak akses.
 * @param array $allowedRoles Daftar role yang diizinkan, misal ['admin', 'petugas']
 * @return void
 */
function requireRole(array $allowedRoles): void {
    requireLogin();
    $role = getCurrentRole();
    if (!in_array($role, $allowedRoles, true)) {
        http_response_code(403);
        echo "<h1>403 Forbidden</h1><p>Anda tidak memiliki hak akses ke halaman ini.</p>";
        echo '<a href="' . BASE_URL . '/index.php">Kembali ke Beranda</a>';
        exit;
    }
}

/**
 * Redirect user ke dashboard sesuai role-nya
 * @return void
 */
function redirectToDashboard(): void {
    $role = getCurrentRole();
    switch ($role) {
        case ROLE_ADMIN:
            header('Location: ' . BASE_URL . '/admin/dashboard.php');
            break;
        case ROLE_PETUGAS:
            header('Location: ' . BASE_URL . '/petugas/dashboard.php');
            break;
        case ROLE_USER:
        default:
            header('Location: ' . BASE_URL . '/user/dashboard.php');
            break;
    }
    exit;
}

/**
 * Membuat session login setelah kredensial diverifikasi.
 * Melakukan regenerasi session ID untuk mencegah session fixation.
 *
 * @param array $user Baris data user dari database
 * @return void
 */
function createUserSession(array $user): void {
    startSecureSession();
    session_regenerate_id(true);

    $_SESSION['user_id']   = $user['user_id'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role']      = $user['role'];
}

/**
 * Menghancurkan session (logout)
 * @return void
 */
function destroyUserSession(): void {
    startSecureSession();
    $_SESSION = [];

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

    session_destroy();
}

/**
 * Generate CSRF token dan simpan di session
 * @return string
 */
function generateCsrfToken(): string {
    startSecureSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifikasi CSRF token dari request
 * @param string|null $token
 * @return bool
 */
function verifyCsrfToken(?string $token): bool {
    startSecureSession();
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}
