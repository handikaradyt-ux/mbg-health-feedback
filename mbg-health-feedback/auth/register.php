<?php
/**
 * Halaman dan proses registrasi akun pengguna baru (role = user)
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../helpers/validation_helper.php';
require_once __DIR__ . '/../helpers/user_model.php';
require_once __DIR__ . '/../helpers/audit_helper.php';

startSecureSession();

// Jika sudah login, redirect ke dashboard
if (isLoggedIn()) {
    redirectToDashboard();
}

$errors = [];
$old = [
    'full_name' => '',
    'username'  => '',
    'email'     => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        $errors[] = 'Token keamanan tidak valid. Silakan coba lagi.';
    } else {
        $fullName        = sanitizeInput($_POST['full_name'] ?? '');
        $username        = sanitizeInput($_POST['username'] ?? '');
        $email           = sanitizeInput($_POST['email'] ?? '');
        $password        = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        $old['full_name'] = $fullName;
        $old['username']  = $username;
        $old['email']     = $email;

        // Validasi input
        if (!isValidFullName($fullName)) {
            $errors[] = 'Nama lengkap wajib diisi (maksimal 100 karakter).';
        }
        if (!isValidUsername($username)) {
            $errors[] = 'Username harus 3-50 karakter, hanya huruf, angka, dan underscore.';
        }
        if (!isValidEmail($email)) {
            $errors[] = 'Format email tidak valid.';
        }
        if (!isValidPassword($password)) {
            $errors[] = 'Password minimal 6 karakter.';
        }
        if ($password !== $passwordConfirm) {
            $errors[] = 'Konfirmasi password tidak cocok.';
        }

        // Cek duplikasi
        if (empty($errors)) {
            if (getUserByUsername($username) !== false) {
                $errors[] = 'Username sudah digunakan, silakan pilih username lain.';
            }
            if ($email !== '' && getUserByEmail($email) !== false) {
                $errors[] = 'Email sudah digunakan, silakan gunakan email lain.';
            }
        }

        // Jika lolos validasi, simpan
        if (empty($errors)) {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
            $emailToSave  = $email === '' ? null : $email;

            $newUserId = createUser($fullName, $username, $passwordHash, ROLE_USER, $emailToSave);

            if ($newUserId !== false) {
                logAudit($newUserId, 'REGISTER', 'users', $newUserId, "Registrasi akun baru: {$username}");

                $_SESSION['register_success'] = 'Registrasi berhasil! Silakan login menggunakan akun baru Anda.';
                header('Location: ' . BASE_URL . '/auth/login.php');
                exit;
            } else {
                $errors[] = 'Terjadi kesalahan saat menyimpan data. Silakan coba lagi.';
            }
        }
    }
}

$csrfToken = generateCsrfToken();
$pageTitle = 'Registrasi Akun';
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
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-body">
                    <h3 class="card-title text-center mb-4"><?= escapeOutput(APP_NAME) ?></h3>
                    <h5 class="text-center mb-4 text-muted">Registrasi Akun Pengguna</h5>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= escapeOutput($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="<?= BASE_URL ?>/auth/register.php">
                        <input type="hidden" name="csrf_token" value="<?= escapeOutput($csrfToken) ?>">

                        <div class="mb-3">
                            <label for="full_name" class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" maxlength="100" value="<?= escapeOutput($old['full_name']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" maxlength="50" value="<?= escapeOutput($old['username']) ?>" required>
                            <div class="form-text">3-50 karakter, hanya huruf, angka, dan underscore.</div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email (opsional)</label>
                            <input type="email" class="form-control" id="email" name="email" maxlength="100" value="<?= escapeOutput($old['email']) ?>">
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required minlength="6">
                            <div class="form-text">Minimal 6 karakter.</div>
                        </div>

                        <div class="mb-3">
                            <label for="password_confirm" class="form-label">Konfirmasi Password</label>
                            <input type="password" class="form-control" id="password_confirm" name="password_confirm" required minlength="6">
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Daftar</button>
                    </form>

                    <p class="text-center mt-3 mb-0">
                        Sudah punya akun? <a href="<?= BASE_URL ?>/auth/login.php">Login di sini</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>