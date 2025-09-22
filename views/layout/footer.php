</main>
  <script>
    const mmChartStore = window.__mmChartStore = window.__mmChartStore || new Map();

    function mmBuildLineChart(id, labels, dataset) {
      const canvas = document.getElementById(id);
      if (!canvas || typeof Chart === 'undefined') {
        mmChartStore.delete(id);
        return false;
      }

      const existing = mmChartStore.get(id);
      if (existing && existing.chart) {
        existing.chart.destroy();
      }

      window.updateChartGlobals && window.updateChartGlobals();
      const palette = window.getChartPalette ? window.getChartPalette() : {};

      const chart = new Chart(canvas, {
        type: 'line',
        data: {
          labels,
          datasets: [{
            label: '<?= addslashes(__('Amount')) ?>',
            data: dataset,
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
              labels: { color: palette.axis || '#2f443a' }
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

      mmChartStore.set(id, { chart, type: 'line', labels, dataset });
      return chart;
    }

    window.renderLineChart = (id, labels, data) => {
      const chart = mmBuildLineChart(id, labels, data);
      const key = `chart:${id}`;
      if (window.registerChartTheme) {
        window.registerChartTheme(key, () => {
          const entry = mmChartStore.get(id);
          if (!entry) return false;
          return mmBuildLineChart(id, entry.labels, entry.dataset);
        });
      }
      return chart;
    };

    function mmBuildDoughnutChart(id, labels, values) {
      const canvas = document.getElementById(id);
      if (!canvas || typeof Chart === 'undefined') {
        mmChartStore.delete(id);
        return false;
      }

      const existing = mmChartStore.get(id);
      if (existing && existing.chart) {
        existing.chart.destroy();
      }

      window.updateChartGlobals && window.updateChartGlobals();
      const palette = window.getChartPalette ? window.getChartPalette() : {};
      const segments = (pal) => {
        const base = (pal && pal.doughnutSegments) || [];
        return values.map((_, idx) => base[idx % base.length] || '#4b966e');
      };

      const chart = new Chart(canvas, {
        type: 'doughnut',
        data: {
          labels,
          datasets: [{
            data: values,
            backgroundColor: segments(palette),
            borderColor: palette.doughnutBorder || '#4b966e33',
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
              labels: { color: palette.axis || '#2f443a' }
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

      mmChartStore.set(id, { chart, type: 'doughnut', labels, dataset: values });
      return chart;
    }

    window.renderDoughnut = (id, labels, data) => {
      const chart = mmBuildDoughnutChart(id, labels, data);
      const key = `chart:${id}`;
      if (window.registerChartTheme) {
        window.registerChartTheme(key, () => {
          const entry = mmChartStore.get(id);
          if (!entry) return false;
          return mmBuildDoughnutChart(id, entry.labels, entry.dataset);
        });
      }
      return chart;
    };
  </script>

  <script src="https://unpkg.com/lucide@latest"></script>
  <script>
    lucide.createIcons();
  </script>
</body>
</html>
