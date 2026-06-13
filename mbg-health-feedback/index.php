<?php
/**
 * Entry point: redirect ke login atau dashboard
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/auth/session.php';

startSecureSession();

if (isLoggedIn()) {
    redirectToDashboard();
} else {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}
