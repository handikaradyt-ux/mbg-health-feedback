<?php
/**
 * admin/reports/feedback.php
 * Laporan Kepuasan Menu MBG
 * Akses: admin (penuh) | petugas (read-only, tanpa tombol ekspor)
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../helpers/feedback_model.php';
require_once __DIR__ . '/../../helpers/report_model.php';

requireRole([ROLE_ADMIN, ROLE_PETUGAS]);

// Tentukan apakah user adalah admin (untuk kontrol fitur ekspor & sidebar)
$isAdmin = getCurrentRole() === ROLE_ADMIN;

$pdo = getDBConnection();

// ── Parameter Filter ────────────────────────────────────────────────────────
$periode    = $_GET['periode']    ?? date('Y-m');          // format: 2025-06
$menuId     = isset($_GET['menu_id']) && $_GET['menu_id'] !== '' ? (int) $_GET['menu_id'] : null;
$minRating  = isset($_GET['min_rating']) && $_GET['min_rating'] !== '' ? (int) $_GET['min_rating'] : null;
$page       = max(1, (int) ($_GET['page'] ?? 1));
$perPage    = 10;

// Periode → tanggal awal & akhir bulan
$dateStart = $periode . '-01';
$dateEnd   = date('Y-m-t', strtotime($dateStart));

// Bulan sebelumnya (untuk grafik perbandingan)
$prevMonth      = date('Y-m', strtotime('-1 month', strtotime($dateStart)));
$prevDateStart  = $prevMonth . '-01';
$prevDateEnd    = date('Y-m-t', strtotime($prevDateStart));

// ── Daftar menu untuk dropdown filter ───────────────────────────────────────
$menuList = getFeedbackMenuList($pdo);

// ── Kartu Statistik ─────────────────────────────────────────────────────────
$summary     = getFeedbackSummary($pdo, $dateStart, $dateEnd, $menuId, $minRating);
$bestMenu    = getFeedbackBestWorstMenu($pdo, $dateStart, $dateEnd, 'best');
$worstMenu   = getFeedbackBestWorstMenu($pdo, $dateStart, $dateEnd, 'worst');

// ── Data Grafik Perbandingan Rating ─────────────────────────────────────────
$chartCurrent  = getFeedbackRatingByMenu($pdo, $dateStart,  $dateEnd);
$chartPrev     = getFeedbackRatingByMenu($pdo, $prevDateStart, $prevDateEnd);

// Gabung label dari kedua bulan
$allMenuNames = [];
foreach ($chartCurrent as $r) { $allMenuNames[$r['menu_id']] = $r['menu_name']; }
foreach ($chartPrev    as $r) { $allMenuNames[$r['menu_id']] = $r['menu_name']; }

$chartCurrentMap = [];
foreach ($chartCurrent as $r) { $chartCurrentMap[$r['menu_id']] = (float) $r['avg_rating']; }
$chartPrevMap    = [];
foreach ($chartPrev    as $r) { $chartPrevMap[$r['menu_id']]    = (float) $r['avg_rating']; }

$chartLabels       = [];
$chartDataCurrent  = [];
$chartDataPrev     = [];
foreach ($allMenuNames as $mid => $mname) {
    // Singkat nama agar muat di X-axis
    $chartLabels[]      = mb_strlen($mname) > 12 ? mb_substr($mname, 0, 10) . '…' : $mname;
    $chartDataCurrent[] = $chartCurrentMap[$mid] ?? 0;
    $chartDataPrev[]    = $chartPrevMap[$mid]    ?? 0;
}

// Label bulan Indonesia
$bulanId = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
function labelBulan(string $ym, array $bulanId): string {
    [$y, $m] = explode('-', $ym);
    return $bulanId[(int)$m - 1] . ' ' . $y;
}
$labelCurrent = labelBulan($periode,   $bulanId);
$labelPrev    = labelBulan($prevMonth, $bulanId);

// ── Distribusi Rating per Menu ───────────────────────────────────────────────
$ratingDist = getFeedbackRatingDistribution($pdo, $dateStart, $dateEnd, $menuId, $minRating);

// ── Tabel Peringkat ─────────────────────────────────────────────────────────
$rankingTotal = getFeedbackRankingCount($pdo, $dateStart, $dateEnd, $menuId, $minRating);
$rankingData  = getFeedbackRanking($pdo, $dateStart, $dateEnd, $menuId, $minRating, $page, $perPage);
$totalPages   = (int) ceil($rankingTotal / $perPage);

// ── Build URL untuk pagination & filter ─────────────────────────────────────
function buildUrl(array $overrides = []): string {
    $params = array_merge([
        'periode'    => $_GET['periode']    ?? '',
        'menu_id'    => $_GET['menu_id']    ?? '',
        'min_rating' => $_GET['min_rating'] ?? '',
        'page'       => $_GET['page']       ?? 1,
    ], $overrides);
    return '?' . http_build_query(array_filter($params, fn($v) => $v !== ''));
}

$pageTitle = 'Laporan Kepuasan Menu';
require_once __DIR__ . '/../../includes/header.php';
?>
<?php if ($isAdmin): ?>
    <?php require_once __DIR__ . '/../../includes/sidebar_admin.php'; ?>
<?php else: ?>
    <?php require_once __DIR__ . '/../../includes/sidebar_petugas.php'; ?>
<?php endif; ?>

<div id="main-content">

    <!-- Judul Halaman -->
    <div class="d-flex align-items-center justify-content-between mb-1">
        <div>
            <h4 class="fw-bold mb-0">Laporan Kepuasan Menu MBG</h4>
            <p class="text-muted small mb-0">Analisis agregat feedback dan rating dari peserta tervalidasi</p>
        </div>
        <?php if ($isAdmin): ?>
        <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>/admin/reports/export_pdf.php?type=feedback&periode=<?= urlencode($periode) ?>&menu_id=<?= $menuId ?? '' ?>&min_rating=<?= $minRating ?? '' ?>"
               class="btn btn-sm btn-outline-danger" target="_blank">
                <i class="bi bi-file-earmark-pdf me-1"></i>Ekspor PDF
            </a>
            <a href="<?= BASE_URL ?>/admin/reports/export_excel.php?type=feedback&periode=<?= urlencode($periode) ?>&menu_id=<?= $menuId ?? '' ?>&min_rating=<?= $minRating ?? '' ?>"
               class="btn btn-sm btn-outline-success" target="_blank">
                <i class="bi bi-file-earmark-excel me-1"></i>Ekspor Excel
            </a>
        </div>
        <?php else: ?>
        <span class="badge bg-secondary">
            <i class="bi bi-eye me-1"></i>Mode Hanya Lihat
        </span>
        <?php endif; ?>
    </div>

    <!-- ── FILTER ── -->
    <div class="card border-0 shadow-sm mb-4 mt-3">
        <div class="card-body py-3">
            <form method="GET" action="">
                <div class="row g-3 align-items-end">
                    <!-- Pilih Periode -->
                    <div class="col-12 col-sm-6 col-md-3">
                        <label class="form-label small text-muted mb-1">Pilih Periode</label>
                        <input type="month" name="periode" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($periode) ?>">
                    </div>
                    <!-- Pilih Menu -->
                    <div class="col-12 col-sm-6 col-md-3">
                        <label class="form-label small text-muted mb-1">Pilih Menu</label>
                        <select name="menu_id" class="form-select form-select-sm">
                            <option value="">Semua Menu</option>
                            <?php foreach ($menuList as $m): ?>
                                <option value="<?= $m['menu_id'] ?>"
                                    <?= $menuId === (int)$m['menu_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($m['menu_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Min Rating -->
                    <div class="col-12 col-sm-6 col-md-3">
                        <label class="form-label small text-muted mb-1">Min. Rating</label>
                        <select name="min_rating" class="form-select form-select-sm">
                            <option value="">Semua</option>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?= $i ?>" <?= $minRating === $i ? 'selected' : '' ?>>
                                    <?= $i ?> Bintang
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <!-- Tombol -->
                    <div class="col-12 col-sm-6 col-md-3">
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-bar-chart-line me-1"></i>Generate Laporan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ── KARTU STATISTIK ── -->
    <div class="row g-3 mb-4">

        <!-- Total Feedback Tervalidasi -->
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center"
                         style="width:48px;height:48px;flex-shrink:0">
                        <i class="bi bi-patch-check-fill text-primary fs-5"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold" style="font-size:.7rem;letter-spacing:.05em">
                            Total Feedback Tervalidasi
                        </div>
                        <div class="fw-bold fs-4 lh-1 mt-1">
                            <?= number_format($summary['total_approved']) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rata-rata Rating Global -->
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-warning bg-opacity-10 d-flex align-items-center justify-content-center"
                         style="width:48px;height:48px;flex-shrink:0">
                        <i class="bi bi-star-fill text-warning fs-5"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold" style="font-size:.7rem;letter-spacing:.05em">
                            Rata-rata Rating Global
                        </div>
                        <div class="fw-bold fs-4 lh-1 mt-1">
                            <?= number_format((float)$summary['avg_rating'], 1) ?>
                            <span class="text-muted fw-normal" style="font-size:.9rem">/ 5.0</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Menu Terbaik -->
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center"
                         style="width:48px;height:48px;flex-shrink:0">
                        <i class="bi bi-hand-thumbs-up-fill text-success fs-5"></i>
                    </div>
                    <div style="min-width:0">
                        <div class="text-muted small text-uppercase fw-semibold" style="font-size:.7rem;letter-spacing:.05em">
                            Menu Terbaik
                        </div>
                        <?php if ($bestMenu): ?>
                            <div class="fw-bold text-truncate lh-1 mt-1" style="font-size:1rem"
                                 title="<?= htmlspecialchars($bestMenu['menu_name']) ?>">
                                <?= htmlspecialchars($bestMenu['menu_name']) ?>
                            </div>
                            <div class="small text-warning mt-1">
                                <i class="bi bi-star-fill"></i>
                                <?= number_format((float)$bestMenu['avg_rating'], 1) ?>
                            </div>
                        <?php else: ?>
                            <div class="text-muted small mt-1">Belum ada data</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Menu Terendah -->
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100 border-danger border-opacity-25">
                <div class="card-body d-flex align-items-center gap-3" style="background:rgba(220,53,69,.04);border-radius:.375rem">
                    <div class="rounded-circle bg-danger bg-opacity-10 d-flex align-items-center justify-content-center"
                         style="width:48px;height:48px;flex-shrink:0">
                        <i class="bi bi-exclamation-triangle-fill text-danger fs-5"></i>
                    </div>
                    <div style="min-width:0">
                        <div class="text-danger small text-uppercase fw-semibold" style="font-size:.7rem;letter-spacing:.05em">
                            Menu Terendah
                        </div>
                        <?php if ($worstMenu): ?>
                            <div class="fw-bold text-truncate lh-1 mt-1" style="font-size:1rem"
                                 title="<?= htmlspecialchars($worstMenu['menu_name']) ?>">
                                <?= htmlspecialchars($worstMenu['menu_name']) ?>
                            </div>
                            <div class="small text-warning mt-1">
                                <i class="bi bi-star-fill"></i>
                                <?= number_format((float)$worstMenu['avg_rating'], 1) ?>
                            </div>
                        <?php else: ?>
                            <div class="text-muted small mt-1">Belum ada data</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- ── GRAFIK + DISTRIBUSI ── -->
    <div class="row g-3 mb-4">

        <!-- Grafik Perbandingan Rating -->
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3">
                        Perbandingan Rating
                        <span class="text-muted fw-normal">(<?= $labelPrev ?> vs <?= $labelCurrent ?>)</span>
                    </h6>
                    <?php if (empty($allMenuNames)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-bar-chart fs-1 d-block mb-2"></i>Tidak ada data untuk periode ini
                        </div>
                    <?php else: ?>
                        <div style="height:280px;position:relative">
                            <canvas id="feedbackCompareChart"></canvas>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Distribusi Rating per Menu -->
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3">Distribusi Rating per Menu</h6>
                    <?php if (empty($ratingDist)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-pie-chart fs-1 d-block mb-2"></i>Tidak ada data untuk periode ini
                        </div>
                    <?php else: ?>
                        <?php foreach ($ratingDist as $item): ?>
                            <?php
                                $total = array_sum($item['dist']);
                                $pct   = [];
                                for ($s = 1; $s <= 5; $s++) {
                                    $pct[$s] = $total > 0 ? round($item['dist'][$s] / $total * 100, 1) : 0;
                                }
                                // Warna per bintang: 1=merah, 2=oranye, 3=kuning, 4=hijau muda, 5=hijau
                                $starColors = [1=>'#ef4444', 2=>'#f97316', 3=>'#eab308', 4=>'#84cc16', 5=>'#22c55e'];
                            ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="small fw-semibold"><?= htmlspecialchars($item['menu_name']) ?></span>
                                    <span class="text-muted small"><?= number_format($total) ?> reviews</span>
                                </div>
                                <div class="d-flex" style="height:10px;border-radius:6px;overflow:hidden">
                                    <?php for ($s = 1; $s <= 5; $s++): ?>
                                        <?php if ($pct[$s] > 0): ?>
                                            <div style="width:<?= $pct[$s] ?>%;background:<?= $starColors[$s] ?>"
                                                 title="<?= $s ?>★: <?= $pct[$s] ?>%"></div>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <!-- Legenda -->
                        <div class="d-flex gap-3 mt-2 flex-wrap">
                            <?php foreach ($starColors as $s => $c): ?>
                                <span class="small d-flex align-items-center gap-1">
                                    <span style="display:inline-block;width:10px;height:10px;background:<?= $c ?>;border-radius:2px"></span>
                                    <?= $s ?>★
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

    <!-- ── TABEL PERINGKAT ── -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="fw-semibold mb-0">Peringkat Kepuasan Menu</h6>
                <span class="text-muted small"><?= number_format($rankingTotal) ?> menu ditemukan</span>
            </div>

            <?php if (empty($rankingData)): ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-table fs-1 d-block mb-2"></i>Tidak ada data untuk filter ini
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width:80px">Peringkat</th>
                                <th>Nama Menu</th>
                                <th class="text-center">Total Feedback</th>
                                <th class="text-center">Rata-rata Rating</th>
                                <th style="width:160px">Distribusi Rating</th>
                                <th class="text-center" style="width:110px">Tren Bulan Ini</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $globalRank = ($page - 1) * $perPage + 1;
                            foreach ($rankingData as $row):
                                $rankDisplay = $globalRank;
                                // Tren: bandingkan avg_rating bulan ini vs bulan lalu
                                $diff = (float)$row['avg_rating'] - (float)($row['prev_avg_rating'] ?? $row['avg_rating']);
                                if (abs($diff) < 0.05) {
                                    $trendIcon  = '<i class="bi bi-dash text-secondary fs-5"></i>';
                                    $trendClass = 'text-secondary';
                                } elseif ($diff > 0) {
                                    $trendIcon  = '<i class="bi bi-arrow-up-circle-fill text-success fs-5"></i>';
                                    $trendClass = 'text-success';
                                } else {
                                    $trendIcon  = '<i class="bi bi-arrow-down-circle-fill text-danger fs-5"></i>';
                                    $trendClass = 'text-danger';
                                }

                                // Ikon peringkat 1-3
                                if ($rankDisplay === 1) {
                                    $rankHtml = '<i class="bi bi-trophy-fill text-warning fs-5"></i>';
                                } elseif ($rankDisplay === 2) {
                                    $rankHtml = '<i class="bi bi-trophy-fill text-secondary fs-5"></i>';
                                } elseif ($rankDisplay === 3) {
                                    $rankHtml = '<i class="bi bi-trophy-fill text-danger" style="color:#cd7f32!important" ></i>';
                                } else {
                                    $rankHtml = '<span class="fw-bold text-muted">' . $rankDisplay . '</span>';
                                }

                                // Mini progress bar distribusi (5 segmen)
                                $distTotal = 0;
                                $starColors = [1=>'#ef4444', 2=>'#f97316', 3=>'#eab308', 4=>'#84cc16', 5=>'#22c55e'];
                                for ($s = 1; $s <= 5; $s++) {
                                    $distTotal += (int)($row['star_' . $s] ?? 0);
                                }
                            ?>
                            <tr class="<?= (float)$row['avg_rating'] < 3.0 ? 'table-danger bg-opacity-25' : '' ?>">
                                <td class="text-center"><?= $rankHtml ?></td>
                                <td>
                                    <span class="fw-semibold <?= (float)$row['avg_rating'] < 3.0 ? 'text-danger' : '' ?>">
                                        <?= htmlspecialchars($row['menu_name']) ?>
                                    </span>
                                </td>
                                <td class="text-center"><?= number_format((int)$row['total_feedback']) ?></td>
                                <td class="text-center">
                                    <span class="fw-bold <?= (float)$row['avg_rating'] >= 4 ? 'text-success' : ((float)$row['avg_rating'] >= 3 ? 'text-warning' : 'text-danger') ?>">
                                        <?= number_format((float)$row['avg_rating'], 1) ?>
                                    </span>
                                    <i class="bi bi-star-fill text-warning small"></i>
                                </td>
                                <td>
                                    <div class="d-flex" style="height:8px;border-radius:4px;overflow:hidden">
                                        <?php for ($s = 1; $s <= 5; $s++):
                                            $cnt = (int)($row['star_' . $s] ?? 0);
                                            $w   = $distTotal > 0 ? round($cnt / $distTotal * 100, 1) : 0;
                                            if ($w > 0):
                                        ?>
                                            <div style="width:<?= $w ?>%;background:<?= $starColors[$s] ?>"
                                                 title="<?= $s ?>★: <?= $w ?>%"></div>
                                        <?php endif; endfor; ?>
                                    </div>
                                </td>
                                <td class="text-center <?= $trendClass ?>"><?= $trendIcon ?></td>
                            </tr>
                            <?php $globalRank++; endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <nav class="mt-3 d-flex justify-content-center">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= buildUrl(['page' => $page - 1]) ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                        <?php
                        $start = max(1, $page - 2);
                        $end   = min($totalPages, $page + 2);
                        if ($start > 1):
                        ?>
                            <li class="page-item"><a class="page-link" href="<?= buildUrl(['page' => 1]) ?>">1</a></li>
                            <?php if ($start > 2): ?>
                                <li class="page-item disabled"><span class="page-link">…</span></li>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php for ($p = $start; $p <= $end; $p++): ?>
                            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                <a class="page-link" href="<?= buildUrl(['page' => $p]) ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                        <?php if ($end < $totalPages): ?>
                            <?php if ($end < $totalPages - 1): ?>
                                <li class="page-item disabled"><span class="page-link">…</span></li>
                            <?php endif; ?>
                            <li class="page-item"><a class="page-link" href="<?= buildUrl(['page' => $totalPages]) ?>"><?= $totalPages ?></a></li>
                        <?php endif; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= buildUrl(['page' => $page + 1]) ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>

</div><!-- end #main-content -->

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<!-- ── Inisialisasi Chart.js ── -->
<?php if (!empty($allMenuNames)): ?>
<script>
(function () {
    const labels      = <?= json_encode(array_values($chartLabels),      JSON_UNESCAPED_UNICODE) ?>;
    const dataCurrent = <?= json_encode(array_values($chartDataCurrent)) ?>;
    const dataPrev    = <?= json_encode(array_values($chartDataPrev))    ?>;
    const labelCurrent = <?= json_encode($labelCurrent, JSON_UNESCAPED_UNICODE) ?>;
    const labelPrev    = <?= json_encode($labelPrev,    JSON_UNESCAPED_UNICODE) ?>;

    if (typeof initFeedbackCompareChart === 'function') {
        initFeedbackCompareChart('feedbackCompareChart', labels, dataPrev, dataCurrent, labelPrev, labelCurrent);
    }
})();
</script>
<?php endif; ?>