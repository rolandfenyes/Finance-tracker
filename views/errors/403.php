<section class="min-h-[40vh] flex flex-col items-center justify-center text-center space-y-4">
  <h1 class="text-4xl font-semibold text-gray-900 dark:text-gray-100"><?= __('Forbidden') ?></h1>
  <?php if (!empty($message)): ?>
    <p class="text-gray-500 dark:text-gray-400 max-w-lg"><?= htmlspecialchars($message, ENT_QUOTES) ?></p>
  <?php else: ?>
    <p class="text-gray-500 dark:text-gray-400 max-w-lg"><?= __('You are not authorized to access this page.') ?></p>
  <?php endif; ?>
</section>
