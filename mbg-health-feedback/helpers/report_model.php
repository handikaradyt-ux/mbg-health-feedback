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

/**
 * helpers/report_model.php — TAMBAHAN REVISI 3
 * Fungsi-fungsi baru untuk Laporan Kepuasan Menu (admin/reports/feedback.php)
 *
 * INSTRUKSI: Tempelkan blok kode ini di bagian BAWAH file report_model.php
 * yang sudah ada, setelah fungsi getHealthTableCount().
 * Jangan hapus atau ubah fungsi yang sudah ada sebelumnya.
 */
 
// ─────────────────────────────────────────────────────────────────────────────
// REVISI 3 — Admin → Laporan Kepuasan Menu (feedback.php)
// ─────────────────────────────────────────────────────────────────────────────
 
/**
 * Bangun klausa WHERE dinamis untuk filter Laporan Feedback.
 * Dipakai secara internal oleh fungsi-fungsi laporan feedback.
 *
 * @param string   $dateStart  Format Y-m-d
 * @param string   $dateEnd    Format Y-m-d
 * @param int|null $menuId     null = semua menu
 * @param int|null $minRating  null = semua rating (1–5)
 * @return array [ 'where' => string, 'params' => array ]
 */
function _feedbackFilterClause(
    string $dateStart,
    string $dateEnd,
    ?int   $menuId    = null,
    ?int   $minRating = null
): array {
    $where  = "WHERE f.validation_status = 'approved'
               AND   f.feedback_date BETWEEN :date_start AND :date_end";
    $params = [
        ':date_start' => $dateStart,
        ':date_end'   => $dateEnd,
    ];
 
    if ($menuId !== null) {
        $where .= " AND f.menu_id = :menu_id";
        $params[':menu_id'] = $menuId;
    }
 
    if ($minRating !== null) {
        $where .= " AND f.rating >= :min_rating";
        $params[':min_rating'] = $minRating;
    }
 
    return ['where' => $where, 'params' => $params];
}
 
/**
 * Kartu ringkasan Laporan Feedback.
 *
 * Mengembalikan:
 *   [
 *     'total_approved' => int,   // jumlah feedback approved dalam filter
 *     'avg_rating'     => float, // rata-rata rating global
 *   ]
 *
 * @param PDO      $pdo
 * @param string   $dateStart
 * @param string   $dateEnd
 * @param int|null $menuId
 * @param int|null $minRating
 * @return array
 */
