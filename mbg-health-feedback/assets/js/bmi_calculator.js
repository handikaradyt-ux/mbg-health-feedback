/**
 * assets/js/bmi_calculator.js
 * Script kalkulasi & preview BMI real-time
 * Kompatibel dengan form #healthForm pada user/health/input.php
 */

document.addEventListener('DOMContentLoaded', function () {
    const heightInput  = document.getElementById('height_cm');
    const weightInput  = document.getElementById('weight_kg');
    const bmiValueEl   = document.getElementById('bmiValue');
    const bmiCategoryEl = document.getElementById('bmiCategory');
    const alertEl      = document.getElementById('bmiValidationAlert');
    const form         = document.getElementById('healthForm');

    if (!heightInput || !weightInput) return;

    const HEIGHT_MIN = 50;
    const HEIGHT_MAX = 250;
    const WEIGHT_MIN = 10;
    const WEIGHT_MAX = 300;

    // Warna badge sesuai kategori (selaras dengan dashboard & history)
    const categoryColors = {
        'Underweight': 'info',
        'Normal':      'success',
        'Overweight':  'warning',
        'Obese':       'danger'
    };

    /**
     * Tentukan kategori BMI (sinkron dengan bmi_helper.php)
     */
    function getBmiCategory(bmi) {
        if (bmi < 18.5) return 'Underweight';
        if (bmi < 25)   return 'Normal';
        if (bmi < 30)   return 'Overweight';
        return 'Obese';
    }

    /**
     * Reset preview ke kondisi awal
     */
    function resetPreview() {
        bmiValueEl.textContent = '--';
        bmiValueEl.className = 'display-3 fw-bold text-muted';
        bmiCategoryEl.textContent = 'Belum dihitung';
        bmiCategoryEl.className = 'badge bg-secondary fs-6';
    }

    /**
     * Tampilkan pesan validasi
     */
    function showAlert(message) {
        if (!alertEl) return;
        alertEl.textContent = message;
        alertEl.classList.remove('d-none');
    }

    function hideAlert() {
        if (!alertEl) return;
        alertEl.classList.add('d-none');
        alertEl.textContent = '';
    }

    /**
     * Hitung & tampilkan preview BMI realtime
     */
    function updatePreview() {
        const heightVal = parseFloat(heightInput.value);
        const weightVal = parseFloat(weightInput.value);

        hideAlert();

        // Belum lengkap diisi
        if (isNaN(heightVal) || isNaN(weightVal) || heightInput.value === '' || weightInput.value === '') {
            resetPreview();
            return;
        }

        // Validasi range
        let errors = [];
        if (heightVal < HEIGHT_MIN || heightVal > HEIGHT_MAX) {
            errors.push(`Tinggi badan harus antara ${HEIGHT_MIN}-${HEIGHT_MAX} cm.`);
        }
        if (weightVal < WEIGHT_MIN || weightVal > WEIGHT_MAX) {
            errors.push(`Berat badan harus antara ${WEIGHT_MIN}-${WEIGHT_MAX} kg.`);
        }

        if (errors.length > 0) {
            showAlert(errors.join(' '));
            resetPreview();
            return;
        }

        // Hitung BMI = berat / (tinggi_m^2)
        const heightM = heightVal / 100;
        const bmi = weightVal / (heightM * heightM);
        const bmiRounded = Math.round(bmi * 100) / 100;
        const category = getBmiCategory(bmiRounded);
        const colorClass = categoryColors[category] || 'secondary';

        bmiValueEl.textContent = bmiRounded.toFixed(2);
        bmiValueEl.className = `display-3 fw-bold text-${colorClass}`;
        bmiCategoryEl.textContent = category;
        bmiCategoryEl.className = `badge bg-${colorClass} fs-6`;
    }

    heightInput.addEventListener('input', updatePreview);
    weightInput.addEventListener('input', updatePreview);

    // Validasi sebelum submit form
    if (form) {
        form.addEventListener('submit', function (e) {
            const heightVal = parseFloat(heightInput.value);
            const weightVal = parseFloat(weightInput.value);

            let errors = [];
            if (isNaN(heightVal) || heightVal < HEIGHT_MIN || heightVal > HEIGHT_MAX) {
                errors.push(`Tinggi badan harus antara ${HEIGHT_MIN}-${HEIGHT_MAX} cm.`);
            }
            if (isNaN(weightVal) || weightVal < WEIGHT_MIN || weightVal > WEIGHT_MAX) {
                errors.push(`Berat badan harus antara ${WEIGHT_MIN}-${WEIGHT_MAX} kg.`);
            }

            if (errors.length > 0) {
                e.preventDefault();
                showAlert(errors.join(' '));
            }
        });
    }

    // Inisialisasi awal (jika ada value re-fill dari server)
    updatePreview();
});