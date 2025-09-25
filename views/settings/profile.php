<section class="max-w-2xl mx-auto">
  <div class="card">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <h1 class="text-xl font-semibold">Profile</h1>
      <div class="flex items-center gap-2">
        <a href="/settings" class="hidden sm:inline-flex items-center gap-1 text-sm font-medium text-accent">
          <span aria-hidden="true">←</span>
          <span><?= __('Back to Settings') ?></span>
        </a>
        <a href="/more" class="inline-flex sm:hidden items-center gap-1 text-sm font-medium text-accent">
          <span aria-hidden="true">←</span>
          <span><?= __('Back to More') ?></span>
        </a>
      </div>
    </div>

    <?php if (!empty($_SESSION['flash'])): ?>
      <p class="mt-3 text-red-600 text-sm"><?= $_SESSION['flash']; unset($_SESSION['flash']); ?></p>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_success'])): ?>
      <p class="mt-3 text-brand-600 text-sm"><?= $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?></p>
    <?php endif; ?>

    <form method="post" action="/settings/profile" class="grid gap-4 mt-5">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />

      <div class="field">
        <label class="label">Email</label>
        <input class="input bg-gray-50" value="<?= htmlspecialchars($user['email']) ?>" disabled />
        <p class="help">Email changes are disabled here.</p>
      </div>

      <div class="field">
        <label class="label">Full name</label>
        <input name="full_name" class="input" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" />
      </div>

      <div class="field">
        <label class="label">Date of birth</label>
        <input name="date_of_birth" type="date" class="input" value="<?= htmlspecialchars($user['date_of_birth'] ?? '') ?>" />
      </div>

      <div class="grid md:grid-cols-2 gap-3">
        <div class="field">
          <label class="label">New password</label>
          <input name="password" type="password" class="input" placeholder="Leave blank to keep current" />
        </div>
        <div class="field">
          <label class="label">Confirm password</label>
          <input name="password2" type="password" class="input" placeholder="Confirm new password" />
        </div>
      </div>

      <div class="flex justify-end">
        <button class="btn btn-primary">Save changes</button>
      </div>
    </form>

    <div class="mt-8 border-t border-white/40 pt-6 dark:border-slate-800/60">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-lg font-semibold text-slate-800 dark:text-slate-100"><?= __('Biometric sign-in') ?></h2>
          <p class="text-sm text-slate-600 dark:text-slate-400">
            <?= __('Register passkeys to sign in with Face ID or your fingerprint on supported devices.') ?>
          </p>
        </div>
        <button type="button" id="passkey-register-button" class="btn btn-ghost whitespace-nowrap">
          <?= __('Add device passkey') ?>
        </button>
      </div>
      <p class="mt-2 text-xs text-slate-500 dark:text-slate-500"><?= __('Passkeys require a compatible device and browser.') ?></p>
      <div
        id="passkey-register-message"
        class="mt-3 hidden rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-600 dark:border-rose-500/50 dark:bg-rose-500/10 dark:text-rose-200"
        role="alert"
      ></div>

      <?php if (!empty($passkeys)): ?>
        <ul class="mt-4 space-y-3">
          <?php foreach ($passkeys as $pk): ?>
            <?php
            $created = !empty($pk['created_at']) ? date('Y-m-d', strtotime($pk['created_at'])) : null;
            $used = !empty($pk['last_used']) ? date('Y-m-d', strtotime($pk['last_used'])) : null;
            ?>
            <li class="flex flex-col gap-3 rounded-2xl border border-white/60 bg-white/60 p-3 shadow-sm dark:border-slate-800 dark:bg-slate-900/40 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <div class="font-medium text-slate-800 dark:text-slate-100"><?= htmlspecialchars($pk['label'] ?: __('Passkey')) ?></div>
                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                  <?php if ($used): ?>
                    <?= __('Last used :date', ['date' => htmlspecialchars($used)]) ?>
                  <?php elseif ($created): ?>
                    <?= __('Added :date', ['date' => htmlspecialchars($created)]) ?>
                  <?php else: ?>
                    <?= __('Ready to use') ?>
                  <?php endif; ?>
                </div>
              </div>
              <form method="post" action="/settings/passkeys/delete" class="flex items-center justify-end gap-2">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                <input type="hidden" name="id" value="<?= (int)$pk['id'] ?>" />
                <button class="text-xs font-semibold text-rose-600 hover:underline dark:text-rose-300" type="submit">
                  <?= __('Remove') ?>
                </button>
              </form>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="mt-4 text-sm text-slate-500 dark:text-slate-400"><?= __('No passkeys yet.') ?></p>
      <?php endif; ?>
    </div>
  </div>
</section>

