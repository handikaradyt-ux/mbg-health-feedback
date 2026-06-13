<?php
/**
 * petugas/validate_feedback.php
 * Antrian dan proses validasi feedback menu
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../helpers/validation_helper.php';
require_once __DIR__ . '/../helpers/feedback_model.php';
require_once __DIR__ . '/../helpers/audit_helper.php';

requireRole([ROLE_PETUGAS]);

$pdo         = getDBConnection();
$validatorId = getCurrentUserId();
$message     = '';
$msgType     = '';

// ── Proses validasi (POST) ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $feedbackId = isset($_POST['feedback_id']) ? (int) $_POST['feedback_id'] : 0;
    $newStatus  = $_POST['new_status']          ?? '';
    $notes      = trim($_POST['notes']          ?? '');
    $csrfToken  = $_POST['csrf_token']          ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        $message = 'Token CSRF tidak valid. Silakan refresh halaman.';
        $msgType = 'danger';
    } elseif (!in_array($newStatus, ['approved', 'revised', 'rejected'], true)) {
        $message = 'Status tidak valid.';
        $msgType = 'danger';
    } elseif ($feedbackId <= 0) {
        $message = 'ID feedback tidak valid.';
        $msgType = 'danger';
    } else {
        $stmt = $pdo->prepare("SELECT validation_status FROM feedbacks WHERE feedback_id = :id");
        $stmt->execute([':id' => $feedbackId]);
        $row = $stmt->fetch();

        if (!$row) {
            $message = 'Feedback tidak ditemukan.';
            $msgType = 'danger';
        } else {
            $oldStatus = $row['validation_status'];

            $stmt = $pdo->prepare(
                "UPDATE feedbacks SET validation_status = :status WHERE feedback_id = :id"
            );
            $stmt->execute([':status' => $newStatus, ':id' => $feedbackId]);

            $stmt = $pdo->prepare("
                INSERT INTO validation_records
                    (validator_id, data_type, data_id, old_status, new_status, validation_notes, validated_at)
                VALUES
                    (:vid, 'feedback', :did, :old, :new, :notes, NOW())
            ");
            $stmt->execute([
                ':vid'   => $validatorId,
                ':did'   => $feedbackId,
                ':old'   => $oldStatus,
                ':new'   => $newStatus,
                ':notes' => $notes,
            ]);

            logAudit(
                $validatorId,
                'VALIDATE_FEEDBACK',
                'feedbacks',
                $feedbackId,
                "Status diubah dari '{$oldStatus}' menjadi '{$newStatus}'. Catatan: {$notes}"
            );

            $statusLabel = match($newStatus) {
                'approved' => 'Disetujui',
                'revised'  => 'Direvisi',
                'rejected' => 'Ditolak',
            };
            $message = "Feedback #{$feedbackId} berhasil {$statusLabel}.";
            $msgType = 'success';
        }
    }
}

// ── Filter ────────────────────────────────────────────────────────────────────
$filterMenu   = trim($_GET['menu']   ?? '');
$filterRating = $_GET['rating']      ?? '';
$filterDate   = $_GET['date']        ?? '';
$filterSearch = trim($_GET['search'] ?? '');

$sql = "
    SELECT f.feedback_id, f.rating, f.comment, f.feedback_date, f.validation_status,
           u.user_id, u.full_name, u.username, u.created_at AS joined_at,
           m.menu_id, m.menu_name, m.menu_date
    FROM   feedbacks f
    JOIN   users u ON u.user_id = f.user_id
    JOIN   menus  m ON m.menu_id = f.menu_id
    WHERE  f.validation_status = 'pending'
";
$params = [];

if ($filterMenu) {
    $sql .= " AND m.menu_name LIKE :menu";
    $params[':menu'] = "%{$filterMenu}%";
}
if ($filterRating) {
    $sql .= " AND f.rating = :rating";
    $params[':rating'] = (int) $filterRating;
}
if ($filterDate) {
    $sql .= " AND f.feedback_date = :date";
    $params[':date'] = $filterDate;
}
if ($filterSearch) {
    $sql .= " AND (u.full_name LIKE :search OR u.username LIKE :search2 OR m.menu_name LIKE :search3)";
    $params[':search']  = "%{$filterSearch}%";
    $params[':search2'] = "%{$filterSearch}%";
    $params[':search3'] = "%{$filterSearch}%";
}
$sql .= " ORDER BY f.created_at ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$feedbacks = $stmt->fetchAll();

// Total pending
$pendingCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM feedbacks WHERE validation_status = 'pending'"
)->fetchColumn();

// Daftar menu unik untuk filter dropdown
$menuList = $pdo->query(
    "SELECT DISTINCT m.menu_name FROM feedbacks f JOIN menus m ON m.menu_id = f.menu_id ORDER BY m.menu_name ASC"
)->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Validasi Feedback Menu';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_petugas.php';
$csrfToken = generateCsrfToken();
?>

<div id="main-content">

    <!-- ── Header ── -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0 fw-bold">Validasi Feedback Menu</h4>
        </div>
    </div>

    <!-- Badge pending -->
    <?php if ($pendingCount > 0): ?>
        <div class="mb-3">
            <span class="badge bg-warning text-dark px-3 py-2">
                <i class="bi bi-chat-square me-1"></i><?= $pendingCount ?> feedback menunggu validasi
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
                <div class="col-sm">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="bi bi-search text-muted"></i>
                        </span>
                        <input type="text" name="search" class="form-control border-start-0"
                               placeholder="Cari nama pengguna atau menu..."
                               value="<?= escapeOutput($filterSearch) ?>">
                    </div>
                </div>
                <div class="col-sm-auto">
                    <select name="menu" class="form-select form-select-sm" style="min-width:140px">
                        <option value="">Semua Menu</option>
                        <?php foreach ($menuList as $mn): ?>
                            <option value="<?= escapeOutput($mn) ?>"
                                    <?= $filterMenu === $mn ? 'selected' : '' ?>>
                                <?= escapeOutput($mn) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-auto">
                    <select name="rating" class="form-select form-select-sm">
                        <option value="">Semua Rating</option>
                        <?php for ($r = 5; $r >= 1; $r--): ?>
                            <option value="<?= $r ?>" <?= (int)$filterRating === $r ? 'selected' : '' ?>>
                                <?= $r ?> Bintang
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-sm-auto">
                    <input type="date" name="date" class="form-control form-control-sm"
                           value="<?= escapeOutput($filterDate) ?>">
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
    <div class="row g-3">

        <!-- Tabel Feedback -->
        <div class="col-lg-7" id="fbTableCol">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center py-3">
                    <span class="fw-semibold small">
                        <i class="bi bi-list-ul me-2"></i>Antrian Feedback
                    </span>
                    <small class="text-muted">Menampilkan <?= count($feedbacks) ?> feedback</small>
                </div>
                <?php if (empty($feedbacks)): ?>
                    <div class="card-body text-center py-5">
                        <i class="bi bi-chat-square-check fs-1 text-success mb-3 d-block"></i>
                        <h5 class="text-muted">Semua feedback sudah divalidasi!</h5>
                        <p class="text-muted small mb-0">Tidak ada antrian tersisa.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">No.</th>
                                    <th>Pengguna</th>
                                    <th>Menu yang Dinilai</th>
                                    <th>Rating</th>
                                    <th>Komentar</th>
                                    <th>Status</th>
                                    <th class="text-center pe-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($feedbacks as $i => $fb): ?>
                                    <tr class="fb-row"
                                        data-id="<?= $fb['feedback_id'] ?>"
                                        data-name="<?= escapeOutput($fb['full_name']) ?>"
                                        data-username="<?= escapeOutput($fb['username']) ?>"
                                        data-joined="<?= date('M Y', strtotime($fb['joined_at'])) ?>"
                                        data-menu="<?= escapeOutput($fb['menu_name']) ?>"
                                        data-menudate="<?= date('d M Y', strtotime($fb['menu_date'])) ?>"
                                        data-rating="<?= $fb['rating'] ?>"
                                        data-comment="<?= escapeOutput($fb['comment'] ?? '') ?>"
                                        style="cursor:pointer">
                                        <td class="ps-3 text-muted small"><?= $i + 1 ?></td>
                                        <td>
                                            <div class="fw-semibold small"><?= escapeOutput($fb['full_name']) ?></div>
                                            <small class="text-muted">@<?= escapeOutput($fb['username']) ?></small>
                                        </td>
                                        <td class="small">
                                            <div class="fw-semibold"><?= escapeOutput($fb['menu_name']) ?></div>
                                            <small class="text-muted">
                                                <?= date('d/m/Y', strtotime($fb['menu_date'])) ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php for ($s = 1; $s <= 5; $s++): ?>
                                                <i class="bi bi-star<?= $s <= $fb['rating'] ? '-fill' : '' ?> text-warning"
                                                   style="font-size:.85rem"></i>
                                            <?php endfor; ?>
                                        </td>
                                        <td class="small text-muted" style="max-width:160px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis">
                                            <?= escapeOutput($fb['comment'] ?? '-') ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning text-dark">Menunggu Validasi</span>
                                        </td>
                                        <td class="text-center pe-3">
                                            <button class="btn btn-sm btn-primary btn-fb-periksa"
                                                    data-fb-id="<?= $fb['feedback_id'] ?>">
                                                <i class="bi bi-eye me-1"></i>Periksa
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Panel Detail Feedback -->
        <div class="col-lg-5" id="fbDetailCol" style="display:none;">
            <div class="card border-0 shadow-sm sticky-top" style="top:1rem;">
                <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center py-3">
                    <span class="fw-semibold">Detail Feedback</span>
                    <button type="button" class="btn-close btn-sm" id="btnCloseFbDetail"></button>
                </div>
                <div class="card-body" id="fbDetailBody">
                    <!-- diisi via JS -->
                </div>
            </div>
        </div>

    </div><!-- end row -->
</div><!-- end #main-content -->

<script>
(function () {
    const rows       = document.querySelectorAll('.fb-row');
    const detailCol  = document.getElementById('fbDetailCol');
    const detailBody = document.getElementById('fbDetailBody');
    const btnClose   = document.getElementById('btnCloseFbDetail');
    const csrfToken  = <?= json_encode($csrfToken) ?>;

    function renderStars(rating, big) {
        let s = '';
        const size = big ? '1.4rem' : '.9rem';
        for (let i = 1; i <= 5; i++) {
            s += `<i class="bi bi-star${i <= parseInt(rating) ? '-fill' : ''} text-warning" style="font-size:${size}"></i>`;
        }
        return s;
    }

    function renderDetail(data) {
        detailBody.innerHTML = `
            <!-- User info -->
            <div class="d-flex align-items-center gap-3 mb-4">
                <div class="rounded-circle bg-secondary bg-opacity-15 d-flex align-items-center justify-content-center fw-bold text-secondary"
                     style="width:52px;height:52px;font-size:1.2rem;flex-shrink:0">
                    ${data.name.charAt(0).toUpperCase()}
                </div>
                <div>
                    <div class="fw-bold">${data.name}</div>
                    <div class="text-muted small">Bergabung sejak ${data.joined}</div>
                </div>
            </div>

            <!-- Menu yang dinilai -->
            <div class="mb-3">
                <div class="text-muted small fw-semibold mb-1 text-uppercase" style="letter-spacing:.05em">Menu yang Dinilai</div>
                <div class="fw-bold">${data.menu}</div>
                <div class="text-muted small">
                    <i class="bi bi-calendar3 me-1"></i>Disajikan: ${data.menudate}
                </div>
            </div>

            <!-- Rating -->
            <div class="mb-3">
                <div class="text-muted small fw-semibold mb-2 text-uppercase" style="letter-spacing:.05em">Rating Penilaian</div>
                <div class="mb-2">${renderStars(data.rating, true)}</div>
                ${data.comment ? `
                <div class="bg-light border rounded-3 p-3 fst-italic text-muted small">
                    "${data.comment}"
                </div>` : '<div class="text-muted small fst-italic">Tidak ada komentar.</div>'}
            </div>

            <!-- Form Validasi -->
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="${csrfToken}">
                <input type="hidden" name="feedback_id" value="${data.id}">

                <div class="mb-3">
                    <div class="text-muted small fw-semibold mb-2 text-uppercase" style="letter-spacing:.05em">Keputusan Validasi</div>
                    <div class="d-flex flex-column gap-2">
                        <button type="submit" name="new_status" value="approved"
                                class="btn btn-outline-success text-start px-3 py-2 decision-card">
                            <i class="bi bi-check-circle me-2"></i>Setujui Feedback
                        </button>
                        <button type="submit" name="new_status" value="revised"
                                class="btn btn-outline-warning text-start px-3 py-2 decision-card">
                            <i class="bi bi-pencil me-2"></i>Tandai Perlu Revisi
                        </button>
                        <button type="submit" name="new_status" value="rejected"
                                class="btn btn-outline-danger text-start px-3 py-2 decision-card">
                            <i class="bi bi-x-circle me-2"></i>Tolak Feedback
                        </button>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">
                        Catatan untuk Pengguna
                        <span class="text-muted fw-normal">(wajib jika Ditolak)</span>
                    </label>
                    <textarea class="form-control form-control-sm" name="notes" rows="3"
                              placeholder="Jelaskan alasan keputusan Anda..."></textarea>
                </div>

                <button type="submit" name="new_status" value="_confirm" id="fbBtnConfirm"
                        class="btn btn-primary w-100 py-2 fw-semibold d-none">
                    <i class="bi bi-check2-circle me-2"></i>Konfirmasi Keputusan
                </button>
            </form>
        `;

        // Highlight pada klik card decision
        detailBody.querySelectorAll('.decision-card').forEach(btn => {
            btn.addEventListener('click', function() {
                detailBody.querySelectorAll('.decision-card').forEach(b => b.classList.remove('active-choice'));
                this.classList.add('active-choice');
            });
        });
    }

    function openDetail(row) {
        const data = {
            id:       row.dataset.id,
            name:     row.dataset.name,
            username: row.dataset.username,
            joined:   row.dataset.joined,
            menu:     row.dataset.menu,
            menudate: row.dataset.menudate,
            rating:   row.dataset.rating,
            comment:  row.dataset.comment,
        };
        renderDetail(data);
        detailCol.style.display = 'block';

        rows.forEach(r => r.classList.remove('table-active'));
        row.classList.add('table-active');
    }

    function closeDetail() {
        detailCol.style.display = 'none';
        rows.forEach(r => r.classList.remove('table-active'));
    }

    rows.forEach(row => {
        row.querySelector('.btn-fb-periksa').addEventListener('click', function(e) {
            e.stopPropagation();
            openDetail(row);
        });
        row.addEventListener('click', () => openDetail(row));
    });

    btnClose.addEventListener('click', closeDetail);
})();
</script>

<style>
.fb-row:hover { background-color: rgba(0,0,0,.03); }
.fb-row.table-active { background-color: rgba(13,110,253,.06) !important; }
.active-choice { box-shadow: 0 0 0 2px rgba(0,0,0,.15) !important; }
.decision-card { border-width: 1.5px; transition: all .15s; }
.decision-card:hover { transform: translateX(2px); }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>