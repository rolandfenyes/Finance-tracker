<?php

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../fx.php';
require_once __DIR__ . '/../stocks/PriceProviderAdapter.php';
require_once __DIR__ . '/../stocks/Adapters/NullPriceProvider.php';
require_once __DIR__ . '/../stocks/Adapters/FinnhubAdapter.php';
require_once __DIR__ . '/../stocks/PriceDataService.php';
require_once __DIR__ . '/../stocks/PortfolioService.php';
require_once __DIR__ . '/../stocks/TradeService.php';
require_once __DIR__ . '/../stocks/SignalsService.php';
require_once __DIR__ . '/../stocks/ChartsService.php';
require_once __DIR__ . '/../stocks/CashService.php';

use Stocks\Adapters\FinnhubAdapter;
use Stocks\Adapters\NullPriceProvider;
use Stocks\ChartsService;
use Stocks\CashService;
use Stocks\PortfolioService;
use Stocks\PriceDataService;
use Stocks\SignalsService;
use Stocks\TradeService;

function stocks_index(PDO $pdo): void
{
    require_login();
    $userId = uid();
    $priceService = stocks_price_service($pdo);
    $portfolio = stocks_portfolio_service($pdo);
    $signalsService = new SignalsService($pdo, $priceService);
    $chartsService = new ChartsService($pdo, $priceService);

    $filters = [
        'search' => $_GET['q'] ?? null,
        'sector' => $_GET['sector'] ?? null,
        'currency' => $_GET['currency'] ?? null,
        'watchlist_only' => !empty($_GET['watchlist']),
        'realized_period' => $_GET['period'] ?? null,
    ];
    $overview = $portfolio->buildOverview($userId, $filters, false);
    $holdings = $overview['holdings'];
    $totals = $overview['totals'] + ['user_id' => $userId, 'default_target' => 10.0];

    $insights = [];
    foreach ($holdings as $holding) {
        $insights[$holding['symbol']] = $signalsService->analyze($userId, $holding, ['prev_close' => $holding['prev_close'] ?? null], $totals);
    }

    $chartRange = strtoupper($_GET['chartRange'] ?? '6M');
    $portfolioChart = $chartsService->portfolioValueSeries($userId, $chartRange);
    $series = array_values(array_filter($portfolioChart['series'], static fn($value) => $value !== null));
    $startValue = $series ? (float)$series[0] : 0.0;
    $endValue = $series ? (float)$series[count($series) - 1] : $startValue;
    $changeValue = $endValue - $startValue;
    $changePct = ($startValue > 0) ? ($changeValue / $startValue) * 100 : 0.0;
    $chartMeta = [
        'range' => $chartRange,
        'start' => $startValue,
        'end' => $endValue,
        'change' => $changeValue,
        'change_pct' => $changePct,
    ];
    $refreshSeconds = stocks_refresh_seconds($userId, $pdo);

    $userCurrencies = stocks_user_currencies($pdo, $userId);

    view('stocks/index', [
        'overview' => $overview,
        'insights' => $insights,
        'portfolioChart' => $portfolioChart,
        'chartMeta' => $chartMeta,
        'chartRange' => $chartRange,
        'filters' => $filters,
        'refreshSeconds' => $refreshSeconds,
        'userCurrencies' => $userCurrencies,
        'error' => $_GET['error'] ?? null,
    ]);
}

function stocks_transactions(PDO $pdo): void
{
    require_login();
    $userId = uid();
    $priceService = stocks_price_service($pdo);
    $portfolio = stocks_portfolio_service($pdo);

    $transactions = $portfolio->listTransactions($userId);
    $baseCurrency = fx_user_main($pdo, $userId) ?: 'EUR';

    view('stocks/transactions', [
        'transactions' => $transactions,
        'baseCurrency' => $baseCurrency,
    ]);
}

