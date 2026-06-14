<?php
/**
 * admin/reports/health.php
 * Laporan Kesehatan Program MBG — Admin
 *
 * Menampilkan:
 *  - Filter periode, kategori BMI, urutan
 *  - Kartu ringkasan (total, rata-rata BMI, % Normal, % Perlu Perhatian)
 *  - Grafik tren rata-rata BMI per bulan (Chart.js yang sudah ada)
 *  - Tabel data health_records (approved) dengan pagination
 *  - Tombol Export PDF & Excel
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/auth/session.php';
require_once dirname(__DIR__, 2) . '/helpers/validation_helper.php';
require_once dirname(__DIR__, 2) . '/helpers/report_model.php';
require_once dirname(__DIR__, 2) . '/config/database.php';

// Hanya admin yang boleh akses
requireRole([ROLE_ADMIN]);

$pdo = getDbConnection();

// ────────────────────────────────────────────────────────────────────────────
// 1. Ambil & validasi parameter filter
// ────────────────────────────────────────────────────────────────────────────
$periode     = $_GET['periode']      ?? 'bulanan';
$dateStart   = $_GET['date_start']   ?? '';
$dateEnd     = $_GET['date_end']     ?? '';
$bmiCat      = $_GET['bmi_cat']      ?? 'semua';
$sortBy      = $_GET['sort_by']      ?? 'terbaru';
$page        = max(1, (int)($_GET['page'] ?? 1));
$perPage     = 10;

// Tentukan rentang tanggal berdasarkan pilihan periode
$today = date('Y-m-d');
switch ($periode) {
    case 'mingguan':
        $dateStart = date('Y-m-d', strtotime('-6 days'));
        $dateEnd   = $today;
        break;
    case 'bulanan':
        $dateStart = date('Y-m-01');
        $dateEnd   = date('Y-m-t');
        break;
    case 'semesteran':
        $dateStart = date('Y-m-d', strtotime('-6 months'));
        $dateEnd   = $today;
        break;
    case 'kustom':
        // Gunakan input user; fallback ke bulan ini jika kosong
        if (empty($dateStart)) $dateStart = date('Y-m-01');
        if (empty($dateEnd))   $dateEnd   = date('Y-m-t');
        break;
    default:
        $dateStart = date('Y-m-01');
        $dateEnd   = date('Y-m-t');
}

// Sanitasi
$dateStart = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStart) ? $dateStart : date('Y-m-01');
$dateEnd   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateEnd)   ? $dateEnd   : date('Y-m-t');
if ($dateStart > $dateEnd) [$dateStart, $dateEnd] = [$dateEnd, $dateStart];

$allowedCat  = ['semua', 'Underweight', 'Normal', 'Overweight', 'Obese'];
$allowedSort = ['terbaru', 'terlama', 'bmi_tinggi', 'bmi_rendah'];
if (!in_array($bmiCat, $allowedCat, true))   $bmiCat  = 'semua';
if (!in_array($sortBy, $allowedSort, true))  $sortBy  = 'terbaru';

// ────────────────────────────────────────────────────────────────────────────
// 2. Ambil data dari model
// ────────────────────────────────────────────────────────────────────────────
$summary   = getHealthSummary($pdo, $dateStart, $dateEnd, $bmiCat);
$chartData = getHealthBmiTrendByMonth($pdo, $dateStart, $dateEnd, $bmiCat);
$tableData = getHealthTableData($pdo, $dateStart, $dateEnd, $bmiCat, $sortBy, $page, $perPage);
$totalRows = getHealthTableCount($pdo, $dateStart, $dateEnd, $bmiCat);
$totalPages = max(1, (int)ceil($totalRows / $perPage));

// ────────────────────────────────────────────────────────────────────────────
// 3. Siapkan data grafik untuk JS
// ────────────────────────────────────────────────────────────────────────────
$chartLabels = array_column($chartData, 'label');
$chartValues = array_map(fn($r) => round((float)$r['avg_bmi'], 1), $chartData);

// Helper: buat URL pagination mempertahankan semua filter
function paginationUrl(int $p): string {
    $params = $_GET;
    $params['page'] = $p;
    return '?' . http_build_query($params);
}

$pageTitle = 'Laporan Kesehatan';
require_once dirname(__DIR__, 2) . '/includes/header.php';
require_once dirname(__DIR__, 2) . '/includes/sidebar_admin.php';
?>

<!-- ── Main Content ── -->
<div id="main-content">

    <!-- Judul halaman -->
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h4 class="fw-bold mb-0">Laporan Kesehatan Program MBG</h4>
            <p class="text-muted small mb-0">Rekapitulasi data kesehatan seluruh peserta</p>
        </div>
        <div class="d-flex gap-2">
            <a href="export_pdf.php?<?= http_build_query(array_merge($_GET, ['type'=>'health'])) ?>"
               class="btn btn-danger btn-sm" target="_blank">
                <i class="bi bi-file-pdf me-1"></i>Ekspor PDF
            </a>
            <a href="export_excel.php?<?= http_build_query(array_merge($_GET, ['type'=>'health'])) ?>"
               class="btn btn-success btn-sm">
                <i class="bi bi-file-earmark-excel me-1"></i>Ekspor Excel
            </a>
        </div>
    </div>

    <div class="row g-3">

        <!-- ── Kolom Kiri: Parameter Filter ── -->
        <div class="col-md-4 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3">
                        <i class="bi bi-gear me-1 text-secondary"></i>Parameter Laporan
                    </h6>

                    <form method="get" id="filterForm">

                        <!-- Periode -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-uppercase text-muted mb-2">Periode</label>
                            <?php
                            $periodes = [
                                'mingguan'  => 'Mingguan',
                                'bulanan'   => 'Bulanan',
                                'semesteran'=> 'Semesteran',
                                'kustom'    => 'Rentang Kustom',
                            ];
                            foreach ($periodes as $val => $label):
                            ?>
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="radio"
                                       name="periode" id="p_<?= $val ?>"
                                       value="<?= $val ?>"
                                       <?= $periode === $val ? 'checked' : '' ?>
                                       onchange="toggleKustom()">
                                <label class="form-check-label" for="p_<?= $val ?>"><?= $label ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Rentang Kustom -->
                        <div id="kustomRange" class="mb-3" style="display:<?= $periode === 'kustom' ? 'block' : 'none' ?>">
                            <label class="form-label small fw-semibold">Pilih Tanggal</label>
                            <div class="d-flex align-items-center gap-1">
                                <input type="date" name="date_start" class="form-control form-control-sm"
                                       value="<?= escapeOutput($dateStart) ?>">
                                <span class="text-muted">-</span>
                                <input type="date" name="date_end" class="form-control form-control-sm"
                                       value="<?= escapeOutput($dateEnd) ?>">
                            </div>
                        </div>

                        <!-- Filter Kategori BMI -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold" for="bmi_cat">Filter Kategori BMI</label>
                            <select name="bmi_cat" id="bmi_cat" class="form-select form-select-sm">
                                <option value="semua"       <?= $bmiCat==='semua'       ?'selected':'' ?>>Semua</option>
                                <option value="Underweight" <?= $bmiCat==='Underweight' ?'selected':'' ?>>Underweight</option>
                                <option value="Normal"      <?= $bmiCat==='Normal'      ?'selected':'' ?>>Normal</option>
                                <option value="Overweight"  <?= $bmiCat==='Overweight'  ?'selected':'' ?>>Overweight</option>
                                <option value="Obese"       <?= $bmiCat==='Obese'       ?'selected':'' ?>>Obese</option>
                            </select>
                        </div>

                        <!-- Urutan -->
                        <div class="mb-4">
                            <label class="form-label small fw-semibold" for="sort_by">Urutkan berdasarkan</label>
                            <select name="sort_by" id="sort_by" class="form-select form-select-sm">
                                <option value="terbaru"   <?= $sortBy==='terbaru'   ?'selected':'' ?>>Tanggal Terbaru</option>
                                <option value="terlama"   <?= $sortBy==='terlama'   ?'selected':'' ?>>Tanggal Terlama</option>
                                <option value="bmi_tinggi"<?= $sortBy==='bmi_tinggi'?'selected':'' ?>>BMI Tertinggi</option>
                                <option value="bmi_rendah"<?= $sortBy==='bmi_rendah'?'selected':'' ?>>BMI Terendah</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-bar-chart-line me-1"></i>Generate Laporan
                        </button>

                    </form>
                </div>
            </div>
        </div><!-- /kolom kiri -->

        <!-- ── Kolom Kanan: Ringkasan + Grafik + Tabel ── -->
        <div class="col-md-8 col-lg-9">

            <!-- Kartu Ringkasan -->
            <div class="row g-3 mb-3">

                <!-- Total Data -->
                <div class="col-6 col-xl-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-body py-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="text-muted small mb-1 text-uppercase fw-semibold" style="font-size:.7rem">Total Data</p>
                                    <h3 class="fw-bold text-primary mb-0"><?= number_format((int)$summary['total']) ?></h3>
                                </div>
                                <span class="text-primary fs-4"><i class="bi bi-people"></i></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Rata-rata BMI -->
                <div class="col-6 col-xl-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-body py-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="text-muted small mb-1 text-uppercase fw-semibold" style="font-size:.7rem">Rata-rata BMI</p>
                                    <h3 class="fw-bold mb-0"><?= number_format((float)$summary['avg_bmi'], 1) ?></h3>
                                </div>
                                <span class="text-info fs-4"><i class="bi bi-clipboard2-pulse"></i></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- % Normal -->
                <div class="col-6 col-xl-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-body py-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="text-muted small mb-1 text-uppercase fw-semibold" style="font-size:.7rem">% Kategori Normal</p>
                                    <h3 class="fw-bold text-success mb-0"><?= number_format((float)$summary['pct_normal'], 0) ?>%</h3>
                                </div>
                                <span class="text-success fs-4"><i class="bi bi-check-circle"></i></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- % Perlu Perhatian -->
                <div class="col-6 col-xl-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-body py-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="text-muted small mb-1 text-uppercase fw-semibold" style="font-size:.7rem">Perlu Perhatian (Obese+UW)</p>
                                    <h3 class="fw-bold text-warning mb-0"><?= number_format((float)$summary['pct_attention'], 0) ?>%</h3>
                                </div>
                                <span class="text-warning fs-4"><i class="bi bi-exclamation-triangle"></i></span>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- /kartu ringkasan -->

            <!-- Grafik Tren BMI -->
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3">Tren Rata-rata BMI per Bulan</h6>
                    <?php if (empty($chartData)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-bar-chart fs-2 d-block mb-2"></i>
                            Tidak ada data pada periode ini.
                        </div>
                    <?php else: ?>
                    <div style="position:relative;height:260px;">
                        <canvas id="bmiTrendChart"></canvas>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tabel Data -->
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="px-3 pt-3 pb-2 d-flex justify-content-between align-items-center">
                        <h6 class="fw-semibold mb-0">Data Kesehatan Peserta</h6>
                        <span class="text-muted small">
                            Menampilkan <?= (($page-1)*$perPage)+1 ?>–<?= min($page*$perPage, $totalRows) ?>
                            dari <?= number_format($totalRows) ?> data
                        </span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">No</th>
                                    <th>Nama Pengguna</th>
                                    <th>Tanggal Input</th>
                                    <th class="text-end">TB (cm)</th>
                                    <th class="text-end">BB (kg)</th>
                                    <th class="text-end">BMI</th>
                                    <th>Kategori</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($tableData)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        Tidak ada data yang sesuai filter.
                                    </td>
                                </tr>
                            <?php else: ?>
                            <?php
                            $no = ($page - 1) * $perPage + 1;
                            $catBadge = [
                                'Underweight' => 'bg-info text-dark',
                                'Normal'      => 'bg-success',
                                'Overweight'  => 'bg-warning text-dark',
                                'Obese'       => 'bg-danger',
                            ];
                            foreach ($tableData as $row):
                                $badge = $catBadge[$row['bmi_category']] ?? 'bg-secondary';
                            ?>
                                <tr>
                                    <td class="ps-3 text-muted"><?= $no++ ?></td>
                                    <td><?= escapeOutput($row['full_name']) ?></td>
                                    <td><?= date('d M Y', strtotime($row['input_date'])) ?></td>
                                    <td class="text-end"><?= number_format((float)$row['height_cm'], 1) ?></td>
                                    <td class="text-end"><?= number_format((float)$row['weight_kg'], 1) ?></td>
                                    <td class="text-end fw-semibold"><?= number_format((float)$row['bmi_value'], 2) ?></td>
                                    <td>
                                        <span class="badge <?= $badge ?>"><?= escapeOutput($row['bmi_category']) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div class="px-3 py-2 border-top d-flex justify-content-end">
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= paginationUrl($page - 1) ?>">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                </li>
                                <?php
                                $start = max(1, $page - 2);
                                $end   = min($totalPages, $page + 2);
                                if ($start > 1): ?>
                                    <li class="page-item"><a class="page-link" href="<?= paginationUrl(1) ?>">1</a></li>
                                    <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                                <?php endif;
                                for ($i = $start; $i <= $end; $i++): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="<?= paginationUrl($i) ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor;
                                if ($end < $totalPages): ?>
                                    <?php if ($end < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                                    <li class="page-item"><a class="page-link" href="<?= paginationUrl($totalPages) ?>"><?= $totalPages ?></a></li>
                                <?php endif; ?>
                                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= paginationUrl($page + 1) ?>">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>

                </div>
            </div><!-- /tabel -->

        </div><!-- /kolom kanan -->
    </div><!-- /row -->
</div><!-- /main-content -->

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>

<!-- ── Inisialisasi Grafik (menggunakan chart instance baru, bukan initBmiChart) ── -->
<?php if (!empty($chartData)): ?>
<script>
(function () {
    // Data dari PHP
    var labels = <?= json_encode($chartLabels) ?>;
    var values = <?= json_encode($chartValues) ?>;

    // Garis referensi batas normal BMI
    var normalHigh = labels.map(function() { return 24.9; });
    var normalLow  = labels.map(function() { return 18.5; });

    var ctx = document.getElementById('bmiTrendChart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Rata-rata BMI',
                    data: values,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37,99,235,0.08)',
                    tension: 0.35,
                    fill: true,
                    pointRadius: 5,
                    pointBackgroundColor: '#2563eb',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointHoverRadius: 7,
                    order: 1
                },
                {
                    label: 'Batas Normal (18.5 – 24.9)',
                    data: normalHigh,
                    borderColor: '#22c55e',
                    backgroundColor: 'transparent',
                    borderDash: [6, 4],
                    borderWidth: 1.5,
                    pointRadius: 0,
                    pointHoverRadius: 0,
                    fill: false,
                    tension: 0,
                    order: 2
                },
                {
                    label: '',
                    data: normalLow,
                    borderColor: '#22c55e',
                    backgroundColor: 'transparent',
                    borderDash: [6, 4],
                    borderWidth: 1.5,
                    pointRadius: 0,
                    pointHoverRadius: 0,
                    fill: false,
                    tension: 0,
                    order: 3
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 20,
                        font: { size: 12 },
                        filter: function(item) { return item.text !== ''; }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            if (context.datasetIndex === 0) {
                                return ' Rata-rata BMI: ' + context.parsed.y.toFixed(1);
                            }
                            return context.dataset.label ? ' ' + context.dataset.label : null;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    min: 14,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    ticks: { font: { size: 11 }, color: '#6b7280' }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 11 }, color: '#6b7280' }
                }
            }
        }
    });
})();
</script>
<?php endif; ?>

<script>
function toggleKustom() {
    var val = document.querySelector('input[name="periode"]:checked');
    var box = document.getElementById('kustomRange');
    if (box) box.style.display = (val && val.value === 'kustom') ? 'block' : 'none';
}
</script>