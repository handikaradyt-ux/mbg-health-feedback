<?php
/**
 * admin/menu/index.php
 * Halaman daftar & kelola Menu MBG — Admin
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/auth/session.php';
require_once dirname(__DIR__, 2) . '/helpers/validation_helper.php';
require_once dirname(__DIR__, 2) . '/helpers/menu_model.php';

requireLogin();
requireRole([ROLE_ADMIN]);

$pdo = getDBConnection();

/* ── AJAX: toggle status ── */
if (
    isset($_POST['action']) &&
    $_POST['action'] === 'toggle_status' &&
    isset($_POST['menu_id'])
) {
    header('Content-Type: application/json');
    $menuId = (int) $_POST['menu_id'];
    $ok     = toggleMenuStatus($pdo, $menuId);

    // Ambil nilai is_active terbaru
    $menu = getMenuById($pdo, $menuId);
    echo json_encode([
        'success'   => $ok,
        'is_active' => $menu ? (int) $menu['is_active'] : null,
    ]);
    exit;
}

/* ── Filter & Search ── */
$search  = trim($_GET['search']  ?? '');
$status  = $_GET['status']       ?? '';      // '' | '0' | '1'
$date    = $_GET['date']         ?? '';      // YYYY-MM-DD

/* ── Pagination ── */
$perPage     = 10;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$totalRows   = countAllMenus($pdo, $search, $status, $date);
$totalPages  = max(1, (int) ceil($totalRows / $perPage));
$currentPage = min($currentPage, $totalPages);

$menus = getAllMenus($pdo, $search, $status, $date, $currentPage, $perPage);

/* ── Helper: buat URL dengan parameter dipertahankan ── */
function buildUrl(array $override = []): string {
    $params = array_merge([
        'search' => $_GET['search'] ?? '',
        'status' => $_GET['status'] ?? '',
        'date'   => $_GET['date']   ?? '',
        'page'   => $_GET['page']   ?? 1,
    ], $override);
    return '?' . http_build_query(array_filter($params, fn($v) => $v !== ''));
}

/* ── Pesan flash ── */
$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$pageTitle = 'Kelola Menu MBG';
require_once dirname(__DIR__, 2) . '/includes/header.php';
require_once dirname(__DIR__, 2) . '/includes/sidebar_admin.php';
?>

