<?php
/**
 * helpers/health_model.php
 * Fungsi query tabel health_records
 */

/**
 * Ambil seluruh riwayat BMI milik user, opsional filter tanggal
 */
function getHealthHistoryByUser(PDO $pdo, int $userId, ?string $startDate = null, ?string $endDate = null): array {
    $sql = "SELECT * FROM health_records WHERE user_id = :uid";
    $params = [':uid' => $userId];

    if (!empty($startDate)) {
        $sql .= " AND input_date >= :start_date";
        $params[':start_date'] = $startDate;
    }
    if (!empty($endDate)) {
        $sql .= " AND input_date <= :end_date";
        $params[':end_date'] = $endDate;
    }

    $sql .= " ORDER BY input_date ASC, created_at ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Ambil data untuk grafik perkembangan BMI (tanggal & nilai BMI)
 */
function getBmiChartData(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("
        SELECT input_date, bmi_value
        FROM health_records
        WHERE user_id = :uid
        ORDER BY input_date ASC, created_at ASC
    ");
    $stmt->execute([':uid' => $userId]);
    return $stmt->fetchAll();
}

/**
 * Simpan data kesehatan baru ke health_records
 */
function insertHealthRecord(
    PDO $pdo,
    int $userId,
    float $heightCm,
    float $weightKg,
    float $bmiValue,
    string $bmiCategory,
    string $inputDate,
    ?string $notes = null
): bool {
    $stmt = $pdo->prepare("
        INSERT INTO health_records
            (user_id, height_cm, weight_kg, bmi_value, bmi_category, validation_status, input_date, notes)
        VALUES
            (:uid, :height, :weight, :bmi, :category, 'pending', :input_date, :notes)
    ");
    return $stmt->execute([
        ':uid'        => $userId,
        ':height'     => $heightCm,
        ':weight'     => $weightKg,
        ':bmi'        => $bmiValue,
        ':category'   => $bmiCategory,
        ':input_date' => $inputDate,
        ':notes'      => $notes,
    ]);
}

/**
 * Ambil seluruh data kesehatan berstatus pending, join dengan users.
 * Dipakai oleh petugas/validate_health.php dan dashboard preview.
 *
 * @param PDO      $pdo
 * @param int|null $limit  null = ambil semua; integer = batasi jumlah baris
 * @return array
 */
function getPendingHealthRecords(PDO $pdo, ?int $limit = null): array
{
    $sql = "
        SELECT hr.health_id, hr.height_cm, hr.weight_kg, hr.bmi_value,
               hr.bmi_category, hr.input_date, hr.notes,
               u.user_id, u.full_name, u.username
        FROM   health_records hr
        JOIN   users u ON u.user_id = hr.user_id
        WHERE  hr.validation_status = 'pending'
        ORDER  BY hr.created_at ASC
    ";
    if ($limit !== null) {
        $sql .= " LIMIT " . (int) $limit;
    }
    return $pdo->query($sql)->fetchAll();
}
