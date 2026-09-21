(() => {
    const examples = {
        hourly: {inductive: 22.6, capacitive: 9.1, labels: ['00:00', '00:30', '01:00'], points: [94, 87, 72, 77, 59, 64, 48, 39, 44, 28, 24]},
        daily: {inductive: 18.4, capacitive: 8.2, labels: ['00:00', '12:00', '23:00'], points: [94, 83, 89, 65, 78, 60, 72, 52, 62, 44, 49]},
        monthly: {inductive: 16.7, capacitive: 7.4, labels: ['Ay başı', 'Ay ortası', 'Son ölçüm'], points: [88, 96, 77, 85, 74, 61, 70, 64, 57, 65, 58]},
    };
    document.querySelectorAll('[data-period]').forEach(button => {
        button.addEventListener('click', () => {
            const example = examples[button.dataset.period];
            document.querySelectorAll('[data-period]').forEach(item => {
                const selected = item === button;
                item.classList.toggle('active', selected);
                item.setAttribute('aria-pressed', String(selected));
            });
            [['inductive', 20], ['capacitive', 15]].forEach(([kind, threshold]) => {
                const value = example[kind];
                const alert = value > threshold;
                const label = document.getElementById(`${kind}-value`);
                label.textContent = `%${value.toLocaleString('tr-TR', {minimumFractionDigits: 1})}`;
                label.classList.toggle('alert', alert);
                const status = document.getElementById(`${kind}-status`);
                status.textContent = alert ? 'Eşik aşımı' : 'Eşik içinde';
                status.classList.toggle('alert', alert);
                const bar = document.getElementById(`${kind}-bar`);
                bar.style.width = `${Math.min(value / 25 * 100, 100)}%`;
                bar.style.background = alert ? '#ffb79c' : '#d5f77b';
            });
            ['start', 'middle', 'end'].forEach((position, i) => { document.getElementById(`axis-${position}`).textContent = example.labels[i]; });
            const path = example.points.map((point, i) => `${i === 0 ? 'M' : 'L'}${i * 44} ${point}`).join('');
            document.getElementById('chart-line').setAttribute('d', path);
            document.getElementById('chart-area').setAttribute('d', `${path}V132H0Z`);
        });
    });
})();
