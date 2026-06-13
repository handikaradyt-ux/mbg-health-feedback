<?php
/**
 * Halaman dan proses login
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../helpers/validation_helper.php';
require_once __DIR__ . '/../helpers/user_model.php';
require_once __DIR__ . '/../helpers/audit_helper.php';

startSecureSession();

// Jika sudah login, redirect ke dashboard sesuai role
if (isLoggedIn()) {
    redirectToDashboard();
}

$errors = [];
$successMessage = $_SESSION['register_success'] ?? null;
unset($_SESSION['register_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        $errors[] = 'Token keamanan tidak valid. Silakan coba lagi.';
    } else {
        $username = sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $errors[] = 'Username dan password wajib diisi.';
        } else {
            $user = getUserByUsername($username);

            if ($user === false || !password_verify($password, $user['password_hash'])) {
                $errors[] = 'Username atau password salah.';
                logAudit(null, 'LOGIN_FAILED', 'users', null, "Percobaan login gagal untuk username: {$username}");
            } elseif ((int) $user['is_active'] === 0) {
                $errors[] = 'Akun Anda nonaktif. Silakan hubungi Admin.';
                logAudit((int) $user['user_id'], 'LOGIN_FAILED', 'users', (int) $user['user_id'], 'Login gagal: akun nonaktif');
            } else {
                // Login berhasil
                createUserSession($user);
                logAudit((int) $user['user_id'], 'LOGIN', 'users', (int) $user['user_id'], "Login berhasil sebagai {$user['role']}");
                redirectToDashboard();
            }
        }
    }
}

$csrfToken = generateCsrfToken();
$pageTitle = 'Login';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escapeOutput($pageTitle) ?> | <?= escapeOutput(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/custom.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container">
    <div class="row justify-content-center mt-5">
        <div class="col-md-5">
            <div class="card shadow">
                <div class="card-body">
                    <h3 class="card-title text-center mb-1"><?= escapeOutput(APP_NAME) ?></h3>
                    <p class="text-center text-muted mb-4">Sistem Evaluasi Program MBG</p>

                    <?php if ($successMessage): ?>
                        <div class="alert alert-success"><?= escapeOutput($successMessage) ?></div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= escapeOutput($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="<?= BASE_URL ?>/auth/login.php">
                        <input type="hidden" name="csrf_token" value="<?= escapeOutput($csrfToken) ?>">

                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required autofocus>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Login</button>
                    </form>

                    <p class="text-center mt-3 mb-0">
                        Belum punya akun? <a href="<?= BASE_URL ?>/auth/register.php">Daftar di sini</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
