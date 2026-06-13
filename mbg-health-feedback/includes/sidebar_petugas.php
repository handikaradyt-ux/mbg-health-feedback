<?php
/**
 * includes/sidebar_petugas.php
 * Sidebar navigasi untuk role Petugas Validasi
 */
$cp = basename($_SERVER['PHP_SELF']);
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

        <!-- <li class="nav-item mt-2">
            <span class="nav-section-label">Validasi</span>
        </li> -->
        <li class="nav-item">
            <a class="nav-link <?= $cp === 'validate_health.php' ? 'active' : '' ?>"
               href="<?= BASE_URL ?>/petugas/validate_health.php">
                <i class="bi bi-clipboard2-pulse me-2"></i>Data Kesehatan
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $cp === 'validate_feedback.php' ? 'active' : '' ?>"
               href="<?= BASE_URL ?>/petugas/validate_feedback.php">
                <i class="bi bi-chat-square-check me-2"></i>Feedback Menu
            </a>
        </li>

        <!-- <li class="nav-item mt-2">
            <span class="nav-section-label">Sistem</span>
        </li> -->
        <li class="nav-item">
            <a class="nav-link <?= $cp === 'audit_log.php' ? 'active' : '' ?>"
               href="<?= BASE_URL ?>/petugas/audit_log.php">
                <i class="bi bi-journal-bookmark me-2"></i>Audit Log
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $cp === 'feedback.php' ? 'active' : '' ?>"
               href="<?= BASE_URL ?>/admin/reports/feedback.php ">
                <i class="bi bi-journal-bookmark me-2"></i>Kepuasan Menu
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
