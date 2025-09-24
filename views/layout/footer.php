</main>
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

      const html = document.documentElement;
      const hasClassList = !!(html && html.classList);

      const parseState = (raw) => {
        if (!raw) return null;
        try {
          const parsed = JSON.parse(raw);
          if (parsed && typeof parsed === 'object') {
            return parsed;
          }
        } catch (err) {
          // ignore parse errors
        }
        return null;
      };

      const resolveScrollTarget = (state) => {
        if (!state || typeof state !== 'object') return null;
        const value = typeof state.y === 'number' ? state.y : parseInt(state.y, 10);
        if (Number.isNaN(value)) return null;
        return Math.max(0, Math.round(value));
      };

      const storageKey = toKey(currentPath());
      let storedState;
      let hasLoadedState = false;

      const loadStoredState = () => {
        if (hasLoadedState) {
          return storedState;
        }

        hasLoadedState = true;
        const raw = storage.getItem(storageKey);
        if (!raw) {
          storedState = null;
          return storedState;
        }

        storage.removeItem(storageKey);
        storedState = parseState(raw);
        return storedState;
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

      const initialState = loadStoredState();
      const initialScrollTarget = resolveScrollTarget(initialState);
      if (hasClassList && typeof initialScrollTarget === 'number' && initialScrollTarget > 0) {
        html.classList.add('mm-scroll-restoring');
      }

      const releaseScrollMask = () => {
        if (!hasClassList || !html.classList.contains('mm-scroll-restoring')) {
          return;
        }

        const body = document.body;
        let cleaned = false;

        const cleanup = () => {
          if (cleaned) {
            return;
          }
          cleaned = true;
          html.classList.remove('mm-scroll-restoring');
          html.classList.remove('mm-scroll-restore-ready');
        };

        const startFade = () => {
          html.classList.add('mm-scroll-restore-ready');

          if (body && typeof body.addEventListener === 'function') {
            const handleTransitionEnd = (event) => {
              if (!event || event.propertyName === 'opacity') {
                body.removeEventListener('transitionend', handleTransitionEnd);
                cleanup();
              }
            };

            body.addEventListener('transitionend', handleTransitionEnd);
            window.setTimeout(() => {
              body.removeEventListener('transitionend', handleTransitionEnd);
              cleanup();
            }, 400);
          } else {
            window.setTimeout(cleanup, 240);
          }
        };

        if (typeof window.requestAnimationFrame === 'function') {
          window.requestAnimationFrame(() => {
            window.requestAnimationFrame(startFade);
          });
        } else {
          window.setTimeout(startFade, 0);
        }
      };

      const restore = () => {
        const state = loadStoredState();
        if (!state) {
          releaseScrollMask();
          return;
        }

        const scrollTarget = resolveScrollTarget(state);
        if (typeof scrollTarget === 'number') {
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

        releaseScrollMask();
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
