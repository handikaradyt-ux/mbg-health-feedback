<?php
/**
 * user/health/input.php
 * Form input data kesehatan (TB & BB) untuk role User
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../helpers/validation_helper.php';
require_once __DIR__ . '/../../helpers/bmi_helper.php';
require_once __DIR__ . '/../../helpers/health_model.php';

requireRole([ROLE_USER]);

$pdo    = getDBConnection();
$userId = getCurrentUserId();

$successMessage = '';
$errorMessage    = '';

// Nilai default form (untuk re-fill jika error)
$heightInput = '';
$weightInput = '';
$notesInput  = '';

// ── Hasil preview setelah submit (untuk ditampilkan jika sukses) ────────────
$resultBmi      = null;
$resultCategory = null;

// ── Proses Submit ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';

    $heightInput = trim($_POST['height_cm'] ?? '');
    $weightInput = trim($_POST['weight_kg'] ?? '');
    $notesInput  = trim($_POST['notes'] ?? '');
    $notes       = $notesInput !== '' ? $notesInput : null;

    if (!verifyCsrfToken($csrfToken)) {
        $errorMessage = 'Token keamanan tidak valid. Silakan coba lagi.';
    } elseif (!isValidHeight($heightInput)) {
        $errorMessage = 'Tinggi badan harus berupa angka antara 50 - 250 cm.';
    } elseif (!isValidWeight($weightInput)) {
        $errorMessage = 'Berat badan harus berupa angka antara 10 - 300 kg.';
    } elseif ($notes !== null && mb_strlen($notes) > 1000) {
        $errorMessage = 'Catatan terlalu panjang.';
    } else {
        $heightCm = (float) $heightInput;
        $weightKg = (float) $weightInput;

        $bmiValue    = calculateBmi($weightKg, $heightCm);
        $bmiCategory = getBmiCategory($bmiValue);
        $inputDate   = date('Y-m-d');

        $saved = insertHealthRecord(
            $pdo,
            $userId,
            $heightCm,
            $weightKg,
            $bmiValue,
            $bmiCategory,
            $inputDate,
            $notes
        );

        if ($saved) {
            $successMessage = 'Data kesehatan berhasil disimpan!';
            $resultBmi       = $bmiValue;
            $resultCategory  = $bmiCategory;

            // Reset form setelah sukses
            $heightInput = '';
            $weightInput = '';
            $notesInput  = '';
        } else {
            $errorMessage = 'Gagal menyimpan data. Silakan coba lagi.';
        }
    }
}

$csrfToken = generateCsrfToken();

$bmiColors = [
    'Underweight' => 'info',
    'Normal'      => 'success',
    'Overweight'  => 'warning',
    'Obese'       => 'danger',
];

$pageTitle = 'Input Data Kesehatan';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_user.php';
?>

<div id="main-content">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0 fw-bold">Input Data Kesehatan</h4>
        <a href="<?= BASE_URL ?>/user/health/history.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-graph-up me-1"></i>Lihat Riwayat
        </a>
    </div>

    <?php if ($successMessage): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-1"></i><?= escapeOutput($successMessage) ?>
            <?php if ($resultBmi !== null): ?>
                — BMI Anda: <strong><?= escapeOutput($resultBmi) ?></strong>
                (<span class="badge bg-<?= $bmiColors[$resultCategory] ?? 'secondary' ?>"><?= escapeOutput($resultCategory) ?></span>)
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle me-1"></i><?= escapeOutput($errorMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-3">

        <!-- Form Input -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent fw-semibold border-bottom">
                    <i class="bi bi-pencil-square text-primary me-2"></i>Form Input Tinggi &amp; Berat Badan
                </div>
                <div class="card-body">
                    <form method="POST" action="<?= BASE_URL ?>/user/health/input.php" id="healthForm" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= escapeOutput($csrfToken) ?>">

                        <div class="mb-3">
                            <label for="height_cm" class="form-label">Tinggi Badan (cm)</label>
                            <input type="number" step="0.1" min="50" max="250" class="form-control"
                                   id="height_cm" name="height_cm"
                                   value="<?= escapeOutput($heightInput) ?>"
                                   placeholder="Contoh: 165.5" required>
                            <div class="form-text">Rentang valid: 50 - 250 cm</div>
                        </div>

                        <div class="mb-3">
                            <label for="weight_kg" class="form-label">Berat Badan (kg)</label>
                            <input type="number" step="0.1" min="10" max="300" class="form-control"
                                   id="weight_kg" name="weight_kg"
                                   value="<?= escapeOutput($weightInput) ?>"
                                   placeholder="Contoh: 60.0" required>
                            <div class="form-text">Rentang valid: 10 - 300 kg</div>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Catatan (opsional)</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"
                                      maxlength="1000"
                                      placeholder="Catatan tambahan..."><?= escapeOutput($notesInput) ?></textarea>
                        </div>

                        <div id="bmiValidationAlert" class="alert alert-warning d-none small"></div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i>Simpan Data
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Preview BMI Realtime -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent fw-semibold border-bottom">
                    <i class="bi bi-speedometer2 text-success me-2"></i>Preview BMI
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <div class="text-center" id="bmiPreview">
                        <div class="display-3 fw-bold text-muted" id="bmiValue">--</div>
                        <div class="text-muted mb-2">Indeks Massa Tubuh</div>
                        <span class="badge bg-secondary fs-6" id="bmiCategory">Belum dihitung</span>

                        <hr>

                        <div class="text-start small text-muted">
                            <div class="d-flex justify-content-between">
                                <span>Underweight</span><span>&lt; 18.5</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Normal</span><span>18.5 - 24.9</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Overweight</span><span>25 - 29.9</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Obese</span><span>&ge; 30</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

</div><!-- end #main-content -->

<script src="<?= BASE_URL ?>/assets/js/bmi_calculator.js"></script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>