<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../fx.php';

function require_onboarding_gate(PDO $pdo, int $step){
  // optional: prevent skipping steps
  $q = $pdo->prepare('SELECT onboard_step FROM users WHERE id=?');
  $q->execute([uid()]);
  $s = (int)$q->fetchColumn();
  if ($s < $step-1) redirect('/onboard/rules'); // go back to start
}

function set_step(PDO $pdo, int $step){
  $pdo->prepare('UPDATE users SET onboard_step=? WHERE id=?')->execute([$step, uid()]);
}

/** STEP 2: Cashflow rules */
function onboard_rules_form(PDO $pdo){
  set_step($pdo, 2);
  view('onboard/rules', [
    // helpful copy, examples are rendered in the view
  ]);
}

function onboard_rules_submit(PDO $pdo){
  verify_csrf(); require_login();
  // Expect an array of rules: [{label, percent, applies_to}], minimal MVP:
  // We'll store into cashflow_rules table you already have.
  $rows = $_POST['rules'] ?? [];  // rules[label][], rules[percent][] etc. if you prefer flat fields
  $u = uid();

  // Simple example parser (adapt to your form names):
  $pdo->beginTransaction();
  try {
    // Optional: wipe any existing rules the user created in onboarding
    // $pdo->prepare('DELETE FROM cashflow_rules WHERE user_id=?')->execute([$u]);

    foreach ($rows as $r) {
      $label = trim($r['label'] ?? '');
      $pct   = (float)($r['percent'] ?? 0);
      if ($label==='' || $pct<=0) continue;
      $pdo->prepare('INSERT INTO cashflow_rules(user_id,label,percent) VALUES (?,?,?)')
          ->execute([$u,$label,$pct]);
    }
    $pdo->commit();
  } catch(Throwable $e) {
    $pdo->rollBack();
  }

  set_step($pdo, 3);
  redirect('/onboard/currencies');
}

function onboard_currencies_form(PDO $pdo){
  require_login(); set_step($pdo, 3);
  // pre-fill with geo/IP guess later; for now show USD/EUR/HUF
  $uc = $pdo->prepare("SELECT code,is_main FROM user_currencies WHERE user_id=? ORDER BY is_main DESC, code");
  $uc->execute([uid()]); $list = $uc->fetchAll(PDO::FETCH_ASSOC);
  view('onboard/currencies', compact('list'));
}

function onboard_currencies_submit(PDO $pdo){
  verify_csrf(); require_login(); $u=uid();
  $codes = array_filter(array_map('trim', $_POST['codes'] ?? []));
  $main  = strtoupper(trim($_POST['main'] ?? ''));

  if (!count($codes)) {
    // sensible fallback
    $codes = ['USD']; $main = 'USD';
  }
  $pdo->beginTransaction();
  try {
    $pdo->prepare('DELETE FROM user_currencies WHERE user_id=?')->execute([$u]);
    foreach ($codes as $c) {
      $c = strtoupper($c);
      $pdo->prepare('INSERT INTO user_currencies(user_id,code,is_main) VALUES(?,?,?)')
          ->execute([$u,$c, (strtoupper($main) === $c)]);
    }
    $pdo->commit();
  } catch(Throwable $e) { $pdo->rollBack(); }

  set_step($pdo, 4);
  redirect('/onboard/incomes');
}

function onboard_incomes_form(PDO $pdo){
  set_step($pdo, 4);
  // show 1–2 rows to add (label, amount, currency, category optional)
  $uc = $pdo->prepare("SELECT code,is_main FROM user_currencies WHERE user_id=? ORDER BY is_main DESC, code");
  $uc->execute([uid()]); $curr = $uc->fetchAll(PDO::FETCH_ASSOC);
  view('onboard/incomes', compact('curr'));
}

function onboard_incomes_submit(PDO $pdo){
  verify_csrf(); require_login(); $u=uid();
  $rows = $_POST['incomes'] ?? [];
  $pdo->beginTransaction();
  try {
    foreach ($rows as $r) {
      $label = trim($r['label'] ?? 'Salary');
      $amount= (float)($r['amount'] ?? 0);
      $cur   = strtoupper(trim($r['currency'] ?? ''));
      if ($amount <= 0) continue;
      $pdo->prepare("INSERT INTO basic_incomes(user_id,label,amount,currency,valid_from) VALUES (?,?,?,?,CURRENT_DATE)")
          ->execute([$u,$label,$amount,$cur ?: null]);
    }
    $pdo->commit();
  } catch(Throwable $e) { $pdo->rollBack(); }

  set_step($pdo, 5);
  redirect('/onboard/categories');
}

