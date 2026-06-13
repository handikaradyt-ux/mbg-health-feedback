<?php
/**
 * helpers/validation_helper.php
 * Fungsi sanitasi input, escaping output, dan validasi field form
 */

// ── Output / Input helpers ───────────────────────────────────────────────────

/**
 * Sanitasi input: trim + hapus tag HTML
 */
function sanitizeInput(string $input): string
{
    return trim(strip_tags($input));
}

/**
 * Escape output untuk mencegah XSS
 */
function escapeOutput(?string $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// ── Validasi field form ──────────────────────────────────────────────────────

/**
 * Validasi nama lengkap: tidak kosong, maks 100 karakter
 */
function isValidFullName(string $value): bool
{
    $len = mb_strlen(trim($value));
    return $len >= 1 && $len <= 100;
}

/**
 * Validasi username: 3–50 karakter, hanya huruf/angka/underscore
 */
function isValidUsername(string $value): bool
{
    return (bool) preg_match('/^[a-zA-Z0-9_]{3,50}$/', $value);
}

/**
 * Validasi format email (boleh kosong — jika diisi harus valid)
 */
function isValidEmail(string $value): bool
{
    if ($value === '') {
        return true; // email opsional
    }
    return filter_var($value, FILTER_VALIDATE_EMAIL) !== false
        && mb_strlen($value) <= 100;
}

/**
 * Validasi password: minimal 6 karakter
 */
function isValidPassword(string $value): bool
{
    return mb_strlen($value) >= 6;
}

// ── Validasi data kesehatan ──────────────────────────────────────────────────

/**
 * Validasi panjang string generik (min–max karakter)
 */
function validateLength(string $value, int $min, int $max): bool
{
    $len = mb_strlen($value);
    return $len >= $min && $len <= $max;
}

/**
 * Validasi angka dalam range inklusif
 */
function validateRange(float $value, float $min, float $max): bool
{
    return $value >= $min && $value <= $max;
}