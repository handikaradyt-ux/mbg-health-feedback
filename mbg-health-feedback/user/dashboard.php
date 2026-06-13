<?php
/**
 * user/dashboard.php
 * Dashboard/beranda untuk role User (Peserta MBG)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../helpers/validation_helper.php';

requireRole([ROLE_USER]);

$pdo    = getDBConnection();
$userId = getCurrentUserId();

// ── Data ─────────────────────────────────────────────────────────────────────

// Data kesehatan terbaru
$stmt = $pdo->prepare("
    SELECT * FROM health_records
    WHERE  user_id = :uid
    ORDER  BY input_date DESC, created_at DESC
    LIMIT  1
");
$stmt->execute([':uid' => $userId]);
$latestHealth = $stmt->fetch();

// Total data kesehatan
$stmt = $pdo->prepare("SELECT COUNT(*) FROM health_records WHERE user_id = :uid");
$stmt->execute([':uid' => $userId]);
$totalHealthRecords = (int) $stmt->fetchColumn();

// Data kesehatan pending
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM health_records WHERE user_id = :uid AND validation_status = 'pending'"
);
$stmt->execute([':uid' => $userId]);
$pendingHealth = (int) $stmt->fetchColumn();

// Total feedback
$stmt = $pdo->prepare("SELECT COUNT(*) FROM feedbacks WHERE user_id = :uid");
$stmt->execute([':uid' => $userId]);
$totalFeedback = (int) $stmt->fetchColumn();

// Label warna BMI
$bmiColors = [
    'Underweight' => 'info',
    'Normal'      => 'success',
    'Overweight'  => 'warning',
    'Obese'       => 'danger',
];

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_user.php';
?>

<div id="main-content">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 fw-bold">Beranda</h4>
            <small class="text-muted">
                Selamat datang, <?= escapeOutput(getCurrentFullName()) ?>!
            </small>
        </div>
        <span class="text-muted small"><?= date('l, d F Y') ?></span>
    </div>

    <!-- Stat Cards -->
    <div class="row g-3 mb-4">

        <div class="col-sm-4">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-body py-4">
                    <i class="bi bi-clipboard2-pulse-fill fs-1 text-primary mb-2"></i>
                    <div class="fs-2 fw-bold"><?= $totalHealthRecords ?></div>
                    <div class="text-muted small">Total Data Kesehatan</div>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="<?= BASE_URL ?>/user/health/input.php"
                       class="btn btn-sm btn-outline-primary w-100">
                        <i class="bi bi-plus-circle me-1"></i>Input Baru
                    </a>
                </div>
            </div>
        </div>

        <div class="col-sm-4">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-body py-4">
                    <i class="bi bi-hourglass-split fs-1 text-warning mb-2"></i>
                    <div class="fs-2 fw-bold"><?= $pendingHealth ?></div>
                    <div class="text-muted small">Menunggu Validasi</div>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="<?= BASE_URL ?>/user/health/history.php"
                       class="btn btn-sm btn-outline-warning w-100">
                        <i class="bi bi-graph-up me-1"></i>Lihat Riwayat
                    </a>
                </div>
            </div>
        </div>

        <div class="col-sm-4">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-body py-4">
                    <i class="bi bi-star-fill fs-1 text-success mb-2"></i>
                    <div class="fs-2 fw-bold"><?= $totalFeedback ?></div>
                    <div class="text-muted small">Total Feedback Diberikan</div>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="<?= BASE_URL ?>/user/feedback/index.php"
                       class="btn btn-sm btn-outline-success w-100">
                        <i class="bi bi-chat-square-text me-1"></i>Beri Feedback
                    </a>
                </div>
            </div>
        </div>

    </div>

    <!-- Status Kesehatan Terkini -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent fw-semibold border-bottom">
            <i class="bi bi-heart-pulse text-danger me-2"></i>Status Kesehatan Terkini
        </div>
        <div class="card-body">
            <?php if ($latestHealth): ?>
                <?php
                $bmiColor = $bmiColors[$latestHealth['bmi_category']] ?? 'secondary';
                $statusColor = match($latestHealth['validation_status']) {
                    'approved' => 'success',
                    'rejected' => 'danger',
                    'revised'  => 'warning',
                    default    => 'secondary',
                };
                ?>
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm mb-0">
                            <tr>
                                <th class="text-muted fw-normal" style="width:45%">Tanggal Input</th>
                                <td><?= escapeOutput($latestHealth['input_date']) ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">Tinggi Badan</th>
                                <td><?= escapeOutput($latestHealth['height_cm']) ?> cm</td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">Berat Badan</th>
                                <td><?= escapeOutput($latestHealth['weight_kg']) ?> kg</td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">Nilai BMI</th>
                                <td><?= escapeOutput($latestHealth['bmi_value']) ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">Kategori BMI</th>
                                <td>
                                    <span class="badge bg-<?= $bmiColor ?>">
                                        <?= escapeOutput($latestHealth['bmi_category']) ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">Status Validasi</th>
                                <td>
                                    <span class="badge bg-<?= $statusColor ?>">
                                        <?= escapeOutput(ucfirst($latestHealth['validation_status'])) ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6 d-flex align-items-center justify-content-center mt-3 mt-md-0">
                        <div class="text-center">
                            <div class="display-1 fw-bold text-<?= $bmiColor ?>">
                                <?= escapeOutput($latestHealth['bmi_value']) ?>
                            </div>
                            <div class="text-muted">Indeks Massa Tubuh</div>
                            <span class="badge bg-<?= $bmiColor ?> mt-1">
                                <?= escapeOutput($latestHealth['bmi_category']) ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="<?= BASE_URL ?>/user/health/history.php"
                       class="btn btn-sm btn-outline-secondary me-2">
                        <i class="bi bi-graph-up me-1"></i>Lihat Riwayat &amp; Grafik
                    </a>
                    <a href="<?= BASE_URL ?>/user/health/input.php"
                       class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-plus-circle me-1"></i>Input Data Baru
                    </a>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="bi bi-clipboard2-x fs-1 text-muted"></i>
                    <p class="text-muted mt-2 mb-3">
                        Anda belum memiliki data kesehatan.
                    </p>
                    <a href="<?= BASE_URL ?>/user/health/input.php"
                       class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i>Input Data Kesehatan Sekarang
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div><!-- end #main-content -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>