function onboard_categories_form(PDO $pdo){
  set_step($pdo, 5);
  $suggest = [
    'income'   => ['Salary','Bonus','Refunds'],
    'spending' => ['Rent','Utilities','Groceries','Transport','Eating out','Entertainment','Subscriptions'],
  ];
  view('onboard/categories', compact('suggest'));
}

function onboard_categories_submit(PDO $pdo){
  verify_csrf(); require_login(); $u=uid();
  $selIn  = $_POST['income']   ?? [];
  $selOut = $_POST['spending'] ?? [];
  $color  = '#6B7280';

  $pdo->beginTransaction();
  try {
    foreach ($selIn as $label) {
      $pdo->prepare("INSERT INTO categories(user_id,label,kind,color) VALUES (?,?,?,?)")
          ->execute([$u, trim($label),'income',$color]);
    }
    foreach ($selOut as $label) {
      $pdo->prepare("INSERT INTO categories(user_id,label,kind,color) VALUES (?,?,?,?)")
          ->execute([$u, trim($label),'spending',$color]);
    }
    $pdo->commit();
  } catch(Throwable $e){ $pdo->rollBack(); }

  set_step($pdo, 6);
  redirect('/onboard/done');
}

function onboard_done(PDO $pdo){
  set_step($pdo, 6);
  // Optionally: create EF system categories if not present yet
  require_once __DIR__ . '/../helpers_ef.php';
  ef_ensure_categories($pdo, uid());

  view('onboard/done', []);
}

// Pull a master list once (pass to the view). Replace/extend as needed.
function onboard_all_currencies(): array {
  return [
    ['code'=>'USD','name'=>'United States Dollar'],
    ['code'=>'EUR','name'=>'Euro'],
    ['code'=>'HUF','name'=>'Hungarian Forint'],
    ['code'=>'GBP','name'=>'Pound Sterling'],
    ['code'=>'CHF','name'=>'Swiss Franc'],
    ['code'=>'JPY','name'=>'Japanese Yen'],
    ['code'=>'CAD','name'=>'Canadian Dollar'],
    ['code'=>'AUD','name'=>'Australian Dollar'],
    ['code'=>'CZK','name'=>'Czech Koruna'],
    ['code'=>'PLN','name'=>'Polish Złoty'],
    ['code'=>'SEK','name'=>'Swedish Krona'],
    ['code'=>'NOK','name'=>'Norwegian Krone'],
    ['code'=>'DKK','name'=>'Danish Krone'],
    ['code'=>'RON','name'=>'Romanian Leu'],
    ['code'=>'TRY','name'=>'Turkish Lira'],
    ['code'=>'INR','name'=>'Indian Rupee'],
    ['code'=>'CNY','name'=>'Chinese Yuan'],
    ['code'=>'NZD','name'=>'New Zealand Dollar'],
    ['code'=>'SGD','name'=>'Singapore Dollar'],
    ['code'=>'ZAR','name'=>'South African Rand'],
  ];
}

function onboard_currencies_index(PDO $pdo) {
  require_login(); $u = uid();

  // NOTE: only code + is_main; no id
  $q = $pdo->prepare("SELECT code, is_main FROM user_currencies WHERE user_id=? ORDER BY is_main DESC, code");
  $q->execute([$u]);
  $currencies = $q->fetchAll(PDO::FETCH_ASSOC);

  $allCurrencies = onboard_all_currencies();
  view('onboard/currencies', compact('currencies','allCurrencies'));
}

function onboard_currencies_add(PDO $pdo) {
  verify_csrf(); require_login(); $u = uid();

  $code  = strtoupper(trim($_POST['code'] ?? ''));
  $isMain = !empty($_POST['is_main']);

  if ($code === '') { $_SESSION['flash']='Pick a currency.'; redirect('/onboard/currencies'); }

  // validate code
  $valid = array_filter(onboard_all_currencies(), fn($c) => strtoupper($c['code']) === $code);
  if (!$valid) { $_SESSION['flash']='Unknown currency code.'; redirect('/onboard/currencies'); }

  // no duplicates
  $exists = $pdo->prepare("SELECT 1 FROM user_currencies WHERE user_id=? AND UPPER(code)=?");
  $exists->execute([$u,$code]);
  if ($exists->fetchColumn()) { $_SESSION['flash']='You already added this currency.'; redirect('/onboard/currencies'); }

  $pdo->beginTransaction();
  try {
    // ensure exactly one main
    if ($isMain) {
      $pdo->prepare("UPDATE user_currencies SET is_main=FALSE WHERE user_id=?")->execute([$u]);
    } else {
      $hasMain = $pdo->prepare("SELECT 1 FROM user_currencies WHERE user_id=? AND is_main=TRUE");
      $hasMain->execute([$u]);
      if (!$hasMain->fetch()) $isMain = true;
    }

    $ins = $pdo->prepare("INSERT INTO user_currencies(user_id, code, is_main) VALUES (?,?,?)");
    $ins->execute([$u, $code, $isMain ? 1 : 0]);

    $pdo->commit();
    $_SESSION['flash']='Currency added.';
  } catch (Throwable $e) {
    $pdo->rollBack();
    $_SESSION['flash']='Could not add currency.';
  }
  redirect('/onboard/currencies'); // <-- important
}

