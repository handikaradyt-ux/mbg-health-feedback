<?php
/**
 * includes/sidebar_user.php
 * Sidebar navigasi untuk role User (Peserta MBG)
 */
$cp  = basename($_SERVER['PHP_SELF']);
$dir = basename(dirname($_SERVER['PHP_SELF']));
?>
<nav class="sidebar bg-dark d-flex flex-column p-0">
    <div class="px-3 py-3 border-bottom border-secondary">
        <small class="nav-section-label d-block mb-0">Menu Pengguna</small>
    </div>

    <ul class="nav flex-column px-2 py-2 flex-grow-1">

        <li class="nav-item">
            <a class="nav-link <?= $cp === 'dashboard.php' ? 'active' : '' ?>"
               href="<?= BASE_URL ?>/user/dashboard.php">
                <i class="bi bi-house me-2"></i>Beranda
            </a>
        </li>

        <li class="nav-item mt-2">
            <span class="nav-section-label">Kesehatan</span>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($cp === 'input.php'   && $dir === 'health') ? 'active' : '' ?>"
               href="<?= BASE_URL ?>/user/health/input.php">
                <i class="bi bi-pencil-square me-2"></i>Input Data Kesehatan
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($cp === 'history.php' && $dir === 'health') ? 'active' : '' ?>"
               href="<?= BASE_URL ?>/user/health/history.php">
                <i class="bi bi-graph-up me-2"></i>Riwayat &amp; Grafik BMI
            </a>
        </li>

        <li class="nav-item mt-2">
            <span class="nav-section-label">Feedback Menu</span>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($cp === 'index.php'   && $dir === 'feedback') ? 'active' : '' ?>"
               href="<?= BASE_URL ?>/user/feedback/index.php">
                <i class="bi bi-star me-2"></i>Beri Feedback
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($cp === 'history.php' && $dir === 'feedback') ? 'active' : '' ?>"
               href="<?= BASE_URL ?>/user/feedback/history.php">
                <i class="bi bi-clock-history me-2"></i>Riwayat Feedback
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