<script>
  (function () {
    const blockButton = document.getElementById('passkey-register-button');
    const messageEl = document.getElementById('passkey-register-message');
    if (!blockButton) {
      return;
    }

    const showMessage = (text, tone = 'error') => {
      if (!messageEl) return;
      messageEl.textContent = text;
      messageEl.classList.remove('hidden');
      if (tone === 'error') {
        messageEl.classList.remove('text-emerald-600');
        messageEl.classList.add('text-rose-600');
      } else {
        messageEl.classList.remove('text-rose-600');
        messageEl.classList.add('text-emerald-600');
      }
    };

    const clearMessage = () => {
      if (!messageEl) return;
      messageEl.classList.add('hidden');
      messageEl.textContent = '';
    };

    if (!window.PublicKeyCredential) {
      blockButton.disabled = true;
      blockButton.classList.add('opacity-60', 'cursor-not-allowed');
      blockButton.textContent = <?= json_encode(__('Passkeys are not supported in this browser.')) ?>;
      return;
    }

    const base64urlToBuffer = (value) => {
      const normalized = value.replace(/-/g, '+').replace(/_/g, '/');
      const padded = normalized + '='.repeat((4 - (normalized.length % 4)) % 4);
      const binary = atob(padded);
      const bytes = new Uint8Array(binary.length);
      for (let i = 0; i < binary.length; i += 1) {
        bytes[i] = binary.charCodeAt(i);
      }
      return bytes.buffer;
    };

    const bufferToBase64url = (buffer) => {
      const bytes = new Uint8Array(buffer);
      let binary = '';
      bytes.forEach((b) => {
        binary += String.fromCharCode(b);
      });
      return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
    };

    const setLoading = (loading) => {
      blockButton.disabled = loading;
      if (loading) {
        blockButton.classList.add('opacity-70');
      } else {
        blockButton.classList.remove('opacity-70');
      }
    };

    const deviceLabel = () => {
      if (navigator.userAgentData && navigator.userAgentData.platform) {
        return navigator.userAgentData.platform + ' passkey';
      }
      if (navigator.platform) {
        return navigator.platform + ' passkey';
      }
      return <?= json_encode(__('Passkey')) ?>;
    };

    blockButton.addEventListener('click', async () => {
      clearMessage();
      setLoading(true);
      try {
        const optionsResp = await fetch('/webauthn/options/register', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': <?= json_encode(csrf_token()) ?>,
          },
          body: '{}',
        });
        const optionsJson = await optionsResp.json();
        if (!optionsResp.ok || !optionsJson.success) {
          const err = optionsJson && optionsJson.error ? optionsJson.error : <?= json_encode(__('Could not start passkey registration.')) ?>;
          throw new Error(err);
        }

        const publicKey = optionsJson.publicKey;
        publicKey.challenge = base64urlToBuffer(publicKey.challenge);
        if (publicKey.user && publicKey.user.id) {
          publicKey.user.id = base64urlToBuffer(publicKey.user.id);
        }
        if (Array.isArray(publicKey.excludeCredentials)) {
          publicKey.excludeCredentials = publicKey.excludeCredentials.map((cred) => ({
            ...cred,
            id: base64urlToBuffer(cred.id),
          }));
        }

        const credential = await navigator.credentials.create({ publicKey });
        if (!credential) {
          throw new Error(<?= json_encode(__('Biometric prompt was dismissed.')) ?>);
        }

        const attestation = {
          id: credential.id,
          rawId: bufferToBase64url(credential.rawId),
          type: credential.type,
          response: {
            clientDataJSON: bufferToBase64url(credential.response.clientDataJSON),
            attestationObject: bufferToBase64url(credential.response.attestationObject),
          },
        };

        const registerResp = await fetch('/webauthn/register', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': <?= json_encode(csrf_token()) ?>,
          },
          body: JSON.stringify({ credential: attestation, label: deviceLabel() }),
        });
        const registerJson = await registerResp.json();
        if (!registerResp.ok || !registerJson.success) {
          const err = registerJson && registerJson.error ? registerJson.error : <?= json_encode(__('Could not register passkey.')) ?>;
          throw new Error(err);
        }

        showMessage(<?= json_encode(__('Passkey added. Reloading…')) ?>, 'success');
        setTimeout(() => {
          window.location.reload();
        }, 400);
      } catch (error) {
        if (error && (error.name === 'AbortError' || error.name === 'NotAllowedError')) {
          showMessage(<?= json_encode(__('Biometric prompt was dismissed.')) ?>);
        } else {
          showMessage(error && error.message ? error.message : <?= json_encode(__('Could not register passkey.')) ?>);
        }
      } finally {
        setLoading(false);
      }
    });
  })();
</script>
