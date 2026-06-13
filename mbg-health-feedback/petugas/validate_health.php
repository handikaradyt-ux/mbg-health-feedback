<?php
/**
 * petugas/validate_health.php
 * Antrian dan proses validasi data kesehatan
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../helpers/validation_helper.php';
require_once __DIR__ . '/../helpers/health_model.php';
require_once __DIR__ . '/../helpers/audit_helper.php';

requireRole([ROLE_PETUGAS]);

$pdo         = getDBConnection();
$validatorId = getCurrentUserId();
$message     = '';
$msgType     = '';

// ── Proses validasi (POST) ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $healthId  = isset($_POST['health_id'])  ? (int) $_POST['health_id']  : 0;
    $newStatus = $_POST['new_status']        ?? '';
    $notes     = trim($_POST['notes']        ?? '');
    $csrfToken = $_POST['csrf_token']        ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        $message = 'Token CSRF tidak valid. Silakan refresh halaman.';
        $msgType = 'danger';
    } elseif (!in_array($newStatus, ['approved', 'revised', 'rejected'], true)) {
        $message = 'Status tidak valid.';
        $msgType = 'danger';
    } elseif ($healthId <= 0) {
        $message = 'ID data tidak valid.';
        $msgType = 'danger';
    } else {
        $stmt = $pdo->prepare("SELECT validation_status FROM health_records WHERE health_id = :id");
        $stmt->execute([':id' => $healthId]);
        $row = $stmt->fetch();

        if (!$row) {
            $message = 'Data kesehatan tidak ditemukan.';
            $msgType = 'danger';
        } else {
            $oldStatus = $row['validation_status'];

            $stmt = $pdo->prepare(
                "UPDATE health_records SET validation_status = :status WHERE health_id = :id"
            );
            $stmt->execute([':status' => $newStatus, ':id' => $healthId]);

            $stmt = $pdo->prepare("
                INSERT INTO validation_records
                    (validator_id, data_type, data_id, old_status, new_status, validation_notes, validated_at)
                VALUES
                    (:vid, 'health_record', :did, :old, :new, :notes, NOW())
            ");
            $stmt->execute([
                ':vid'   => $validatorId,
                ':did'   => $healthId,
                ':old'   => $oldStatus,
                ':new'   => $newStatus,
                ':notes' => $notes,
            ]);

            logAudit(
                $validatorId,
                'VALIDATE_HEALTH',
                'health_records',
                $healthId,
                "Status diubah dari '{$oldStatus}' menjadi '{$newStatus}'. Catatan: {$notes}"
            );

            $statusLabel = match($newStatus) {
                'approved' => 'Disetujui',
                'revised'  => 'Direvisi',
                'rejected' => 'Ditolak',
            };
            $message = "Data kesehatan #{$healthId} berhasil {$statusLabel}.";
            $msgType = 'success';
        }
    }
}

// ── Filter ────────────────────────────────────────────────────────────────────
$filterStatus    = $_GET['status']     ?? 'pending';
$filterDateFrom  = $_GET['date_from']  ?? '';
$filterDateTo    = $_GET['date_to']    ?? '';
$filterSearch    = trim($_GET['search'] ?? '');

$sql = "
    SELECT hr.health_id, hr.height_cm, hr.weight_kg, hr.bmi_value,
           hr.bmi_category, hr.input_date, hr.notes, hr.validation_status,
           u.user_id, u.full_name, u.username,
           (SELECT COUNT(*) FROM health_records WHERE user_id = u.user_id) AS total_records
    FROM   health_records hr
    JOIN   users u ON u.user_id = hr.user_id
    WHERE  1=1
";
$params = [];

if ($filterStatus) {
    $sql .= " AND hr.validation_status = :status";
    $params[':status'] = $filterStatus;
}
if ($filterDateFrom) {
    $sql .= " AND hr.input_date >= :date_from";
    $params[':date_from'] = $filterDateFrom;
}
if ($filterDateTo) {
    $sql .= " AND hr.input_date <= :date_to";
    $params[':date_to'] = $filterDateTo;
}
if ($filterSearch) {
    $sql .= " AND (u.full_name LIKE :search OR u.username LIKE :search2)";
    $params[':search']  = "%{$filterSearch}%";
    $params[':search2'] = "%{$filterSearch}%";
}

$sql .= " ORDER BY hr.created_at ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

// Hitung total pending untuk badge
$pendingCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM health_records WHERE validation_status = 'pending'"
)->fetchColumn();

$pageTitle = 'Validasi Data Kesehatan';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_petugas.php';
$csrfToken = generateCsrfToken();
?>

<div id="main-content">

    <!-- ── Header ── -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0 fw-bold">Validasi Data Kesehatan</h4>
        </div>
    </div>

    <!-- Badge pending -->
    <?php if ($pendingCount > 0): ?>
        <div class="mb-3">
            <span class="badge bg-warning text-dark px-3 py-2">
                <i class="bi bi-clock me-1"></i><?= $pendingCount ?> data menunggu validasi
            </span>
        </div>
    <?php endif; ?>

    <?php if ($message): ?>
        <div class="alert alert-<?= $msgType ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-<?= $msgType === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
            <?= escapeOutput($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- ── Filter ── -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-3">
            <form method="GET" action="" class="row g-2 align-items-end">
                <div class="col-sm-auto">
                    <label class="form-label small mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm" style="min-width:160px">
                        <option value="pending"  <?= $filterStatus === 'pending'  ? 'selected' : '' ?>>Menunggu Validasi</option>
                        <option value="approved" <?= $filterStatus === 'approved' ? 'selected' : '' ?>>Disetujui</option>
                        <option value="revised"  <?= $filterStatus === 'revised'  ? 'selected' : '' ?>>Perlu Revisi</option>
                        <option value="rejected" <?= $filterStatus === 'rejected' ? 'selected' : '' ?>>Ditolak</option>
                        <option value=""         <?= $filterStatus === ''         ? 'selected' : '' ?>>Semua Status</option>
                    </select>
                </div>
                <div class="col-sm-auto">
                    <label class="form-label small mb-1">Dari Tanggal</label>
                    <input type="date" name="date_from" class="form-control form-control-sm"
                           value="<?= escapeOutput($filterDateFrom) ?>">
                </div>
                <div class="col-sm-auto">
                    <label class="form-label small mb-1">Sampai Tanggal</label>
                    <input type="date" name="date_to" class="form-control form-control-sm"
                           value="<?= escapeOutput($filterDateTo) ?>">
                </div>
                <div class="col-sm">
                    <label class="form-label small mb-1">Pencarian</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="bi bi-search text-muted"></i>
                        </span>
                        <input type="text" name="search" class="form-control border-start-0"
                               placeholder="Cari nama pengguna..."
                               value="<?= escapeOutput($filterSearch) ?>">
                    </div>
                </div>
                <div class="col-sm-auto">
                    <button type="submit" class="btn btn-primary btn-sm px-3">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Split Layout: Tabel + Panel Detail ── -->
    <div class="row g-3" id="splitLayout">

        <!-- Tabel Antrian -->
        <div class="col-lg-7" id="tableCol">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center py-3">
                    <span class="fw-semibold small">
                        <i class="bi bi-list-ul me-2"></i>
                        <?= $filterStatus === 'pending' ? 'Antrian Data Kesehatan' : 'Daftar Data Kesehatan' ?>
                    </span>
                    <small class="text-muted">Menampilkan <?= count($records) ?> data</small>
                </div>
                <?php if (empty($records)): ?>
                    <div class="card-body text-center py-5">
                        <i class="bi bi-check2-all fs-1 text-success mb-3 d-block"></i>
                        <h5 class="text-muted">
                            <?= $filterStatus === 'pending' ? 'Semua data kesehatan sudah divalidasi!' : 'Tidak ada data ditemukan.' ?>
                        </h5>
                        <p class="text-muted small mb-0">Tidak ada antrian tersisa.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="healthTable">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">#</th>
                                    <th>Nama Pengguna</th>
                                    <th>Tanggal Input</th>
                                    <th>TB (cm)</th>
                                    <th>BB (kg)</th>
                                    <th>BMI</th>
                                    <th>Kategori</th>
                                    <th>Status</th>
                                    <th class="text-center pe-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($records as $i => $rec):
                                    $bmiClass = match($rec['bmi_category']) {
                                        'Underweight' => 'text-info',
                                        'Normal'      => 'text-success',
                                        'Overweight'  => 'text-warning',
                                        'Obese'       => 'text-danger',
                                        default       => 'text-secondary',
                                    };
                                    $statusBadge = match($rec['validation_status']) {
                                        'pending'  => 'bg-warning text-dark',
                                        'approved' => 'bg-success',
                                        'revised'  => 'bg-info text-dark',
                                        'rejected' => 'bg-danger',
                                        default    => 'bg-secondary',
                                    };
                                    $statusLabel = match($rec['validation_status']) {
                                        'pending'  => 'Menunggu',
                                        'approved' => 'Disetujui',
                                        'revised'  => 'Revisi',
                                        'rejected' => 'Ditolak',
                                        default    => ucfirst($rec['validation_status']),
                                    };
                                ?>
                                    <tr class="health-row"
                                        data-id="<?= $rec['health_id'] ?>"
                                        data-name="<?= escapeOutput($rec['full_name']) ?>"
                                        data-username="<?= escapeOutput($rec['username']) ?>"
                                        data-height="<?= $rec['height_cm'] ?>"
                                        data-weight="<?= $rec['weight_kg'] ?>"
                                        data-bmi="<?= number_format($rec['bmi_value'], 1) ?>"
                                        data-category="<?= escapeOutput($rec['bmi_category']) ?>"
                                        data-date="<?= date('d/m/Y', strtotime($rec['input_date'])) ?>"
                                        data-notes="<?= escapeOutput($rec['notes'] ?? '') ?>"
                                        data-total="<?= $rec['total_records'] ?>"
                                        style="cursor:pointer">
                                        <td class="ps-3 text-muted small"><?= $i + 1 ?></td>
                                        <td>
                                            <div class="fw-semibold small"><?= escapeOutput($rec['full_name']) ?></div>
                                            <small class="text-muted">@<?= escapeOutput($rec['username']) ?></small>
                                        </td>
                                        <td class="small"><?= date('d M Y', strtotime($rec['input_date'])) ?></td>
                                        <td class="small"><?= $rec['height_cm'] ?></td>
                                        <td class="small"><?= $rec['weight_kg'] ?></td>
                                        <td class="fw-bold <?= $bmiClass ?>"><?= number_format($rec['bmi_value'], 1) ?></td>
                                        <td>
                                            <span class="small <?= $bmiClass ?> fw-semibold">
                                                <?= escapeOutput($rec['bmi_category']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?= $statusBadge ?>"><?= $statusLabel ?></span>
                                        </td>
                                        <td class="text-center pe-3">
                                            <button class="btn btn-sm btn-primary btn-periksa"
                                                    data-health-id="<?= $rec['health_id'] ?>">
                                                <i class="bi bi-eye me-1"></i>Periksa
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Simple pagination info -->
                    <div class="card-footer bg-transparent py-2">
                        <small class="text-muted">
                            Menampilkan 1–<?= count($records) ?> dari <?= count($records) ?> data
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Panel Detail -->
        <div class="col-lg-5" id="detailCol" style="display:none;">
            <div class="card border-0 shadow-sm sticky-top" style="top:1rem;">
                <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center py-3">
                    <span class="fw-semibold">Detail Data Kesehatan</span>
                    <button type="button" class="btn-close btn-sm" id="btnCloseDetail"></button>
                </div>
                <div class="card-body" id="detailBody">
                    <!-- diisi via JS -->
                </div>
            </div>
        </div>

    </div><!-- end row -->
</div><!-- end #main-content -->

<script>
(function () {
    const rows      = document.querySelectorAll('.health-row');
    const detailCol = document.getElementById('detailCol');
    const detailBody = document.getElementById('detailBody');
    const tableCol  = document.getElementById('tableCol');
    const btnClose  = document.getElementById('btnCloseDetail');
    const csrfToken = <?= json_encode($csrfToken) ?>;

    function bmiPercent(bmi) {
        // Scale: 0–40+ → 0–100%  (capped at 40)
        return Math.min((bmi / 40) * 100, 100);
    }

    function bmiBarColor(category) {
        const map = { Normal: '#198754', Overweight: '#ffc107', Obese: '#dc3545', Underweight: '#0dcaf0' };
        return map[category] || '#6c757d';
    }

    function renderDetail(data) {
        const pct   = bmiPercent(parseFloat(data.bmi));
        const color = bmiBarColor(data.category);

        detailBody.innerHTML = `
            <!-- User Info -->
            <div class="d-flex align-items-center gap-3 mb-4">
                <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center fw-bold text-primary"
                     style="width:52px;height:52px;font-size:1.2rem;flex-shrink:0">
                    ${data.name.charAt(0).toUpperCase()}${data.name.split(' ')[1] ? data.name.split(' ')[1].charAt(0).toUpperCase() : ''}
                </div>
                <div>
                    <div class="fw-bold">${data.name}</div>
                    <div class="text-muted small">@${data.username} &bull; Terdaftar</div>
                    <div class="small text-muted">
                        <i class="bi bi-database me-1"></i>Total Rekam: ${data.total}
                    </div>
                </div>
            </div>

            <!-- Tinggi & Berat -->
            <div class="row g-3 mb-3">
                <div class="col-6">
                    <div class="card border bg-light rounded-3 text-center py-3">
                        <div class="text-muted small mb-1">Tinggi Badan</div>
                        <div class="fs-4 fw-bold">${data.height} <span class="fs-6 fw-normal text-muted">cm</span></div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card border bg-light rounded-3 text-center py-3">
                        <div class="text-muted small mb-1">Berat Badan</div>
                        <div class="fs-4 fw-bold">${data.weight} <span class="fs-6 fw-normal text-muted">kg</span></div>
                    </div>
                </div>
            </div>

            <!-- BMI -->
            <div class="card border rounded-3 mb-3 p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small text-uppercase fw-semibold" style="letter-spacing:.05em">Indeks Massa Tubuh</span>
                    <span class="badge" style="background:${color}">${data.category}</span>
                </div>
                <div class="fs-2 fw-bold mb-2" style="color:${color}">${data.bmi}</div>
                <div class="position-relative" style="height:8px;border-radius:4px;overflow:hidden;background:linear-gradient(to right,#0dcaf0 0%,#198754 25%,#ffc107 60%,#dc3545 100%)">
                    <div class="position-absolute top-0" style="width:12px;height:12px;background:#212529;border-radius:50%;top:-2px;left:calc(${pct}% - 6px);border:2px solid #fff;box-shadow:0 0 0 2px #212529"></div>
                </div>
                <div class="d-flex justify-content-between mt-1" style="font-size:.7rem;color:#999">
                    <span>0</span><span>18.5</span><span>25</span><span>30</span><span>40+</span>
                </div>
            </div>

            <!-- Catatan Pengguna -->
            ${data.notes ? `
            <div class="mb-3">
                <div class="text-muted small fw-semibold mb-1 text-uppercase" style="letter-spacing:.05em">Catatan Pengguna</div>
                <div class="bg-warning bg-opacity-10 border border-warning border-opacity-25 rounded-3 p-3">
                    <i class="bi bi-quote me-1 text-warning"></i>
                    <span class="small fst-italic">${data.notes}</span>
                </div>
            </div>` : ''}

            <!-- Form Validasi -->
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="${csrfToken}">
                <input type="hidden" name="health_id" value="${data.id}">

                <div class="mb-3">
                    <div class="text-muted small fw-semibold mb-2 text-uppercase" style="letter-spacing:.05em">Keputusan Validasi</div>
                    <div class="d-flex gap-2">
                        <button type="submit" name="new_status" value="approved"
                                class="btn btn-outline-success flex-fill py-2 decision-btn"
                                onclick="setDecision(this)">
                            <i class="bi bi-check-circle me-1"></i>Setujui
                        </button>
                        <button type="submit" name="new_status" value="revised"
                                class="btn btn-outline-warning flex-fill py-2 decision-btn"
                                onclick="setDecision(this)">
                            <i class="bi bi-pencil me-1"></i>Revisi
                        </button>
                        <button type="submit" name="new_status" value="rejected"
                                class="btn btn-outline-danger flex-fill py-2 decision-btn"
                                onclick="setDecision(this)">
                            <i class="bi bi-x-circle me-1"></i>Tolak
                        </button>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">
                        Catatan Validasi
                        <span class="text-muted fw-normal">(Opsional untuk Setujui)</span>
                    </label>
                    <textarea class="form-control form-control-sm" name="notes" rows="3"
                              placeholder="Masukkan alasan revisi atau penolakan..."></textarea>
                </div>

                <button type="submit" name="new_status" value="_confirm" id="btnConfirm"
                        class="btn btn-primary w-100 py-2 d-none">
                    <i class="bi bi-check2-circle me-2"></i>Konfirmasi Keputusan
                </button>
            </form>
        `;
    }

    function openDetail(row) {
        const data = {
            id:       row.dataset.id,
            name:     row.dataset.name,
            username: row.dataset.username,
            height:   row.dataset.height,
            weight:   row.dataset.weight,
            bmi:      row.dataset.bmi,
            category: row.dataset.category,
            date:     row.dataset.date,
            notes:    row.dataset.notes,
            total:    row.dataset.total,
        };
        renderDetail(data);
        detailCol.style.display = 'block';
        tableCol.classList.remove('col-lg-7');
        tableCol.classList.add('col-lg-7');

        // Highlight active row
        rows.forEach(r => r.classList.remove('table-active'));
        row.classList.add('table-active');
    }

    function closeDetail() {
        detailCol.style.display = 'none';
        rows.forEach(r => r.classList.remove('table-active'));
    }

    rows.forEach(row => {
        row.querySelector('.btn-periksa').addEventListener('click', function(e) {
            e.stopPropagation();
            openDetail(row);
        });
        row.addEventListener('click', function() {
            openDetail(row);
        });
    });

    btnClose.addEventListener('click', closeDetail);

    window.setDecision = function(btn) {
        // Visual feedback — highlight chosen button
        document.querySelectorAll('.decision-btn').forEach(b => {
            b.classList.remove('active-decision');
        });
        btn.classList.add('active-decision');
    };
})();
</script>

<style>
.health-row:hover { background-color: rgba(0,0,0,.03); }
.health-row.table-active { background-color: rgba(13,110,253,.06) !important; }
.active-decision { opacity: 1 !important; box-shadow: 0 0 0 2px rgba(0,0,0,.15); }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>