<?php
/**
 * user/feedback/history.php
 * Riwayat feedback milik user
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../helpers/validation_helper.php';
require_once __DIR__ . '/../../helpers/feedback_model.php';

requireRole([ROLE_USER]);

$pdo    = getDBConnection();
$userId = getCurrentUserId();

$feedbackHistory = getFeedbackHistoryByUser($pdo, $userId);

$statusColors = [
    'pending'  => 'secondary',
    'approved' => 'success',
    'rejected' => 'danger',
    'revised'  => 'warning',
];

$pageTitle = 'Riwayat Feedback';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_user.php';
?>

<div id="main-content">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0 fw-bold">Riwayat Feedback</h4>
        <a href="<?= BASE_URL ?>/user/feedback/index.php" class="btn btn-success btn-sm">
            <i class="bi bi-star me-1"></i>Beri Feedback Baru
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent fw-semibold border-bottom">
            <i class="bi bi-chat-square-text text-success me-2"></i>Daftar Feedback Anda
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="feedbackHistoryTable" class="table table-striped table-hover align-middle w-100">
                    <thead>
                        <tr>
                            <th>Menu</th>
                            <th>Tanggal Menu</th>
                            <th>Rating</th>
                            <th>Komentar</th>
                            <th>Tanggal Feedback</th>
                            <th>Status Validasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($feedbackHistory as $row): ?>
                            <?php $statusColor = $statusColors[$row['validation_status']] ?? 'secondary'; ?>
                            <tr>
                                <td><?= escapeOutput($row['menu_name']) ?></td>
                                <td><?= escapeOutput($row['menu_date']) ?></td>
                                <td>
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="bi bi-star<?= $i <= (int) $row['rating'] ? '-fill text-warning' : ' text-muted' ?>"></i>
                                    <?php endfor; ?>
                                </td>
                                <td><?= escapeOutput($row['comment'] ?? '-') ?></td>
                                <td><?= escapeOutput($row['feedback_date']) ?></td>
                                <td><span class="badge bg-<?= $statusColor ?>"><?= escapeOutput(ucfirst($row['validation_status'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div><!-- end #main-content -->

<!-- DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.8/js/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    $('#feedbackHistoryTable').DataTable({
        order: [[4, 'desc']],
        pageLength: 10,
        language: {
            search: "Cari:",
            lengthMenu: "Tampilkan _MENU_ data",
            info: "Menampilkan _START_ - _END_ dari _TOTAL_ data",
            paginate: { previous: "Sebelumnya", next: "Selanjutnya" },
            zeroRecords: "Tidak ada data ditemukan",
            infoEmpty: "Tidak ada data"
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>