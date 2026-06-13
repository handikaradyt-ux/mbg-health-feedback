<?php
/**
 * admin/dashboard.php
 * Dashboard ringkasan untuk Admin
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../helpers/validation_helper.php';

requireRole([ROLE_ADMIN]);

$pdo = getDBConnection();

// ── Statistik ────────────────────────────────────────────────────────────────
$totalActiveUsers = (int) $pdo->query(
    "SELECT COUNT(*) FROM users WHERE role = 'user' AND is_active = 1"
)->fetchColumn();

$totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

$totalHealthRecords = (int) $pdo->query(
    "SELECT COUNT(*) FROM health_records"
)->fetchColumn();

$totalFeedback = (int) $pdo->query("SELECT COUNT(*) FROM feedbacks")->fetchColumn();

$pendingHealth = (int) $pdo->query(
    "SELECT COUNT(*) FROM health_records WHERE validation_status = 'pending'"
)->fetchColumn();

$pendingFeedback = (int) $pdo->query(
    "SELECT COUNT(*) FROM feedbacks WHERE validation_status = 'pending'"
)->fetchColumn();

$totalPending = $pendingHealth + $pendingFeedback;

// 5 Aktivitas terbaru dari audit log
$recentActivities = $pdo->query("
    SELECT al.action_type, al.description, al.created_at,
           u.full_name, u.username
    FROM   audit_logs al
    LEFT JOIN users u ON al.user_id = u.user_id
    ORDER  BY al.created_at DESC
    LIMIT  5
")->fetchAll();

$pageTitle = 'Dashboard Admin';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_admin.php';
?>

<div id="main-content">

    <!-- Heading -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 fw-bold">Dashboard Admin</h4>
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
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3">
                        <i class="bi bi-people-fill fs-4 text-primary"></i>
                    </div>
                    <div>
                        <div class="fs-2 fw-bold lh-1"><?= $totalActiveUsers ?></div>
                        <div class="text-muted small">Pengguna Aktif</div>
                        <div class="text-muted" style="font-size:.75rem;">Total akun: <?= $totalUsers ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-info bg-opacity-10 p-3">
                        <i class="bi bi-clipboard2-pulse-fill fs-4 text-info"></i>
                    </div>
                    <div>
                        <div class="fs-2 fw-bold lh-1"><?= $totalHealthRecords ?></div>
                        <div class="text-muted small">Data Kesehatan</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3">
                        <i class="bi bi-chat-square-text-fill fs-4 text-success"></i>
                    </div>
                    <div>
                        <div class="fs-2 fw-bold lh-1"><?= $totalFeedback ?></div>
                        <div class="text-muted small">Total Feedback</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-warning bg-opacity-10 p-3">
                        <i class="bi bi-hourglass-split fs-4 text-warning"></i>
                    </div>
                    <div>
                        <div class="fs-2 fw-bold lh-1"><?= $totalPending ?></div>
                        <div class="text-muted small">Pending Validasi</div>
                        <div class="text-muted" style="font-size:.75rem;">
                            Kesehatan: <?= $pendingHealth ?> &bull; Feedback: <?= $pendingFeedback ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Aktivitas Terbaru -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent fw-semibold border-bottom">
            <i class="bi bi-journal-bookmark text-primary me-2"></i>Aktivitas Terbaru
        </div>
        <div class="card-body p-0">
            <?php if (empty($recentActivities)): ?>
                <p class="text-muted text-center py-4 mb-0">Belum ada aktivitas.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Waktu</th>
                                <th>Pengguna</th>
                                <th>Aksi</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentActivities as $log): ?>
                                <tr>
                                    <td class="small text-nowrap">
                                        <?= escapeOutput(date('d/m/Y H:i', strtotime($log['created_at']))) ?>
                                    </td>
                                    <td class="small">
                                        <?= escapeOutput($log['full_name'] ?? $log['username'] ?? 'Sistem') ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?= escapeOutput($log['action_type']) ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted">
                                        <?= escapeOutput($log['description'] ?? '') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-footer bg-transparent">
            <a href="<?= BASE_URL ?>/admin/audit_log.php" class="small">
                Lihat semua audit log &rarr;
            </a>
        </div>
    </div>

</div><!-- end #main-content -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
