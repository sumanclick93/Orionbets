(() => {
  const boot = () => {
    const payloadEl = document.getElementById('chart-payload');
    if (!payloadEl || typeof Chart === 'undefined') return;
    const data = JSON.parse(payloadEl.textContent);
    const styles = getComputedStyle(document.documentElement);
    const text = styles.getPropertyValue('--color-text').trim();
    const muted = styles.getPropertyValue('--color-text-muted').trim();
    const primary = styles.getPropertyValue('--color-primary').trim();
    const border = styles.getPropertyValue('--color-border').trim();

    Chart.defaults.color = muted;
    Chart.defaults.borderColor = border;
    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;

    const destroyAll = () => Object.values(Chart.instances || {}).forEach((c) => c.destroy?.());

    const line = (id, labels, values, label) => {
      const ctx = document.getElementById(id);
      if (!ctx) return;
      new Chart(ctx, {
        type: 'line',
        data: {
          labels,
          datasets: [{ label, data: values, borderColor: primary, tension: 0.25, fill: false, pointRadius: 3 }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: true,
          plugins: { legend: { display: false } },
          scales: { x: { ticks: { color: muted, maxRotation: 0, autoSkip: true } }, y: { ticks: { color: muted } } },
        },
      });
    };

    const bar = (id, labels, values) => {
      const ctx = document.getElementById(id);
      if (!ctx) return;
      new Chart(ctx, {
        type: 'bar',
        data: { labels, datasets: [{ data: values, backgroundColor: primary }] },
        options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { display: false } } },
      });
    };

    const doughnut = (id, labels, values) => {
      const ctx = document.getElementById(id);
      if (!ctx) return;
      new Chart(ctx, {
        type: 'doughnut',
        data: {
          labels,
          datasets: [{ data: values, backgroundColor: [primary, text, muted] }],
        },
        options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom' } } },
      });
    };

    const draw = () => {
      document.querySelectorAll('canvas').forEach((c) => {
        const chart = Chart.getChart(c);
        chart?.destroy();
      });
      line(
        'chart-cumulative',
        (data.cumulative || []).map((p) => p.date),
        (data.cumulative || []).map((p) => p.units),
        'Units'
      );
      bar(
        'chart-monthly',
        (data.monthly || []).map((p) => p.label),
        (data.monthly || []).map((p) => p.units)
      );
      doughnut('chart-wl', Object.keys(data.distribution || {}), Object.values(data.distribution || {}));
      bar('chart-sports', Object.keys(data.sports || {}), Object.values(data.sports || {}));
      bar('chart-leagues', Object.keys(data.leagues || {}), Object.values(data.leagues || {}));
    };

    draw();
    document.addEventListener('edgeplay:theme', draw);
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => setTimeout(boot, 50));
  } else {
    setTimeout(boot, 50);
  }
})();
