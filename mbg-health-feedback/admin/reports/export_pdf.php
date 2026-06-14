<?php
/**
 * admin/reports/export_pdf.php
 * Export Laporan — Kesehatan & Feedback
 * Tanpa mPDF / library pihak ketiga. Menggunakan window.print() bawaan browser.
 * Akses: admin only.
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/auth/session.php';
require_once dirname(__DIR__, 2) . '/helpers/report_model.php';
require_once dirname(__DIR__, 2) . '/config/database.php';

requireRole([ROLE_ADMIN]);

$pdo    = getDBConnection();
$userId = getCurrentUserId();
$adminName = getCurrentFullName();

// ────────────────────────────────────────────────────────────────────────────
// 1. Tentukan mode: index (pilih laporan) atau print (tampilan cetak)
// ────────────────────────────────────────────────────────────────────────────
$type = $_GET['type'] ?? '';
$allowedTypes = ['health', 'feedback'];

if (in_array($type, $allowedTypes, true)) {

    // ────────────────────────────────────────────────────────────────────────
    // MODE PRINT — siapkan filter & data, lalu render halaman siap cetak
    // ────────────────────────────────────────────────────────────────────────

    if ($type === 'health') {

        $bmiCat    = $_GET['bmi_cat'] ?? 'semua';
        $allowedCat = ['semua', 'Underweight', 'Normal', 'Overweight', 'Obese'];
        if (!in_array($bmiCat, $allowedCat, true)) $bmiCat = 'semua';

        $dateStart = $_GET['date_start'] ?? date('Y-m-01');
        $dateEnd   = $_GET['date_end']   ?? date('Y-m-t');
        $dateStart = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStart) ? $dateStart : date('Y-m-01');
        $dateEnd   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateEnd)   ? $dateEnd   : date('Y-m-t');
        if ($dateStart > $dateEnd) [$dateStart, $dateEnd] = [$dateEnd, $dateStart];

        $rows = getHealthExportData($pdo, $dateStart, $dateEnd, $bmiCat);

        $fileName = 'Laporan_Kesehatan_' . $dateStart . '_sd_' . $dateEnd . '.pdf';
        $reportTitle = 'Laporan Data Kesehatan Peserta';
        $periodeLabel = date('d M Y', strtotime($dateStart)) . ' s/d ' . date('d M Y', strtotime($dateEnd));
        $catLabel = $bmiCat === 'semua' ? 'Semua Kategori BMI' : $bmiCat;

    } else { // feedback

        $periode   = $_GET['periode'] ?? date('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', $periode)) $periode = date('Y-m');
        $dateStart = $periode . '-01';
        $dateEnd   = date('Y-m-t', strtotime($dateStart));

        $menuId    = isset($_GET['menu_id']) && $_GET['menu_id'] !== '' ? (int) $_GET['menu_id'] : null;
        $minRating = isset($_GET['min_rating']) && $_GET['min_rating'] !== '' ? (int) $_GET['min_rating'] : null;

        $rows = getFeedbackExportData($pdo, $dateStart, $dateEnd, $menuId, $minRating);

        $fileName = 'Laporan_Feedback_' . $periode . '.pdf';
        $reportTitle = 'Laporan Kepuasan Menu MBG';
        $bulanId = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        [$y, $m] = explode('-', $periode);
        $periodeLabel = $bulanId[(int)$m - 1] . ' ' . $y;
        $catLabel = ($menuId ? 'Menu Terpilih' : 'Semua Menu')
                  . ($minRating ? ', Min. Rating ' . $minRating . '★' : '');
    }

    // ── Simpan riwayat export (hanya saat user benar2 membuka halaman cetak) ──
    saveReportDownload($pdo, $userId, $type, $fileName);

    $generatedAt = date('d M Y H:i');
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($reportTitle) ?></title>
    <style>
        @page { size: A4; margin: 18mm 14mm; }
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            color: #1f2937;
            margin: 0;
            padding: 24px;
            font-size: 13px;
        }
        .print-toolbar {
            position: sticky; top: 0;
            background: #f8fafc; border-bottom: 1px solid #e2e8f0;
            padding: 12px 0; margin-bottom: 20px;
            display: flex; gap: 8px; justify-content: flex-end;
        }
        .btn {
            padding: 8px 16px; border-radius: 6px; border: none;
            font-size: 13px; font-weight: 600; cursor: pointer;
            text-decoration: none; display: inline-block;
        }
        .btn-print  { background: #2563eb; color: #fff; }
        .btn-back   { background: #e2e8f0; color: #1f2937; }

        .report-header {
            display: flex; justify-content: space-between; align-items: flex-start;
            border-bottom: 3px solid #2563eb; padding-bottom: 12px; margin-bottom: 16px;
        }
        .report-header h1 { font-size: 20px; margin: 0 0 4px; color: #1e3a8a; }
        .report-header p  { margin: 2px 0; font-size: 12px; color: #475569; }
        .report-meta { text-align: right; font-size: 12px; color: #475569; }

        .info-box {
            background: #f1f5f9; border-radius: 6px; padding: 10px 14px;
            margin-bottom: 16px; font-size: 12px; display: flex; gap: 24px; flex-wrap: wrap;
        }
        .info-box span b { color: #1e293b; }

        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; font-size: 12px; text-align: left; }
        th { background: #2563eb; color: #fff; text-transform: uppercase; font-size: 10.5px; letter-spacing: .03em; }
        tbody tr:nth-child(even) { background: #f8fafc; }
        td.text-center, th.text-center { text-align: center; }
        td.text-end { text-align: right; }

        .badge {
            display: inline-block; padding: 2px 8px; border-radius: 10px;
            font-size: 10.5px; font-weight: 600; color: #fff;
        }
        .badge-Underweight, .badge-pending  { background: #f59e0b; }
        .badge-Normal,      .badge-approved { background: #22c55e; }
        .badge-Overweight,  .badge-revised  { background: #eab308; color:#1f2937; }
        .badge-Obese,       .badge-rejected { background: #ef4444; }

        .footer-note { margin-top: 24px; font-size: 11px; color: #94a3b8; text-align: center; }

        @media print {
            .print-toolbar { display: none !important; }
            body { padding: 0; }
        }
    </style>
</head>
<body>

    <div class="print-toolbar">
        <a href="<?= BASE_URL ?>/admin/reports/export_pdf.php" class="btn btn-back">&larr; Kembali</a>
        <button class="btn btn-print" onclick="window.print()">🖨️ Cetak / Simpan PDF</button>
    </div>

    <div class="report-header">
        <div>
            <h1><?= htmlspecialchars($reportTitle) ?></h1>
            <p>Sistem Informasi MBG-Health &amp; Feedback</p>
            <p>Periode: <?= htmlspecialchars($periodeLabel) ?></p>
        </div>
        <div class="report-meta">
            <p>Dicetak oleh: <b><?= htmlspecialchars($adminName ?? '-') ?></b></p>
            <p>Tanggal cetak: <?= $generatedAt ?></p>
            <p>Total data: <?= count($rows) ?> baris</p>
        </div>
    </div>

    <div class="info-box">
        <span>Filter: <b><?= htmlspecialchars($catLabel) ?></b></span>
        <span>Rentang: <b><?= htmlspecialchars($periodeLabel) ?></b></span>
    </div>

    <?php if ($type === 'health'): ?>
        <table>
            <thead>
                <tr>
                    <th class="text-center" style="width:32px">No</th>
                    <th>Nama Pengguna</th>
                    <th>Tanggal Input</th>
                    <th class="text-end">TB (cm)</th>
                    <th class="text-end">BB (kg)</th>
                    <th class="text-end">BMI</th>
                    <th>Kategori</th>
                    <th class="text-center">Status Validasi</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="8" class="text-center">Tidak ada data untuk filter ini.</td></tr>
            <?php else: $no = 1; foreach ($rows as $r): ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td><?= htmlspecialchars($r['full_name']) ?></td>
                    <td><?= date('d M Y', strtotime($r['input_date'])) ?></td>
                    <td class="text-end"><?= number_format((float)$r['height_cm'], 1) ?></td>
                    <td class="text-end"><?= number_format((float)$r['weight_kg'], 1) ?></td>
                    <td class="text-end"><?= number_format((float)$r['bmi_value'], 2) ?></td>
                    <td><span class="badge badge-<?= $r['bmi_category'] ?>"><?= htmlspecialchars($r['bmi_category']) ?></span></td>
                    <td class="text-center"><span class="badge badge-<?= $r['validation_status'] ?>"><?= ucfirst($r['validation_status']) ?></span></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th class="text-center" style="width:32px">No</th>
                    <th>Nama Pengguna</th>
                    <th>Nama Menu</th>
                    <th class="text-center">Rating</th>
                    <th>Komentar</th>
                    <th>Tanggal Feedback</th>
                    <th class="text-center">Status Validasi</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="7" class="text-center">Tidak ada data untuk filter ini.</td></tr>
            <?php else: $no = 1; foreach ($rows as $r): ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td><?= htmlspecialchars($r['full_name']) ?></td>
                    <td><?= htmlspecialchars($r['menu_name']) ?></td>
                    <td class="text-center"><?= (int)$r['rating'] ?> ★</td>
                    <td><?= htmlspecialchars($r['comment'] ?? '-') ?></td>
                    <td><?= date('d M Y', strtotime($r['feedback_date'])) ?></td>
                    <td class="text-center"><span class="badge badge-<?= $r['validation_status'] ?>"><?= ucfirst($r['validation_status']) ?></span></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <p class="footer-note">Dokumen ini digenerate otomatis oleh Sistem Informasi MBG-Health &amp; Feedback.</p>

    <script>
        // Auto-buka dialog print saat halaman siap (opsional, bisa dihapus jika tidak diinginkan)
        // window.onload = function () { window.print(); };
    </script>
</body>
</html>
    <?php
    exit;
}

// ────────────────────────────────────────────────────────────────────────────
// MODE INDEX — halaman pilihan laporan + riwayat export
// ────────────────────────────────────────────────────────────────────────────

$menuList = function_exists('getFeedbackMenuList') ? getFeedbackMenuList($pdo) : [];
$history  = getReportDownloadHistory($pdo, 20);

$pageTitle = 'Ekspor Laporan';
require_once dirname(__DIR__, 2) . '/includes/header.php';
require_once dirname(__DIR__, 2) . '/includes/sidebar_admin.php';
?>

<div id="main-content">

    <div class="mb-4">
        <h4 class="fw-bold mb-0">Ekspor Laporan</h4>
        <p class="text-muted small mb-0">Buat tampilan siap cetak laporan dalam format PDF (via Print to PDF browser)</p>
    </div>

    <!-- ── Laporan Kesehatan ── -->
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex align-items-start gap-3 mb-3">
                <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" style="width:48px;height:48px;flex-shrink:0">
                    <i class="bi bi-clipboard2-pulse text-primary fs-5"></i>
                </div>
                <div>
                    <h6 class="fw-semibold mb-1">Laporan Data Kesehatan</h6>
                    <p class="text-muted small mb-0">Rekapitulasi TB, BB, BMI, kategori, dan status validasi peserta.</p>
                </div>
            </div>
            <form method="get" action="export_pdf.php" target="_blank" class="row g-2 align-items-end">
                <input type="hidden" name="type" value="health">
                <div class="col-12 col-md-3">
                    <label class="form-label small text-muted mb-1">Tanggal Mulai</label>
                    <input type="date" name="date_start" class="form-control form-control-sm" value="<?= date('Y-m-01') ?>">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label small text-muted mb-1">Tanggal Akhir</label>
                    <input type="date" name="date_end" class="form-control form-control-sm" value="<?= date('Y-m-t') ?>">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label small text-muted mb-1">Kategori BMI</label>
                    <select name="bmi_cat" class="form-select form-select-sm">
                        <option value="semua">Semua Kategori BMI</option>
                        <option value="Underweight">Underweight</option>
                        <option value="Normal">Normal</option>
                        <option value="Overweight">Overweight</option>
                        <option value="Obese">Obese</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <button type="submit" class="btn btn-danger btn-sm w-100">
                        <i class="bi bi-file-earmark-pdf me-1"></i>Export Laporan Kesehatan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Laporan Feedback ── -->
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex align-items-start gap-3 mb-3">
                <div class="rounded-circle bg-warning bg-opacity-10 d-flex align-items-center justify-content-center" style="width:48px;height:48px;flex-shrink:0">
                    <i class="bi bi-star-fill text-warning fs-5"></i>
                </div>
                <div>
                    <h6 class="fw-semibold mb-1">Laporan Kepuasan Menu</h6>
                    <p class="text-muted small mb-0">Rekap rating, komentar, dan status validasi feedback menu.</p>
                </div>
            </div>
            <form method="get" action="export_pdf.php" target="_blank" class="row g-2 align-items-end">
                <input type="hidden" name="type" value="feedback">
                <div class="col-12 col-md-3">
                    <label class="form-label small text-muted mb-1">Periode</label>
                    <input type="month" name="periode" class="form-control form-control-sm" value="<?= date('Y-m') ?>">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label small text-muted mb-1">Menu</label>
                    <select name="menu_id" class="form-select form-select-sm">
                        <option value="">Semua Menu</option>
                        <?php foreach ($menuList as $m): ?>
                            <option value="<?= $m['menu_id'] ?>"><?= htmlspecialchars($m['menu_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label small text-muted mb-1">Min. Rating</label>
                    <select name="min_rating" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        <?php for ($i=1;$i<=5;$i++): ?>
                            <option value="<?= $i ?>"><?= $i ?> Bintang</option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <button type="submit" class="btn btn-danger btn-sm w-100">
                        <i class="bi bi-file-earmark-pdf me-1"></i>Export Laporan Feedback
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Riwayat Export ── -->
    <div class="card shadow-sm">
        <div class="card-body">
            <h6 class="fw-semibold mb-3">Riwayat Export</h6>
            <?php if (empty($history)): ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-clock-history fs-1 d-block mb-2"></i>Belum ada riwayat export.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>No</th>
                                <th>Jenis Laporan</th>
                                <th>Nama File</th>
                                <th>Admin</th>
                                <th>Tanggal Export</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $no = 1; foreach ($history as $h): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td>
                                    <?php if ($h['report_type'] === 'health'): ?>
                                        <span class="badge bg-primary">Data Kesehatan</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Kepuasan Menu</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($h['file_name']) ?></td>
                                <td><?= htmlspecialchars($h['admin_name']) ?></td>
                                <td><?= date('d M Y H:i', strtotime($h['downloaded_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>