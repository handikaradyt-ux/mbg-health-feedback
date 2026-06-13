<?php
/**
 * includes/header.php
 * Template: HTML head + navbar + pembuka layout wrapper
 *
 * Variabel yang diharapkan sebelum di-include:
 *   $pageTitle (string) — judul tab browser (opsional)
 */

if (!function_exists('isLoggedIn')) {
    require_once dirname(__DIR__) . '/auth/session.php';
}
if (!function_exists('escapeOutput')) {
    require_once dirname(__DIR__) . '/helpers/validation_helper.php';
}

$pageTitle    = $pageTitle ?? APP_NAME;
$currentRole  = getCurrentRole();
$currentName  = getCurrentFullName();
$currentUname = $_SESSION['username'] ?? '';

$roleBadge = [
    ROLE_ADMIN   => ['label' => 'Admin',   'class' => 'bg-danger'],
    ROLE_PETUGAS => ['label' => 'Petugas', 'class' => 'bg-warning text-dark'],
    ROLE_USER    => ['label' => 'User',    'class' => 'bg-success'],
];
$badge = $roleBadge[$currentRole] ?? ['label' => ucfirst((string)$currentRole), 'class' => 'bg-secondary'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escapeOutput($pageTitle) ?> &mdash; <?= escapeOutput(APP_NAME) ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/custom.css" rel="stylesheet">
    <style>
        body          { display: flex; flex-direction: column; min-height: 100vh; }
        .page-wrapper { display: flex; flex: 1; }
        .sidebar      { width: 240px; min-width: 240px; min-height: 100%; }
        #main-content { flex: 1; padding: 1.5rem; background-color: #f8f9fa; overflow-y: auto; }

        /* Sidebar item style (berlaku untuk semua sidebar) */
        .sidebar .nav-link          { padding: .45rem .75rem; font-size: .9rem; color: rgba(255,255,255,.8); border-radius: .375rem; }
        .sidebar .nav-link.active   { background-color: #198754 !important; color: #fff !important; }
        .sidebar .nav-link:hover:not(.active) { background-color: rgba(255,255,255,.1); color: #fff; }
        .sidebar .nav-section-label { font-size: .7rem; color: rgba(255,255,255,.4); font-weight: 700;
                                      text-transform: uppercase; padding: .4rem .75rem; pointer-events: none; }
    </style>
</head>
<body>

<!-- ── Navbar ── -->
<nav class="navbar navbar-expand-lg navbar-dark bg-success shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>">
            <i class="bi bi-heart-pulse-fill me-1"></i><?= escapeOutput(APP_NAME) ?>
        </a>

        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#navbarTop">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarTop">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                <li class="nav-item">
                    <span class="navbar-text text-white-50 small me-2">
                        <i class="bi bi-person-circle me-1"></i>
                        <?= escapeOutput($currentName ?? $currentUname) ?>
                        <span class="badge <?= $badge['class'] ?> ms-1"><?= $badge['label'] ?></span>
                    </span>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="<?= BASE_URL ?>/auth/change_password.php">
                        <i class="bi bi-key me-1"></i>Ganti Password
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="<?= BASE_URL ?>/auth/logout.php"
                       onclick="return confirm('Yakin ingin logout?')">
                        <i class="bi bi-box-arrow-right me-1"></i>Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- ── Layout Wrapper: sidebar + main ── -->
<div class="page-wrapper">
