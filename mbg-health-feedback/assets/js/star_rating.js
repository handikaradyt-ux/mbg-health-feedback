/**
 * assets/js/star_rating.js
 * Script interaksi rating bintang
 */

document.addEventListener('DOMContentLoaded', function () {
    const ratingGroups = document.querySelectorAll('.star-rating');

    ratingGroups.forEach(function (group) {
        const radios = group.querySelectorAll('input[type="radio"]');
        const form   = group.closest('form');

        // Validasi: pastikan rating dipilih sebelum submit
        if (form) {
            form.addEventListener('submit', function (e) {
                const checked = group.querySelector('input[type="radio"]:checked');
                if (!checked) {
                    e.preventDefault();
                    alert('Silakan pilih rating bintang terlebih dahulu.');
                }
            });
        }
    });
});