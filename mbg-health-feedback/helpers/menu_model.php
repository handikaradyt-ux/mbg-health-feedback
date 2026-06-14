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

/**
 * Ambil semua menu dengan filter, search, dan pagination
 */
function getAllMenus(
    PDO $pdo,
    string $search   = '',
    string $status   = '',
    string $date     = '',
    int    $page     = 1,
    int    $perPage  = 10
): array {
    $conditions = ['1=1'];
    $params     = [];

    if ($search !== '') {
        $conditions[] = 'menu_name LIKE :search';
        $params[':search'] = '%' . $search . '%';
    }
    if ($status !== '') {
        $conditions[] = 'is_active = :status';
        $params[':status'] = (int) $status;
    }
    if ($date !== '') {
        $conditions[] = 'menu_date = :date';
        $params[':date'] = $date;
    }

    $where  = implode(' AND ', $conditions);
    $offset = ($page - 1) * $perPage;

    $stmt = $pdo->prepare("
        SELECT * FROM menus
        WHERE {$where}
        ORDER BY menu_date DESC
        LIMIT :limit OFFSET :offset
    ");
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Hitung total menu (untuk pagination)
 */
function countAllMenus(
    PDO    $pdo,
    string $search = '',
    string $status = '',
    string $date   = ''
): int {
    $conditions = ['1=1'];
    $params     = [];

    if ($search !== '') {
        $conditions[] = 'menu_name LIKE :search';
        $params[':search'] = '%' . $search . '%';
    }
    if ($status !== '') {
        $conditions[] = 'is_active = :status';
        $params[':status'] = (int) $status;
    }
    if ($date !== '') {
        $conditions[] = 'menu_date = :date';
        $params[':date'] = $date;
    }

    $where = implode(' AND ', $conditions);
    $stmt  = $pdo->prepare("SELECT COUNT(*) FROM menus WHERE {$where}");
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();
    return (int) $stmt->fetchColumn();
}

/**
 * Buat menu baru
 */
function createMenu(PDO $pdo, array $data): bool {
    $stmt = $pdo->prepare("
        INSERT INTO menus (menu_name, nutrition_desc, menu_date, is_active, created_by, created_at)
        VALUES (:menu_name, :nutrition_desc, :menu_date, 1, :created_by, NOW())
    ");
    return $stmt->execute([
        ':menu_name'     => $data['menu_name'],
        ':nutrition_desc'=> $data['nutrition_desc'],
        ':menu_date'     => $data['menu_date'],
        ':created_by'    => $data['created_by'],
    ]);
}

/**
 * Update menu
 */
function updateMenu(PDO $pdo, int $menuId, array $data): bool {
    $stmt = $pdo->prepare("
        UPDATE menus
        SET menu_name     = :menu_name,
            nutrition_desc= :nutrition_desc,
            menu_date     = :menu_date,
            is_active     = :is_active,
            updated_at    = NOW()
        WHERE menu_id = :id
    ");
    return $stmt->execute([
        ':menu_name'     => $data['menu_name'],
        ':nutrition_desc'=> $data['nutrition_desc'],
        ':menu_date'     => $data['menu_date'],
        ':is_active'     => (int) $data['is_active'],
        ':id'            => $menuId,
    ]);
}

/**
 * Toggle status aktif/nonaktif
 */
function toggleMenuStatus(PDO $pdo, int $menuId): bool {
    $stmt = $pdo->prepare("
        UPDATE menus
        SET is_active  = IF(is_active = 1, 0, 1),
            updated_at = NOW()
        WHERE menu_id = :id
    ");
    return $stmt->execute([':id' => $menuId]);
}

/**
 * Hitung rata-rata rating dari feedbacks (approved)
 */
function getAverageRating(PDO $pdo, int $menuId): float {
    $stmt = $pdo->prepare("
        SELECT AVG(rating) as avg_rating
        FROM feedbacks
        WHERE menu_id = :menu_id
          AND validation_status = 'approved'
    ");
    $stmt->execute([':menu_id' => $menuId]);
    $result = $stmt->fetchColumn();
    return $result ? round((float) $result, 1) : 0.0;
}

/**
 * Hitung jumlah feedback (approved)
 */
function getFeedbackCount(PDO $pdo, int $menuId): int {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM feedbacks
        WHERE menu_id = :menu_id
          AND validation_status = 'approved'
    ");
    $stmt->execute([':menu_id' => $menuId]);
    return (int) $stmt->fetchColumn();
}
