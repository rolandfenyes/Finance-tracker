<?php
  $defaults = [
    'form_classes' => 'grid gap-4 md:grid-cols-12 md:items-end',
    'amount_id' => 'quick-add-amount',
    'button_wrapper_classes' => 'md:col-span-4 flex md:justify-end',
    'button_classes' => 'btn btn-primary w-full md:w-auto',
    'button_label' => __('Add'),
    'data_restore_focus' => '#quick-add-amount',
    'data_restore_focus_select' => true,
    'render_button' => true,
    'form_id' => null,
  ];
  $cfg = array_merge($defaults, $quickAddConfig ?? []);
  $amountId = $cfg['amount_id'];
  $restoreFocus = $cfg['data_restore_focus'];
  $restoreSelect = $cfg['data_restore_focus_select'];
  $renderButton = $cfg['render_button'];
  $formId = $cfg['form_id'];
?>
<form
  class="<?= htmlspecialchars($cfg['form_classes']) ?>"
  method="post"
  action="/months/tx/add"
  data-restore-focus="<?= htmlspecialchars($restoreFocus) ?>"
  data-restore-focus-select="<?= $restoreSelect ? 'true' : 'false' ?>"
  <?= $formId ? 'id="'.htmlspecialchars($formId).'"' : '' ?>
>
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
  <input type="hidden" name="y" value="<?= $y ?>" />
  <input type="hidden" name="m" value="<?= $m ?>" />

  <div class="field md:col-span-2">
    <label class="label"><?= __('Type') ?></label>
    <select name="kind" class="select">
      <option value="income">Income</option>
      <option value="spending" selected>Spending</option>
    </select>
  </div>

  <div class="field md:col-span-3">
    <label class="label"><?= __('Category') ?></label>
    <select name="category_id" class="select">
      <option value="">— Category —</option>
      <?php foreach($cats as $c): ?>
        <option value="<?= $c['id'] ?>"><?= ucfirst($c['kind']) ?> · <?= htmlspecialchars($c['label']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="field md:col-span-4">
    <label class="label"><?= __('Amount') ?></label>
    <div class="grid grid-cols-5 gap-2">
      <input id="<?= htmlspecialchars($amountId) ?>" name="amount" type="number" step="0.01" class="input col-span-3" placeholder="0.00" required />
      <select name="currency" class="select col-span-2">
        <?php foreach ($userCurrencies as $c): ?>
          <option value="<?= htmlspecialchars($c['code']) ?>" <?= $c['is_main'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($c['code']) ?>
          </option>
        <?php endforeach; ?>
        <?php if (!count($userCurrencies)): ?><option value="HUF">HUF</option><?php endif; ?>
      </select>
    </div>
  </div>

  <div class="field md:col-span-2">
    <label class="label"><?= __('Date') ?></label>
    <input name="occurred_on" type="date" value="<?= date('Y-m-d') ?>" class="input" />
  </div>

  <div class="field md:col-span-8">
    <label class="label"><?= __('Note') ?> <span class="help">(<?= __('optional') ?>)</span></label>
    <input name="note" class="input" placeholder="<?= __('Add a short note…') ?>" />
  </div>

  <?php if ($renderButton): ?>
    <div class="<?= htmlspecialchars($cfg['button_wrapper_classes']) ?>">
      <button class="<?= htmlspecialchars($cfg['button_classes']) ?>"><?= htmlspecialchars($cfg['button_label']) ?></button>
    </div>
  <?php endif; ?>
</form>
<?php unset($quickAddConfig); ?>
