<?php
/**
 * admin/users/create.php
 * Form tambah pengguna baru (role: user / petugas)
 */

require_once dirname(__DIR__, 2) . '/auth/session.php';
require_once dirname(__DIR__, 2) . '/helpers/user_model.php';
require_once dirname(__DIR__, 2) . '/helpers/audit_helper.php';
require_once dirname(__DIR__, 2) . '/helpers/validation_helper.php';

requireRole([ROLE_ADMIN]);
startSecureSession();

$pageTitle = 'Tambah Pengguna Baru';
$errors = [];

$old = [
    'full_name' => '',
    'username'  => '',
    'email'     => '',
    'role'      => ROLE_USER,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Token keamanan tidak valid. Silakan coba lagi.';
    }

    $old['full_name'] = trim($_POST['full_name'] ?? '');
    $old['username']  = trim($_POST['username'] ?? '');
    $old['email']     = trim($_POST['email'] ?? '');
    $old['role']      = trim($_POST['role'] ?? ROLE_USER);

    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['password_confirm'] ?? '';

    // --- Validasi ---
    if ($old['full_name'] === '') {
        $errors[] = 'Nama lengkap wajib diisi.';
    }
    if ($old['username'] === '') {
        $errors[] = 'Username wajib diisi.';
    } elseif (!preg_match('/^[a-zA-Z0-9_.]{3,50}$/', $old['username'])) {
        $errors[] = 'Username hanya boleh huruf, angka, titik, underscore (3-50 karakter).';
    } elseif (usernameExists($old['username'])) {
        $errors[] = 'Username sudah digunakan, silakan pilih username lain.';
    }

    if ($old['email'] !== '') {
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format email tidak valid.';
        } elseif (emailExists($old['email'])) {
            $errors[] = 'Email sudah digunakan, silakan gunakan email lain.';
        }
    } else {
        $old['email'] = null;
    }

    if (!in_array($old['role'], [ROLE_USER, ROLE_PETUGAS], true)) {
        $errors[] = 'Peran tidak valid.';
    }

    if (strlen($password) < 6) {
        $errors[] = 'Password minimal 6 karakter.';
    } elseif ($password !== $confirm) {
        $errors[] = 'Konfirmasi password tidak cocok.';
    }

    // --- Simpan ---
    if (empty($errors)) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $newUserId = createUser(
            $old['full_name'],
            $old['username'],
            $passwordHash,
            $old['role'],
            $old['email']
        );

        if ($newUserId !== false) {
            logAudit(
                getCurrentUserId(),
                'CREATE',
                'users',
                $newUserId,
                "Menambahkan pengguna baru '{$old['username']}' dengan peran '{$old['role']}'"
            );

            $_SESSION['flash'] = [
                'type'    => 'success',
                'message' => "Pengguna '{$old['full_name']}' berhasil ditambahkan.",
            ];
            header('Location: ' . BASE_URL . '/admin/users/index.php');
            exit;
        } else {
            $errors[] = 'Gagal menyimpan data pengguna. Silakan coba lagi.';
        }
    }
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
require_once dirname(__DIR__, 2) . '/includes/sidebar_admin.php';
?>

<div id="main-content">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <h2 class="fw-bold mb-1">Tambah Pengguna Baru</h2>
            <p class="text-muted mb-0">Buat akun baru untuk Pengguna atau Petugas Validasi.</p>
        </div>
        <a href="<?= BASE_URL ?>/admin/users/index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                    <li><?= escapeOutput($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="<?= BASE_URL ?>/admin/users/create.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control"
                               value="<?= escapeOutput($old['full_name']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control"
                               value="<?= escapeOutput($old['username']) ?>" required>
                        <div class="form-text">Huruf, angka, titik, underscore. 3-50 karakter.</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control"
                               value="<?= escapeOutput($old['email'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Peran <span class="text-danger">*</span></label>
                        <select name="role" class="form-select" required>
                            <option value="<?= ROLE_USER ?>"    <?= $old['role'] === ROLE_USER    ? 'selected' : '' ?>>Pengguna</option>
                            <option value="<?= ROLE_PETUGAS ?>" <?= $old['role'] === ROLE_PETUGAS ? 'selected' : '' ?>>Petugas Validasi</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" minlength="6" required>
                        <div class="form-text">Minimal 6 karakter.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Konfirmasi Password <span class="text-danger">*</span></label>
                        <input type="password" name="password_confirm" class="form-control" minlength="6" required>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-lg me-1"></i>Simpan Pengguna
                    </button>
                    <a href="<?= BASE_URL ?>/admin/users/index.php" class="btn btn-outline-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>