function onboard_currencies_delete(PDO $pdo) {
  verify_csrf(); require_login(); $u = uid();
  $code = strtoupper(trim($_POST['code'] ?? ''));
  if ($code === '') { redirect('/onboard/currencies'); }

  $row = $pdo->prepare("SELECT code, is_main FROM user_currencies WHERE user_id=? AND UPPER(code)=?");
  $row->execute([$u,$code]);
  $cur = $row->fetch(PDO::FETCH_ASSOC);
  if (!$cur) { redirect('/onboard/currencies'); }

  $cnt = $pdo->prepare("SELECT COUNT(*) FROM user_currencies WHERE user_id=?");
  $cnt->execute([$u]); 
  if ((int)$cnt->fetchColumn() <= 1) { $_SESSION['flash']='You must keep at least one currency.'; redirect('/onboard/currencies'); }

  $pdo->beginTransaction();
  try {
    $pdo->prepare("DELETE FROM user_currencies WHERE user_id=? AND UPPER(code)=?")->execute([$u,$code]);

    if (!empty($cur['is_main'])) {
      $first = $pdo->prepare("SELECT code FROM user_currencies WHERE user_id=? ORDER BY code LIMIT 1");
      $first->execute([$u]); 
      if ($newMain = $first->fetchColumn()) {
        $pdo->prepare("UPDATE user_currencies SET is_main=FALSE WHERE user_id=?")->execute([$u]);
        $pdo->prepare("UPDATE user_currencies SET is_main=TRUE WHERE user_id=? AND code=?")->execute([$u,$newMain]);
      }
    }

    $pdo->commit();
    $_SESSION['flash']='Currency removed.';
  } catch (Throwable $e) {
    $pdo->rollBack();
    $_SESSION['flash']='Could not remove currency.';
  }
  redirect('/onboard/currencies'); // <-- important
}

function onboard_has_rules(PDO $pdo, int $u): bool {
  $stmt = $pdo->prepare("SELECT 1 FROM cashflow_rules WHERE user_id=? LIMIT 1");
  $stmt->execute([$u]);
  return (bool)$stmt->fetchColumn();
}

function onboard_has_currencies(PDO $pdo, int $u): bool {
  // At least one currency and exactly one main
  $c1 = $pdo->prepare("SELECT COUNT(*) FROM user_currencies WHERE user_id=?");
  $c1->execute([$u]);
  $cnt = (int)$c1->fetchColumn();

  $c2 = $pdo->prepare("SELECT COUNT(*) FROM user_currencies WHERE user_id=? AND is_main=TRUE");
  $c2->execute([$u]);
  $mains = (int)$c2->fetchColumn();

  return $cnt >= 1 && $mains === 1;
}

function onboard_has_income(PDO $pdo, int $u): bool {
  // basic incomes step completed if at least one exists
  $stmt = $pdo->prepare("SELECT 1 FROM basic_incomes WHERE user_id=? LIMIT 1");
  $stmt->execute([$u]);
  return (bool)$stmt->fetchColumn();
}

function onboard_has_categories(PDO $pdo, int $u): bool {
  // basic categories step completed if user created or accepted defaults
  $stmt = $pdo->prepare("SELECT 1 FROM categories WHERE user_id=? LIMIT 1");
  $stmt->execute([$u]);
  return (bool)$stmt->fetchColumn();
}

// --- the dispatcher to the next step ---
function onboard_next(PDO $pdo) {
  require_login(); $u = uid();

  // Ordered flow:
  // 1) Rules → 2) Currencies → 3) Incomes → 4) Categories → 5) Done
  if (!onboard_has_rules($pdo, $u))       { redirect('/onboard/rules'); }
  if (!onboard_has_currencies($pdo, $u))  { redirect('/onboard/currencies'); }
  if (!onboard_has_income($pdo, $u))      { redirect('/onboard/income'); }
  if (!onboard_has_categories($pdo, $u))  { redirect('/onboard/categories'); }

  // All set → final screen
  redirect('/onboard/done');
}

