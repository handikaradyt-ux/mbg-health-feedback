<?php
/**
 * helpers/menu_model.php
 * Fungsi query tabel menus
 */

/**
 * Ambil seluruh menu MBG yang aktif
 */
function getActiveMenus(PDO $pdo): array {
    $stmt = $pdo->prepare("
        SELECT * FROM menus
        WHERE is_active = 1
        ORDER BY menu_date DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Ambil satu menu berdasarkan ID
 */
function getMenuById(PDO $pdo, int $menuId): ?array {
    $stmt = $pdo->prepare("SELECT * FROM menus WHERE menu_id = :id");
    $stmt->execute([':id' => $menuId]);
    $result = $stmt->fetch();
    return $result ?: null;
}