<?php
/**
 * helpers/user_model.php
 * Fungsi-fungsi query untuk tabel `users`
 */

require_once dirname(__DIR__) . '/config/database.php';

/**
 * Ambil user berdasarkan username.
 *
 * @return array|false
 */
function getUserByUsername(string $username): array|false
{
    try {
        $pdo  = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT user_id, username, password_hash, full_name, role, email, is_active
            FROM   users
            WHERE  username = :username
            LIMIT  1
        ");
        $stmt->execute([':username' => $username]);
        return $stmt->fetch() ?: false;
    } catch (PDOException $e) {
        error_log('getUserByUsername error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Ambil user berdasarkan email.
 * Dipakai register.php untuk cek duplikasi email.
 *
 * @return array|false
 */
function getUserByEmail(string $email): array|false
{
    try {
        $pdo  = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT user_id, username, email, role, is_active
            FROM   users
            WHERE  email = :email
            LIMIT  1
        ");
        $stmt->execute([':email' => $email]);
        return $stmt->fetch() ?: false;
    } catch (PDOException $e) {
        error_log('getUserByEmail error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Ambil user berdasarkan user_id.
 *
 * @return array|false
 */
function getUserById(int $userId): array|false
{
    try {
        $pdo  = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT user_id, username, password_hash, full_name, role,
                   email, is_active, created_at, updated_at
            FROM   users
            WHERE  user_id = :user_id
            LIMIT  1
        ");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetch() ?: false;
    } catch (PDOException $e) {
        error_log('getUserById error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Buat user baru.
 *
 * URUTAN PARAMETER disesuaikan dengan register.php:
 *   createUser($fullName, $username, $passwordHash, $role, $email)
 *
 * @return int|false  user_id yang baru dibuat, atau false jika gagal
 */
function createUser(
    string  $fullName,
    string  $username,
    string  $passwordHash,
    string  $role      = ROLE_USER,
    ?string $email     = null
): int|false {
    try {
        $pdo  = getDBConnection();
        $stmt = $pdo->prepare("
            INSERT INTO users (full_name, username, password_hash, role, email, is_active)
            VALUES (:full_name, :username, :password_hash, :role, :email, 1)
        ");
        $stmt->execute([
            ':full_name'     => $fullName,
            ':username'      => $username,
            ':password_hash' => $passwordHash,
            ':role'          => $role,
            ':email'         => $email,
        ]);
        return (int) $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log('createUser error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Ambil semua user (dengan paginasi & pencarian).
 */
function getAllUsers(int $limit = 10, int $offset = 0, string $search = ''): array
{
    try {
        $pdo = getDBConnection();
        if ($search !== '') {
            $stmt = $pdo->prepare("
                SELECT user_id, username, full_name, role, email, is_active, created_at
                FROM   users
                WHERE  username LIKE :search OR full_name LIKE :search
                ORDER  BY created_at DESC
                LIMIT  :lim OFFSET :off
            ");
            $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
        } else {
            $stmt = $pdo->prepare("
                SELECT user_id, username, full_name, role, email, is_active, created_at
                FROM   users
                ORDER  BY created_at DESC
                LIMIT  :lim OFFSET :off
            ");
        }
        $stmt->bindValue(':lim', $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('getAllUsers error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Hitung total user (untuk paginasi).
 */
function countUsers(string $search = ''): int
{
    try {
        $pdo = getDBConnection();
        if ($search !== '') {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM users
                WHERE  username LIKE :search OR full_name LIKE :search
            ");
            $stmt->execute([':search' => '%' . $search . '%']);
        } else {
            $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        }
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log('countUsers error: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Update data user (tanpa password).
 */
function updateUser(int $userId, string $fullName, string $role, ?string $email): bool
{
    try {
        $pdo  = getDBConnection();
        $stmt = $pdo->prepare("
            UPDATE users
            SET    full_name  = :full_name,
                   role       = :role,
                   email      = :email,
                   updated_at = NOW()
            WHERE  user_id = :user_id
        ");
        return $stmt->execute([
            ':full_name' => $fullName,
            ':role'      => $role,
            ':email'     => $email,
            ':user_id'   => $userId,
        ]);
    } catch (PDOException $e) {
        error_log('updateUser error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Update password_hash user.
 */
function updateUserPassword(int $userId, string $newHash): bool
{
    try {
        $pdo  = getDBConnection();
        $stmt = $pdo->prepare("
            UPDATE users
            SET    password_hash = :hash,
                   updated_at    = NOW()
            WHERE  user_id = :user_id
        ");
        return $stmt->execute([':hash' => $newHash, ':user_id' => $userId]);
    } catch (PDOException $e) {
        error_log('updateUserPassword error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Toggle status aktif/nonaktif user.
 */
function setUserActiveStatus(int $userId, bool $isActive): bool
{
    try {
        $pdo  = getDBConnection();
        $stmt = $pdo->prepare("
            UPDATE users
            SET    is_active  = :is_active,
                   updated_at = NOW()
            WHERE  user_id = :user_id
        ");
        return $stmt->execute([
            ':is_active' => (int) $isActive,
            ':user_id'   => $userId,
        ]);
    } catch (PDOException $e) {
        error_log('setUserActiveStatus error: ' . $e->getMessage());
        return false;
    }
}