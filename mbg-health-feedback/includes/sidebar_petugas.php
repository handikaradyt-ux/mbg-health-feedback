<?php
/**
 * includes/sidebar_petugas.php
 * Sidebar navigasi untuk role Petugas Validasi
 */
$cp = basename($_SERVER['PHP_SELF']);

// Hitung pending untuk badge — pdo sudah tersedia dari halaman pemanggil
$_sbPendingHealth    = 0;
$_sbPendingFeedback  = 0;
if (isset($pdo)) {
    try {
        $_sbPendingHealth   = (int) $pdo->query(
            "SELECT COUNT(*) FROM health_records WHERE validation_status = 'pending'"
        )->fetchColumn();
        $_sbPendingFeedback = (int) $pdo->query(
            "SELECT COUNT(*) FROM feedbacks WHERE validation_status = 'pending'"
        )->fetchColumn();
    } catch (\Throwable $e) {
        // Abaikan error — badge tidak tampil, navigasi tetap berjalan
    }
}
?>
<nav class="sidebar bg-dark d-flex flex-column p-0">
    <div class="px-3 py-3 border-bottom border-secondary">
        <small class="nav-section-label d-block mb-0">Petugas Validasi</small>
    </div>

    <ul class="nav flex-column px-2 py-2 flex-grow-1">

        <li class="nav-item">
            <a class="nav-link <?= $cp === 'dashboard.php' ? 'active' : '' ?>"
               href="<?= BASE_URL ?>/petugas/dashboard.php">
                <i class="bi bi-speedometer2 me-2"></i>Dashboard
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link d-flex justify-content-between align-items-center
                      <?= $cp === 'validate_health.php' ? 'active' : '' ?>"
               href="<?= BASE_URL ?>/petugas/validate_health.php">
                <span>
                    <i class="bi bi-clipboard2-pulse me-2"></i>Validasi Data Kesehatan
                </span>
                <?php if ($_sbPendingHealth > 0): ?>
                    <span class="badge rounded-pill bg-warning text-dark ms-1">
                        <?= $_sbPendingHealth ?>
                    </span>
                <?php endif; ?>
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link d-flex justify-content-between align-items-center
                      <?= $cp === 'validate_feedback.php' ? 'active' : '' ?>"
               href="<?= BASE_URL ?>/petugas/validate_feedback.php">
                <span>
                    <i class="bi bi-chat-square-check me-2"></i>Validasi Feedback
                </span>
                <?php if ($_sbPendingFeedback > 0): ?>
                    <span class="badge rounded-pill bg-warning text-dark ms-1">
                        <?= $_sbPendingFeedback ?>
                    </span>
                <?php endif; ?>
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?= $cp === 'audit_log.php' ? 'active' : '' ?>"
               href="<?= BASE_URL ?>/petugas/audit_log.php">
                <i class="bi bi-journal-bookmark me-2"></i>Audit Log
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?= $cp === 'feedback.php' ? 'active' : '' ?>"
               href="<?= BASE_URL ?>/admin/reports/feedback.php">
                <i class="bi bi-graph-up me-2"></i>Kepuasan Menu
            </a>
        </li>

    </ul>

    <div class="px-3 py-2 border-top border-secondary">
        <a href="<?= BASE_URL ?>/auth/logout.php"
           class="btn btn-sm btn-outline-danger w-100"
           onclick="return confirm('Yakin ingin logout?')">
            <i class="bi bi-box-arrow-right me-1"></i>Logout
        </a>
    </div>
</nav>
