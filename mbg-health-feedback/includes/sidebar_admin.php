<?php
/**
 * includes/sidebar_admin.php
 * Sidebar navigasi untuk role Admin
 */
$cp  = basename($_SERVER['PHP_SELF']);
$dir = basename(dirname($_SERVER['PHP_SELF']));

function saActive(string $page): string {
    global $cp, $dir;
    return $cp === $page ? 'active' : '';
}
function saDirActive(string $folder): string {
    global $dir;
    return $dir === $folder ? 'active' : '';
}
?>
<nav class="sidebar bg-dark d-flex flex-column p-0">
    <div class="px-3 py-3 border-bottom border-secondary">
        <small class="nav-section-label d-block mb-0">Admin Panel</small>
    </div>

    <ul class="nav flex-column px-2 py-2 flex-grow-1">

        <li class="nav-item">
            <a class="nav-link <?= saActive('dashboard.php') ?>"
               href="<?= BASE_URL ?>/admin/dashboard.php">
                <i class="bi bi-speedometer2 me-2"></i>Dashboard
            </a>
        </li>

        <li class="nav-item mt-2">
            <span class="nav-section-label">Manajemen</span>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= saDirActive('users') ?>"
               href="<?= BASE_URL ?>/admin/users/index.php">
                <i class="bi bi-people me-2"></i>Pengguna
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= saDirActive('menu') ?>"
               href="<?= BASE_URL ?>/admin/menu/index.php">
                <i class="bi bi-journal-text me-2"></i>Menu MBG
            </a>
        </li>

        <li class="nav-item mt-2">
            <span class="nav-section-label">Laporan</span>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= saActive('health.php') ?>"
               href="<?= BASE_URL ?>/admin/reports/health.php">
                <i class="bi bi-heart-pulse me-2"></i>Laporan Kesehatan
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= saActive('feedback.php') ?>"
               href="<?= BASE_URL ?>/admin/reports/feedback.php">
                <i class="bi bi-bar-chart-line me-2"></i>Laporan Feedback
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?= BASE_URL ?>/admin/reports/export_pdf.php" target="_blank">
                <i class="bi bi-file-pdf me-2"></i>Export PDF
            </a>
        </li>

        <li class="nav-item mt-2">
            <span class="nav-section-label">Sistem</span>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= saActive('audit_log.php') ?>"
               href="<?= BASE_URL ?>/admin/audit_log.php">
                <i class="bi bi-journal-bookmark me-2"></i>Audit Log
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
