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

    const colorWin = '#10B981';
    const colorWinAlpha = 'rgba(16, 185, 129, 0.85)';
    const colorLoss = '#EF4444';
    const colorLossAlpha = 'rgba(239, 68, 68, 0.85)';
    const colorPush = '#3B82F6';
    const colorPushAlpha = 'rgba(59, 130, 246, 0.85)';

    Chart.defaults.color = muted;
    Chart.defaults.borderColor = border;
    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;

    const line = (id, labels, values, label) => {
      const ctx = document.getElementById(id);
      if (!ctx) return;
      new Chart(ctx, {
        type: 'line',
        data: {
          labels,
          datasets: [{
            label,
            data: values,
            borderColor: colorWin,
            borderWidth: 2.5,
            tension: 0.25,
            fill: false,
            pointRadius: 4,
            pointHoverRadius: 6,
            pointBackgroundColor: values.map((v) => (v > 0 ? colorWin : (v < 0 ? colorLoss : colorPush))),
            pointBorderColor: values.map((v) => (v > 0 ? colorWin : (v < 0 ? colorLoss : colorPush))),
            segment: {
              borderColor: (ctx) => {
                const p0 = ctx.p0.parsed.y;
                const p1 = ctx.p1.parsed.y;
                if (p1 > p0) return colorWin;
                if (p1 < p0) return colorLoss;
                return colorPush;
              },
            },
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: true,
          plugins: { legend: { display: false } },
          scales: { x: { ticks: { color: muted, maxRotation: 0, autoSkip: true } }, y: { ticks: { color: muted } } },
        },
      });
    };

    const bar = (id, labels, values, colors) => {
      const ctx = document.getElementById(id);
      if (!ctx) return;
      new Chart(ctx, {
        type: 'bar',
        data: { labels, datasets: [{ data: values, backgroundColor: colors || primary }] },
        options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { display: false } } },
      });
    };

    const doughnut = (id, distribution) => {
      const ctx = document.getElementById(id);
      if (!ctx) return;

      const keys = Object.keys(distribution || {});
      const labels = keys.map((k) => {
        const key = k.toLowerCase();
        if (key === 'won' || key === 'win') return 'Wins';
        if (key === 'lost' || key === 'loss') return 'Losses';
        if (key === 'push') return 'Pushes';
        return k.charAt(0).toUpperCase() + k.slice(1);
      });
      const values = Object.values(distribution || {});
      const colors = keys.map((k) => {
        const key = k.toLowerCase();
        if (key === 'won' || key === 'win' || key === 'wins') return colorWin;
        if (key === 'lost' || key === 'loss' || key === 'losses') return colorLoss;
        if (key === 'push' || key === 'pushes' || key === 'ties') return colorPush;
        return primary;
      });

      new Chart(ctx, {
        type: 'doughnut',
        data: {
          labels,
          datasets: [{ data: values, backgroundColor: colors }],
        },
        options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom' } } },
      });
    };

    const draw = () => {
      document.querySelectorAll('canvas').forEach((c) => {
        const chart = Chart.getChart(c);
        chart?.destroy();
      });

      const monthlyData = data.monthly || [];
      const monthlyColors = monthlyData.map((p) => {
        if (p.units > 0) return colorWinAlpha;
        if (p.units < 0) return colorLossAlpha;
        return colorPushAlpha;
      });

      line(
        'chart-cumulative',
        (data.cumulative || []).map((p) => p.date),
        (data.cumulative || []).map((p) => p.units),
        'Units'
      );
      bar(
        'chart-monthly',
        monthlyData.map((p) => p.label),
        monthlyData.map((p) => p.units),
        monthlyColors
      );
      doughnut('chart-wl', data.distribution || {});
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
