</main>
  <footer class="mx-auto mt-auto w-full text-xs text-slate-500 dark:text-slate-400">
    <?php $appMeta = app_config('app') ?? []; ?>
    <div class="flex flex-col gap-2 border-t border-white/50 p-4 dark:border-slate-800/60 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex-1 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between max-w-6xl mx-auto">
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

    function mmApplyDoughnutTheme(chart, palette, values) {
      if (!chart || !palette) return;

      const datasetValues = Array.isArray(values) ? values : [];
      const dataset = chart.data && Array.isArray(chart.data.datasets)
        ? chart.data.datasets[0]
        : null;
      const segments = (pal) => {
        const base = (pal && pal.doughnutSegments) || [];
        return datasetValues.map((_, idx) => base[idx % base.length] || '#4b966e');
      };

      if (dataset) {
        dataset.data = datasetValues.slice();
        dataset.backgroundColor = segments(palette);
        dataset.borderColor = palette.doughnutBorder || '#4b966e33';
        dataset.borderWidth = 2;
      }

      chart.options = chart.options || {};
      chart.options.plugins = chart.options.plugins || {};

      const legend = chart.options.plugins.legend || (chart.options.plugins.legend = {});
      legend.position = 'bottom';
      legend.labels = legend.labels || {};
      legend.labels.color = palette.axis || '#2f443a';

      const tooltip = chart.options.plugins.tooltip || (chart.options.plugins.tooltip = {});
      tooltip.backgroundColor = palette.tooltipBg || 'rgba(255,255,255,0.96)';
      tooltip.borderColor = palette.tooltipBorder || 'rgba(75,150,110,0.35)';
      tooltip.borderWidth = 1;
      tooltip.titleColor = palette.tooltipText || '#233d30';
      tooltip.bodyColor = palette.tooltipText || '#233d30';
    }

    function mmBuildDoughnutChart(id, labels, values) {
      const canvas = document.getElementById(id);
      if (!canvas || typeof Chart === 'undefined') {
        mmChartStore.delete(id);
        return false;
      }

      window.updateChartGlobals && window.updateChartGlobals();
      const palette = window.getChartPalette ? window.getChartPalette() : {};
      const valuesArray = Array.isArray(values) ? values : [];
      const existing = mmChartStore.get(id);

      if (existing && existing.chart && existing.chart.config && existing.chart.config.type === 'doughnut') {
        existing.chart.data.labels = labels;
        if (!Array.isArray(existing.chart.data.datasets)) {
          existing.chart.data.datasets = [];
        }
        if (!existing.chart.data.datasets.length) {
          existing.chart.data.datasets.push({ data: [] });
        }
        mmApplyDoughnutTheme(existing.chart, palette, valuesArray);
        existing.chart.update();
        existing.labels = labels;
        existing.dataset = valuesArray;
        mmChartStore.set(id, existing);
        return existing.chart;
      }

      if (existing && existing.chart) {
        existing.chart.destroy();
      }

      const chart = new Chart(canvas, {
        type: 'doughnut',
        data: {
          labels,
          datasets: [{
            data: valuesArray.slice(),
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '58%',
          plugins: {
            legend: {},
            tooltip: {}
          }
        }
      });

      mmApplyDoughnutTheme(chart, palette, valuesArray);

      mmChartStore.set(id, { chart, type: 'doughnut', labels, dataset: valuesArray });
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

  <script>
    (function () {
      const CHUNK_SIZE = 8;
      const QUOTE_PATH = 'api/stocks/quotes';
      const HISTORY_PATH = 'api/stocks/history';

      const buildApiUrl = (path, params = {}) => {
        const base = new URL(path, window.location.origin + window.location.pathname);
        Object.entries(params).forEach(([key, value]) => {
          if (value === undefined || value === null || value === '') {
            return;
          }
          base.searchParams.set(key, value);
        });
        return base;
      };

      const chunk = (array, size) => {
        const result = [];
        for (let i = 0; i < array.length; i += size) {
          result.push(array.slice(i, i + size));
        }
        return result;
      };

      const formatCurrency = (amount, currency, options = {}) => {
        if (typeof amount !== 'number' || Number.isNaN(amount)) {
          return '—';
        }
        const safeCurrency = currency && typeof currency === 'string' ? currency.toUpperCase() : 'USD';
        let formatted;
        try {
          formatted = new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency: safeCurrency,
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
          }).format(amount);
        } catch (err) {
          formatted = amount.toFixed(2);
        }

        if (options.showCode) {
          return `${formatted} ${safeCurrency}`;
        }

        return `${formatted} ${options.hideCode ? '' : safeCurrency}`.trim();
      };

      const formatPercent = (value) => {
        if (typeof value !== 'number' || Number.isNaN(value)) {
          return '—';
        }
        const sign = value > 0 ? '+' : '';
        return `${sign}${value.toFixed(2)}%`;
      };

      const fetchQuotes = async (symbols) => {
        const list = Array.isArray(symbols) ? symbols : [];
        const unique = Array.from(new Set(list.map((s) => String(s || '').trim().toUpperCase()).filter(Boolean)));
        if (!unique.length) {
          return {};
        }

        const results = {};
        for (const subset of chunk(unique, CHUNK_SIZE)) {
          const url = buildApiUrl(QUOTE_PATH, { symbols: subset.join(',') });
          try {
            const response = await fetch(url.toString(), { credentials: 'same-origin' });
            if (!response.ok) continue;
            const data = await response.json();
            if (data && data.success === false) {
              throw new Error(data.error || 'Quote request failed');
            }
            const items = data && data.quotes && typeof data.quotes === 'object'
              ? data.quotes
              : {};
            Object.entries(items).forEach(([symbol, payload]) => {
              if (!symbol) return;
              const key = String(symbol).toUpperCase();
              results[key] = payload;
            });
          } catch (err) {
            console.error('Quote fetch failed', err);
          }
        }

        return results;
      };

      const fetchHistory = async (symbol, range = '1mo', interval = '1d') => {
        const sym = String(symbol || '').trim();
        if (!sym) return null;

        const url = buildApiUrl(HISTORY_PATH, {
          symbol: sym,
          range,
          interval,
        });
        try {
          const response = await fetch(url.toString(), { credentials: 'same-origin' });
          if (!response.ok) return null;
          const payload = await response.json();
          if (payload && payload.success === false) {
            throw new Error(payload.error || 'History request failed');
          }
          const history = payload && payload.history ? payload.history : null;
          if (!history || !Array.isArray(history.timestamps) || !Array.isArray(history.closes)) {
            return null;
          }
          return history;
        } catch (err) {
          console.error('History fetch failed', err);
          return null;
        }
      };

      const buildPortfolioHistory = (positions, histories, currencyRates, baseCurrency) => {
        const labelsMap = new Map();
        const base = (baseCurrency || 'USD').toUpperCase();
        const rateFor = (currency, fallbackRate) => {
          const key = currency ? String(currency).toUpperCase() : '';
          if (key && currencyRates && typeof currencyRates[key] !== 'undefined') {
            return Number(currencyRates[key]);
          }
          if (typeof fallbackRate === 'number' && !Number.isNaN(fallbackRate)) {
            return fallbackRate;
          }
          return 1;
        };

        (Array.isArray(positions) ? positions : []).forEach((pos, idx) => {
          const history = Array.isArray(histories) ? histories[idx] : null;
          if (!history || !Array.isArray(history.timestamps) || !Array.isArray(history.closes)) {
            return;
          }
          const qty = Number(pos && pos.qty ? pos.qty : 0);
          if (!qty) {
            return;
          }
          const currency = pos && pos.currency ? pos.currency : base;
          const fallbackRate = pos && typeof pos.rate_to_main !== 'undefined' ? Number(pos.rate_to_main) : 1;
          const rate = rateFor(currency, fallbackRate);

          history.timestamps.forEach((ts, index) => {
            const close = history.closes[index];
            if (typeof close !== 'number' || Number.isNaN(close)) {
              return;
            }
            const date = new Date(ts * 1000);
            if (!Number.isFinite(date.getTime())) {
              return;
            }
            const label = date.toISOString().slice(0, 10);
            const value = close * qty * rate;
            labelsMap.set(label, (labelsMap.get(label) || 0) + value);
          });
        });

        const labels = Array.from(labelsMap.keys()).sort();
        const values = labels.map((label) => labelsMap.get(label) || 0);
        return { labels, values };
      };

      window.MyMoneyMapStocksToolkit = {
        formatCurrency,
        formatPercent,
        fetchQuotes,
        fetchHistory,
        buildPortfolioHistory,
      };

      try {
        window.dispatchEvent(new Event('stocks-toolkit-ready'));
      } catch (err) {
        // ignore event dispatch issues
      }
    })();
  </script>

  <script src="https://unpkg.com/lucide@latest"></script>
  <script>
    lucide.createIcons();
  </script>
</body>
</html>
