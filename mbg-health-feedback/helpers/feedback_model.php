<?php
/**
 * helpers/feedback_model.php
 * Fungsi query tabel feedbacks
 */

/**
 * Cek apakah user sudah memberi feedback untuk menu tertentu
 */
function hasUserFeedbackForMenu(PDO $pdo, int $userId, int $menuId): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM feedbacks
        WHERE user_id = :uid AND menu_id = :mid
    ");
    $stmt->execute([':uid' => $userId, ':mid' => $menuId]);
    return (int) $stmt->fetchColumn() > 0;
}

/**
 * Simpan feedback baru
 */
function insertFeedback(PDO $pdo, int $userId, int $menuId, int $rating, ?string $comment): bool {
    $stmt = $pdo->prepare("
        INSERT INTO feedbacks (user_id, menu_id, rating, comment, validation_status, feedback_date)
        VALUES (:uid, :mid, :rating, :comment, 'pending', CURDATE())
    ");
    return $stmt->execute([
        ':uid'     => $userId,
        ':mid'     => $menuId,
        ':rating'  => $rating,
        ':comment' => $comment,
    ]);
}

/**
 * Ambil seluruh riwayat feedback milik user (join dengan menus)
 */
function getFeedbackHistoryByUser(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("
        SELECT f.*, m.menu_name, m.menu_date
        FROM feedbacks f
        JOIN menus m ON m.menu_id = f.menu_id
        WHERE f.user_id = :uid
        ORDER BY f.feedback_date DESC, f.created_at DESC
    ");
    $stmt->execute([':uid' => $userId]);
    return $stmt->fetchAll();
}

/**
 * Ambil seluruh feedback berstatus pending, join dengan users & menus.
 * Dipakai oleh petugas/validate_feedback.php dan dashboard preview.
 *
 * @param PDO      $pdo
 * @param int|null $limit  null = ambil semua; integer = batasi jumlah baris
 * @return array
 */
function getPendingFeedbacks(PDO $pdo, ?int $limit = null): array
{
    $sql = "
        SELECT f.feedback_id, f.rating, f.comment, f.feedback_date,
               u.user_id, u.full_name, u.username,
               m.menu_id, m.menu_name, m.menu_date
        FROM   feedbacks f
        JOIN   users u ON u.user_id = f.user_id
        JOIN   menus  m ON m.menu_id = f.menu_id
        WHERE  f.validation_status = 'pending'
        ORDER  BY f.created_at ASC
    ";
    if ($limit !== null) {
        $sql .= " LIMIT " . (int) $limit;
    }
    return $pdo->query($sql)->fetchAll();
}
