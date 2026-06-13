<?php
/**
 * helpers/audit_helper.php
 * Fungsi pencatatan audit log ke tabel audit_logs
 *
 * PERBAIKAN dari versi sebelumnya:
 *   1. getDB()       → getDBConnection()   (sesuai nama di config/database.php)
 *   2. `action`      → `action_type`       (sesuai schema db_mbg)
 *   3. `table_name`  → `target_table`      (sesuai schema db_mbg)
 *   4. `record_id`   → `target_id`         (sesuai schema db_mbg)
 *   5. Tambah kolom  `user_agent`          (ada di schema, sebelumnya tidak diisi)
 */

require_once dirname(__DIR__) . '/config/database.php';

/**
 * Catat aksi ke tabel audit_logs.
 *
 * @param int|null $user_id      ID pengguna (null jika belum login)
 * @param string   $action_type  Kode aksi: LOGIN, LOGOUT, LOGIN_FAILED, CREATE, UPDATE, DELETE …
 * @param string   $target_table Nama tabel yang terpengaruh
 * @param int|null $target_id    ID record yang terpengaruh (null jika tidak relevan)
 * @param string   $description  Deskripsi singkat aksi
 */
function logAudit(
    ?int   $user_id,
    string $action_type,
    string $target_table,
    ?int   $target_id   = null,
    string $description = ''
): void {
    try {
        $pdo  = getDBConnection(); // ← PERBAIKAN: getDB() → getDBConnection()
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs
                (user_id, action_type, target_table, target_id,
                 description, ip_address, user_agent, created_at)
            VALUES
                (:user_id, :action_type, :target_table, :target_id,
                 :description, :ip_address, :user_agent, NOW())
        ");
        $stmt->execute([
            ':user_id'      => $user_id,
            ':action_type'  => strtoupper(substr($action_type,  0, 100)),
            ':target_table' => substr($target_table, 0, 50),
            ':target_id'    => $target_id,
            ':description'  => substr($description,  0, 500),
            ':ip_address'   => $_SERVER['REMOTE_ADDR']     ?? 'unknown',
            ':user_agent'   => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);

    } catch (PDOException $e) {
        // Audit log gagal tidak boleh crash aplikasi — silent fail
        error_log('Audit log error: ' . $e->getMessage());
    }
}
