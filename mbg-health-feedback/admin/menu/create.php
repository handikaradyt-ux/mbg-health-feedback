<?php
/**
 * admin/menu/create.php
 * Form tambah Menu MBG baru — Admin
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/auth/session.php';
require_once dirname(__DIR__, 2) . '/helpers/validation_helper.php';
require_once dirname(__DIR__, 2) . '/helpers/menu_model.php';

requireLogin();
requireRole([ROLE_ADMIN]);

$pdo    = getDBConnection();
$errors = [];
$old    = ['menu_name' => '', 'nutrition_desc' => '', 'menu_date' => ''];

/* ── Proses Submit ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $menuName     = trim($_POST['menu_name']      ?? '');
    $nutritionDesc= trim($_POST['nutrition_desc'] ?? '');
    $menuDate     = trim($_POST['menu_date']      ?? '');

    $old = [
        'menu_name'     => $menuName,
        'nutrition_desc'=> $nutritionDesc,
        'menu_date'     => $menuDate,
    ];

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
        $ok = createMenu($pdo, [
            'menu_name'     => $menuName,
            'nutrition_desc'=> $nutritionDesc,
            'menu_date'     => $menuDate,
            'created_by'    => $_SESSION['user_id'] ?? null,
        ]);

        if ($ok) {
            $_SESSION['flash_success'] = 'Menu berhasil ditambahkan.';
            header('Location: ' . BASE_URL . '/admin/menu/index.php');
            exit;
        } else {
            $errors['general'] = 'Terjadi kesalahan saat menyimpan data. Silakan coba lagi.';
        }
    }
}

$pageTitle = 'Tambah Menu MBG';
require_once dirname(__DIR__, 2) . '/includes/header.php';
require_once dirname(__DIR__, 2) . '/includes/sidebar_admin.php';
?>

<div id="main-content">

    <!-- Page Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-bold mb-1">Tambah Menu MBG</h3>
            <p class="text-muted mb-0 small">
                <a href="<?= BASE_URL ?>/admin/menu/index.php" class="text-decoration-none text-muted">
                    <i class="bi bi-arrow-left me-1"></i>Kembali ke daftar menu
                </a>
            </p>
        </div>
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
                <i class="bi bi-journal-plus me-2 text-primary"></i>Informasi Menu Baru
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
                        value="<?= escapeOutput($old['menu_name']) ?>"
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
                        placeholder="Contoh: Ayam goreng 1 potong, sayur bayam, tempe orek, nasi putih..."
                        rows="4"
                        required
                    ><?= escapeOutput($old['nutrition_desc']) ?></textarea>
                    <?php if (isset($errors['nutrition_desc'])): ?>
                        <div class="invalid-feedback"><?= escapeOutput($errors['nutrition_desc']) ?></div>
                    <?php endif; ?>
                    <div class="form-text">Jelaskan kandungan dan komposisi gizi menu ini.</div>
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
                        value="<?= escapeOutput($old['menu_date']) ?>"
                        required
                    >
                    <?php if (isset($errors['menu_date'])): ?>
                        <div class="invalid-feedback"><?= escapeOutput($errors['menu_date']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Info: is_active & created_by otomatis -->
                <div class="alert alert-info border-0 py-2 px-3 mb-4 small">
                    <i class="bi bi-info-circle me-1"></i>
                    Menu baru akan langsung berstatus <strong>Aktif</strong>.
                    Status dapat diubah dari halaman daftar menu.
                </div>

                <!-- Tombol -->
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-floppy me-1"></i>Simpan Menu
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