</main>
  <footer class="mx-auto mt-auto w-full max-w-6xl px-4 pt-10 pb-10 text-xs text-slate-500 dark:text-slate-400">
    <?php $appMeta = app_config('app') ?? []; ?>
    <div class="flex flex-col gap-2 border-t border-white/50 pt-6 dark:border-slate-800/60 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <span>&copy; <?= date('Y') ?> <?= htmlspecialchars($appMeta['name'] ?? 'MyMoneyMap') ?></span>
        <span class="mx-1">·</span>
        <a href="/privacy" class="text-accent hover:underline"><?= __('Privacy Policy') ?></a>
      </div>
      <div class="flex flex-wrap items-center gap-3">
        <?php if (is_logged_in()): ?>
          <a href="/settings/privacy" class="text-accent hover:underline"><?= __('Data & Privacy controls') ?></a>
        <?php endif; ?>
        <span><?= __('Secure sessions & encrypted storage by default.') ?></span>
      </div>
    </div>
  </footer>
  <script>
    (function () {
      let storage;
      try {
        storage = window.sessionStorage;
      } catch (err) {
        storage = null;
      }
      if (!storage) {
        return;
      }

      const KEY_PREFIX = 'mymoneymap:scroll:';
      const currentPath = () => window.location.pathname || '/';
      const toKey = (key) => KEY_PREFIX + key;
      const isTruthy = (value) => {
        if (!value) return false;
        const normalized = String(value).toLowerCase();
        return normalized === '1' || normalized === 'true' || normalized === 'yes' || normalized === 'on';
      };

      const formMutates = (form) => {
        if (!form || isTruthy(form.dataset.skipScroll)) return false;

        const method = (form.getAttribute('method') || 'GET').toUpperCase();
        if (method !== 'GET') return true;

        const overrideInput = form.querySelector('input[name="_method"]');
        if (overrideInput) {
          const override = String(overrideInput.value || '').toUpperCase();
          if (override && override !== 'GET') {
            return true;
          }
        }

        return form.hasAttribute('data-preserve-scroll');
      };

      const rememberScroll = (form) => {
        if (!formMutates(form)) return;

        const key = (form.dataset.scrollKey && form.dataset.scrollKey.trim() !== '')
          ? form.dataset.scrollKey.trim()
          : currentPath();

        const scrollY = Math.max(0, Math.round(
          window.scrollY || window.pageYOffset || document.documentElement.scrollTop || 0
        ));

        const state = { y: scrollY };

        if (form.dataset.restoreFocus) {
          state.focus = form.dataset.restoreFocus;
          if (isTruthy(form.dataset.restoreFocusSelect)) {
            state.focusSelect = true;
          }
        }

        try {
          storage.setItem(toKey(key), JSON.stringify(state));
        } catch (err) {
          // Ignore storage errors
        }
      };

      document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        rememberScroll(form);
      }, true);

      const restore = () => {
        const key = currentPath();
        const raw = storage.getItem(toKey(key));
        if (!raw) return;

        storage.removeItem(toKey(key));

        let state;
        try {
          state = JSON.parse(raw);
        } catch (err) {
          return;
        }
        if (!state || typeof state !== 'object') return;

        const scrollTarget = typeof state.y === 'number' ? state.y : parseInt(state.y, 10);
        if (!Number.isNaN(scrollTarget)) {
          try {
            window.scrollTo({ top: scrollTarget, behavior: 'instant' });
          } catch (err) {
            window.scrollTo(0, scrollTarget);
          }
        }

        if (state.focus) {
          const el = document.querySelector(state.focus);
          if (el) {
            try {
              el.focus({ preventScroll: true });
            } catch (err) {
              try {
                el.focus();
              } catch (err2) {
                // ignore focus errors
              }
            }

            if (state.focusSelect && typeof el.select === 'function') {
              try {
                el.select();
              } catch (err) {
                // ignore select errors
              }
            }
          }
        }
      };

      if (document.readyState === 'complete') {
        restore();
      } else {
        window.addEventListener('load', restore, { once: true });
      }
    })();
  </script>
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