function onboard_income(PDO $pdo){
  require_login(); $u = uid();

  // user currencies for selector
  $uc = $pdo->prepare("SELECT code, is_main FROM user_currencies WHERE user_id=? ORDER BY is_main DESC, code");
  $uc->execute([$u]);
  $userCurrencies = $uc->fetchAll(PDO::FETCH_ASSOC) ?: [['code'=>'HUF','is_main'=>true]];

  // existing basic incomes (if any)
  $q = $pdo->prepare("
    SELECT b.id, b.label, b.amount, b.currency, b.valid_from, b.valid_to,
           c.label AS cat_label
      FROM basic_incomes b
      LEFT JOIN categories c ON c.id=b.category_id AND c.user_id=b.user_id
     WHERE b.user_id=?
     ORDER BY COALESCE(b.valid_from, CURRENT_DATE) DESC, b.id DESC
  ");
  $q->execute([$u]);
  $rows = $q->fetchAll(PDO::FETCH_ASSOC);

  // income categories for optional tagging
  $cats = $pdo->prepare("SELECT id,label FROM categories WHERE user_id=? AND kind='income' ORDER BY lower(label)");
  $cats->execute([$u]);
  $categories = $cats->fetchAll(PDO::FETCH_ASSOC);

  view('onboard/income', compact('rows','userCurrencies','categories'));
}

function onboard_income_add(PDO $pdo){
  verify_csrf(); require_login(); $u = uid();

  $label   = trim($_POST['label'] ?? '');
  $amount  = (float)($_POST['amount'] ?? 0);
  $currency= strtoupper(trim($_POST['currency'] ?? ''));
  $catId   = ($_POST['category_id'] ?? '') !== '' ? (int)$_POST['category_id'] : null;
  $from    = $_POST['valid_from'] ?: date('Y-m-d');

  if ($label === '' || $amount <= 0) {
    $_SESSION['flash'] = 'Please provide a name and a positive amount.';
    redirect('/onboard/income');
  }

  if ($currency === '') {
    $currency = fx_user_main($pdo, uid()) ?: 'HUF';
  }

  // validate category ownership
  if ($catId !== null) {
    $chk = $pdo->prepare("SELECT 1 FROM categories WHERE id=? AND user_id=? AND kind='income'");
    $chk->execute([$catId, $u]);
    if (!$chk->fetch()) $catId = null;
  }

  $ins = $pdo->prepare("
    INSERT INTO basic_incomes(user_id,label,amount,currency,category_id,valid_from)
    VALUES (?,?,?,?,?,?)
  ");
  $ins->execute([$u,$label,$amount,$currency,$catId,$from]);

  // After add, go to next step automatically
  redirect('/onboard/next');
}

function onboard_income_delete(PDO $pdo){
  verify_csrf(); require_login(); $u = uid();
  $id = (int)($_POST['id'] ?? 0);
  if ($id) {
    $pdo->prepare("DELETE FROM basic_incomes WHERE id=? AND user_id=?")->execute([$id,$u]);
  }
}
function onboard_categories_index(PDO $pdo){
  require_login(); $u = uid();

  $stmt = $pdo->prepare("
    SELECT id, label, kind, COALESCE(NULLIF(color,''), '#6B7280') AS color
    FROM categories
    WHERE user_id = ?
    ORDER BY kind, LOWER(label)
  ");
  $stmt->execute([$u]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  view('onboard/categories', compact('rows'));
}

function onboard_categories_add(PDO $pdo){
  verify_csrf(); require_login(); $u = uid();

  $kind  = (($_POST['kind'] ?? '') === 'spending') ? 'spending' : 'income';
  $label = trim($_POST['label'] ?? '');
  $color = trim($_POST['color'] ?? '#6B7280');
  if ($label === '') {
    $_SESSION['flash_err'] = 'Label is required.';
    redirect('/onboard/categories');
  }

  $ins = $pdo->prepare("
    INSERT INTO categories(user_id, label, kind, color)
    VALUES (?,?,?,?)
  ");
  $ins->execute([$u, $label, $kind, $color]);

  $_SESSION['flash_ok'] = 'Category added.';
  redirect('/onboard/categories');
}

function onboard_categories_delete(PDO $pdo){
  verify_csrf(); require_login(); $u = uid();

  $id = (int)($_POST['id'] ?? 0);
  if (!$id) { redirect('/onboard/categories'); }

  // Safe delete
  $del = $pdo->prepare("DELETE FROM categories WHERE id=? AND user_id=?");
  try {
    $del->execute([$id,$u]);
    $_SESSION['flash'] = 'Category removed.';
  } catch (Throwable $e) {
    $_SESSION['flash'] = 'Could not delete this category.';
  }
  redirect('/onboard/categories');
}

/* If you don’t already have normalize_hex() loaded here, add this tiny fallback: */
if (!function_exists('normalize_hex')) {
  function normalize_hex(?string $v): ?string {
    $v = strtoupper(trim((string)$v));
    return preg_match('/^#([0-9A-F]{3}|[0-9A-F]{6})$/', $v) ? $v : '#6B7280';
  }
}