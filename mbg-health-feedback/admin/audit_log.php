<?php
/**
 * admin/audit_log.php
 * Halaman lihat audit log — khusus Admin
 *
 * Fitur:
 *  - Ringkasan statistik (total log hari ini, login aktivitas, aksi validasi)
 *  - Pencarian berdasarkan nama aktor atau deskripsi
 *  - Filter jenis aksi, peran pengguna, rentang tanggal
 *  - Tabel log dengan label berwarna per action_type
 *  - Pagination
 *  - Read-only (immutable — tidak ada tombol edit/hapus)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../helpers/validation_helper.php';

requireRole([ROLE_ADMIN]);

$pdo = getDBConnection();

// ── Konstanta pagination ──────────────────────────────────────────────────────
define('LOGS_PER_PAGE', 10);

// ── Parameter filter dari GET ─────────────────────────────────────────────────
$search      = trim($_GET['search']      ?? '');
$filterAction = trim($_GET['action_type'] ?? '');
$filterRole   = trim($_GET['role']        ?? '');
$dateFrom     = trim($_GET['date_from']   ?? '');
$dateTo       = trim($_GET['date_to']     ?? '');
$page         = max(1, (int) ($_GET['page'] ?? 1));
$offset       = ($page - 1) * LOGS_PER_PAGE;

// ── Daftar action_type yang tersedia (untuk dropdown) ─────────────────────────
$actionTypes = $pdo->query(
    "SELECT DISTINCT action_type FROM audit_logs ORDER BY action_type ASC"
)->fetchAll(PDO::FETCH_COLUMN);

// ── Bangun WHERE clause dinamis ───────────────────────────────────────────────
$where  = [];
$params = [];

if ($search !== '') {
    $where[]           = "(u.full_name LIKE :search OR u.username LIKE :search2 OR al.description LIKE :search3)";
    $params[':search']  = "%{$search}%";
    $params[':search2'] = "%{$search}%";
    $params[':search3'] = "%{$search}%";
}
if ($filterAction !== '') {
    $where[]                 = "al.action_type = :action_type";
    $params[':action_type']  = $filterAction;
}
if ($filterRole !== '') {
    $where[]          = "u.role = :role";
    $params[':role']  = $filterRole;
}
if ($dateFrom !== '') {
    $where[]              = "DATE(al.created_at) >= :date_from";
    $params[':date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $where[]            = "DATE(al.created_at) <= :date_to";
    $params[':date_to'] = $dateTo;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ── Query utama ───────────────────────────────────────────────────────────────
$sqlBase = "
    FROM   audit_logs al
    LEFT   JOIN users u ON u.user_id = al.user_id
    {$whereSql}
";

// Hitung total untuk paginasi
$totalStmt = $pdo->prepare("SELECT COUNT(*) {$sqlBase}");
$totalStmt->execute($params);
$totalLogs  = (int) $totalStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalLogs / LOGS_PER_PAGE));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * LOGS_PER_PAGE;

// Ambil data halaman ini
$dataStmt = $pdo->prepare("
    SELECT al.log_id, al.user_id, al.action_type, al.target_table,
           al.target_id, al.description, al.ip_address, al.created_at,
           u.full_name, u.username, u.role
    {$sqlBase}
    ORDER  BY al.created_at DESC
    LIMIT  :lim OFFSET :off
");
foreach ($params as $key => $val) {
    $dataStmt->bindValue($key, $val);
}
$dataStmt->bindValue(':lim', LOGS_PER_PAGE, PDO::PARAM_INT);
$dataStmt->bindValue(':off', $offset,       PDO::PARAM_INT);
$dataStmt->execute();
$logs = $dataStmt->fetchAll();

// ── Ringkasan statistik ───────────────────────────────────────────────────────
$todayTotal = (int) $pdo->query(
    "SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at) = CURDATE()"
)->fetchColumn();

$todayLogin = (int) $pdo->query(
    "SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at) = CURDATE() AND action_type IN ('LOGIN','LOGIN_FAILED')"
)->fetchColumn();

$todayValidasi = (int) $pdo->query(
    "SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at) = CURDATE() AND action_type LIKE 'VALIDATE%'"
)->fetchColumn();

// ── Helper: badge warna per action_type ──────────────────────────────────────
function actionBadge(string $action): string
{
    $map = [
        'LOGIN'              => 'primary',
        'LOGIN_FAILED'       => 'danger',
        'LOGOUT'             => 'secondary',
        'CREATE'             => 'success',
        'CREATE_USER'        => 'success',
        'UPDATE'             => 'info',
        'DELETE'             => 'danger',
        'VALIDATE_FEEDBACK'  => 'warning',
        'VALIDATE_HEALTH'    => 'warning',
        'VALIDATE_APPROVED'  => 'success',
        'VALIDATE_REVISED'   => 'orange',   // custom
        'SUBMIT_HEALTH'      => 'info',
        'SUBMIT_FEEDBACK'    => 'info',
    ];
    $color = $map[$action] ?? 'secondary';

    if ($color === 'orange') {
        return "<span class=\"badge badge-orange\">{$action}</span>";
    }
    return "<span class=\"badge bg-{$color} text-" . ($color === 'warning' ? 'dark' : 'white') . "\">{$action}</span>";
}

// ── Helper: label peran ───────────────────────────────────────────────────────
function roleBadge(?string $role): string
{
    if ($role === null) return '<span class="badge bg-secondary text-white">-</span>';
    $map = [
        'admin'   => ['Admin',    'bg-dark'],
        'petugas' => ['Petugas',  'bg-petugas'],
        'user'    => ['Pengguna', 'bg-user'],
    ];
    [$label, $cls] = $map[$role] ?? [ucfirst($role), 'bg-secondary'];
    return "<span class=\"badge {$cls}\">{$label}</span>";
}

// ── Helper: format target data ────────────────────────────────────────────────
function formatTarget(?string $table, ?int $id): string
{
    if ($table === null || $table === '') return '<span class="text-muted">—</span>';
    $label = match($table) {
        'users'          => 'User',
        'feedbacks'      => 'Feedback',
        'health_records' => 'HealthRecord',
        'menus'          => 'Menu',
        default          => ucfirst($table),
    };
    $suffix = $id ? "#{$id}" : '';
    return "<code class=\"text-secondary small\">{$label}{$suffix}</code>";
}

// ── Bangun query string untuk paginasi (tanpa param page) ────────────────────
function buildQuery(array $exclude = ['page']): string
{
    $params = $_GET;
    foreach ($exclude as $k) unset($params[$k]);
    return $params ? ('&' . http_build_query($params)) : '';
}

$pageTitle = 'Audit Log Sistem';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_admin.php';
?>

<div id="main-content">

    <!-- ── Page header ───────────────────────────────────────────────────────── -->
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h4 class="mb-1 fw-bold">Audit Log Sistem</h4>
            <p class="text-muted small mb-0">Pantau semua aktivitas dan perubahan data dalam sistem MBG-Health.</p>
        </div>
        <span class="badge bg-light border text-secondary px-3 py-2" style="font-size:.78rem">
            <i class="bi bi-lock-fill me-1 text-muted"></i>Log bersifat immutable — tidak dapat diedit atau dihapus
        </span>
    </div>

    <!-- ── Statistik ─────────────────────────────────────────────────────────── -->
    <div class="row g-3 mb-4">
        <!-- Total log hari ini -->
        <div class="col-sm-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-3 bg-primary bg-opacity-10 d-flex align-items-center justify-content-center"
                         style="width:48px;height:48px;flex-shrink:0">
                        <i class="bi bi-journal-text text-primary fs-5"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Log Hari Ini</div>
                        <div class="fw-bold fs-4 lh-1"><?= number_format($todayTotal) ?></div>
                        <div class="text-muted" style="font-size:.72rem">
                            <i class="bi bi-graph-up-arrow text-success me-1"></i>Normal volume
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Login aktivitas -->
        <div class="col-sm-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-3 bg-info bg-opacity-10 d-flex align-items-center justify-content-center"
                         style="width:48px;height:48px;flex-shrink:0">
                        <i class="bi bi-box-arrow-in-right text-info fs-5"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Login Aktivitas</div>
                        <div class="fw-bold fs-4 lh-1"><?= number_format($todayLogin) ?></div>
                        <div class="text-muted" style="font-size:.72rem">
                            <i class="bi bi-clock me-1"></i>2 jam terakhir
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Aksi validasi -->
        <div class="col-sm-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-3 bg-warning bg-opacity-10 d-flex align-items-center justify-content-center"
                         style="width:48px;height:48px;flex-shrink:0">
                        <i class="bi bi-patch-check text-warning fs-5"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Aksi Validasi</div>
                        <div class="fw-bold fs-4 lh-1"><?= number_format($todayValidasi) ?></div>
                        <div class="text-muted" style="font-size:.72rem">
                            <i class="bi bi-graph-up-arrow text-warning me-1"></i>Tinggi hari ini
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Filter & pencarian ────────────────────────────────────────────────── -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-3">
            <form method="GET" action="" class="row g-2 align-items-end">

                <!-- Pencarian teks -->
                <div class="col-sm">
                    <label class="form-label form-label-sm text-muted mb-1">Pencarian</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="bi bi-search text-muted"></i>
                        </span>
                        <input type="text" name="search" class="form-control border-start-0"
                               placeholder="Cari berdasarkan nama aktor atau deskripsi..."
                               value="<?= escapeOutput($search) ?>">
                    </div>
                </div>

                <!-- Jenis aksi -->
                <div class="col-sm-auto">
                    <label class="form-label form-label-sm text-muted mb-1">Jenis Aksi</label>
                    <select name="action_type" class="form-select form-select-sm" style="min-width:150px">
                        <option value="">Semua Aksi</option>
                        <?php foreach ($actionTypes as $at): ?>
                            <option value="<?= escapeOutput($at) ?>"
                                    <?= $filterAction === $at ? 'selected' : '' ?>>
                                <?= escapeOutput($at) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Peran aktor -->
                <div class="col-sm-auto">
                    <label class="form-label form-label-sm text-muted mb-1">Peran Aktor</label>
                    <select name="role" class="form-select form-select-sm" style="min-width:130px">
                        <option value="">Semua Peran</option>
                        <option value="admin"   <?= $filterRole === 'admin'   ? 'selected' : '' ?>>Admin</option>
                        <option value="petugas" <?= $filterRole === 'petugas' ? 'selected' : '' ?>>Petugas</option>
                        <option value="user"    <?= $filterRole === 'user'    ? 'selected' : '' ?>>Pengguna</option>
                    </select>
                </div>

                <!-- Rentang waktu -->
                <div class="col-sm-auto">
                    <label class="form-label form-label-sm text-muted mb-1">Rentang Waktu</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="bi bi-calendar3 text-muted" style="font-size:.8rem"></i>
                        </span>
                        <input type="date" name="date_from" class="form-control border-start-0"
                               value="<?= escapeOutput($dateFrom) ?>" style="max-width:130px">
                        <span class="input-group-text bg-white">–</span>
                        <input type="date" name="date_to" class="form-control"
                               value="<?= escapeOutput($dateTo) ?>" style="max-width:130px">
                    </div>
                </div>

                <!-- Tombol -->
                <div class="col-sm-auto d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm px-3">
                        <i class="bi bi-funnel me-1"></i>Terapkan Filter
                    </button>
                    <a href="<?= BASE_URL ?>/admin/audit_log.php" class="btn btn-outline-secondary btn-sm px-3">
                        Reset
                    </a>
                </div>

            </form>
        </div>
    </div>

    <!-- ── Tabel log ─────────────────────────────────────────────────────────── -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">

            <?php if (empty($logs)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-journal-x fs-1 d-block mb-2 opacity-25"></i>
                    Tidak ada entri log yang cocok dengan filter saat ini.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:.875rem">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4 py-3" style="min-width:140px">WAKTU</th>
                                <th class="py-3" style="min-width:130px">PENGGUNA</th>
                                <th class="py-3">PERAN</th>
                                <th class="py-3" style="min-width:150px">JENIS AKSI</th>
                                <th class="py-3" style="min-width:130px">TARGET DATA</th>
                                <th class="py-3 pe-4">DESKRIPSI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr class="log-row">
                                    <td class="ps-4 text-muted small" style="white-space:nowrap">
                                        <?= escapeOutput(date('d M Y, H:i', strtotime($log['created_at']))) ?>
                                    </td>
                                    <td>
                                        <?php if ($log['full_name']): ?>
                                            <div class="fw-semibold"><?= escapeOutput($log['full_name']) ?></div>
                                            <div class="text-muted small">@<?= escapeOutput($log['username']) ?></div>
                                        <?php else: ?>
                                            <span class="text-muted small fst-italic">Sistem / Anonim</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= roleBadge($log['role']) ?></td>
                                    <td><?= actionBadge($log['action_type']) ?></td>
                                    <td><?= formatTarget($log['target_table'], $log['target_id']) ?></td>
                                    <td class="pe-4 text-muted small" style="max-width:260px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis"
                                        title="<?= escapeOutput($log['description']) ?>">
                                        <?= escapeOutput($log['description'] ?: '—') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- ── Pagination ── -->
                <div class="d-flex justify-content-between align-items-center px-4 py-3 border-top">
                    <span class="text-muted small">
                        Menampilkan
                        <?= number_format($offset + 1) ?>–<?= number_format(min($offset + LOGS_PER_PAGE, $totalLogs)) ?>
                        dari <strong><?= number_format($totalLogs) ?></strong> entri log
                    </span>
                    <?php if ($totalPages > 1): ?>
                        <nav>
                            <ul class="pagination pagination-sm mb-0 gap-1">
                                <!-- Sebelumnya -->
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link rounded"
                                       href="?page=<?= $page - 1 . buildQuery() ?>">
                                        <i class="bi bi-chevron-left" style="font-size:.7rem"></i>
                                    </a>
                                </li>

                                <?php
                                // Tampilkan maks 5 nomor halaman di sekitar halaman aktif
                                $start = max(1, $page - 2);
                                $end   = min($totalPages, $page + 2);
                                if ($start > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link rounded" href="?page=1<?= buildQuery() ?>">1</a>
                                    </li>
                                    <?php if ($start > 2): ?>
                                        <li class="page-item disabled"><span class="page-link">…</span></li>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php for ($p = $start; $p <= $end; $p++): ?>
                                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                        <a class="page-link rounded"
                                           href="?page=<?= $p . buildQuery() ?>"><?= $p ?></a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($end < $totalPages): ?>
                                    <?php if ($end < $totalPages - 1): ?>
                                        <li class="page-item disabled"><span class="page-link">…</span></li>
                                    <?php endif; ?>
                                    <li class="page-item">
                                        <a class="page-link rounded"
                                           href="?page=<?= $totalPages . buildQuery() ?>"><?= $totalPages ?></a>
                                    </li>
                                <?php endif; ?>

                                <!-- Berikutnya -->
                                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                    <a class="page-link rounded"
                                       href="?page=<?= $page + 1 . buildQuery() ?>">
                                        <i class="bi bi-chevron-right" style="font-size:.7rem"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>

            <?php endif; ?>
        </div>
    </div>

</div><!-- /#main-content -->

<style>
/* ── Badge peran ──────────────────────────────────────────────────────────── */
.badge.bg-petugas { background-color: #9b59b6; color: #fff; }
.badge.bg-user    { background-color: #27ae60; color: #fff; }

/* ── Badge aksi orange (VALIDATE_REVISED) ──────────────────────────────────── */
.badge.badge-orange { background-color: #fd7e14; color: #fff; font-weight: 500; }

/* ── Hover baris tabel ────────────────────────────────────────────────────── */
.log-row:hover { background-color: rgba(0, 0, 0, .03); }
.log-row td    { vertical-align: middle; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>