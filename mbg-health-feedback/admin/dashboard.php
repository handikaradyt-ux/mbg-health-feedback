<?php
/**
 * admin/dashboard.php
 * Dashboard ringkasan untuk Admin — sesuai desain
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../helpers/validation_helper.php';
require_once __DIR__ . '/../helpers/report_model.php';

requireRole([ROLE_ADMIN]);

$pdo = getDBConnection();

// ── Statistik Kartu ──────────────────────────────────────────────────────────
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

// ── Data Grafik Bar: Data Kesehatan per Bulan (6 bulan terakhir) ─────────────
$healthMonthlyData = getAdminHealthMonthlyData($pdo, 6);

// ── Data Grafik Donut: Distribusi Kategori BMI ───────────────────────────────
$bmiDistribution = getAdminBmiDistribution($pdo);

// ── Encode JSON untuk Chart.js ───────────────────────────────────────────────
$chartLabels = json_encode(array_column($healthMonthlyData, 'label'));
$chartValues = json_encode(array_column($healthMonthlyData, 'total'));
$bmiLabels   = json_encode(array_column($bmiDistribution,  'label'));
$bmiValues   = json_encode(array_column($bmiDistribution,  'total'));
$bmiColors   = json_encode(['#6cb4ee', '#4caf50', '#ffc107', '#ef5350']);

$pageTitle = 'Dashboard Admin';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_admin.php';
?>

<style>
/* ── Stat Cards ─────────────────────────────────────────────── */
.stat-card { border-radius: .75rem !important; transition: box-shadow .2s; }
.stat-card:hover { box-shadow: 0 4px 18px rgba(0,0,0,.1) !important; }
.stat-card--warning { background-color: #fffbf0 !important; }

.stat-label {
    font-size: .65rem; font-weight: 700; letter-spacing: .06em;
    color: #8a96a3; text-transform: uppercase; margin-bottom: .1rem;
}
.stat-value { font-size: 2rem; font-weight: 700; line-height: 1.1; color: #1a2332; }
.stat-sub   { font-size: .72rem; color: #a0aab4; margin-top: .15rem; }

/* ── Stat Icons ─────────────────────────────────────────────── */
.stat-icon {
    width: 48px; height: 48px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; font-size: 1.4rem;
}
.stat-icon--blue   { background: #e8f0fe; color: #4a72b8; }
.stat-icon--teal   { background: #e3f6f5; color: #2a9d8f; }
.stat-icon--indigo { background: #ede7f6; color: #5c6bc0; }
.stat-icon--orange { background: #fff3e0; color: #f59e0b; }
</style>

<div id="main-content">

    <!-- Heading -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0 fw-bold text-dark">Dashboard Admin</h4>
        <span class="text-muted small"><?= date('l, d F Y') ?></span>
    </div>

    <!-- ── Stat Cards ── -->
    <div class="row g-3 mb-4">

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 stat-card">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="stat-icon stat-icon--blue">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div>
                        <div class="stat-label">Total Pengguna Aktif</div>
                        <div class="stat-value"><?= number_format($totalActiveUsers) ?></div>
                        <div class="stat-sub">Total akun: <?= number_format($totalUsers) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 stat-card">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="stat-icon stat-icon--teal">
                        <i class="bi bi-clipboard2-pulse-fill"></i>
                    </div>
                    <div>
                        <div class="stat-label">Total Data Kesehatan</div>
                        <div class="stat-value"><?= number_format($totalHealthRecords) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 stat-card">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="stat-icon stat-icon--indigo">
                        <i class="bi bi-star-fill"></i>
                    </div>
                    <div>
                        <div class="stat-label">Total Feedback</div>
                        <div class="stat-value"><?= number_format($totalFeedback) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 stat-card stat-card--warning">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="stat-icon stat-icon--orange">
                        <i class="bi bi-clock-fill"></i>
                    </div>
                    <div>
                        <div class="stat-label text-warning">Menunggu Validasi</div>
                        <div class="stat-value text-warning"><?= number_format($totalPending) ?></div>
                        <div class="stat-sub">
                            Kesehatan: <?= $pendingHealth ?> &bull; Feedback: <?= $pendingFeedback ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /stat cards -->

    <!-- ── Grafik ── -->
    <div class="row g-3">

        <!-- Bar Chart -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3">Data Kesehatan per Bulan</h6>
                    <div style="position:relative; height:280px;">
                        <canvas id="chartHealthMonthly"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Donut Chart -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex flex-column">
                    <h6 class="fw-semibold mb-3">Distribusi Kategori BMI</h6>
                    <div class="d-flex align-items-center justify-content-center flex-grow-1 gap-4 flex-wrap">
                        <div style="position:relative; width:200px; height:200px; flex-shrink:0;">
                            <canvas id="chartBmi"></canvas>
                            <div style="position:absolute; top:50%; left:50%;
                                        transform:translate(-50%,-50%);
                                        text-align:center; pointer-events:none;">
                                <div class="fw-semibold" style="font-size:.85rem; color:#6c757d;">Total</div>
                                <div class="fw-bold text-dark" style="font-size:1.25rem;">
                                    <?= number_format($totalHealthRecords) ?>
                                </div>
                            </div>
                        </div>
                        <div id="bmiLegend" class="d-flex flex-column gap-2"></div>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /grafik -->

</div><!-- end #main-content -->

<?php
// ── PENTING: footer.php dimuat DULU agar Chart.js tersedia ──────────────────
// Script chart ditaruh SETELAH footer supaya <script chart.umd.min.js> sudah
// ada di DOM sebelum kode ini berjalan.
require_once __DIR__ . '/../includes/footer.php';
?>

<!--
    Script chart diletakkan DI SINI — setelah footer.php — sehingga:
    1. Bootstrap bundle sudah dimuat.
    2. Chart.js (chart.umd.min.js di footer) sudah dimuat.
    3. Elemen <canvas> sudah ada di DOM.
-->
<script>
(function () {
    // Tunggu sampai seluruh DOM & script selesai
    window.addEventListener('load', function () {

        // ── Data dari PHP ────────────────────────────────────────────────────
        var barLabels  = <?= $chartLabels ?>;
        var barValues  = <?= $chartValues ?>;
        var bmiLabels  = <?= $bmiLabels ?>;
        var bmiValues  = <?= $bmiValues ?>;
        var bmiColors  = <?= $bmiColors ?>;

        // ── Bar Chart: Data Kesehatan per Bulan ─────────────────────────────
        var ctxBar = document.getElementById('chartHealthMonthly');
        if (ctxBar && typeof Chart !== 'undefined') {
            var gradientColors = barLabels.map(function (_, i) {
                var alpha = 0.30 + (i / Math.max(barLabels.length - 1, 1)) * 0.70;
                return 'rgba(24, 95, 165, ' + alpha + ')';
            });

            new Chart(ctxBar, {
                type: 'bar',
                data: {
                    labels: barLabels,
                    datasets: [{
                        label: 'Data Kesehatan',
                        data: barValues,
                        backgroundColor: gradientColors,
                        borderRadius: 5,
                        borderSkipped: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) { return ' ' + ctx.parsed.y + ' data'; }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { font: { size: 11 }, color: '#7a8494', autoSkip: false }
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(0,0,0,.06)' },
                            ticks: { font: { size: 11 }, color: '#7a8494', precision: 0 }
                        }
                    }
                }
            });
        }

        // ── Donut Chart: Distribusi Kategori BMI ────────────────────────────
        var ctxDonut = document.getElementById('chartBmi');
        if (ctxDonut && typeof Chart !== 'undefined') {
            new Chart(ctxDonut, {
                type: 'doughnut',
                data: {
                    labels: bmiLabels,
                    datasets: [{
                        data: bmiValues,
                        backgroundColor: bmiColors,
                        borderWidth: 2,
                        borderColor: '#ffffff',
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '68%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    return ' ' + ctx.label + ': ' + ctx.parsed + ' data';
                                }
                            }
                        }
                    }
                }
            });
        }

        // ── Custom Legend BMI ────────────────────────────────────────────────
        var legendEl = document.getElementById('bmiLegend');
        if (legendEl) {
            var totalBmi = bmiValues.reduce(function (a, b) { return a + b; }, 0) || 1;
            bmiLabels.forEach(function (lbl, i) {
                var pct  = Math.round(bmiValues[i] / totalBmi * 100);
                var item = document.createElement('div');
                item.className = 'd-flex align-items-center gap-2';
                item.innerHTML =
                    '<span style="width:12px;height:12px;border-radius:50%;' +
                    'background:' + bmiColors[i] + ';flex-shrink:0;display:inline-block;"></span>' +
                    '<span style="font-size:.82rem;color:#4a5568;">' + lbl + ' (' + pct + '%)</span>';
                legendEl.appendChild(item);
            });
        }

    }); // end window.load
})();
</script>
