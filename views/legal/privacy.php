<section class="mx-auto w-full max-w-4xl space-y-8">
  <header class="card">
    <h1 class="text-2xl font-semibold text-slate-900 dark:text-white"><?= __('Privacy Policy') ?></h1>
    <p class="mt-3 text-sm text-slate-600 dark:text-slate-400"><?= __('This notice explains how MyMoneyMap collects, uses, and protects your personal information in line with the EU General Data Protection Regulation (GDPR).') ?></p>
    <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">
      <strong><?= __('Last updated:') ?></strong>
      <?= htmlspecialchars(date('F j, Y')) ?>
    </p>
  </header>

  <article class="card space-y-6 text-sm leading-relaxed text-slate-700 dark:text-slate-300">
    <section>
      <h2 class="text-lg font-semibold text-slate-900 dark:text-white"><?= __('1. Data controller & contact') ?></h2>
      <p class="mt-2"><?= __('MyMoneyMap acts as the data controller for the information you provide within this application. For any privacy request—including access, correction, or deletion—contact us at:') ?></p>
      <ul class="mt-2 list-disc space-y-1 pl-5">
        <li><?= __('Email: privacy@mymoneymap.local (or the address specified by your deployment owner)') ?></li>
        <li><?= __('Postal: MyMoneyMap Privacy Office, 123 Finance Street, Budapest, Hungary') ?></li>
      </ul>
    </section>

    <section>
      <h2 class="text-lg font-semibold text-slate-900 dark:text-white"><?= __('2. Personal data we process') ?></h2>
      <p class="mt-2"><?= __('We only store data that you explicitly enter or generate while using MyMoneyMap:') ?></p>
      <ul class="mt-2 list-disc space-y-1 pl-5">
        <li><?= __('Account details: email address, hashed password, preferred language, and theme selections.') ?></li>
        <li><?= __('Financial tracking data: transactions, categories, goals, loans, emergency fund history, scheduled payments, and uploaded notes.') ?></li>
        <li><?= __('Security credentials: active sessions, remember-me tokens, and registered passkeys.') ?></li>
        <li><?= __('Voluntary feedback you submit through the in-app feedback tool.') ?></li>
      </ul>
      <p class="mt-2"><?= __('No banking credentials or payment-card numbers are collected or processed by this application.') ?></p>
    </section>

    <section>
      <h2 class="text-lg font-semibold text-slate-900 dark:text-white"><?= __('3. Legal bases for processing') ?></h2>
      <ul class="mt-2 list-disc space-y-1 pl-5">
        <li><?= __('Performance of a contract: operating the budgeting features you request.') ?></li>
        <li><?= __('Legitimate interests: safeguarding the service, preventing fraud, and improving product quality.') ?></li>
        <li><?= __('Consent: optional analytics or marketing features (disabled by default in this open-source distribution).') ?></li>
      </ul>
    </section>

    <section>
      <h2 class="text-lg font-semibold text-slate-900 dark:text-white"><?= __('4. Data retention') ?></h2>
      <p class="mt-2"><?= __('Your data is kept only while your account remains active. You may export or delete your account at any time from Settings → Data & Privacy. When deletion is confirmed we purge all personal records within 30 days, except where law requires longer retention.') ?></p>
    </section>

    <section>
      <h2 class="text-lg font-semibold text-slate-900 dark:text-white"><?= __('5. Security measures') ?></h2>
      <ul class="mt-2 list-disc space-y-1 pl-5">
        <li><?= __('Encrypted connections (HTTPS) are required to protect data in transit.') ?></li>
        <li><?= __('Passwords are hashed using industry-standard one-way functions; passkeys and tokens are stored using strong cryptography.') ?></li>
        <li><?= __('Role-based access controls ensure only authenticated users can reach their own data.') ?></li>
        <li><?= __('Audit-friendly exports and deletion workflows help you meet GDPR accountability obligations.') ?></li>
      </ul>
    </section>

    <section>
      <h2 class="text-lg font-semibold text-slate-900 dark:text-white"><?= __('6. Your GDPR rights') ?></h2>
      <p class="mt-2"><?= __('You can exercise the following rights directly in the application or by contacting us:') ?></p>
      <ul class="mt-2 list-disc space-y-1 pl-5">
        <li><?= __('Access and portability (Article 15 & 20): download a structured JSON export of all records.') ?></li>
        <li><?= __('Rectification (Article 16): update personal details from Settings → Profile.') ?></li>
        <li><?= __('Erasure (Article 17): permanently delete your account and data from Settings → Data & Privacy.') ?></li>
        <li><?= __('Restriction and objection (Articles 18 & 21): contact us to pause processing if you believe data is inaccurate or unlawfully handled.') ?></li>
        <li><?= __('Complaint (Article 77): lodge a complaint with your local supervisory authority if you believe your rights were violated.') ?></li>
      </ul>
    </section>

    <section>
      <h2 class="text-lg font-semibold text-slate-900 dark:text-white"><?= __('7. International transfers') ?></h2>
      <p class="mt-2"><?= __('MyMoneyMap is typically self-hosted. If your deployment transfers data outside the EU/EEA, the operator must ensure adequate safeguards such as Standard Contractual Clauses or hosting within approved regions.') ?></p>
    </section>

    <section>
      <h2 class="text-lg font-semibold text-slate-900 dark:text-white"><?= __('8. Changes to this notice') ?></h2>
      <p class="mt-2"><?= __('We may update this policy to reflect new features or legal requirements. Significant changes will be announced in-app, and the revision date will update accordingly.') ?></p>
    </section>
  </article>

  <footer class="text-center text-xs text-slate-500 dark:text-slate-400">
    <p><?= __('Need help exercising your rights? Reach out through the Data & Privacy controls or email us directly.') ?></p>
    <a href="/settings/privacy" class="mt-2 inline-flex items-center gap-2 text-sm font-semibold text-accent">
      <i data-lucide="shield-check" class="h-4 w-4"></i>
      <span><?= __('Open Data & Privacy controls') ?></span>
    </a>
  </footer>
</section>
