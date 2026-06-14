<?php
/**
 * admin/users/edit.php
 * Form edit pengguna — nama, username, email, role; password opsional
 */

require_once dirname(__DIR__, 2) . '/auth/session.php';
require_once dirname(__DIR__, 2) . '/helpers/user_model.php';
require_once dirname(__DIR__, 2) . '/helpers/audit_helper.php';
require_once dirname(__DIR__, 2) . '/helpers/validation_helper.php';

requireRole([ROLE_ADMIN]);
startSecureSession();

$pageTitle = 'Edit Pengguna';
$errors = [];

$userId = (int) ($_GET['id'] ?? $_POST['user_id'] ?? 0);
$user = getUserById($userId);

if (!$user) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Pengguna tidak ditemukan.'];
    header('Location: ' . BASE_URL . '/admin/users/index.php');
    exit;
}

$old = [
    'full_name' => $user['full_name'],
    'username'  => $user['username'],
    'email'     => $user['email'] ?? '',
    'role'      => $user['role'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Token keamanan tidak valid. Silakan coba lagi.';
    }

    $old['full_name'] = trim($_POST['full_name'] ?? '');
    $old['username']  = trim($_POST['username'] ?? '');
    $old['email']     = trim($_POST['email'] ?? '');
    $old['role']      = trim($_POST['role'] ?? '');

    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    // --- Validasi ---
    if ($old['full_name'] === '') {
        $errors[] = 'Nama lengkap wajib diisi.';
    }
    if ($old['username'] === '') {
        $errors[] = 'Username wajib diisi.';
    } elseif (!preg_match('/^[a-zA-Z0-9_.]{3,50}$/', $old['username'])) {
        $errors[] = 'Username hanya boleh huruf, angka, titik, underscore (3-50 karakter).';
    } elseif (usernameExists($old['username'], $userId)) {
        $errors[] = 'Username sudah digunakan oleh pengguna lain.';
    }

    $emailToSave = $old['email'] !== '' ? $old['email'] : null;
    if ($emailToSave !== null) {
        if (!filter_var($emailToSave, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format email tidak valid.';
        } elseif (emailExists($emailToSave, $userId)) {
            $errors[] = 'Email sudah digunakan oleh pengguna lain.';
        }
    }

    if (!in_array($old['role'], [ROLE_ADMIN, ROLE_PETUGAS, ROLE_USER], true)) {
        $errors[] = 'Peran tidak valid.';
    }

    // Password opsional — jika diisi, harus valid & cocok
    $changePassword = ($password !== '' || $confirm !== '');
    if ($changePassword) {
        if (strlen($password) < 6) {
            $errors[] = 'Password baru minimal 6 karakter.';
        } elseif ($password !== $confirm) {
            $errors[] = 'Konfirmasi password baru tidak cocok.';
        }
    }

    // Cegah admin menonaktifkan/mengubah role dirinya sendiri jadi non-admin
    if ($userId === getCurrentUserId() && $old['role'] !== ROLE_ADMIN) {
        $errors[] = 'Anda tidak dapat mengubah peran akun Anda sendiri.';
    }

    // --- Simpan ---
    if (empty($errors)) {
        $success = updateUser($userId, $old['full_name'], $old['role'], $emailToSave);

        if ($success && $changePassword) {
            $success = updateUserPassword($userId, password_hash($password, PASSWORD_DEFAULT));
        }

        // Username di-update terpisah karena updateUser() saat ini tidak menanganinya
        if ($success && $old['username'] !== $user['username']) {
            try {
                $pdo = getDBConnection();
                $stmt = $pdo->prepare("UPDATE users SET username = :username WHERE user_id = :user_id");
                $stmt->execute([':username' => $old['username'], ':user_id' => $userId]);
            } catch (PDOException $e) {
                error_log('update username error: ' . $e->getMessage());
                $success = false;
            }
        }

        if ($success) {
            $desc = "Mengubah data pengguna '{$old['username']}'"
                  . ($changePassword ? ' (termasuk perubahan password)' : '');
            logAudit(getCurrentUserId(), 'UPDATE', 'users', $userId, $desc);

            $_SESSION['flash'] = [
                'type'    => 'success',
                'message' => "Data pengguna '{$old['full_name']}' berhasil diperbarui.",
            ];
            header('Location: ' . BASE_URL . '/admin/users/index.php');
            exit;
        } else {
            $errors[] = 'Gagal memperbarui data pengguna. Silakan coba lagi.';
        }
    }
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
require_once dirname(__DIR__, 2) . '/includes/sidebar_admin.php';
?>

<div id="main-content">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <h2 class="fw-bold mb-1">Edit Pengguna</h2>
            <p class="text-muted mb-0">Perbarui data akun: <?= escapeOutput($user['username']) ?></p>
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
            <form method="POST" action="<?= BASE_URL ?>/admin/users/edit.php?id=<?= $userId ?>" novalidate>
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="user_id" value="<?= $userId ?>">

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
                        <select name="role" class="form-select" required
                            <?= $userId === getCurrentUserId() ? 'disabled' : '' ?>>
                            <option value="<?= ROLE_USER ?>"    <?= $old['role'] === ROLE_USER    ? 'selected' : '' ?>>Pengguna</option>
                            <option value="<?= ROLE_PETUGAS ?>" <?= $old['role'] === ROLE_PETUGAS ? 'selected' : '' ?>>Petugas Validasi</option>
                            <option value="<?= ROLE_ADMIN ?>"   <?= $old['role'] === ROLE_ADMIN   ? 'selected' : '' ?>>Admin</option>
                        </select>
                        <?php if ($userId === getCurrentUserId()): ?>
                            <input type="hidden" name="role" value="<?= $old['role'] ?>">
                            <div class="form-text text-warning">Anda tidak dapat mengubah peran akun sendiri.</div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12"><hr class="my-2"></div>

                    <div class="col-md-6">
                        <label class="form-label">Password Baru</label>
                        <input type="password" name="password" class="form-control" minlength="6">
                        <div class="form-text">Kosongkan jika tidak ingin mengubah password.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Konfirmasi Password Baru</label>
                        <input type="password" name="password_confirm" class="form-control" minlength="6">
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-lg me-1"></i>Simpan Perubahan
                    </button>
                    <a href="<?= BASE_URL ?>/admin/users/index.php" class="btn btn-outline-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>