function stocks_detail(PDO $pdo, string $symbol): void
{
    require_login();
    $userId = uid();
    $symbol = strtoupper($symbol);
    $priceService = stocks_price_service($pdo);
    $signalsService = new SignalsService($pdo, $priceService);
    $chartsService = new ChartsService($pdo, $priceService);

    $stockRow = stocks_fetch_stock($pdo, $symbol);
    if (!$stockRow) {
        http_response_code(404);
        view('errors/404');
        return;
    }

    $baseCurrency = fx_user_main($pdo, $userId);
    $positionStmt = $pdo->prepare('SELECT sp.*, s.name, s.symbol, s.currency FROM stock_positions sp JOIN stocks s ON s.id = sp.stock_id WHERE sp.user_id=? AND UPPER(s.symbol)=?');
    $positionStmt->execute([$userId, $symbol]);
    $position = $positionStmt->fetch(PDO::FETCH_ASSOC);

    $quote = $priceService->getLiveQuotes([$symbol]);
    $quoteRow = $quote[0] ?? null;
    $historyRange = $_GET['range'] ?? '6M';
    $historyBounds = stocks_history_bounds($historyRange);
    $priceHistory = $priceService->getDailyHistory($symbol, $historyBounds['start'], $historyBounds['end']);

    $holdingLike = [
        'symbol' => $symbol,
        'currency' => $stockRow['currency'] ?? 'USD',
        'avg_cost' => $position['avg_cost_ccy'] ?? 0,
        'qty' => $position['qty'] ?? 0,
        'last_price' => $quoteRow['last'] ?? null,
        'prev_close' => $quoteRow['prev_close'] ?? null,
        'weight_pct' => $position && ($position['qty'] ?? 0) > 0 ? 0.0 : 0.0,
    ];
    $totals = ['user_id' => $userId, 'default_target' => 10.0];
    $insights = $signalsService->analyze($userId, $holdingLike, ['prev_close' => $quoteRow['prev_close'] ?? null], $totals);

    $positionSeries = $chartsService->positionValueSeries($userId, $symbol, $historyRange);

    $realizedStmt = $pdo->prepare('SELECT COALESCE(SUM(realized_pl_base),0) FROM stock_realized_pl WHERE user_id=? AND stock_id=? AND DATE_TRUNC(\'year\', closed_at)=DATE_TRUNC(\'year\', CURRENT_DATE)');
    $realizedStmt->execute([$userId, $stockRow['id']]);
    $realizedYtd = (float)$realizedStmt->fetchColumn();

    $settings = stocks_settings($pdo, $userId);
    $isWatched = stocks_is_watched($pdo, $userId, (int)$stockRow['id']);

    $userCurrencies = stocks_user_currencies($pdo, $userId);

    view('stocks/show', [
        'stock' => $stockRow,
        'position' => $position,
        'quote' => $quoteRow,
        'priceHistory' => $priceHistory,
        'insights' => $insights,
        'positionSeries' => $positionSeries,
        'realizedYtd' => $realizedYtd,
        'settings' => $settings,
        'isWatched' => $isWatched,
        'historyRange' => $historyRange,
        'baseCurrency' => $baseCurrency,
        'userCurrencies' => $userCurrencies,
    ]);
}

