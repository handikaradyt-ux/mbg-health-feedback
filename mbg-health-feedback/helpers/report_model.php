<?php
/**
 * helpers/report_model.php
 * Fungsi query untuk pelaporan/statistik
 *
 * REVISI 1: Ditambahkan dua fungsi baru untuk Dashboard Admin:
 *   - getAdminHealthMonthlyData()
 *   - getAdminBmiDistribution()
 *
 * REVISI 2: Ditambahkan empat fungsi baru untuk Laporan Kesehatan Admin:
 *   - getHealthSummary()
 *   - getHealthBmiTrendByMonth()
 *   - getHealthTableData()
 *   - getHealthTableCount()
 */

/**
 * Data validasi per hari untuk N hari terakhir (dipakai Chart.js dashboard petugas).
 *
 * Mengembalikan array N elemen:
 *   [ 'label' => 'Sen 09/06', 'total' => 5, 'health' => 3, 'feedback' => 2 ]
 *
 * @param PDO $pdo
 * @param int $validatorId  ID petugas yang login
 * @param int $days         Jumlah hari ke belakang (default 7)
 * @return array
 */
function getValidationChartData(PDO $pdo, int $validatorId, int $days = 7): array
{
    $stmt = $pdo->prepare("
        SELECT DATE(validated_at) AS val_date,
               data_type,
               COUNT(*)           AS cnt
        FROM   validation_records
        WHERE  validator_id  = :vid
          AND  validated_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
        GROUP  BY val_date, data_type
        ORDER  BY val_date ASC
    ");
    $stmt->bindValue(':vid',  $validatorId, PDO::PARAM_INT);
    $stmt->bindValue(':days', $days - 1,    PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $map = [];
    foreach ($rows as $r) {
        $d = $r['val_date'];
        if (!isset($map[$d])) {
            $map[$d] = ['health' => 0, 'feedback' => 0];
        }
        $key = $r['data_type'] === 'health_record' ? 'health' : 'feedback';
        $map[$d][$key] = (int) $r['cnt'];
    }

    $locale_days = ['Sun'=>'Min','Mon'=>'Sen','Tue'=>'Sel','Wed'=>'Rab',
                    'Thu'=>'Kam','Fri'=>'Jum','Sat'=>'Sab'];
    $result = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $date  = date('Y-m-d', strtotime("-{$i} days"));
        $dow   = date('D', strtotime($date));
        $label = ($locale_days[$dow] ?? $dow) . ' ' . date('d/m', strtotime($date));
        $h = $map[$date]['health']   ?? 0;
        $f = $map[$date]['feedback'] ?? 0;
        $result[] = ['label' => $label, 'total' => $h + $f, 'health' => $h, 'feedback' => $f];
    }
    return $result;
}

// ─────────────────────────────────────────────────────────────────────────────
// REVISI 1 — Dashboard Admin
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Jumlah data kesehatan yang diinput per bulan selama N bulan terakhir.
 * Dipakai untuk Bar Chart "Data Kesehatan per Bulan" di dashboard Admin.
 *
 * Mengembalikan array N elemen:
 *   [ 'label' => 'Jan', 'month' => '2025-01', 'total' => 52 ]
 *
 * @param PDO $pdo
 * @param int $months  Jumlah bulan ke belakang (default 6)
 * @return array
 */
function getAdminHealthMonthlyData(PDO $pdo, int $months = 6): array
{
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(input_date, '%Y-%m') AS month,
               COUNT(*)                          AS total
        FROM   health_records
        WHERE  input_date >= DATE_SUB(CURDATE(), INTERVAL :m MONTH)
        GROUP  BY month
        ORDER  BY month ASC
    ");
    $stmt->bindValue(':m', $months, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $map = [];
    foreach ($rows as $r) {
        $map[$r['month']] = (int) $r['total'];
    }

    $bulan = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

    $result = [];
    for ($i = $months - 1; $i >= 0; $i--) {
        $ts    = strtotime("first day of -$i month");
        $key   = date('Y-m', $ts);
        $idx   = (int) date('n', $ts) - 1;
        $label = $bulan[$idx] . ' ' . date('Y', $ts);
        $result[] = [
            'label' => $label,
            'month' => $key,
            'total' => $map[$key] ?? 0,
        ];
    }
    return $result;
}

/**
 * Distribusi kategori BMI dari seluruh data health_records.
 * Dipakai untuk Donut Chart "Distribusi Kategori BMI" di dashboard Admin.
 *
 * @param PDO $pdo
 * @return array
 */
function getAdminBmiDistribution(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT bmi_category AS label,
               COUNT(*)     AS total
        FROM   health_records
        GROUP  BY bmi_category
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $order = ['Underweight', 'Normal', 'Overweight', 'Obese'];

    $map = [];
    foreach ($rows as $r) {
        $map[$r['label']] = (int) $r['total'];
    }

    $result = [];
    foreach ($order as $cat) {
        $result[] = [
            'label' => $cat,
            'total' => $map[$cat] ?? 0,
        ];
    }

    foreach ($map as $label => $total) {
        if (!in_array($label, $order, true)) {
            $result[] = ['label' => $label, 'total' => $total];
        }
    }

    return $result;
}

// ─────────────────────────────────────────────────────────────────────────────
// REVISI 2 — Admin → Laporan Kesehatan (health.php)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Bangun klausa WHERE dinamis untuk filter Laporan Kesehatan.
 * Dipakai secara internal oleh fungsi-fungsi di bawah.
 *
 * @param string $dateStart   Format Y-m-d
 * @param string $dateEnd     Format Y-m-d
 * @param string $bmiCat      'semua' | 'Underweight' | 'Normal' | 'Overweight' | 'Obese'
 * @return array [ 'where' => string, 'params' => array ]
 */
function _healthFilterClause(string $dateStart, string $dateEnd, string $bmiCat): array
{
    $where  = "WHERE h.validation_status = 'approved'
               AND   h.input_date BETWEEN :date_start AND :date_end";
    $params = [
        ':date_start' => $dateStart,
        ':date_end'   => $dateEnd,
    ];

    if ($bmiCat !== 'semua') {
        $where .= " AND h.bmi_category = :bmi_cat";
        $params[':bmi_cat'] = $bmiCat;
    }

    return ['where' => $where, 'params' => $params];
}

/**
 * Kartu ringkasan Laporan Kesehatan.
 *
 * Mengembalikan:
 *   [
 *     'total'       => int,    // jumlah data approved dalam filter
 *     'avg_bmi'     => float,  // rata-rata BMI
 *     'pct_normal'  => float,  // persentase kategori Normal (0–100)
 *     'pct_attention'=> float, // persentase (Overweight + Obese) (0–100)
 *   ]
 *
 * @param PDO    $pdo
 * @param string $dateStart
 * @param string $dateEnd
 * @param string $bmiCat
 * @return array
 */
function getHealthSummary(PDO $pdo, string $dateStart, string $dateEnd, string $bmiCat): array
{
    $f = _healthFilterClause($dateStart, $dateEnd, $bmiCat);

    $sql = "
        SELECT
            COUNT(*)                                          AS total,
            COALESCE(AVG(h.bmi_value), 0)                   AS avg_bmi,
            COALESCE(
                SUM(CASE WHEN h.bmi_category = 'Normal' THEN 1 ELSE 0 END)
                / NULLIF(COUNT(*), 0) * 100, 0)             AS pct_normal,
            COALESCE(
                SUM(CASE WHEN h.bmi_category IN ('Overweight','Obese','Underweight')
                         THEN 1 ELSE 0 END)
                / NULLIF(COUNT(*), 0) * 100, 0)             AS pct_attention
        FROM health_records h
        {$f['where']}
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($f['params'] as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return [
        'total'        => (int)   ($row['total']        ?? 0),
        'avg_bmi'      => (float) ($row['avg_bmi']      ?? 0),
        'pct_normal'   => (float) ($row['pct_normal']   ?? 0),
        'pct_attention'=> (float) ($row['pct_attention']?? 0),
    ];
}

/**
 * Data tren rata-rata BMI per bulan dalam rentang filter.
 * Digunakan untuk grafik line di Laporan Kesehatan.
 *
 * Mengembalikan array elemen:
 *   [ 'label' => 'Jan 2025', 'month' => '2025-01', 'avg_bmi' => 22.4 ]
 *
 * @param PDO    $pdo
 * @param string $dateStart
 * @param string $dateEnd
 * @param string $bmiCat
 * @return array
 */
function getHealthBmiTrendByMonth(PDO $pdo, string $dateStart, string $dateEnd, string $bmiCat): array
{
    $f = _healthFilterClause($dateStart, $dateEnd, $bmiCat);

    $sql = "
        SELECT
            DATE_FORMAT(h.input_date, '%Y-%m') AS month,
            AVG(h.bmi_value)                   AS avg_bmi
        FROM health_records h
        {$f['where']}
        GROUP BY month
        ORDER BY month ASC
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($f['params'] as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format label bulan ke Bahasa Indonesia
    $bulan = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    $result = [];
    foreach ($rows as $r) {
        [$year, $mon] = explode('-', $r['month']);
        $label = $bulan[(int)$mon - 1] . ' ' . $year;
        $result[] = [
            'label'   => $label,
            'month'   => $r['month'],
            'avg_bmi' => round((float)$r['avg_bmi'], 2),
        ];
    }
    return $result;
}

/**
 * Data tabel Laporan Kesehatan (dengan JOIN ke users) — terpaginasi.
 *
 * Mengembalikan array row:
 *   [ 'full_name', 'input_date', 'height_cm', 'weight_kg', 'bmi_value', 'bmi_category' ]
 *
 * @param PDO    $pdo
 * @param string $dateStart
 * @param string $dateEnd
 * @param string $bmiCat
 * @param string $sortBy      'terbaru' | 'terlama' | 'bmi_tinggi' | 'bmi_rendah'
 * @param int    $page        Halaman saat ini (1-based)
 * @param int    $perPage     Jumlah baris per halaman
 * @return array
 */
function getHealthTableData(
    PDO    $pdo,
    string $dateStart,
    string $dateEnd,
    string $bmiCat,
    string $sortBy  = 'terbaru',
    int    $page    = 1,
    int    $perPage = 10
): array {
    $f = _healthFilterClause($dateStart, $dateEnd, $bmiCat);

    $orderMap = [
        'terbaru'   => 'h.input_date DESC, h.health_id DESC',
        'terlama'   => 'h.input_date ASC,  h.health_id ASC',
        'bmi_tinggi'=> 'h.bmi_value DESC',
        'bmi_rendah'=> 'h.bmi_value ASC',
    ];
    $orderClause = $orderMap[$sortBy] ?? $orderMap['terbaru'];

    $offset = ($page - 1) * $perPage;

    $sql = "
        SELECT
            u.full_name,
            h.input_date,
            h.height_cm,
            h.weight_kg,
            h.bmi_value,
            h.bmi_category
        FROM health_records h
        JOIN users u ON u.user_id = h.user_id
        {$f['where']}
        ORDER BY {$orderClause}
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($f['params'] as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Jumlah total baris tabel Laporan Kesehatan (untuk hitung total halaman).
 *
 * @param PDO    $pdo
 * @param string $dateStart
 * @param string $dateEnd
 * @param string $bmiCat
 * @return int
 */
function getHealthTableCount(PDO $pdo, string $dateStart, string $dateEnd, string $bmiCat): int
{
    $f = _healthFilterClause($dateStart, $dateEnd, $bmiCat);

    $sql = "
        SELECT COUNT(*) AS cnt
        FROM health_records h
        {$f['where']}
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($f['params'] as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int)($row['cnt'] ?? 0);
}