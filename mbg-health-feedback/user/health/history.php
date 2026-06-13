<?php
/**
 * user/health/history.php
 * Riwayat & grafik perkembangan BMI untuk role User
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../helpers/validation_helper.php';
require_once __DIR__ . '/../../helpers/health_model.php';

requireRole([ROLE_USER]);

$pdo    = getDBConnection();
$userId = getCurrentUserId();

// ── Filter tanggal ──────────────────────────────────────────────────────────
$startDate = $_GET['start_date'] ?? '';
$endDate   = $_GET['end_date']   ?? '';

$startDate = ($startDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) ? $startDate : null;
$endDate   = ($endDate   !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate))   ? $endDate   : null;

// ── Query: Riwayat tabel (DESC) ───────────────────────────────────────────────
// Mengambil semua kolom health_records milik user login, diurutkan input_date DESC.
// Filter tanggal opsional via GET parameter start_date & end_date.
$sqlHistory = "
    SELECT health_id, input_date, height_cm, weight_kg,
           bmi_value, bmi_category, validation_status, notes
    FROM   health_records
    WHERE  user_id = :uid
";
$paramsHistory = [':uid' => $userId];

if ($startDate !== null) {
    $sqlHistory .= " AND input_date >= :start_date";
    $paramsHistory[':start_date'] = $startDate;
}
if ($endDate !== null) {
    $sqlHistory .= " AND input_date <= :end_date";
    $paramsHistory[':end_date'] = $endDate;
}

$sqlHistory .= " ORDER BY input_date DESC, created_at DESC";

$stmtHistory = $pdo->prepare($sqlHistory);
$stmtHistory->execute($paramsHistory);
$history = $stmtHistory->fetchAll();

// ── Query: Data grafik BMI (ASC — urutan kronologis untuk chart garis) ────────
// Hanya ambil input_date + bmi_value karena Chart.js hanya butuh dua kolom itu.
// Tidak dipengaruhi filter tanggal agar grafik selalu tampil lengkap.
$stmtChart = $pdo->prepare("
    SELECT input_date, bmi_value
    FROM   health_records
    WHERE  user_id = :uid
    ORDER  BY input_date ASC, created_at ASC
");
$stmtChart->execute([':uid' => $userId]);
$chartRaw = $stmtChart->fetchAll();

// Siapkan array untuk JSON (dikirim ke Chart.js via json_encode)
$chartLabels = [];
$chartValues = [];
foreach ($chartRaw as $row) {
    $chartLabels[] = $row['input_date'];
    $chartValues[] = (float) $row['bmi_value'];
}

// ── Peta warna badge ──────────────────────────────────────────────────────────
$bmiColors = [
    'Underweight' => 'info',
    'Normal'      => 'success',
    'Overweight'  => 'warning',
    'Obese'       => 'danger',
];

// pending=kuning, approved=hijau, revised=biru, rejected=merah
$statusColors = [
    'pending'  => 'warning',
    'approved' => 'success',
    'revised'  => 'primary',
    'rejected' => 'danger',
];

$statusLabels = [
    'pending'  => 'Pending',
    'approved' => 'Approved',
    'revised'  => 'Revised',
    'rejected' => 'Rejected',
];

$pageTitle = 'Riwayat & Grafik BMI';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_user.php';
?>

<div id="main-content">

    <!-- ── Judul halaman ── -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 fw-bold">
                <i class="bi bi-graph-up text-success me-2"></i>Riwayat &amp; Grafik BMI
            </h4>
            <small class="text-muted">Pantau perkembangan kesehatan Anda</small>
        </div>
        <a href="<?= BASE_URL ?>/user/health/input.php" class="btn btn-success btn-sm">
            <i class="bi bi-plus-circle me-1"></i>Input Data Baru
        </a>
    </div>

    <!-- ══════════════════════════════════════════════════════
         FITUR 2 — Grafik Garis Perkembangan BMI (Chart.js)
         ══════════════════════════════════════════════════════ -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom d-flex align-items-center gap-2">
            <i class="bi bi-activity text-success fs-5"></i>
            <span class="fw-semibold">Grafik Perkembangan BMI</span>
        </div>
        <div class="card-body">
            <?php if (empty($chartRaw)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-bar-chart-line fs-1 d-block mb-2 opacity-25"></i>
                    Belum ada data kesehatan.
                </div>
            <?php else: ?>
                <div style="position:relative; height:320px;">
                    <canvas id="bmiChart"></canvas>
                </div>
                <!-- Legenda kategori BMI -->
                <div class="d-flex flex-wrap gap-3 justify-content-center mt-3">
                    <span class="badge bg-danger px-3 py-2">Obese (&ge; 30)</span>
                    <span class="badge bg-warning text-dark px-3 py-2">Overweight (25–29.9)</span>
                    <span class="badge bg-success px-3 py-2">Normal (18.5–24.9)</span>
                    <span class="badge bg-info text-dark px-3 py-2">Underweight (&lt; 18.5)</span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════
         Filter tanggal (memengaruhi tabel riwayat, bukan grafik)
         ══════════════════════════════════════════════════════ -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2">
            <form method="GET"
                  action="<?= BASE_URL ?>/user/health/history.php"
                  class="row g-2 align-items-end">
                <div class="col-sm-4">
                    <label for="start_date" class="form-label small text-muted mb-1">Dari Tanggal</label>
                    <input type="date" class="form-control form-control-sm" id="start_date"
                           name="start_date" value="<?= escapeOutput($startDate ?? '') ?>">
                </div>
                <div class="col-sm-4">
                    <label for="end_date" class="form-label small text-muted mb-1">Sampai Tanggal</label>
                    <input type="date" class="form-control form-control-sm" id="end_date"
                           name="end_date" value="<?= escapeOutput($endDate ?? '') ?>">
                </div>
                <div class="col-sm-4 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                    <a href="<?= BASE_URL ?>/user/health/history.php"
                       class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x-circle me-1"></i>Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════
         FITUR 1 — Tabel Riwayat Data Kesehatan
         ══════════════════════════════════════════════════════ -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom d-flex align-items-center gap-2">
            <i class="bi bi-table text-primary fs-5"></i>
            <span class="fw-semibold">Riwayat Data Kesehatan</span>
            <?php if (!empty($history)): ?>
                <span class="badge bg-secondary ms-auto"><?= count($history) ?> data</span>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <?php if (empty($history)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>
                    Tidak ada data dalam rentang tanggal tersebut.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Tanggal Input</th>
                                <th class="text-center">Tinggi (cm)</th>
                                <th class="text-center">Berat (kg)</th>
                                <th class="text-center">BMI</th>
                                <th class="text-center">Kategori</th>
                                <th class="text-center">Status Validasi</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $row): ?>
                                <?php
                                    $bmiColor    = $bmiColors[$row['bmi_category']]       ?? 'secondary';
                                    $statusColor = $statusColors[$row['validation_status']] ?? 'secondary';
                                    $statusLabel = $statusLabels[$row['validation_status']] ?? ucfirst($row['validation_status']);
                                ?>
                                <tr>
                                    <td class="ps-3">
                                        <i class="bi bi-calendar3 text-muted me-1"></i>
                                        <?= escapeOutput($row['input_date']) ?>
                                    </td>
                                    <td class="text-center"><?= escapeOutput($row['height_cm']) ?></td>
                                    <td class="text-center"><?= escapeOutput($row['weight_kg']) ?></td>
                                    <td class="text-center fw-semibold"><?= escapeOutput($row['bmi_value']) ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= $bmiColor ?>">
                                            <?= escapeOutput($row['bmi_category']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= $statusColor ?>
                                            <?= $row['validation_status'] === 'pending' ? 'text-dark' : '' ?>">
                                            <?= escapeOutput($statusLabel) ?>
                                        </span>
                                    </td>
                                    <td class="text-muted small">
                                        <?= $row['notes'] !== null && $row['notes'] !== ''
                                            ? escapeOutput($row['notes'])
                                            : '<span class="text-muted fst-italic">—</span>' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div><!-- end #main-content -->

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<?php if (!empty($chartRaw)): ?>
<!--
    Script Chart.js ditulis langsung di halaman ini (sesuai aturan).
    footer.php sudah memuat Chart.js via CDN sebelum blok ini dieksekusi,
    sehingga objek `Chart` sudah tersedia di sini.
-->
<script>
(function () {
    'use strict';

    // Data dari PHP — di-encode JSON agar aman dari XSS
    const labels = <?= json_encode($chartLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const values = <?= json_encode($chartValues, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    // Tentukan warna titik berdasarkan nilai BMI
    function bmiPointColor(bmi) {
        if (bmi < 18.5) return '#0dcaf0'; // info  — Underweight
        if (bmi < 25)   return '#198754'; // green — Normal
        if (bmi < 30)   return '#ffc107'; // yellow— Overweight
        return '#dc3545';                 // red   — Obese
    }

    const pointColors = values.map(bmiPointColor);

    const ctx = document.getElementById('bmiChart').getContext('2d');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Nilai BMI',
                data: values,
                borderColor: '#198754',
                backgroundColor: 'rgba(25, 135, 84, 0.08)',
                borderWidth: 2.5,
                pointRadius: 6,
                pointHoverRadius: 8,
                pointBackgroundColor: pointColors,
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                tension: 0.35,
                fill: true,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: { font: { size: 13 }, usePointStyle: true }
                },
                tooltip: {
                    callbacks: {
                        // Tampilkan kategori BMI di tooltip
                        afterLabel: function (ctx) {
                            const v = ctx.parsed.y;
                            if (v < 18.5) return 'Kategori: Underweight';
                            if (v < 25)   return 'Kategori: Normal';
                            if (v < 30)   return 'Kategori: Overweight';
                            return 'Kategori: Obese';
                        }
                    }
                }
            },
            scales: {
                x: {
                    title: {
                        display: true,
                        text: 'Tanggal Input',
                        font: { size: 12, weight: 'bold' }
                    },
                    grid: { display: false }
                },
                y: {
                    title: {
                        display: true,
                        text: 'Nilai BMI',
                        font: { size: 12, weight: 'bold' }
                    },
                    suggestedMin: 10,
                    suggestedMax: 40,
                    // Garis referensi kategori BMI (annotation manual via afterDraw)
                    grid: { color: 'rgba(0,0,0,0.05)' }
                }
            }
        },
        plugins: [{
            // Plugin kustom: gambar garis referensi kategori BMI di background chart
            id: 'bmiReferenceLines',
            afterDraw(chart) {
                const { ctx, chartArea: { left, right }, scales: { y } } = chart;
                const lines = [
                    { value: 18.5, color: 'rgba(13,202,240,0.5)',  label: '18.5 (Underweight)' },
                    { value: 25,   color: 'rgba(255,193,7,0.5)',   label: '25.0 (Overweight)'  },
                    { value: 30,   color: 'rgba(220,53,69,0.5)',   label: '30.0 (Obese)'       },
                ];
                lines.forEach(line => {
                    const yPos = y.getPixelForValue(line.value);
                    ctx.save();
                    ctx.beginPath();
                    ctx.moveTo(left, yPos);
                    ctx.lineTo(right, yPos);
                    ctx.strokeStyle = line.color;
                    ctx.lineWidth   = 1.5;
                    ctx.setLineDash([6, 4]);
                    ctx.stroke();
                    ctx.setLineDash([]);
                    ctx.fillStyle = line.color.replace('0.5)', '0.9)');
                    ctx.font      = '11px sans-serif';
                    ctx.fillText(line.label, left + 6, yPos - 4);
                    ctx.restore();
                });
            }
        }]
    });
})();
</script>
<?php endif; ?>