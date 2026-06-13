<?php
/**
 * petugas/audit_log.php
 * Riwayat seluruh aktivitas validasi yang dilakukan petugas
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../helpers/validation_helper.php';

requireRole([ROLE_PETUGAS]);

$pdo         = getDBConnection();
$validatorId = getCurrentUserId();

// ── Filter & Pagination ───────────────────────────────────────────────────────
$search       = trim($_GET['search']    ?? '');
$filterType   = $_GET['data_type']      ?? '';
$filterFrom   = $_GET['date_from']      ?? '';
$filterTo     = $_GET['date_to']        ?? '';
$page         = max(1, (int) ($_GET['page'] ?? 1));
$perPage      = 15;
$offset       = ($page - 1) * $perPage;

// Build WHERE clause
$where  = "WHERE vr.validator_id = :vid";
$params = [':vid' => $validatorId];

if ($filterType !== '' && in_array($filterType, ['health_record', 'feedback'], true)) {
    $where .= " AND vr.data_type = :dtype";
    $params[':dtype'] = $filterType;
}
if ($filterFrom !== '') {
    $where .= " AND DATE(vr.validated_at) >= :dfrom";
    $params[':dfrom'] = $filterFrom;
}
if ($filterTo !== '') {
    $where .= " AND DATE(vr.validated_at) <= :dto";
    $params[':dto'] = $filterTo;
}
if ($search !== '') {
    $where .= " AND (u.full_name LIKE :search OR vr.validation_notes LIKE :search2)";
    $params[':search']  = "%{$search}%";
    $params[':search2'] = "%{$search}%";
}

// Count total
$countSql = "
    SELECT COUNT(*)
    FROM   validation_records vr
    JOIN   users u ON u.user_id = vr.validator_id
    {$where}
";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRows  = (int) $countStmt->fetchColumn();
$totalPages = (int) ceil($totalRows / $perPage);

// Fetch paginated rows
$dataSql = "
    SELECT vr.validation_id, vr.data_type, vr.data_id,
           vr.old_status, vr.new_status, vr.validation_notes, vr.validated_at,
           u.full_name AS validator_name
    FROM   validation_records vr
    JOIN   users u ON u.user_id = vr.validator_id
    {$where}
    ORDER  BY vr.validated_at DESC
    LIMIT  :limit OFFSET :offset
";
$dataStmt = $pdo->prepare($dataSql);
// PDO bindValue needed for LIMIT/OFFSET integers
foreach ($params as $k => $v) {
    $dataStmt->bindValue($k, $v);
}
$dataStmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$dataStmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
$dataStmt->execute();
$logs = $dataStmt->fetchAll();

$pageTitle = 'Audit Log Validasi';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_petugas.php';
?>

<div id="main-content">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 fw-bold">
                <i class="bi bi-journal-bookmark me-2 text-secondary"></i>Audit Log Validasi
            </h4>
            <small class="text-muted">Seluruh riwayat aktivitas validasi Anda</small>
        </div>
        <span class="badge bg-secondary fs-6"><?= number_format($totalRows) ?> entri</span>
    </div>

    <!-- Filter bar -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" action="" class="row g-2 align-items-end">
                <div class="col-sm-4">
                    <label class="form-label small fw-semibold mb-1">Cari Nama / Catatan</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control"
                               placeholder="Nama petugas atau catatan..."
                               value="<?= escapeOutput($search) ?>">
                    </div>
                </div>
                <div class="col-sm-2">
                    <label class="form-label small fw-semibold mb-1">Jenis Data</label>
                    <select name="data_type" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        <option value="health_record" <?= $filterType === 'health_record' ? 'selected' : '' ?>>
                            Data Kesehatan
                        </option>
                        <option value="feedback" <?= $filterType === 'feedback' ? 'selected' : '' ?>>
                            Feedback
                        </option>
                    </select>
                </div>
                <div class="col-sm-2">
                    <label class="form-label small fw-semibold mb-1">Dari Tanggal</label>
                    <input type="date" name="date_from" class="form-control form-control-sm"
                           value="<?= escapeOutput($filterFrom) ?>">
                </div>
                <div class="col-sm-2">
                    <label class="form-label small fw-semibold mb-1">Sampai Tanggal</label>
                    <input type="date" name="date_to" class="form-control form-control-sm"
                           value="<?= escapeOutput($filterTo) ?>">
                </div>
                <div class="col-sm-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                    <a href="<?= BASE_URL ?>/petugas/audit_log.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($logs)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-journal-x fs-1 mb-3 d-block"></i>
                    <p class="mb-0">Tidak ada entri yang cocok dengan filter.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tanggal & Waktu</th>
                                <th>Petugas</th>
                                <th>Jenis Data</th>
                                <th>ID Data</th>
                                <th>Status Lama</th>
                                <th>Status Baru</th>
                                <th>Catatan Validasi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log):
                                $newBadge = match($log['new_status']) {
                                    'approved' => 'bg-success',
                                    'rejected' => 'bg-danger',
                                    'revised'  => 'bg-warning text-dark',
                                    'pending'  => 'bg-secondary',
                                    default    => 'bg-light text-dark border',
                                };
                                $oldBadge = match($log['old_status']) {
                                    'approved' => 'bg-success',
                                    'rejected' => 'bg-danger',
                                    'revised'  => 'bg-warning text-dark',
                                    'pending'  => 'bg-secondary',
                                    default    => 'bg-light text-dark border',
                                };
                            ?>
                                <tr>
                                    <td class="small text-nowrap">
                                        <?= date('d/m/Y', strtotime($log['validated_at'])) ?><br>
                                        <span class="text-muted">
                                            <?= date('H:i:s', strtotime($log['validated_at'])) ?>
                                        </span>
                                    </td>
                                    <td class="small fw-semibold">
                                        <?= escapeOutput($log['validator_name']) ?>
                                    </td>
                                    <td>
                                        <?php if ($log['data_type'] === 'health_record'): ?>
                                            <span class="badge bg-danger bg-opacity-10 text-danger">
                                                <i class="bi bi-clipboard2-pulse me-1"></i>Kesehatan
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-info bg-opacity-10 text-info">
                                                <i class="bi bi-chat-square me-1"></i>Feedback
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-center">
                                        <code>#<?= $log['data_id'] ?></code>
                                    </td>
                                    <td>
                                        <span class="badge <?= $oldBadge ?>">
                                            <?= escapeOutput(ucfirst($log['old_status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?= $newBadge ?>">
                                            <?= escapeOutput(ucfirst($log['new_status'])) ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted" style="max-width:200px;">
                                        <?= escapeOutput($log['validation_notes'] ?? '-') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-transparent d-flex justify-content-between align-items-center">
                <small class="text-muted">
                    Halaman <?= $page ?> dari <?= $totalPages ?>
                    (<?= number_format($totalRows) ?> entri)
                </small>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php
                        // Build base query string without page
                        $qp = array_filter([
                            'search'    => $search,
                            'data_type' => $filterType,
                            'date_from' => $filterFrom,
                            'date_to'   => $filterTo,
                        ]);
                        $baseQs = $qp ? '&' . http_build_query($qp) : '';
                        ?>
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?><?= $baseQs ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                        <?php
                        $start = max(1, $page - 2);
                        $end   = min($totalPages, $page + 2);
                        for ($p = $start; $p <= $end; $p++):
                        ?>
                            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $p ?><?= $baseQs ?>">
                                    <?= $p ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?><?= $baseQs ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>

</div><!-- end #main-content -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>