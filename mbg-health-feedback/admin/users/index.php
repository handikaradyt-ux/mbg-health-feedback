<?php
/**
 * admin/users/index.php
 * Daftar pengguna — search, filter role, pagination
 */

require_once dirname(__DIR__, 2) . '/auth/session.php';
require_once dirname(__DIR__, 2) . '/helpers/user_model.php';
require_once dirname(__DIR__, 2) . '/helpers/validation_helper.php';

requireRole([ROLE_ADMIN]);

$pageTitle = 'Manajemen Pengguna';

// --- Filter & Pagination params ---
$search     = trim($_GET['search'] ?? '');
$roleFilter = trim($_GET['role'] ?? '');
$page       = max(1, (int) ($_GET['page'] ?? 1));
$limit      = 10;
$offset     = ($page - 1) * $limit;

$validRoles = [ROLE_ADMIN, ROLE_PETUGAS, ROLE_USER];
if ($roleFilter !== '' && !in_array($roleFilter, $validRoles, true)) {
    $roleFilter = '';
}

$totalUsers = countUsers($search, $roleFilter);
$totalPages = (int) ceil($totalUsers / $limit);
if ($totalPages > 0 && $page > $totalPages) {
    $page   = $totalPages;
    $offset = ($page - 1) * $limit;
}

$users = getAllUsers($limit, $offset, $search, $roleFilter);

// --- Flash message dari redirect ---
startSecureSession();
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$roleLabels = [
    ROLE_ADMIN   => ['label' => 'Admin',            'class' => 'bg-danger'],
    ROLE_PETUGAS => ['label' => 'Petugas Validasi', 'class' => 'bg-warning text-dark'],
    ROLE_USER    => ['label' => 'Pengguna',         'class' => 'bg-primary'],
];

require_once dirname(__DIR__, 2) . '/includes/header.php';
require_once dirname(__DIR__, 2) . '/includes/sidebar_admin.php';
?>

<div id="main-content">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <h2 class="fw-bold mb-1">Manajemen Pengguna</h2>
            <p class="text-muted mb-0">Kelola akses, peran, dan status akun pengguna sistem.</p>
        </div>
        <a href="<?= BASE_URL ?>/admin/users/create.php" class="btn btn-success">
            <i class="bi bi-plus-lg me-1"></i>Tambah Pengguna Baru
        </a>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?= escapeOutput($flash['type']) ?> alert-dismissible fade show" role="alert">
            <?= escapeOutput($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Search & Filter -->
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" action="<?= BASE_URL ?>/admin/users/index.php" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control"
                               placeholder="Cari nama atau username..."
                               value="<?= escapeOutput($search) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="role" class="form-select">
                        <option value="">Semua Peran</option>
                        <option value="<?= ROLE_ADMIN ?>"   <?= $roleFilter === ROLE_ADMIN   ? 'selected' : '' ?>>Admin</option>
                        <option value="<?= ROLE_PETUGAS ?>" <?= $roleFilter === ROLE_PETUGAS ? 'selected' : '' ?>>Petugas Validasi</option>
                        <option value="<?= ROLE_USER ?>"    <?= $roleFilter === ROLE_USER    ? 'selected' : '' ?>>Pengguna</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                    <a href="<?= BASE_URL ?>/admin/users/index.php" class="btn btn-outline-secondary" title="Reset">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabel -->
    <div class="card shadow-sm">
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>No.</th>
                        <th>Nama Lengkap</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Peran</th>
                        <th>Status</th>
                        <th>Tanggal Dibuat</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                Tidak ada data pengguna ditemukan.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $i => $u): ?>
                            <?php
                                $rb = $roleLabels[$u['role']] ?? ['label' => ucfirst($u['role']), 'class' => 'bg-secondary'];
                            ?>
                            <tr>
                                <td><?= $offset + $i + 1 ?></td>
                                <td class="fw-semibold"><?= escapeOutput($u['full_name']) ?></td>
                                <td><?= escapeOutput($u['username']) ?></td>
                                <td><?= escapeOutput($u['email'] ?? '-') ?></td>
                                <td><span class="badge <?= $rb['class'] ?>"><?= $rb['label'] ?></span></td>
                                <td>
                                    <?php if ((int) $u['is_active'] === 1): ?>
                                        <span class="badge bg-success"><i class="bi bi-circle-fill me-1" style="font-size:.5rem;"></i>Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><i class="bi bi-circle-fill me-1" style="font-size:.5rem;"></i>Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                                <td class="text-center">
                                    <a href="<?= BASE_URL ?>/admin/users/edit.php?id=<?= (int) $u['user_id'] ?>"
                                       class="btn btn-sm btn-outline-primary" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form action="<?= BASE_URL ?>/admin/users/toggle_status.php" method="POST" class="d-inline"
                                          onsubmit="return confirm('<?= (int) $u['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?> pengguna ini?');">
                                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                        <input type="hidden" name="user_id" value="<?= (int) $u['user_id'] ?>">
                                        <input type="hidden" name="redirect_page" value="<?= (int) $page ?>">
                                        <input type="hidden" name="redirect_search" value="<?= escapeOutput($search) ?>">
                                        <input type="hidden" name="redirect_role" value="<?= escapeOutput($roleFilter) ?>">
                                        <button type="submit"
                                                class="btn btn-sm <?= (int) $u['is_active'] === 1 ? 'btn-outline-secondary' : 'btn-outline-success' ?>"
                                                title="<?= (int) $u['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                            <i class="bi <?= (int) $u['is_active'] === 1 ? 'bi-toggle2-on' : 'bi-toggle2-off' ?>"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="card-footer d-flex justify-content-between align-items-center bg-white">
            <small class="text-muted">
                Menampilkan <?= $offset + 1 ?>–<?= min($offset + $limit, $totalUsers) ?> dari <?= $totalUsers ?> pengguna
            </small>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php
                        $qs = function (int $p) use ($search, $roleFilter) {
                            return '?' . http_build_query(['search' => $search, 'role' => $roleFilter, 'page' => $p]);
                        };
                    ?>
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $qs(max(1, $page - 1)) ?>">&laquo;</a>
                    </li>
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                            <a class="page-link" href="<?= $qs($p) ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $qs(min($totalPages, $page + 1)) ?>">&raquo;</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>