<div id="main-content">

    <?php if ($flashSuccess): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?= escapeOutput($flashSuccess) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if ($flashError): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= escapeOutput($flashError) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Header Section -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-bold mb-1">Kelola Menu MBG</h3>
            <p class="text-muted mb-0 small">Kelola daftar menu harian Program Makan Bergizi Gratis</p>
        </div>
        <a href="<?= BASE_URL ?>/admin/menu/create.php"
           class="btn btn-primary px-4">
            <i class="bi bi-plus-lg me-1"></i>Tambah Menu Baru
        </a>
    </div>

    <!-- Filter & Search Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" action="" class="row g-2 align-items-end" id="filterForm">
                <!-- Filter Tanggal -->
                <div class="col-auto">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="bi bi-calendar3 text-muted"></i>
                        </span>
                        <input
                            type="date"
                            name="date"
                            class="form-control border-start-0 ps-0"
                            value="<?= escapeOutput($date) ?>"
                            placeholder="mm/dd/yyyy"
                            style="min-width:150px;"
                        >
                    </div>
                </div>

                <!-- Filter Status -->
                <div class="col-auto">
                    <select name="status" class="form-select" style="min-width:150px;">
                        <option value=""  <?= $status === ''  ? 'selected' : '' ?>>Status (Semua)</option>
                        <option value="1" <?= $status === '1' ? 'selected' : '' ?>>Aktif</option>
                        <option value="0" <?= $status === '0' ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                </div>

                <!-- Search -->
                <div class="col">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="bi bi-search text-muted"></i>
                        </span>
                        <input
                            type="text"
                            name="search"
                            class="form-control border-start-0 ps-0"
                            placeholder="Cari nama menu..."
                            value="<?= escapeOutput($search) ?>"
                        >
                    </div>
                </div>

                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-secondary">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                    <?php if ($search || $status !== '' || $date): ?>
                    <a href="?" class="btn btn-outline-danger ms-1">
                        <i class="bi bi-x-circle me-1"></i>Reset
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabel Menu -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width:50px;">No.</th>
                            <th>Nama Menu</th>
                            <th>Deskripsi Gizi</th>
                            <th style="width:130px;">Tanggal Sajian</th>
                            <th style="width:160px;">Rating &amp; Feedback</th>
                            <th style="width:110px;">Status</th>
                            <th style="width:130px;" class="text-end pe-4">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($menus)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                                Tidak ada data menu ditemukan.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php
                        $no = ($currentPage - 1) * $perPage + 1;
                        foreach ($menus as $menu):
                            $menuId  = (int) $menu['menu_id'];
                            $avgRat  = getAverageRating($pdo, $menuId);
                            $fbCount = getFeedbackCount($pdo, $menuId);
                            $isActive = (int) $menu['is_active'];

                            // Bintang rating
                            $fullStars  = (int) floor($avgRat);
                            $halfStar   = ($avgRat - $fullStars) >= 0.5;
                            $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
                        ?>
                        <tr>
                            <td class="ps-4 text-muted"><?= $no++ ?></td>
                            <td class="fw-semibold"><?= escapeOutput($menu['menu_name']) ?></td>
                            <td class="text-muted small" style="max-width:200px;">
                                <?= escapeOutput(mb_strimwidth($menu['nutrition_desc'] ?? '', 0, 40, '...')) ?>
                            </td>
                            <td class="small">
                                <?php
                                    $d = $menu['menu_date'] ?? '';
                                    echo $d ? date('d M Y', strtotime($d)) : '-';
                                ?>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-1 mb-1">
                                    <?php for ($s = 0; $s < $fullStars; $s++): ?>
                                        <i class="bi bi-star-fill text-warning" style="font-size:.85rem;"></i>
                                    <?php endfor; ?>
                                    <?php if ($halfStar): ?>
                                        <i class="bi bi-star-half text-warning" style="font-size:.85rem;"></i>
                                    <?php endif; ?>
                                    <?php for ($s = 0; $s < $emptyStars; $s++): ?>
                                        <i class="bi bi-star text-warning" style="font-size:.85rem;"></i>
                                    <?php endfor; ?>
                                    <span class="fw-bold small ms-1"><?= number_format($avgRat, 1) ?></span>
                                </div>
                                <div class="text-muted" style="font-size:.75rem;"><?= $fbCount ?> Feedback</div>
                            </td>
                            <td>
                                <span class="badge rounded-pill <?= $isActive ? 'bg-success' : 'bg-secondary' ?> px-3 py-2">
                                    <?= $isActive ? 'Aktif' : 'Nonaktif' ?>
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-flex align-items-center justify-content-end gap-2">
                                    <!-- Edit -->
                                    <a href="<?= BASE_URL ?>/admin/menu/edit.php?id=<?= $menuId ?>"
                                       class="btn btn-sm btn-outline-primary border-0 p-1"
                                       title="Edit">
                                        <i class="bi bi-pencil-fill fs-6"></i>
                                    </a>
                                    <!-- Detail -->
                                    <button type="button"
                                            class="btn btn-sm btn-outline-secondary border-0 p-1 btn-detail"
                                            title="Detail"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalDetail"
                                            data-id="<?= $menuId ?>"
                                            data-name="<?= escapeOutput($menu['menu_name']) ?>"
                                            data-desc="<?= escapeOutput($menu['nutrition_desc'] ?? '') ?>"
                                            data-date="<?= $d ? date('d M Y', strtotime($d)) : '-' ?>"
                                            data-rating="<?= number_format($avgRat, 1) ?>"
                                            data-feedback="<?= $fbCount ?>">
                                        <i class="bi bi-eye-fill fs-6"></i>
                                    </button>
                                    <!-- Toggle Switch -->
                                    <div class="form-check form-switch mb-0 ms-1">
                                        <input
                                            class="form-check-input toggle-status"
                                            type="checkbox"
                                            role="switch"
                                            <?= $isActive ? 'checked' : '' ?>
                                            data-id="<?= $menuId ?>"
                                            title="Toggle Status"
                                            style="cursor:pointer; width:2.4em; height:1.3em;"
                                        >
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination Footer -->
        <?php if ($totalRows > 0): ?>
        <div class="card-footer bg-white border-top d-flex align-items-center justify-content-between py-3 px-4">
            <small class="text-muted">
                Menampilkan <?= (($currentPage - 1) * $perPage) + 1 ?>–<?= min($currentPage * $perPage, $totalRows) ?>
                dari <?= $totalRows ?> menu
            </small>
            <nav>
                <ul class="pagination pagination-sm mb-0 gap-1">
                    <!-- Previous -->
                    <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link rounded"
                           href="<?= buildUrl(['page' => $currentPage - 1]) ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>

                    <?php
                    // Tampilkan max 5 halaman sekeliling halaman aktif
                    $startPage = max(1, $currentPage - 2);
                    $endPage   = min($totalPages, $currentPage + 2);

                    if ($startPage > 1): ?>
                        <li class="page-item">
                            <a class="page-link rounded" href="<?= buildUrl(['page' => 1]) ?>">1</a>
                        </li>
                        <?php if ($startPage > 2): ?>
                            <li class="page-item disabled"><span class="page-link">…</span></li>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                    <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                        <a class="page-link rounded" href="<?= buildUrl(['page' => $p]) ?>"><?= $p ?></a>
                    </li>
                    <?php endfor; ?>

                    <?php if ($endPage < $totalPages): ?>
                        <?php if ($endPage < $totalPages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">…</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link rounded" href="<?= buildUrl(['page' => $totalPages]) ?>"><?= $totalPages ?></a>
                        </li>
                    <?php endif; ?>

                    <!-- Next -->
                    <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link rounded"
                           href="<?= buildUrl(['page' => $currentPage + 1]) ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>

</div><!-- end #main-content -->

<!-- ── Modal Detail ── -->
<div class="modal fade" id="modalDetail" tabindex="-1" aria-labelledby="modalDetailLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalDetailLabel">
                    <i class="bi bi-journal-text me-2"></i>Detail Menu
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <dl class="row mb-0">
                    <dt class="col-5 text-muted fw-normal small">Nama Menu</dt>
                    <dd class="col-7 fw-semibold" id="detail-name">—</dd>

                    <dt class="col-5 text-muted fw-normal small">Deskripsi Gizi</dt>
                    <dd class="col-7" id="detail-desc">—</dd>

                    <dt class="col-5 text-muted fw-normal small">Tanggal Sajian</dt>
                    <dd class="col-7" id="detail-date">—</dd>

                    <dt class="col-5 text-muted fw-normal small">Rating</dt>
                    <dd class="col-7" id="detail-rating">—</dd>

                    <dt class="col-5 text-muted fw-normal small">Jumlah Feedback</dt>
                    <dd class="col-7 mb-0" id="detail-feedback">—</dd>
                </dl>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

</div><!-- end .page-wrapper -->

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>

<script>
/* ── Isi Modal Detail ── */
document.querySelectorAll('.btn-detail').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('detail-name').textContent     = this.dataset.name;
        document.getElementById('detail-desc').textContent     = this.dataset.desc || '—';
        document.getElementById('detail-date').textContent     = this.dataset.date;
        document.getElementById('detail-rating').textContent   = this.dataset.rating + ' / 5.0';
        document.getElementById('detail-feedback').textContent = this.dataset.feedback + ' Feedback';
    });
});

/* ── Toggle Status via AJAX ── */
document.querySelectorAll('.toggle-status').forEach(function(toggle) {
    toggle.addEventListener('change', function() {
        var menuId  = this.dataset.id;
        var checked = this.checked;
        var self    = this;

        self.disabled = true;

        fetch(window.location.pathname, {
            method : 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body   : 'action=toggle_status&menu_id=' + encodeURIComponent(menuId),
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                var row    = self.closest('tr');
                var badge  = row.querySelector('.badge');
                var active = data.is_active === 1;

                // Update badge
                badge.textContent = active ? 'Aktif' : 'Nonaktif';
                badge.className   = 'badge rounded-pill px-3 py-2 ' + (active ? 'bg-success' : 'bg-secondary');

                // Sinkronkan state checkbox
                self.checked = active;
            } else {
                // Kembalikan state jika gagal
                self.checked = !checked;
                alert('Gagal mengubah status menu.');
            }
        })
        .catch(function() {
            self.checked = !checked;
            alert('Terjadi kesalahan jaringan.');
        })
        .finally(function() {
            self.disabled = false;
        });
    });
});
</script>