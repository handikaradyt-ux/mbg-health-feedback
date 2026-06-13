<?php
/**
 * petugas/dashboard.php
 * Dashboard ringkasan untuk Petugas Validasi
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../helpers/validation_helper.php';

requireRole([ROLE_PETUGAS]);

$pdo    = getDBConnection();
$userId = getCurrentUserId();

// ── Statistik ────────────────────────────────────────────────────────────────
$pendingHealth = (int) $pdo->query(
    "SELECT COUNT(*) FROM health_records WHERE validation_status = 'pending'"
)->fetchColumn();

$pendingFeedback = (int) $pdo->query(
    "SELECT COUNT(*) FROM feedbacks WHERE validation_status = 'pending'"
)->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM validation_records
     WHERE validator_id = :vid AND DATE(validated_at) = CURDATE()"
);
$stmt->execute([':vid' => $userId]);
$validatedToday = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM validation_records WHERE validator_id = :vid"
);
$stmt->execute([':vid' => $userId]);
$totalValidated = (int) $stmt->fetchColumn();

// 5 validasi terakhir oleh petugas ini
$stmt = $pdo->prepare("
    SELECT vr.data_type, vr.new_status, vr.validation_notes, vr.validated_at
    FROM   validation_records vr
    WHERE  vr.validator_id = :vid
    ORDER  BY vr.validated_at DESC
    LIMIT  5
");
$stmt->execute([':vid' => $userId]);
$myLogs = $stmt->fetchAll();

$pageTitle = 'Dashboard Petugas';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_petugas.php';
?>

<div id="main-content">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 fw-bold">Dashboard Petugas Validasi</h4>
            <small class="text-muted">
                Selamat datang, <?= escapeOutput(getCurrentFullName()) ?>!
            </small>
        </div>
        <span class="text-muted small"><?= date('l, d F Y') ?></span>
    </div>

    <!-- Stat Cards -->
    <div class="row g-3 mb-4">

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-danger bg-opacity-10 p-3">
                        <i class="bi bi-clipboard2-pulse-fill fs-4 text-danger"></i>
                    </div>
                    <div>
                        <div class="fs-2 fw-bold lh-1"><?= $pendingHealth ?></div>
                        <div class="text-muted small">Kesehatan Pending</div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 pt-0">
                    <a href="<?= BASE_URL ?>/petugas/validate_health.php"
                       class="btn btn-sm btn-outline-danger w-100">
                        Proses Sekarang
                    </a>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-warning bg-opacity-10 p-3">
                        <i class="bi bi-chat-square-check-fill fs-4 text-warning"></i>
                    </div>
                    <div>
                        <div class="fs-2 fw-bold lh-1"><?= $pendingFeedback ?></div>
                        <div class="text-muted small">Feedback Pending</div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 pt-0">
                    <a href="<?= BASE_URL ?>/petugas/validate_feedback.php"
                       class="btn btn-sm btn-outline-warning w-100">
                        Proses Sekarang
                    </a>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3">
                        <i class="bi bi-check2-circle fs-4 text-success"></i>
                    </div>
                    <div>
                        <div class="fs-2 fw-bold lh-1"><?= $validatedToday ?></div>
                        <div class="text-muted small">Divalidasi Hari Ini</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3">
                        <i class="bi bi-bar-chart-fill fs-4 text-primary"></i>
                    </div>
                    <div>
                        <div class="fs-2 fw-bold lh-1"><?= $totalValidated ?></div>
                        <div class="text-muted small">Total Validasi Saya</div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Riwayat Validasi -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent fw-semibold border-bottom">
            <i class="bi bi-clock-history text-secondary me-2"></i>Validasi Terakhir Saya
        </div>
        <div class="card-body p-0">
            <?php if (empty($myLogs)): ?>
                <p class="text-muted text-center py-4 mb-0">Belum ada riwayat validasi.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Jenis Data</th>
                                <th>Status</th>
                                <th>Catatan</th>
                                <th>Waktu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($myLogs as $log):
                                $badgeClass = match($log['new_status']) {
                                    'approved' => 'bg-success',
                                    'rejected' => 'bg-danger',
                                    'revised'  => 'bg-warning text-dark',
                                    default    => 'bg-secondary',
                                };
                            ?>
                                <tr>
                                    <td class="small">
                                        <?php if ($log['data_type'] === 'health_record'): ?>
                                            <i class="bi bi-clipboard2-pulse me-1 text-danger"></i>Kesehatan
                                        <?php else: ?>
                                            <i class="bi bi-chat-square me-1 text-info"></i>Feedback
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $badgeClass ?>">
                                            <?= escapeOutput(ucfirst($log['new_status'])) ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted">
                                        <?= escapeOutput($log['validation_notes'] ?? '-') ?>
                                    </td>
                                    <td class="small text-nowrap">
                                        <?= escapeOutput(date('d/m/Y H:i', strtotime($log['validated_at']))) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-footer bg-transparent">
            <a href="<?= BASE_URL ?>/petugas/audit_log.php" class="small">
                Lihat semua riwayat &rarr;
            </a>
        </div>
    </div>

</div><!-- end #main-content -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
