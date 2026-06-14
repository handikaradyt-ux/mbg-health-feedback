<?php
/**
 * admin/menu/edit.php
 * Form edit Menu MBG — Admin
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/auth/session.php';
require_once dirname(__DIR__, 2) . '/helpers/validation_helper.php';
require_once dirname(__DIR__, 2) . '/helpers/menu_model.php';

requireLogin();
requireRole([ROLE_ADMIN]);

$pdo    = getDBConnection();
$menuId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Pastikan menu ada
$menu = getMenuById($pdo, $menuId);
if (!$menu) {
    $_SESSION['flash_error'] = 'Menu tidak ditemukan.';
    header('Location: ' . BASE_URL . '/admin/menu/index.php');
    exit;
}

$errors = [];

/* ── Proses Submit ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $menuName     = trim($_POST['menu_name']      ?? '');
    $nutritionDesc= trim($_POST['nutrition_desc'] ?? '');
    $menuDate     = trim($_POST['menu_date']      ?? '');
    $isActive     = isset($_POST['is_active']) ? 1 : 0;

    // Update $menu dengan nilai baru (untuk re-populate form jika ada error)
    $menu['menu_name']      = $menuName;
    $menu['nutrition_desc'] = $nutritionDesc;
    $menu['menu_date']      = $menuDate;
    $menu['is_active']      = $isActive;

    // Validasi
    if ($menuName === '') {
        $errors['menu_name'] = 'Nama menu wajib diisi.';
    } elseif (mb_strlen($menuName) > 150) {
        $errors['menu_name'] = 'Nama menu maksimal 150 karakter.';
    }

    if ($nutritionDesc === '') {
        $errors['nutrition_desc'] = 'Deskripsi gizi wajib diisi.';
    }

    if ($menuDate === '') {
        $errors['menu_date'] = 'Tanggal sajian wajib diisi.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $menuDate)) {
        $errors['menu_date'] = 'Format tanggal tidak valid.';
    }

    if (empty($errors)) {
        $ok = updateMenu($pdo, $menuId, [
            'menu_name'     => $menuName,
            'nutrition_desc'=> $nutritionDesc,
            'menu_date'     => $menuDate,
            'is_active'     => $isActive,
        ]);

        if ($ok) {
            $_SESSION['flash_success'] = 'Menu berhasil diperbarui.';
            header('Location: ' . BASE_URL . '/admin/menu/index.php');
            exit;
        } else {
            $errors['general'] = 'Terjadi kesalahan saat menyimpan data. Silakan coba lagi.';
        }
    }
}

$pageTitle = 'Edit Menu MBG';
require_once dirname(__DIR__, 2) . '/includes/header.php';
require_once dirname(__DIR__, 2) . '/includes/sidebar_admin.php';
?>

<div id="main-content">

    <!-- Page Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-bold mb-1">Edit Menu MBG</h3>
            <p class="text-muted mb-0 small">
                <a href="<?= BASE_URL ?>/admin/menu/index.php" class="text-decoration-none text-muted">
                    <i class="bi bi-arrow-left me-1"></i>Kembali ke daftar menu
                </a>
            </p>
        </div>
        <span class="badge <?= (int) $menu['is_active'] ? 'bg-success' : 'bg-secondary' ?> px-3 py-2 fs-6">
            <?= (int) $menu['is_active'] ? 'Aktif' : 'Nonaktif' ?>
        </span>
    </div>

    <!-- Error Umum -->
    <?php if (!empty($errors['general'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= escapeOutput($errors['general']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Form Card -->
    <div class="card border-0 shadow-sm" style="max-width:680px;">
        <div class="card-header bg-white border-bottom py-3 px-4">
            <h6 class="mb-0 fw-semibold">
                <i class="bi bi-pencil-square me-2 text-primary"></i>
                Edit: <?= escapeOutput($menu['menu_name']) ?>
            </h6>
        </div>
        <div class="card-body p-4">
            <form method="POST" action="" novalidate>

                <!-- Nama Menu -->
                <div class="mb-4">
                    <label for="menu_name" class="form-label fw-semibold">
                        Nama Menu <span class="text-danger">*</span>
                    </label>
                    <input
                        type="text"
                        id="menu_name"
                        name="menu_name"
                        class="form-control <?= isset($errors['menu_name']) ? 'is-invalid' : '' ?>"
                        placeholder="Contoh: Nasi Ayam Goreng Spesial"
                        value="<?= escapeOutput($menu['menu_name']) ?>"
                        maxlength="150"
                        required
                    >
                    <?php if (isset($errors['menu_name'])): ?>
                        <div class="invalid-feedback"><?= escapeOutput($errors['menu_name']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Deskripsi Gizi -->
                <div class="mb-4">
                    <label for="nutrition_desc" class="form-label fw-semibold">
                        Deskripsi Gizi <span class="text-danger">*</span>
                    </label>
                    <textarea
                        id="nutrition_desc"
                        name="nutrition_desc"
                        class="form-control <?= isset($errors['nutrition_desc']) ? 'is-invalid' : '' ?>"
                        placeholder="Contoh: Ayam goreng 1 potong, sayur bayam, tempe orek..."
                        rows="4"
                        required
                    ><?= escapeOutput($menu['nutrition_desc'] ?? '') ?></textarea>
                    <?php if (isset($errors['nutrition_desc'])): ?>
                        <div class="invalid-feedback"><?= escapeOutput($errors['nutrition_desc']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Tanggal Sajian -->
                <div class="mb-4">
                    <label for="menu_date" class="form-label fw-semibold">
                        Tanggal Sajian <span class="text-danger">*</span>
                    </label>
                    <input
                        type="date"
                        id="menu_date"
                        name="menu_date"
                        class="form-control <?= isset($errors['menu_date']) ? 'is-invalid' : '' ?>"
                        value="<?= escapeOutput($menu['menu_date'] ?? '') ?>"
                        required
                    >
                    <?php if (isset($errors['menu_date'])): ?>
                        <div class="invalid-feedback"><?= escapeOutput($errors['menu_date']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Status -->
                <div class="mb-4">
                    <label class="form-label fw-semibold">Status</label>
                    <div class="d-flex align-items-center gap-3">
                        <div class="form-check form-switch">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                role="switch"
                                id="is_active"
                                name="is_active"
                                <?= (int) $menu['is_active'] ? 'checked' : '' ?>
                                style="width:2.8em; height:1.4em;"
                            >
                            <label class="form-check-label ms-2" for="is_active" id="statusLabel">
                                <?= (int) $menu['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                            </label>
                        </div>
                    </div>
                    <div class="form-text">Matikan untuk menonaktifkan tampilan menu ini.</div>
                </div>

                <!-- Tombol -->
                <div class="d-flex gap-2 pt-2 border-top mt-2">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-floppy me-1"></i>Simpan Perubahan
                    </button>
                    <a href="<?= BASE_URL ?>/admin/menu/index.php" class="btn btn-outline-secondary px-4">
                        <i class="bi bi-x-lg me-1"></i>Batal
                    </a>
                </div>

            </form>
        </div>
    </div>

</div><!-- end #main-content -->
</div><!-- end .page-wrapper -->

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>

<script>
/* Update label status switch secara real-time */
document.getElementById('is_active').addEventListener('change', function() {
    document.getElementById('statusLabel').textContent = this.checked ? 'Aktif' : 'Nonaktif';
});
</script>