function stocks_trade(PDO $pdo): void
{
    verify_csrf();
    require_login();
    $userId = uid();
    $tradeService = new TradeService($pdo);
    $side = strtoupper($_POST['side'] ?? '');
    $symbol = strtoupper(trim($_POST['symbol'] ?? ''));
    $quantity = (float)($_POST['quantity'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $currency = $_POST['currency'] ?? 'USD';
    $fee = isset($_POST['fee']) ? (float)$_POST['fee'] : 0.0;
    $date = $_POST['trade_date'] ?? date('Y-m-d');
    $time = $_POST['trade_time'] ?? '15:30:00';
    $note = $_POST['note'] ?? null;
    $marketInput = $_POST['market'] ?? null;
    $market = $marketInput !== null ? strtoupper(trim((string)$marketInput)) : null;
    if ($market === '') {
        $market = null;
    }
    $executedAt = trim($date . ' ' . $time);

    $payload = [
        'symbol' => $symbol,
        'side' => $side,
        'quantity' => $quantity,
        'price' => $price,
        'currency' => $currency,
        'fee' => $fee,
        'executed_at' => $executedAt,
        'note' => $note,
        'market' => $market,
    ];
    try {
        $tradeService->recordTrade($userId, $payload);
        stocks_portfolio_service($pdo)->invalidateOverviewCache($userId);
        $destination = '/stocks/' . urlencode($symbol);
        if (!stocks_table_exists($pdo, 'stocks')) {
            $destination = '/stocks';
        }
        redirect($destination);
    } catch (Throwable $e) {
        error_log('[stocks_trade] ' . $e->getMessage());
        redirect('/stocks?error=trade');
    }
}

function stocks_import(PDO $pdo): void
{
    verify_csrf();
    require_login();
    $userId = uid();
    $wantsJson = stocks_request_wants_json();
    $responseMeta = [
        'imported' => 0,
        'cash_recorded' => 0,
        'skipped' => 0,
        'ignored' => 0,
    ];

    if (empty($_FILES['csv']) || !isset($_FILES['csv']['tmp_name'])) {
        $_SESSION['flash'] = 'Please choose a CSV file to upload.';
        stocks_import_output($wantsJson, false, $_SESSION['flash'], $responseMeta);
        return;
    }

    $file = $_FILES['csv'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $_SESSION['flash'] = 'Upload failed. Please try again.';
        stocks_import_output($wantsJson, false, $_SESSION['flash'], $responseMeta);
        return;
    }

    $tmpName = $file['tmp_name'];
    if (!is_uploaded_file($tmpName)) {
        $_SESSION['flash'] = 'Invalid upload. Please try again.';
        stocks_import_output($wantsJson, false, $_SESSION['flash'], $responseMeta);
        return;
    }

    $delimiter = stocks_import_detect_delimiter($tmpName);

    $handle = fopen($tmpName, 'r');
    if (!$handle) {
        $_SESSION['flash'] = 'Could not read the uploaded file.';
        stocks_import_output($wantsJson, false, $_SESSION['flash'], $responseMeta);
        return;
    }

    $headerRow = fgetcsv($handle, 0, $delimiter, '"', '\\');
    if ($headerRow === false) {
        fclose($handle);
        $_SESSION['flash'] = 'The CSV file appears to be empty.';
        stocks_import_output($wantsJson, false, $_SESSION['flash'], $responseMeta);
        return;
    }

    $columnMap = stocks_import_header_map($headerRow);
    if (!array_key_exists('date', $columnMap) || !array_key_exists('type', $columnMap)) {
        fclose($handle);
        $_SESSION['flash'] = 'The CSV is missing required headers (Date / Type).';
        stocks_import_output($wantsJson, false, $_SESSION['flash'], $responseMeta);
        return;
    }

    $tradeService = new TradeService($pdo);
    $cashService = new CashService($pdo);
    $imported = 0;
    $skipped = 0;
    $ignored = 0;
    $cashRecorded = 0;
    $pendingReorg = [];
    $skipSamples = [];
    $ignoreSamples = [];
    $cashSamples = [];

    while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
        if (stocks_import_row_is_empty($row)) {
            continue;
        }

        $record = stocks_import_build_record($row, $columnMap);
        $typeRaw = trim((string)($record['type'] ?? ''));
        $type = strtoupper($typeRaw);
        $symbol = strtoupper(trim((string)($record['ticker'] ?? '')));
        $side = stocks_import_detect_side($type);

        if ($side === null || $symbol === '') {
            if ($symbol !== '' && str_contains($type, 'MERGER') && str_contains($type, 'STOCK')) {
                $quantity = abs(stocks_import_to_float($record['quantity'] ?? null));
                if ($quantity <= 0) {
                    $skipped++;
                    if (count($skipSamples) < 3) {
                        $skipSamples[] = $symbol . ' (merger quantity missing)';
                    }
                    continue;
                }

                $currency = strtoupper(trim((string)($record['currency'] ?? 'USD')));
                if ($currency === '') {
                    $currency = 'USD';
                }

                $executedAtRaw = trim((string)($record['date'] ?? ''));
                try {
                    $executedAt = $executedAtRaw !== '' ? new DateTime($executedAtRaw) : new DateTime();
                } catch (Throwable $e) {
                    $skipped++;
                    if (count($skipSamples) < 3) {
                        $skipSamples[] = $symbol . ' (merger date invalid)';
                    }
                    continue;
                }

                $pendingReorg[$symbol] = [
                    'quantity' => $quantity,
                    'currency' => $currency,
                    'executed_at' => $executedAt,
                    'note' => $type !== '' ? ('CSV import: ' . $type) : 'CSV import',
                ];
                continue;
            }

            if ($symbol !== '' && str_contains($type, 'MERGER') && str_contains($type, 'CASH') && isset($pendingReorg[$symbol])) {
                $pending = $pendingReorg[$symbol];
                unset($pendingReorg[$symbol]);

                $currency = strtoupper(trim((string)($record['currency'] ?? '')));
                if ($currency === '') {
                    $currency = $pending['currency'];
                }

                $executedAtRaw = trim((string)($record['date'] ?? ''));
                try {
                    $executedAt = $executedAtRaw !== '' ? new DateTime($executedAtRaw) : clone $pending['executed_at'];
                } catch (Throwable $e) {
                    $executedAt = clone $pending['executed_at'];
                }

                $amount = abs(stocks_import_cash_amount($record, $type));
                if ($amount <= 0) {
                    $skipped++;
                    if (count($skipSamples) < 3) {
                        $skipSamples[] = $symbol . ' (merger cash missing)';
                    }
                    continue;
                }

                $quantity = (float)$pending['quantity'];
                if ($quantity <= 0) {
                    $skipped++;
                    if (count($skipSamples) < 3) {
                        $skipSamples[] = $symbol . ' (merger quantity missing)';
                    }
                    continue;
                }

                $price = $amount / $quantity;
                if ($price <= 0) {
                    $skipped++;
                    if (count($skipSamples) < 3) {
                        $skipSamples[] = $symbol . ' (merger cash insufficient)';
                    }
                    continue;
                }

                $payload = [
                    'symbol' => $symbol,
                    'side' => 'SELL',
                    'quantity' => $quantity,
                    'price' => $price,
                    'currency' => $currency,
                    'executed_at' => $executedAt->format('Y-m-d H:i:sP'),
                    'note' => $pending['note'],
                    'cash_total' => $amount,
                ];

                try {
                    $tradeService->recordTrade($userId, $payload);
                    $imported++;
                } catch (Throwable $e) {
                    $skipped++;
                    if (count($skipSamples) < 3) {
                        $skipSamples[] = $symbol . ' (' . $e->getMessage() . ')';
                    }
                }

                continue;
            }

            if (stocks_import_is_cash_like($type)) {
                $currency = strtoupper(trim((string)($record['currency'] ?? 'USD')));
                if ($currency === '') {
                    $currency = 'USD';
                }
                $executedAtRaw = trim((string)($record['date'] ?? ''));
                try {
                    $executedAt = $executedAtRaw !== '' ? new DateTime($executedAtRaw) : new DateTime();
                } catch (Throwable $e) {
                    $skipped++;
                    if (count($skipSamples) < 3) {
                        $skipSamples[] = 'Cash row (bad date)';
                    }
                    continue;
                }

                $amount = stocks_import_cash_amount($record, $type);
                if ($amount === 0.0) {
                    $skipped++;
                    if (count($skipSamples) < 3) {
                        $skipSamples[] = 'Cash row (missing amount)';
                    }
                    continue;
                }

                try {
                    $cashService->recordMovement($userId, $amount, $currency, $executedAt, $type !== '' ? ('CSV import: ' . $type) : 'CSV import');
                    $cashRecorded++;
                    if (count($cashSamples) < 3) {
                        $cashSamples[] = sprintf('%s %s', moneyfmt(abs($amount), $currency), $amount > 0 ? 'added' : 'withdrawn');
                    }
                } catch (Throwable $e) {
                    $skipped++;
                    if (count($skipSamples) < 3) {
                        $skipSamples[] = 'Cash row (' . $e->getMessage() . ')';
                    }
                }
            } elseif (stocks_import_is_non_trade($type)) {
                $ignored++;
                if (count($ignoreSamples) < 3) {
                    $ignoreSamples[] = $type !== '' ? $type : 'Non-trade row';
                }
            } else {
                $skipped++;
                if (count($skipSamples) < 3) {
                    $skipSamples[] = ($symbol ?: 'Unknown symbol') . ' (' . ($type ?: 'Unknown type') . ')';
                }
            }
            continue;
        }

        $quantity = stocks_import_to_float($record['quantity'] ?? null);
        $price = stocks_import_to_float($record['price'] ?? null);
        if ($quantity <= 0 || $price <= 0) {
            $skipped++;
            if (count($skipSamples) < 3) {
                $skipSamples[] = $symbol . ' (missing qty/price)';
            }
            continue;
        }

        $currency = strtoupper(trim((string)($record['currency'] ?? 'USD')));
        if ($currency === '') {
            $currency = 'USD';
        }

        $executedAtRaw = trim((string)($record['date'] ?? ''));
        try {
            $executedAt = $executedAtRaw !== '' ? new DateTime($executedAtRaw) : new DateTime();
        } catch (Throwable $e) {
            $skipped++;
            if (count($skipSamples) < 3) {
                $skipSamples[] = $symbol . ' (bad date)';
            }
            continue;
        }

        $payload = [
            'symbol' => $symbol,
            'side' => $side,
            'quantity' => $quantity,
            'price' => $price,
            'currency' => $currency,
            'executed_at' => $executedAt->format('Y-m-d H:i:sP'),
            'note' => $type !== '' ? ('CSV import: ' . $type) : 'CSV import',
        ];

        $feeFromFile = stocks_import_to_float($record['fee'] ?? null);
        if ($feeFromFile > 0) {
            $payload['fee'] = $feeFromFile;
        }

        $totalAmount = stocks_import_to_float($record['total'] ?? null);
        if ($totalAmount !== 0.0) {
            $cashTotal = abs($totalAmount);
            if ($cashTotal > 0.0) {
                $payload['cash_total'] = $cashTotal;
            }

            if (!isset($payload['fee'])) {
                $notional = abs($quantity * $price);
                if ($notional > 0.0 && $cashTotal > 0.0) {
                    if ($side === 'BUY') {
                        $feeGuess = $cashTotal - $notional;
                    } else {
                        $feeGuess = $notional - $cashTotal;
                    }

                    if ($feeGuess > 0.005) {
                        $payload['fee'] = round($feeGuess, 2);
                    }
                }
            }
        }

        try {
            $tradeService->recordTrade($userId, $payload);
            $imported++;
        } catch (Throwable $e) {
            $skipped++;
            if (count($skipSamples) < 3) {
                $skipSamples[] = $symbol . ' (' . $e->getMessage() . ')';
            }
        }
    }

    if (!empty($pendingReorg)) {
        foreach ($pendingReorg as $symbol => $pending) {
            $skipped++;
            if (count($skipSamples) < 3) {
                $skipSamples[] = $symbol . ' (merger cash leg missing)';
            }
        }
    }

    fclose($handle);

    if ($imported > 0 || $cashRecorded > 0) {
        $messages = [];
        if ($imported > 0) {
            $messages[] = sprintf('Imported %d trade%s', $imported, $imported === 1 ? '' : 's');
        }
        if ($cashRecorded > 0) {
            $messages[] = sprintf('Recorded %d cash movement%s', $cashRecorded, $cashRecorded === 1 ? '' : 's');
        }
        $_SESSION['flash_success'] = implode(' and ', $messages) . ' from CSV.';
        if ($cashRecorded > 0 && !empty($cashSamples)) {
            $_SESSION['flash_success'] .= ' Examples: ' . implode('; ', $cashSamples);
        }
    }

    if ($ignored > 0) {
        $details = $ignoreSamples ? ' Examples: ' . implode('; ', $ignoreSamples) : '';
        $_SESSION['flash_success'] = ($_SESSION['flash_success'] ?? '')
            ? $_SESSION['flash_success'] . sprintf(' Skipped %d cash/dividend row%s.', $ignored, $ignored === 1 ? '' : 's')
            : sprintf('Skipped %d cash/dividend row%s.', $ignored, $ignored === 1 ? '' : 's');
        if ($details !== '') {
            $_SESSION['flash_success'] .= $details;
        }
    }

    if ($skipped > 0) {
        $message = sprintf('Skipped %d row%s due to validation issues.', $skipped, $skipped === 1 ? '' : 's');
        if (!empty($skipSamples)) {
            $message .= ' Examples: ' . implode('; ', $skipSamples);
        }
        $_SESSION['flash'] = $message;
    }

    if ($imported === 0 && $ignored === 0 && $skipped === 0 && $cashRecorded === 0) {
        $_SESSION['flash'] = 'No data rows were detected in the CSV file.';
    }

    $responseMeta = [
        'imported' => $imported,
        'cash_recorded' => $cashRecorded,
        'skipped' => $skipped,
        'ignored' => $ignored,
    ];
    $messagesOut = array_filter([
        'success' => $_SESSION['flash_success'] ?? null,
        'error' => $_SESSION['flash'] ?? null,
    ]);
    $primaryMessage = $messagesOut['success'] ?? $messagesOut['error'] ?? 'Import complete.';
    if (($imported + $cashRecorded) > 0) {
        stocks_portfolio_service($pdo)->invalidateOverviewCache($userId);
    }

    $payload = $responseMeta + ['messages' => $messagesOut];
    $wasSuccessful = ($imported + $cashRecorded) > 0 && $skipped === 0;
    if (!$wasSuccessful && ($imported + $cashRecorded) > 0) {
        $wasSuccessful = true; // partial success still returns HTTP 200 for UX reload
    }
    stocks_import_output($wantsJson, $wasSuccessful, $primaryMessage, $payload);
}

function stocks_cash_movement(PDO $pdo): void
{
    verify_csrf();
    require_login();
    $userId = uid();

    $action = strtolower(trim((string)($_POST['cash_action'] ?? 'deposit')));
    $currency = strtoupper(trim((string)($_POST['cash_currency'] ?? 'USD')));
    if ($currency === '') {
        $currency = 'USD';
    }

    $amount = stocks_import_to_float($_POST['cash_amount'] ?? null);
    if ($amount <= 0) {
        $_SESSION['flash'] = 'Please enter a cash amount greater than zero.';
        return;
    }

    $date = trim((string)($_POST['cash_date'] ?? date('Y-m-d')));
    $time = trim((string)($_POST['cash_time'] ?? date('H:i')));
    try {
        $executedAt = new DateTime(trim($date . ' ' . $time));
    } catch (Throwable $e) {
        $_SESSION['flash'] = 'Invalid date or time for the cash entry.';
        return;
    }

    $noteRaw = trim((string)($_POST['cash_note'] ?? ''));
    $note = $noteRaw !== '' ? $noteRaw : null;

    $amount = abs($amount);
    if ($action === 'withdraw') {
        $amount = -$amount;
    }

    $cashService = new CashService($pdo);
    try {
        $cashService->recordMovement($userId, $amount, $currency, $executedAt, $note);
        $_SESSION['flash_success'] = $amount >= 0
            ? sprintf('Added %s to your stock cash balance.', moneyfmt($amount, $currency))
            : sprintf('Withdrew %s from your stock cash balance.', moneyfmt(abs($amount), $currency));
        stocks_portfolio_service($pdo)->invalidateOverviewCache($userId);
    } catch (Throwable $e) {
        error_log('[stocks_cash_movement] ' . $e->getMessage());
        $_SESSION['flash'] = 'Unable to record the cash entry. Please ensure the latest migrations have been applied.';
    }
}

function stocks_delete_trade(PDO $pdo): void
{
    verify_csrf();
    require_login();
    $userId = uid();
    $tradeId = (int)($_POST['id'] ?? 0);
    if ($tradeId <= 0) {
        redirect('/stocks');
        return;
    }
    $tradeService = new TradeService($pdo);
    $tradeService->deleteTrade($userId, $tradeId);
    stocks_portfolio_service($pdo)->invalidateOverviewCache($userId);
    redirect('/stocks');
}

function stocks_clear_history(PDO $pdo): void
{
    verify_csrf();
    require_login();
    $userId = uid();
    $tradeService = new TradeService($pdo);

    try {
        $stats = $tradeService->clearUserHistory($userId);
        stocks_portfolio_service($pdo)->invalidateOverviewCache($userId);
        $totalRemoved = array_sum($stats);
        if ($totalRemoved === 0) {
            $_SESSION['flash'] = 'No stock trades were found to clear.';
            return;
        }

        $labels = [
            'trades' => 'trade',
            'positions' => 'position',
            'lots' => 'lot',
            'realized' => 'realized P/L entry',
            'snapshots' => 'snapshot',
            'cash' => 'cash ledger entry',
        ];

        $parts = [];
        foreach ($stats as $key => $count) {
            if ($count <= 0) {
                continue;
            }
            $label = $labels[$key] ?? $key;
            $parts[] = sprintf('%d %s%s', $count, $label, $count === 1 ? '' : 's');
        }

        if (!empty($parts)) {
            $_SESSION['flash_success'] = 'Cleared ' . implode(', ', $parts) . '.';
        } else {
            $_SESSION['flash_success'] = 'Stock trade history cleared.';
        }
    } catch (Throwable $e) {
        error_log('[stocks_clear_history] ' . $e->getMessage());
        $_SESSION['flash'] = 'Unable to clear stock history. Please try again.';
    }
}

function stocks_toggle_watch(PDO $pdo, string $symbol): void
{
    verify_csrf();
    require_login();
    $userId = uid();
    $stock = stocks_fetch_stock($pdo, strtoupper($symbol));
    if (!$stock) {
        redirect('/stocks/' . urlencode($symbol));
        return;
    }
    $existsStmt = $pdo->prepare('SELECT id FROM watchlist WHERE user_id=? AND stock_id=?');
    $existsStmt->execute([$userId, $stock['id']]);
    $exists = $existsStmt->fetchColumn();
    if ($exists) {
        $pdo->prepare('DELETE FROM watchlist WHERE id=?')->execute([$exists]);
    } else {
        $pdo->prepare('INSERT INTO watchlist(user_id, stock_id, created_at) VALUES(?,?, NOW()) ON CONFLICT DO NOTHING')->execute([$userId, $stock['id']]);
    }
    stocks_portfolio_service($pdo)->invalidateOverviewCache($userId);
    redirect('/stocks/' . urlencode($symbol));
}

/**
 * @return array<int, array{code:string,is_main?:mixed}>
 */
function stocks_user_currencies(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT code, is_main FROM user_currencies WHERE user_id=? ORDER BY is_main DESC, code');
    $stmt->execute([$userId]);
    $currencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($currencies) {
        return $currencies;
    }

    $main = fx_user_main($pdo, $userId);
    if ($main) {
        return [['code' => strtoupper($main), 'is_main' => true]];
    }

    return [['code' => 'USD', 'is_main' => true]];
}

function stocks_live_api(PDO $pdo): void
{
    require_login();
    $symbolsParam = $_GET['symbols'] ?? '';
    $symbols = array_filter(array_map('trim', explode(',', $symbolsParam)));
    $priceService = stocks_price_service($pdo);
    $quotes = $priceService->getLiveQuotes($symbols);
    json_response(['quotes' => $quotes]);
}

function stocks_history_api(PDO $pdo, string $symbol): void
{
    require_login();
    $range = $_GET['range'] ?? '6M';
    $priceService = stocks_price_service($pdo);
    $bounds = stocks_history_bounds($range);
    $priceHistory = $priceService->getDailyHistory(strtoupper($symbol), $bounds['start'], $bounds['end']);
    $chartsService = new ChartsService($pdo, $priceService);
    $series = $chartsService->positionValueSeries(uid(), strtoupper($symbol), $range);
    json_response([
        'price' => $priceHistory,
        'position' => $series,
    ]);
}

/**
 * @return array{start: string, end: string}
 */
function stocks_history_bounds(string $range): array
{
    $range = strtoupper($range);
    $end = date('Y-m-d');
    $start = match ($range) {
        '1D' => date('Y-m-d', strtotime('-1 day')),
        '5D' => date('Y-m-d', strtotime('-5 days')),
        '1M' => date('Y-m-d', strtotime('-1 month')),
        '6M' => date('Y-m-d', strtotime('-6 months')),
        '1Y' => date('Y-m-d', strtotime('-1 year')),
        '5Y' => date('Y-m-d', strtotime('-5 years')),
        default => date('Y-m-d', strtotime('-6 months')),
    };

    return ['start' => $start, 'end' => $end];
}

/**
 * @return array{dir: ?string, ttl: int}
 */
function stocks_overview_cache_settings(): array
{
    global $config;
    $root = is_array($config ?? null) ? $config : [];
    $stocks = $root['stocks'] ?? [];
    $dir = $stocks['overview_cache_dir'] ?? null;
    if (is_string($dir)) {
        $dir = rtrim($dir, DIRECTORY_SEPARATOR);
    } else {
        $dir = null;
    }

    $ttl = (int)($stocks['overview_cache_seconds'] ?? 0);
    if ($ttl < 0) {
        $ttl = 0;
    }

    return ['dir' => $dir, 'ttl' => $ttl];
}

function stocks_portfolio_service(PDO $pdo): PortfolioService
{
    static $service;
    if ($service instanceof PortfolioService) {
        return $service;
    }

    $priceService = stocks_price_service($pdo);
    $cashService = new CashService($pdo);
    $settings = stocks_overview_cache_settings();
    $service = new PortfolioService($pdo, $priceService, $cashService, $settings['dir'], $settings['ttl']);
    return $service;
}

function stocks_price_service(PDO $pdo): PriceDataService
{
    static $service;
    if ($service) {
        return $service;
    }
    global $config;
    $providerName = strtolower($config['stocks']['provider'] ?? 'null');
    $adapter = new NullPriceProvider();
    if ($providerName === 'finnhub') {
        $providerCfg = $config['stocks']['providers']['finnhub'] ?? [];
        $apiKey = $providerCfg['api_key'] ?? getenv('FINNHUB_API_KEY');
        $baseUrl = $providerCfg['base_url'] ?? getenv('FINNHUB_BASE_URL') ?: 'https://finnhub.io/api/v1';
        if (!empty($apiKey)) {
            $adapter = new FinnhubAdapter($apiKey, $baseUrl);
        }
    }
    $ttl = (int)($config['stocks']['refresh_seconds'] ?? 10);
    $service = new PriceDataService($pdo, $adapter, $ttl);
    return $service;
}

function stocks_refresh_seconds(int $userId, PDO $pdo): int
{
    $default = stocks_refresh_default();
    if (!stocks_table_exists($pdo, 'user_settings_stocks')) {
        return $default;
    }

    $stmt = $pdo->prepare('SELECT refresh_seconds FROM user_settings_stocks WHERE user_id=?');
    $stmt->execute([$userId]);
    $value = (int)($stmt->fetchColumn() ?: 0);
    if ($value <= 0) {
        return $default;
    }
    return max(5, $value);
}

function stocks_settings(PDO $pdo, int $userId): array
{
    if (!stocks_table_exists($pdo, 'user_settings_stocks')) {
        return ['unrealized_method' => 'AVERAGE', 'realized_method' => 'FIFO'];
    }
    $stmt = $pdo->prepare('SELECT * FROM user_settings_stocks WHERE user_id=?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: ['unrealized_method' => 'AVERAGE', 'realized_method' => 'FIFO'];
}

function stocks_fetch_stock(PDO $pdo, string $symbol): ?array
{
    if (!stocks_table_exists($pdo, 'stocks')) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM stocks WHERE UPPER(symbol)=? LIMIT 1');
    $stmt->execute([$symbol]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function stocks_is_watched(PDO $pdo, int $userId, int $stockId): bool
{
    if (!stocks_table_exists($pdo, 'watchlist')) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT 1 FROM watchlist WHERE user_id=? AND stock_id=?');
    $stmt->execute([$userId, $stockId]);
    return (bool)$stmt->fetchColumn();
}

function stocks_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    $key = strtolower($table);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = ? LIMIT 1');
    $stmt->execute([$table]);
    $cache[$key] = (bool)$stmt->fetchColumn();
    return $cache[$key];
}

function stocks_refresh_default(): int
{
    global $config;
    $value = (int)($config['stocks']['refresh_seconds'] ?? 10);
    return max(5, $value);
}

function stocks_import_detect_delimiter(string $filePath): string
{
    $candidates = [',', ';', "\t", '|'];
    $handle = @fopen($filePath, 'r');
    if (!$handle) {
        return ',';
    }

    $sample = fgets($handle);
    fclose($handle);

    if ($sample === false) {
        return ',';
    }

    $best = ',';
    $bestCount = 0;
    foreach ($candidates as $candidate) {
        $count = substr_count($sample, $candidate);
        if ($count > $bestCount) {
            $best = $candidate;
            $bestCount = $count;
        }
    }

    return $best;
}

/**
 * @param list<string> $header
 * @return array<string,int>
 */
function stocks_import_header_map(array $header): array
{
    $aliases = [
        'date' => 'date',
        'timestamp' => 'date',
        'executed at' => 'date',
        'time' => 'date',
        'ticker' => 'ticker',
        'symbol' => 'ticker',
        'asset' => 'ticker',
        'type' => 'type',
        'action' => 'type',
        'side' => 'type',
        'quantity' => 'quantity',
        'qty' => 'quantity',
        'shares' => 'quantity',
        'price per share' => 'price',
        'price/share' => 'price',
        'price' => 'price',
        'trade price' => 'price',
        'total amount' => 'total',
        'amount' => 'total',
        'gross amount' => 'total',
        'net amount' => 'total',
        'currency' => 'currency',
        'fx currency' => 'currency',
        'fx rate' => 'fx_rate',
        'exchange rate' => 'fx_rate',
        'note' => 'note',
        'memo' => 'note',
        'fee' => 'fee',
        'commission' => 'fee',
    ];

    $map = [];
    foreach ($header as $index => $label) {
        $normalized = stocks_import_normalize_header((string)$label);
        if ($normalized === '') {
            continue;
        }
        if (isset($aliases[$normalized])) {
            $key = $aliases[$normalized];
            if (!array_key_exists($key, $map)) {
                $map[$key] = $index;
            }
        }
    }

    return $map;
}

function stocks_import_normalize_header(string $value): string
{
    $value = preg_replace('/^\xEF\xBB\xBF/', '', $value); // remove BOM if present
    $value = strtolower(trim($value));
    $value = preg_replace('/\s+/', ' ', $value);
    if (!is_string($value)) {
        return '';
    }
    return $value;
}

/**
 * @param list<string|null> $row
 * @param array<string,int> $columnMap
 * @return array<string,string|null>
 */
function stocks_import_build_record(array $row, array $columnMap): array
{
    $record = [];
    foreach ($columnMap as $key => $index) {
        $record[$key] = isset($row[$index]) ? trim((string)$row[$index]) : null;
    }
    return $record;
}

/**
 * @param mixed $value
 */
function stocks_import_to_float($value): float
{
    if ($value === null) {
        return 0.0;
    }
    if (is_numeric($value)) {
        return (float)$value;
    }
    $normalized = preg_replace('/[^0-9\-\.,]/', '', (string)$value);
    if ($normalized === '' || $normalized === '-' || $normalized === '--') {
        return 0.0;
    }
    $normalized = str_replace(',', '', $normalized);
    return (float)$normalized;
}

/**
 * @param list<string|null> $row
 */
function stocks_import_row_is_empty(array $row): bool
{
    foreach ($row as $cell) {
        if (trim((string)$cell) !== '') {
            return false;
        }
    }
    return true;
}

function stocks_import_detect_side(string $type): ?string
{
    $upper = strtoupper($type);
    if (str_contains($upper, 'BUY')) {
        return 'BUY';
    }
    if (str_contains($upper, 'SELL')) {
        return 'SELL';
    }
    return null;
}

function stocks_import_is_non_trade(string $type): bool
{
    $upper = strtoupper($type);
    return str_contains($upper, 'SPLIT') || str_contains($upper, 'MERGER') || str_contains($upper, 'TRANSFER')
        || str_contains($upper, 'REORG');
}

function stocks_import_is_cash_like(string $type): bool
{
    $upper = strtoupper($type);
    if ($upper === '') {
        return false;
    }
    return str_contains($upper, 'CASH')
        || str_contains($upper, 'DIVIDEND')
        || str_contains($upper, 'INTEREST')
        || str_contains($upper, 'FEE')
        || str_contains($upper, 'TAX');
}

/**
 * @param array<string,mixed> $record
 */
function stocks_import_cash_amount(array $record, string $type): float
{
    $total = stocks_import_to_float($record['total'] ?? null);
    if ($total === 0.0) {
        $alt = stocks_import_to_float($record['price'] ?? null);
        if ($alt !== 0.0) {
            $total = $alt;
        }
    }
    if ($total === 0.0) {
        $qty = stocks_import_to_float($record['quantity'] ?? null);
        $price = stocks_import_to_float($record['price'] ?? null);
        if ($qty !== 0.0 && $price !== 0.0) {
            $total = $qty * $price;
        }
    }

    $upper = strtoupper($type);
    if ($total > 0 && (str_contains($upper, 'WITHDRAW') || str_contains($upper, 'FEE') || str_contains($upper, 'TAX'))) {
        return -abs($total);
    }
    if ($total < 0 && (str_contains($upper, 'TOP') || str_contains($upper, 'DEPOSIT') || str_contains($upper, 'DIVIDEND') || str_contains($upper, 'INTEREST'))) {
        return abs($total);
    }
    return $total;
}

function stocks_request_wants_json(): bool
{
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    $requestedWith = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    return str_contains($accept, 'application/json') || $requestedWith === 'xmlhttprequest';
}

/**
 * @param array<string,mixed> $payload
 */
function stocks_import_output(bool $wantsJson, bool $success, string $message, array $payload = []): void
{
    if (!$wantsJson) {
        return;
    }
    header('Content-Type: application/json');
    http_response_code($success ? 200 : 422);
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $payload));
    exit;
}