function getFeedbackSummary(
    PDO    $pdo,
    string $dateStart,
    string $dateEnd,
    ?int   $menuId    = null,
    ?int   $minRating = null
): array {
    $f = _feedbackFilterClause($dateStart, $dateEnd, $menuId, $minRating);
 
    $sql = "
        SELECT
            COUNT(*)                AS total_approved,
            COALESCE(AVG(f.rating), 0) AS avg_rating
        FROM feedbacks f
        {$f['where']}
    ";
 
    $stmt = $pdo->prepare($sql);
    foreach ($f['params'] as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
 
    return [
        'total_approved' => (int)   ($row['total_approved'] ?? 0),
        'avg_rating'     => (float) ($row['avg_rating']     ?? 0),
    ];
}
 
/**
 * Menu terbaik atau terburuk berdasarkan rata-rata rating.
 *
 * @param PDO    $pdo
 * @param string $dateStart
 * @param string $dateEnd
 * @param string $type  'best' | 'worst'
 * @return array|null   [ 'menu_id', 'menu_name', 'avg_rating' ] atau null jika kosong
 */
function getFeedbackBestWorstMenu(
    PDO    $pdo,
    string $dateStart,
    string $dateEnd,
    string $type = 'best'
): ?array {
    $order = $type === 'best' ? 'DESC' : 'ASC';
 
    $stmt = $pdo->prepare("
        SELECT m.menu_id, m.menu_name, AVG(f.rating) AS avg_rating
        FROM   feedbacks f
        JOIN   menus m ON m.menu_id = f.menu_id
        WHERE  f.validation_status = 'approved'
          AND  f.feedback_date BETWEEN :date_start AND :date_end
        GROUP  BY m.menu_id, m.menu_name
        ORDER  BY avg_rating {$order}
        LIMIT  1
    ");
    $stmt->execute([':date_start' => $dateStart, ':date_end' => $dateEnd]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
 
    return $row ?: null;
}
 
/**
 * Rata-rata rating per menu dalam satu periode (untuk grafik batang perbandingan).
 *
 * Mengembalikan array:
 *   [ ['menu_id' => int, 'menu_name' => string, 'avg_rating' => float], … ]
 *
 * @param PDO    $pdo
 * @param string $dateStart
 * @param string $dateEnd
 * @return array
 */
function getFeedbackRatingByMenu(PDO $pdo, string $dateStart, string $dateEnd): array
{
    $stmt = $pdo->prepare("
        SELECT m.menu_id, m.menu_name, AVG(f.rating) AS avg_rating
        FROM   feedbacks f
        JOIN   menus m ON m.menu_id = f.menu_id
        WHERE  f.validation_status = 'approved'
          AND  f.feedback_date BETWEEN :date_start AND :date_end
        GROUP  BY m.menu_id, m.menu_name
        ORDER  BY avg_rating DESC
    ");
    $stmt->execute([':date_start' => $dateStart, ':date_end' => $dateEnd]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
 
/**
 * Distribusi rating (1–5 bintang) per menu dalam rentang filter.
 * Digunakan untuk panel progress bar "Distribusi Rating per Menu".
 *
 * Mengembalikan array per menu:
 *   [
 *     'menu_id'   => int,
 *     'menu_name' => string,
 *     'dist'      => [1 => int, 2 => int, 3 => int, 4 => int, 5 => int],
 *   ]
 *
 * @param PDO      $pdo
 * @param string   $dateStart
 * @param string   $dateEnd
 * @param int|null $menuId
 * @param int|null $minRating
 * @return array
 */
function getFeedbackRatingDistribution(
    PDO    $pdo,
    string $dateStart,
    string $dateEnd,
    ?int   $menuId    = null,
    ?int   $minRating = null
): array {
    $f = _feedbackFilterClause($dateStart, $dateEnd, $menuId, $minRating);
 
    $sql = "
        SELECT
            m.menu_id,
            m.menu_name,
            SUM(CASE WHEN f.rating = 1 THEN 1 ELSE 0 END) AS star_1,
            SUM(CASE WHEN f.rating = 2 THEN 1 ELSE 0 END) AS star_2,
            SUM(CASE WHEN f.rating = 3 THEN 1 ELSE 0 END) AS star_3,
            SUM(CASE WHEN f.rating = 4 THEN 1 ELSE 0 END) AS star_4,
            SUM(CASE WHEN f.rating = 5 THEN 1 ELSE 0 END) AS star_5
        FROM feedbacks f
        JOIN menus m ON m.menu_id = f.menu_id
        {$f['where']}
        GROUP BY m.menu_id, m.menu_name
        ORDER BY SUM(f.rating) / COUNT(f.rating) DESC
    ";
 
    $stmt = $pdo->prepare($sql);
    foreach ($f['params'] as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
 
    $result = [];
    foreach ($rows as $r) {
        $result[] = [
            'menu_id'   => (int) $r['menu_id'],
            'menu_name' => $r['menu_name'],
            'dist'      => [
                1 => (int) $r['star_1'],
                2 => (int) $r['star_2'],
                3 => (int) $r['star_3'],
                4 => (int) $r['star_4'],
                5 => (int) $r['star_5'],
            ],
        ];
    }
    return $result;
}
 
/**
 * Tabel peringkat menu — dengan tren bulan ini vs bulan lalu (terpaginasi).
 *
 * Setiap baris mengembalikan:
 *   menu_id, menu_name, total_feedback, avg_rating,
 *   star_1 … star_5,
 *   prev_avg_rating (rata-rata bulan sebelumnya, null jika tidak ada data)
 *
 * @param PDO      $pdo
 * @param string   $dateStart    Awal bulan aktif (Y-m-d)
 * @param string   $dateEnd      Akhir bulan aktif (Y-m-d)
 * @param int|null $menuId
 * @param int|null $minRating
 * @param int      $page
 * @param int      $perPage
 * @return array
 */
function getFeedbackRanking(
    PDO    $pdo,
    string $dateStart,
    string $dateEnd,
    ?int   $menuId    = null,
    ?int   $minRating = null,
    int    $page      = 1,
    int    $perPage   = 10
): array {
    $f      = _feedbackFilterClause($dateStart, $dateEnd, $menuId, $minRating);
    $offset = ($page - 1) * $perPage;
 
    // Hitung tanggal awal & akhir bulan sebelumnya secara otomatis
    $prevStart = date('Y-m-01', strtotime('-1 month', strtotime($dateStart)));
    $prevEnd   = date('Y-m-t',  strtotime($prevStart));
 
    $sql = "
        SELECT
            m.menu_id,
            m.menu_name,
            COUNT(f.feedback_id)                           AS total_feedback,
            COALESCE(AVG(f.rating), 0)                    AS avg_rating,
            SUM(CASE WHEN f.rating = 1 THEN 1 ELSE 0 END) AS star_1,
            SUM(CASE WHEN f.rating = 2 THEN 1 ELSE 0 END) AS star_2,
            SUM(CASE WHEN f.rating = 3 THEN 1 ELSE 0 END) AS star_3,
            SUM(CASE WHEN f.rating = 4 THEN 1 ELSE 0 END) AS star_4,
            SUM(CASE WHEN f.rating = 5 THEN 1 ELSE 0 END) AS star_5,
            (
                SELECT AVG(fp.rating)
                FROM   feedbacks fp
                WHERE  fp.menu_id          = m.menu_id
                  AND  fp.validation_status = 'approved'
                  AND  fp.feedback_date BETWEEN :prev_start AND :prev_end
            ) AS prev_avg_rating
        FROM feedbacks f
        JOIN menus m ON m.menu_id = f.menu_id
        {$f['where']}
        GROUP  BY m.menu_id, m.menu_name
        ORDER  BY avg_rating DESC
        LIMIT  :lim OFFSET :off
    ";
 
    $stmt = $pdo->prepare($sql);
    foreach ($f['params'] as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':prev_start', $prevStart);
    $stmt->bindValue(':prev_end',   $prevEnd);
    $stmt->bindValue(':lim',  $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':off',  $offset,  PDO::PARAM_INT);
    $stmt->execute();
 
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
 
/**
 * Jumlah total menu pada tabel peringkat (untuk hitung total halaman pagination).
 *
 * @param PDO      $pdo
 * @param string   $dateStart
 * @param string   $dateEnd
 * @param int|null $menuId
 * @param int|null $minRating
 * @return int
 */
function getFeedbackRankingCount(
    PDO    $pdo,
    string $dateStart,
    string $dateEnd,
    ?int   $menuId    = null,
    ?int   $minRating = null
): int {
    $f = _feedbackFilterClause($dateStart, $dateEnd, $menuId, $minRating);
 
    $sql = "
        SELECT COUNT(DISTINCT f.menu_id) AS cnt
        FROM feedbacks f
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

// ─────────────────────────────────────────────────────────────────────────────
// REVISI 4 — Export Laporan (admin/reports/export_pdf.php)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Ambil data lengkap Laporan Kesehatan untuk halaman cetak (tanpa pagination).
 *
 * @param PDO    $pdo
 * @param string $dateStart  Format Y-m-d
 * @param string $dateEnd    Format Y-m-d
 * @param string $bmiCat     'semua' | 'Underweight' | 'Normal' | 'Overweight' | 'Obese'
 * @return array
 */
function getHealthExportData(PDO $pdo, string $dateStart, string $dateEnd, string $bmiCat): array
{
    $f = _healthFilterClause($dateStart, $dateEnd, $bmiCat);

    $sql = "
        SELECT
            u.full_name,
            h.input_date,
            h.height_cm,
            h.weight_kg,
            h.bmi_value,
            h.bmi_category,
            h.validation_status
        FROM health_records h
        JOIN users u ON u.user_id = h.user_id
        {$f['where']}
        ORDER BY h.input_date DESC, h.health_id DESC
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($f['params'] as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Ambil data lengkap Laporan Feedback untuk halaman cetak (tanpa pagination).
 *
 * @param PDO      $pdo
 * @param string   $dateStart
 * @param string   $dateEnd
 * @param int|null $menuId
 * @param int|null $minRating
 * @return array
 */
function getFeedbackExportData(
    PDO    $pdo,
    string $dateStart,
    string $dateEnd,
    ?int   $menuId    = null,
    ?int   $minRating = null
): array {
    $f = _feedbackFilterClause($dateStart, $dateEnd, $menuId, $minRating);

    $sql = "
        SELECT
            u.full_name,
            m.menu_name,
            f.rating,
            f.comment,
            f.feedback_date,
            f.validation_status
        FROM feedbacks f
        JOIN users u ON u.user_id = f.user_id
        JOIN menus m ON m.menu_id = f.menu_id
        {$f['where']}
        ORDER BY f.feedback_date DESC, f.feedback_id DESC
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($f['params'] as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Simpan riwayat export ke tabel report_downloads.
 *
 * @param PDO    $pdo
 * @param int    $userId
 * @param string $reportType  'health' | 'feedback'
 * @param string $fileName
 * @return void
 */
function saveReportDownload(PDO $pdo, int $userId, string $reportType, string $fileName): void
{
    $stmt = $pdo->prepare("
        INSERT INTO report_downloads (user_id, report_type, file_name, downloaded_at)
        VALUES (:user_id, :report_type, :file_name, NOW())
    ");
    $stmt->execute([
        ':user_id'     => $userId,
        ':report_type' => $reportType,
        ':file_name'   => $fileName,
    ]);
}

/**
 * Ambil riwayat export laporan (terbaru -> terlama), join ke users.
 *
 * @param PDO $pdo
 * @param int $limit
 * @return array
 */
function getReportDownloadHistory(PDO $pdo, int $limit = 20): array
{
    $stmt = $pdo->prepare("
        SELECT
            rd.download_id,
            rd.report_type,
            rd.file_name,
            rd.downloaded_at,
            u.full_name AS admin_name
        FROM report_downloads rd
        JOIN users u ON u.user_id = rd.user_id
        ORDER BY rd.downloaded_at DESC
        LIMIT :lim
    ");
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
