<?php
/**
 * Halaman ganti password pengguna
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../helpers/validation_helper.php';
require_once __DIR__ . '/../helpers/user_model.php';
require_once __DIR__ . '/../helpers/audit_helper.php';

requireLogin();

$errors = [];
$successMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        $errors[] = 'Token keamanan tidak valid. Silakan coba lagi.';
    } else {
        $oldPassword     = $_POST['old_password'] ?? '';
        $newPassword     = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $userId = getCurrentUserId();
        $user   = getUserById($userId);

        if ($user === false || !password_verify($oldPassword, $user['password_hash'])) {
            $errors[] = 'Password lama tidak sesuai.';
        } elseif (!isValidPassword($newPassword)) {
            $errors[] = 'Password baru minimal 6 karakter.';
        } elseif ($newPassword !== $confirmPassword) {
            $errors[] = 'Konfirmasi password baru tidak cocok.';
        } else {
            $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 10]);
            if (updateUserPassword($userId, $newHash)) {
                logAudit($userId, 'CHANGE_PASSWORD', 'users', $userId, 'Password berhasil diubah');
                $successMessage = 'Password berhasil diubah.';
            } else {
                $errors[] = 'Terjadi kesalahan saat menyimpan password baru.';
            }
        }
    }
}

$csrfToken = generateCsrfToken();
$pageTitle = 'Ubah Password';
require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="mb-4">Ubah Password</h2>

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

<div class="row">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST" action="<?= BASE_URL ?>/auth/change_password.php">
                    <input type="hidden" name="csrf_token" value="<?= escapeOutput($csrfToken) ?>">

                    <div class="mb-3">
                        <label for="old_password" class="form-label">Password Lama</label>
                        <input type="password" class="form-control" id="old_password" name="old_password" required>
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label">Password Baru</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                        <div class="form-text">Minimal 6 karakter.</div>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Konfirmasi Password Baru</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                    </div>

                    <button type="submit" class="btn btn-primary">Simpan Password Baru</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
