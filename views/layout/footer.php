</main>
  <script>
    // Simple helper for charts (called by pages)
    window.renderLineChart = (id, labels, data) => {
      const ctx = document.getElementById(id);
      if (!ctx || typeof Chart === 'undefined') return;

      window.updateChartGlobals && window.updateChartGlobals();
      const palette = window.getChartPalette ? window.getChartPalette() : {};

      const chart = new Chart(ctx, {
        type: 'line',
        data: {
          labels,
          datasets: [{
            label: '<?= addslashes(__('Amount')) ?>',
            data,
            tension: 0.35,
            fill: true,
            borderWidth: 2,
            borderColor: palette.netLine || '#4b966e',
            backgroundColor: palette.netFillTop || 'rgba(75,150,110,0.24)',
            pointRadius: 3,
            pointHoverRadius: 4,
            pointBackgroundColor: palette.netLine || '#4b966e',
            pointBorderColor: palette.netLine || '#4b966e'
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              labels: {
                color: palette.axis || '#2f443a'
              }
            },
            tooltip: {
              backgroundColor: palette.tooltipBg || 'rgba(255,255,255,0.96)',
              borderColor: palette.tooltipBorder || 'rgba(75,150,110,0.35)',
              borderWidth: 1,
              titleColor: palette.tooltipText || '#233d30',
              bodyColor: palette.tooltipText || '#233d30'
            }
          },
          scales: {
            x: {
              ticks: { color: palette.axis || '#2f443a' },
              grid: { color: palette.grid || 'rgba(17,36,29,0.08)' }
            },
            y: {
              ticks: { color: palette.axis || '#2f443a' },
              grid: { color: palette.grid || 'rgba(17,36,29,0.08)' }
            }
          }
        }
      });

      const applyTheme = (instance) => {
        if (!window.getChartPalette) return;
        const pal = window.getChartPalette();
        const ds = instance.data.datasets[0];
        ds.borderColor = pal.netLine;
        ds.backgroundColor = pal.netFillTop;
        ds.pointBackgroundColor = pal.netLine;
        ds.pointBorderColor = pal.netLine;
        instance.options.plugins.legend.labels.color = pal.axis;
        instance.options.plugins.tooltip.backgroundColor = pal.tooltipBg;
        instance.options.plugins.tooltip.borderColor = pal.tooltipBorder;
        instance.options.plugins.tooltip.titleColor = pal.tooltipText;
        instance.options.plugins.tooltip.bodyColor = pal.tooltipText;
        instance.options.scales.x.ticks.color = pal.axis;
        instance.options.scales.y.ticks.color = pal.axis;
        instance.options.scales.x.grid.color = pal.grid;
        instance.options.scales.y.grid.color = pal.grid;
      };

      applyTheme(chart);
      chart.update('none');
      window.registerChartTheme && window.registerChartTheme(chart, applyTheme);
    };
    window.renderDoughnut = (id, labels, data) => {
      const ctx = document.getElementById(id);
      if (!ctx || typeof Chart === 'undefined') return;

      window.updateChartGlobals && window.updateChartGlobals();
      const palette = window.getChartPalette ? window.getChartPalette() : {};
      const makeSegments = (pal) => {
        const base = (pal && pal.doughnutSegments) || [];
        return data.map((_, idx) => base[idx % base.length] || 'rgba(75,150,110,0.8)');
      };

      const chart = new Chart(ctx, {
        type: 'doughnut',
        data: {
          labels,
          datasets: [{
            data,
            backgroundColor: makeSegments(palette),
            borderColor: palette.doughnutBorder || 'rgba(75,150,110,0.28)',
            borderWidth: 2
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '58%',
          plugins: {
            legend: {
              position: 'bottom',
              labels: {
                color: palette.axis || '#2f443a'
              }
            },
            tooltip: {
              backgroundColor: palette.tooltipBg || 'rgba(255,255,255,0.96)',
              borderColor: palette.tooltipBorder || 'rgba(75,150,110,0.35)',
              borderWidth: 1,
              titleColor: palette.tooltipText || '#233d30',
              bodyColor: palette.tooltipText || '#233d30'
            }
          }
        }
      });

      const applyTheme = (instance) => {
        if (!window.getChartPalette) return;
        const pal = window.getChartPalette();
        instance.data.datasets[0].backgroundColor = makeSegments(pal);
        instance.data.datasets[0].borderColor = pal.doughnutBorder;
        instance.options.plugins.legend.labels.color = pal.axis;
        instance.options.plugins.tooltip.backgroundColor = pal.tooltipBg;
        instance.options.plugins.tooltip.borderColor = pal.tooltipBorder;
        instance.options.plugins.tooltip.titleColor = pal.tooltipText;
        instance.options.plugins.tooltip.bodyColor = pal.tooltipText;
      };

      applyTheme(chart);
      chart.update('none');
      window.registerChartTheme && window.registerChartTheme(chart, applyTheme);
    };
  </script>

  <script src="https://unpkg.com/lucide@latest"></script>
  <script>
    lucide.createIcons();
  </script>
</body>
</html>
