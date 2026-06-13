<?php
/**
 * user/feedback/index.php
 * Daftar menu MBG & form feedback
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../helpers/validation_helper.php';
require_once __DIR__ . '/../../helpers/menu_model.php';
require_once __DIR__ . '/../../helpers/feedback_model.php';

requireRole([ROLE_USER]);

$pdo    = getDBConnection();
$userId = getCurrentUserId();

$successMessage = '';
$errorMessage    = '';

// ── Proses Submit Feedback ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        $errorMessage = 'Token keamanan tidak valid. Silakan coba lagi.';
    } else {
        $menuId  = (int) ($_POST['menu_id'] ?? 0);
        $rating  = (int) ($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        $comment = $comment !== '' ? $comment : null;

        $menu = getMenuById($pdo, $menuId);

        if (!$menu || (int) $menu['is_active'] !== 1) {
            $errorMessage = 'Menu tidak valid atau tidak aktif.';
        } elseif ($rating < 1 || $rating > 5) {
            $errorMessage = 'Rating harus antara 1 sampai 5.';
        } elseif ($comment !== null && mb_strlen($comment) > 500) {
            $errorMessage = 'Komentar maksimal 500 karakter.';
        } elseif (hasUserFeedbackForMenu($pdo, $userId, $menuId)) {
            $errorMessage = 'Anda sudah memberikan feedback untuk menu ini.';
        } else {
            if (insertFeedback($pdo, $userId, $menuId, $rating, $comment)) {
                $successMessage = 'Feedback berhasil dikirim. Terima kasih!';
            } else {
                $errorMessage = 'Gagal menyimpan feedback. Silakan coba lagi.';
            }
        }
    }
}

// ── Data Menu Aktif ──────────────────────────────────────────────────────────
$activeMenus = getActiveMenus($pdo);

// Tandai menu yang sudah diberi feedback oleh user
foreach ($activeMenus as &$menu) {
    $menu['already_feedback'] = hasUserFeedbackForMenu($pdo, $userId, (int) $menu['menu_id']);
}
unset($menu);

$csrfToken = generateCsrfToken();

$pageTitle = 'Feedback Menu MBG';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_user.php';
?>

<div id="main-content">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0 fw-bold">Feedback Menu MBG</h4>
        <a href="<?= BASE_URL ?>/user/feedback/history.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-clock-history me-1"></i>Riwayat Feedback
        </a>
    </div>

    <?php if ($successMessage): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-1"></i><?= escapeOutput($successMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle me-1"></i><?= escapeOutput($errorMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($activeMenus)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-card-list fs-1 text-muted"></i>
                <p class="text-muted mt-2 mb-0">Belum ada menu MBG aktif saat ini.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($activeMenus as $menu): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body d-flex flex-column">
                            <h6 class="fw-bold mb-1"><?= escapeOutput($menu['menu_name']) ?></h6>
                            <small class="text-muted mb-2">
                                <i class="bi bi-calendar3 me-1"></i><?= escapeOutput($menu['menu_date']) ?>
                            </small>
                            <p class="small text-muted flex-grow-1">
                                <?= escapeOutput($menu['description'] ?? '-') ?>
                            </p>

                            <?php if ($menu['already_feedback']): ?>
                                <div class="alert alert-success small mb-0 py-2">
                                    <i class="bi bi-check-circle me-1"></i>Anda sudah memberi feedback untuk menu ini.
                                </div>
                            <?php else: ?>
                                <form method="POST" action="<?= BASE_URL ?>/user/feedback/index.php" class="feedback-form">
                                    <input type="hidden" name="csrf_token" value="<?= escapeOutput($csrfToken) ?>">
                                    <input type="hidden" name="menu_id" value="<?= (int) $menu['menu_id'] ?>">

                                    <label class="form-label small text-muted mb-1">Rating</label>
                                    <div class="star-rating mb-2" data-input-name="rating">
                                        <?php for ($i = 5; $i >= 1; $i--): ?>
                                            <input type="radio" name="rating" id="star<?= $i ?>_<?= (int) $menu['menu_id'] ?>"
                                                   value="<?= $i ?>" required>
                                            <label for="star<?= $i ?>_<?= (int) $menu['menu_id'] ?>" title="<?= $i ?> Bintang">
                                                <i class="bi bi-star-fill"></i>
                                            </label>
                                        <?php endfor; ?>
                                    </div>

                                    <textarea name="comment" class="form-control form-control-sm mb-2" rows="2"
                                              maxlength="500" placeholder="Komentar (opsional)"></textarea>

                                    <button type="submit" class="btn btn-sm btn-success w-100">
                                        <i class="bi bi-send me-1"></i>Kirim Feedback
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div><!-- end #main-content -->

<style>
.star-rating {
    display: inline-flex;
    flex-direction: row-reverse;
}
.star-rating input[type="radio"] {
    display: none;
}
.star-rating label {
    font-size: 1.5rem;
    color: #dee2e6;
    cursor: pointer;
    padding: 0 2px;
    transition: color 0.2s;
}
.star-rating input[type="radio"]:checked ~ label,
.star-rating label:hover,
.star-rating label:hover ~ label {
    color: #ffc107;
}
</style>

<script src="<?= BASE_URL ?>/assets/js/star_rating.js"></script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>