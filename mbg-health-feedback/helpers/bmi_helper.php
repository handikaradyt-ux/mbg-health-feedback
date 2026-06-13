<?php
/**
 * helpers/bmi_helper.php
 * Fungsi kalkulasi BMI dan klasifikasi kategori
 */

/**
 * Hitung nilai BMI
 * BMI = berat_kg / (tinggi_meter ^ 2)
 *
 * @param float $weightKg
 * @param float $heightCm
 * @return float Nilai BMI (2 desimal)
 */
function calculateBmi(float $weightKg, float $heightCm): float {
    $heightM = $heightCm / 100;
    if ($heightM <= 0) {
        return 0.0;
    }
    $bmi = $weightKg / ($heightM * $heightM);
    return round($bmi, 2);
}

/**
 * Tentukan kategori BMI berdasarkan nilai BMI
 *
 * @param float $bmi
 * @return string Salah satu dari: Underweight, Normal, Overweight, Obese
 */
function getBmiCategory(float $bmi): string {
    if ($bmi < 18.5) {
        return 'Underweight';
    } elseif ($bmi < 25) {
        return 'Normal';
    } elseif ($bmi < 30) {
        return 'Overweight';
    } else {
        return 'Obese';
    }
}

/**
 * Validasi nilai tinggi badan (cm)
 * Range valid: 50 - 250 cm
 *
 * @param mixed $heightCm
 * @return bool
 */
function isValidHeight($heightCm): bool {
    if (!is_numeric($heightCm)) {
        return false;
    }
    $height = (float) $heightCm;
    return $height >= 50 && $height <= 250;
}

/**
 * Validasi nilai berat badan (kg)
 * Range valid: 10 - 300 kg
 *
 * @param mixed $weightKg
 * @return bool
 */
function isValidWeight($weightKg): bool {
    if (!is_numeric($weightKg)) {
        return false;
    }
    $weight = (float) $weightKg;
    return $weight >= 10 && $weight <= 300;
}