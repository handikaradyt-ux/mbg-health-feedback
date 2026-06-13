<?php
/**
 * petugas/dashboard.php
 * Dashboard ringkasan untuk Petugas Validasi
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../helpers/validation_helper.php';
require_once __DIR__ . '/../helpers/health_model.php';
require_once __DIR__ . '/../helpers/feedback_model.php';

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
    "SELECT COUNT(*) FROM validation_records
     WHERE validator_id = :vid
       AND MONTH(validated_at) = MONTH(CURDATE())
       AND YEAR(validated_at)  = YEAR(CURDATE())"
);
$stmt->execute([':vid' => $userId]);
$totalThisMonth = (int) $stmt->fetchColumn();

// Delta kesehatan pending vs kemarin
$pendingHealthYesterday = (int) $pdo->query(
    "SELECT COUNT(*) FROM health_records
     WHERE validation_status = 'pending'
       AND DATE(created_at) < CURDATE()"
)->fetchColumn();
$deltaHealth = $pendingHealth - $pendingHealthYesterday;

// Target harian (mis. 20/hari) — bisa disesuaikan
$dailyTarget = 20;

// 5 preview antrian kesehatan
$previewHealth   = getPendingHealthRecords($pdo, 3);

// 3 preview antrian feedback
$previewFeedback = getPendingFeedbacks($pdo, 3);

// 10 aktivitas terakhir petugas ini
$stmt = $pdo->prepare("
    SELECT vr.data_type, vr.new_status, vr.validation_notes, vr.validated_at,
           CASE vr.data_type
               WHEN 'health_record' THEN CONCAT('Data Kesehatan ', u2.full_name, ' divalidasi')
               ELSE                      CONCAT('Feedback Menu ', m.menu_name, ' divalidasi')
           END AS activity_label
    FROM   validation_records vr
    JOIN   users u2 ON u2.user_id = (
               SELECT CASE vr.data_type
                   WHEN 'health_record'
                       THEN (SELECT user_id FROM health_records WHERE health_id = vr.data_id)
                   ELSE
                       (SELECT user_id FROM feedbacks WHERE feedback_id = vr.data_id)
               END
           )
    LEFT JOIN feedbacks  fb ON fb.feedback_id = vr.data_id AND vr.data_type = 'feedback'
    LEFT JOIN menus       m ON m.menu_id = fb.menu_id
    WHERE  vr.validator_id = :vid
    ORDER  BY vr.validated_at DESC
    LIMIT  10
");
$stmt->execute([':vid' => $userId]);
$activityLogs = $stmt->fetchAll();

// Fallback sederhana jika query JOIN kompleks gagal di beberapa setup
if (empty($activityLogs)) {
    $stmt = $pdo->prepare("
        SELECT data_type, new_status, validation_notes, validated_at,
               CONCAT(IF(data_type='health_record','Data Kesehatan ','Feedback Menu '), '#', data_id, ' divalidasi') AS activity_label
        FROM   validation_records
        WHERE  validator_id = :vid
        ORDER  BY validated_at DESC
        LIMIT  10
    ");
    $stmt->execute([':vid' => $userId]);
    $activityLogs = $stmt->fetchAll();
}

$pageTitle = 'Dashboard Petugas';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_petugas.php';
?>

<div id="main-content">

    <!-- ── Header ── -->
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h4 class="mb-1 fw-bold">Dashboard Petugas Validasi</h4>
            <p class="text-muted mb-0 small">
                Selamat datang, <?= escapeOutput(getCurrentFullName()) ?>.
                Berikut ringkasan tugas Anda hari ini.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted small">
                <i class="bi bi-calendar3 me-1"></i><?= date('l, d F Y') ?>
            </span>
        </div>
    </div>

    <!-- ── Stat Cards ── -->
    <div class="row g-3 mb-4">

        <!-- Data Kesehatan Pending -->
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

        <!-- Feedback Pending -->
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


        <!-- Divalidasi Hari Ini -->
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

        <!-- Total Validasi Bulan Ini -->
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3">
                        <i class="bi bi-bar-chart-fill fs-4 text-primary"></i>
                    </div>
                    <div>
                        <div class="fs-2 fw-bold lh-1"><?= $totalThisMonth?></div>
                        <div class="text-muted small">Total Validasi Saya</div>
                    </div>
                </div>
            </div>
        </div>


    </div>

    <!-- ── Antrian Validasi Terbaru ── -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0">Antrian Validasi Terbaru</h5>
        <a href="<?= BASE_URL ?>/petugas/validate_health.php" class="small text-primary text-decoration-none">
            Lihat Semua <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>

    <div class="row g-3 mb-4">

        <!-- Antrian Kesehatan -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center py-3">
                    <span class="fw-semibold">
                        <i class="bi bi-heart-pulse me-2 text-primary"></i>Data Kesehatan
                    </span>
                    <span class="badge bg-warning text-dark"><?= $pendingHealth ?> Pending</span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($previewHealth)): ?>
                        <p class="text-muted text-center py-4 mb-0 small">Tidak ada antrian.</p>
                    <?php else: ?>
                        <table class="table table-sm table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Nama Siswa</th>
                                    <th>Tanggal Input</th>
                                    <th>BMI</th>
                                    <th class="pe-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($previewHealth as $rec):
                                    $bmiBadge = match($rec['bmi_category']) {
                                        'Normal'      => 'bg-success',
                                        'Overweight'  => 'bg-warning text-dark',
                                        'Obese'       => 'bg-danger',
                                        'Underweight' => 'bg-info text-dark',
                                        default       => 'bg-secondary',
                                    };
                                    $isToday = date('Y-m-d') === $rec['input_date'];
                                ?>
                                    <tr>
                                        <td class="ps-3 fw-semibold small"><?= escapeOutput($rec['full_name']) ?></td>
                                        <td class="small text-muted">
                                            <?= $isToday ? 'Hari Ini' : date('d/m/Y', strtotime($rec['input_date'])) ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= $bmiBadge ?>">
                                                <?= number_format($rec['bmi_value'], 1) ?>
                                                (<?= escapeOutput($rec['bmi_category']) ?>)
                                            </span>
                                        </td>
                                        <td class="pe-3">
                                            <a href="<?= BASE_URL ?>/petugas/validate_health.php"
                                               class="btn btn-sm btn-primary px-3">
                                                Periksa
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Antrian Feedback -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center py-3">
                    <span class="fw-semibold">
                        <i class="bi bi-star me-2 text-warning"></i>Feedback Menu
                    </span>
                    <span class="badge bg-warning text-dark"><?= $pendingFeedback ?> Pending</span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($previewFeedback)): ?>
                        <p class="text-muted text-center py-4 mb-0 small">Tidak ada antrian.</p>
                    <?php else: ?>
                        <table class="table table-sm table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Menu & Siswa</th>
                                    <th>Rating</th>
                                    <th class="pe-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($previewFeedback as $fb): ?>
                                    <tr>
                                        <td class="ps-3">
                                            <div class="fw-semibold small"><?= escapeOutput($fb['menu_name']) ?></div>
                                            <div class="text-muted" style="font-size:.78rem"><?= escapeOutput($fb['full_name']) ?></div>
                                        </td>
                                        <td>
                                            <?php for ($s = 1; $s <= 5; $s++): ?>
                                                <i class="bi bi-star<?= $s <= $fb['rating'] ? '-fill' : '' ?> text-warning"
                                                   style="font-size:.8rem"></i>
                                            <?php endfor; ?>
                                        </td>
                                        <td class="pe-3">
                                            <a href="<?= BASE_URL ?>/petugas/validate_feedback.php"
                                               class="btn btn-sm btn-primary px-3">
                                                Periksa
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

    <!-- ── Aktivitas Validasi Terakhir ── -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent border-bottom fw-semibold py-3">
            <i class="bi bi-clock-history text-secondary me-2"></i>Aktivitas Validasi Terakhir
        </div>
        <div class="card-body p-0">
            <?php if (empty($activityLogs)): ?>
                <p class="text-muted text-center py-4 mb-0">Belum ada riwayat validasi.</p>
            <?php else: ?>
                <?php foreach ($activityLogs as $i => $log):
                    $dotColor = match($log['new_status']) {
                        'approved' => 'bg-success',
                        'rejected' => 'bg-success', // desain pakai hijau untuk "diterima"
                        'revised'  => 'bg-warning',
                        default    => 'bg-secondary',
                    };
                    $statusLabel = match($log['new_status']) {
                        'approved' => 'DISETUJUI',
                        'rejected' => 'DITOLAK',
                        'revised'  => 'DIREVISI',
                        default    => strtoupper($log['new_status']),
                    };
                    $statusBadge = match($log['new_status']) {
                        'approved' => 'text-success',
                        'rejected' => 'text-success',
                        'revised'  => 'text-warning',
                        default    => 'text-secondary',
                    };
                    $isToday   = date('Y-m-d') === date('Y-m-d', strtotime($log['validated_at']));
                    $timeStr   = $isToday
                        ? 'Hari Ini, ' . date('H:i', strtotime($log['validated_at']))
                        : 'Kemarin, '  . date('H:i', strtotime($log['validated_at']));
                    $label = $log['activity_label'] ?? (
                        ($log['data_type'] === 'health_record' ? 'Data Kesehatan' : 'Feedback Menu')
                        . ' #' . ($log['data_id'] ?? '') . ' divalidasi'
                    );
                ?>
                    <div class="d-flex align-items-center justify-content-between px-4 py-3
                                <?= $i < count($activityLogs) - 1 ? 'border-bottom' : '' ?>">
                        <div class="d-flex align-items-center gap-3">
                            <span class="rounded-circle <?= $dotColor ?>"
                                  style="width:10px;height:10px;display:inline-block;flex-shrink:0"></span>
                            <div>
                                <div class="small fw-semibold"><?= escapeOutput($label) ?></div>
                                <div class="text-muted" style="font-size:.78rem">
                                    <i class="bi bi-clock me-1"></i><?= $timeStr ?>
                                    <span class="ms-2 fw-semibold <?= $statusBadge ?>"><?= $statusLabel ?></span>
                                </div>
                            </div>
                        </div>
                        <a href="<?= BASE_URL ?>/petugas/audit_log.php"
                           class="text-muted" title="Lihat detail">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                <?php endforeach; ?>
                <?php if (count($activityLogs) >= 10): ?>
                    <div class="text-center py-3 border-top">
                        <a href="<?= BASE_URL ?>/petugas/audit_log.php"
                           class="small text-primary text-decoration-none">
                            Muat Lebih Banyak
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

</div><!-- end #main-content -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
