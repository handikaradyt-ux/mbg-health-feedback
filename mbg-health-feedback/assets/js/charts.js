/**
 * assets/js/charts.js
 * Inisialisasi dan konfigurasi Chart.js
 */

/**
 * Inisialisasi grafik line perkembangan BMI
 * dengan garis referensi Overweight (25) dan Underweight (18.5)
 *
 * @param {string} canvasId  - ID elemen canvas
 * @param {string[]} labels  - Array tanggal (input_date)
 * @param {number[]} values  - Array nilai BMI
 */
function initBmiChart(canvasId, labels, values) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;

    // Format label bulan: "2023-01-10" → "Jan 2023"
    const monthNames = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agt','Sep','Okt','Nov','Des'];
    const formattedLabels = labels.map(function(dateStr) {
        const parts = dateStr.split('-');
        if (parts.length < 2) return dateStr;
        const month = parseInt(parts[1], 10) - 1;
        const year  = parts[0];
        return monthNames[month] + ' ' + year;
    });

    // Garis referensi sebagai dataset (dashed horizontal line)
    const overweightLine  = labels.map(function() { return 25.0; });
    const underweightLine = labels.map(function() { return 18.5; });

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: formattedLabels,
            datasets: [
                {
                    label: 'BMI Anda',
                    data: values,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.08)',
                    tension: 0.35,
                    fill: true,
                    pointRadius: 5,
                    pointBackgroundColor: '#2563eb',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointHoverRadius: 7,
                    order: 1
                },
                {
                    label: 'Batas Overweight (25.0)',
                    data: overweightLine,
                    borderColor: '#ef4444',
                    backgroundColor: 'transparent',
                    borderDash: [6, 4],
                    borderWidth: 1.5,
                    pointRadius: 0,
                    pointHoverRadius: 0,
                    fill: false,
                    tension: 0,
                    order: 2
                },
                {
                    label: 'Batas Underweight (18.5)',
                    data: underweightLine,
                    borderColor: '#22c55e',
                    backgroundColor: 'transparent',
                    borderDash: [6, 4],
                    borderWidth: 1.5,
                    pointRadius: 0,
                    pointHoverRadius: 0,
                    fill: false,
                    tension: 0,
                    order: 3
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 20,
                        font: { size: 12 },
                        generateLabels: function(chart) {
                            return chart.data.datasets.map(function(ds, i) {
                                return {
                                    text: ds.label,
                                    fillStyle: ds.borderColor,
                                    strokeStyle: ds.borderColor,
                                    lineWidth: 2,
                                    hidden: false,
                                    datasetIndex: i,
                                    lineDash: ds.borderDash || []
                                };
                            });
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            if (context.datasetIndex === 0) {
                                return ' BMI Anda: ' + context.parsed.y.toFixed(1);
                            }
                            return ' ' + context.dataset.label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    title: { display: false },
                    beginAtZero: false,
                    min: 14,
                    grid: {
                        color: 'rgba(0,0,0,0.05)'
                    },
                    ticks: {
                        font: { size: 11 },
                        color: '#6b7280'
                    }
                },
                x: {
                    title: { display: false },
                    grid: { display: false },
                    ticks: {
                        font: { size: 11 },
                        color: '#6b7280'
                    }
                }
            }
        }
    });
}