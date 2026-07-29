// owner-analytics.js
// Chart.js setup for Owner Analytics Dashboard

document.addEventListener('DOMContentLoaded', function() {
    // Dummy data for demonstration
    const occupancyData = {
        labels: Array.from({length: 30}, (_, i) => `Day ${i+1}`),
        datasets: [{
            label: 'Occupancy Rate',
            data: [70, 72, 68, 75, 80, 78, 74, 76, 79, 81, 77, 73, 75, 78, 80, 82, 85, 83, 81, 80, 78, 76, 74, 72, 70, 68, 66, 65, 67, 69],
            borderColor: '#2563eb', // blue
            backgroundColor: 'rgba(37,99,235,0.08)',
            tension: 0.4,
            fill: true,
            pointRadius: 0
        }]
    };
    new Chart(document.getElementById('occupancyChart'), {
        type: 'line',
        data: occupancyData,
        options: {
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } },
                x: { display: false }
            }
        }
    });

    const revenueData = {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
        datasets: [
            { label: 'Rooms', data: [120000, 135000, 128000, 140000, 150000, 145000], backgroundColor: '#22c55e' }, // green
            { label: 'F&B', data: [30000, 32000, 31000, 35000, 37000, 36000], backgroundColor: '#fbbf24' }, // yellow
            { label: 'Spa', data: [10000, 12000, 11000, 13000, 14000, 13500], backgroundColor: '#f97316' }, // orange
            { label: 'Activities', data: [8000, 9000, 8500, 9500, 10000, 9800], backgroundColor: '#0ea5e9' } // blue
        ]
    };
    new Chart(document.getElementById('revenueChart'), {
        type: 'bar',
        data: revenueData,
        options: {
            plugins: { legend: { position: 'bottom' } },
            responsive: true,
            scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } }
        }
    });

    const bookingSourcesData = {
        labels: ['Direct', 'OTA', 'Agent'],
        datasets: [{
            data: [55, 30, 15],
            backgroundColor: ['#2563eb', '#22c55e', '#f97316'],
            borderWidth: 0
        }]
    };
    new Chart(document.getElementById('bookingSourcesChart'), {
        type: 'pie',
        data: bookingSourcesData,
        options: {
            plugins: { legend: { position: 'bottom' } }
        }
    });

    const repeatGuestData = {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
        datasets: [{
            label: 'Repeat Guest Rate',
            data: [18, 20, 22, 21, 23, 25],
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37,99,235,0.08)',
            tension: 0.4,
            fill: true,
            pointRadius: 0
        }]
    };
    new Chart(document.getElementById('repeatGuestChart'), {
        type: 'line',
        data: repeatGuestData,
        options: {
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, max: 30, ticks: { callback: v => v + '%' } },
                x: { }
            }
        }
    });

    const satisfactionData = {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
        datasets: [{
            label: 'Satisfaction',
            data: [4.2, 4.3, 4.1, 4.4, 4.5, 4.3],
            backgroundColor: '#64748b', // neutral
            borderRadius: 6
        }]
    };
    new Chart(document.getElementById('satisfactionChart'), {
        type: 'bar',
        data: satisfactionData,
        options: {
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, max: 5, ticks: { stepSize: 1 } }
            }
        }
    });

    // KPI values (dummy)
    document.getElementById('kpi-occupancy').textContent = '78%';
    document.getElementById('kpi-adr').textContent = '₱4,200';
    document.getElementById('kpi-revpar').textContent = '₱3,276';
    document.getElementById('kpi-satisfaction').textContent = '4.3';
});

function applyFilters() {
    // Placeholder: In production, fetch filtered data via AJAX and update charts
    alert('Filters applied (demo only).');
}
