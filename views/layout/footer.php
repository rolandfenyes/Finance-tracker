</main>
  <footer class="border-t border-gray-200 py-8 text-center text-sm text-gray-500">© <?= date('Y') ?> MoneyMap</footer>
  <script>
    // Simple helper for charts (called by pages)
    window.renderLineChart = (id, labels, data) => {
      const ctx = document.getElementById(id); if(!ctx) return;
      new Chart(ctx, { type: 'line', data: { labels, datasets: [{ label: 'Amount', data, tension: 0.35, fill: false }] }, options: { responsive: true, maintainAspectRatio: false } });
    };
    window.renderDoughnut = (id, labels, data) => {
      const ctx = document.getElementById(id); if(!ctx) return;
      new Chart(ctx, { type: 'doughnut', data: { labels, datasets: [{ data }] }, options: { responsive: true, maintainAspectRatio: false } });
    };
  </script>
</body>
</html>