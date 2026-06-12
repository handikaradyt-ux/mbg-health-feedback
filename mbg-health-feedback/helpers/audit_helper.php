<?php
/**
 * helpers/audit_helper.php
 * Fungsi pencatatan audit log ke tabel audit_logs
 */

require_once dirname(__DIR__) . '/config/database.php';

/**
 * Catat aksi ke audit log
 *
 * @param int|null $user_id      ID pengguna yang melakukan aksi (null jika belum login)
 * @param string   $action       Nama aksi (LOGIN, LOGOUT, CREATE, UPDATE, DELETE, dll.)
 * @param string   $table_name   Nama tabel yang terpengaruh
 * @param int|null $record_id    ID record yang terpengaruh
 * @param string   $description  Deskripsi aksi
 */
function logAudit(?int $user_id, string $action, string $table_name, ?int $record_id = null, string $description = ''): void {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO audit_logs (user_id, action, table_name, record_id, description, ip_address, created_at)
            VALUES (:user_id, :action, :table_name, :record_id, :description, :ip_address, NOW())
        ");
        $stmt->execute([
            ':user_id'     => $user_id,
            ':action'      => strtoupper(substr($action, 0, 50)),
            ':table_name'  => substr($table_name, 0, 100),
            ':record_id'   => $record_id,
            ':description' => substr($description, 0, 255),
            ':ip_address'  => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);
    } catch (PDOException $e) {
        // Audit log gagal tidak boleh crash aplikasi — silent fail
        error_log('Audit log error: ' . $e->getMessage());
    }
}