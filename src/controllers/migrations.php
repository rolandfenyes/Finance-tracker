<?php

require_once __DIR__ . '/../helpers.php';

function maintenance_run_migrations(PDO $pdo): void
{
    require_admin();

    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        verify_csrf();
    }

    $runAt = date('Y-m-d H:i:s');

    try {
        $result = run_all_migrations($pdo);
    } catch (Throwable $e) {
        http_response_code(500);
        view('admin/migrations', [
            'pageTitle' => __('Database migrations'),
            'results' => [],
            'summary' => [
                'total' => 0,
                'applied' => 0,
                'skipped' => 0,
                'failed' => 1,
                'pending' => 0,
            ],
            'directory' => null,
            'error' => $e->getMessage(),
            'ranAt' => $runAt,
            'targetEmail' => null,
        ]);
        return;
    }

    $summary = $result['summary'] ?? [];
    if (!empty($summary['failed'])) {
        http_response_code(500);
    }

    view('admin/migrations', [
        'pageTitle' => __('Database migrations'),
        'results' => $result['results'] ?? [],
        'summary' => $summary,
        'directory' => $result['directory'] ?? null,
        'error' => $result['error'] ?? null,
        'ranAt' => $runAt,
        'targetEmail' => null,
    ]);
}

/**
 * @return array{
 *   directory: string,
 *   results: array<int, array{filename: string, status: string, message?: string}>,
 *   summary: array{total: int, applied: int, skipped: int, failed: int, pending: int}
 * }
 */
function run_all_migrations(PDO $pdo): array
{
    $dir = realpath(__DIR__ . '/../../migrations');
    if ($dir === false) {
        throw new RuntimeException('Migrations directory not found.');
    }

    $files = glob($dir . '/*.sql');
    if ($files === false) {
        throw new RuntimeException('Unable to read migrations directory.');
    }

    sort($files, SORT_NATURAL);

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS schema_migrations (
            filename TEXT PRIMARY KEY,
            run_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )"
    );

    $existing = [];
    $existingStmt = $pdo->query('SELECT filename FROM schema_migrations');
    if ($existingStmt instanceof PDOStatement) {
        $rows = $existingStmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($rows as $value) {
            $existing[(string)$value] = true;
        }
    }

    $results = [];
    $appliedCount = 0;
    $skippedCount = 0;
    $failedCount = 0;
    $pendingCount = 0;
    $encounteredFailure = false;

    $insertStmt = $pdo->prepare(
        'INSERT INTO schema_migrations (filename, run_at) VALUES (?, NOW()) ON CONFLICT (filename) DO NOTHING'
    );

    foreach ($files as $file) {
        $filename = basename((string)$file);

        if ($encounteredFailure) {
            $results[] = [
                'filename' => $filename,
                'status' => 'pending',
            ];
            $pendingCount++;
            continue;
        }

        if (isset($existing[$filename])) {
            $results[] = [
                'filename' => $filename,
                'status' => 'skipped',
                'message' => __('Already applied'),
            ];
            $skippedCount++;
            continue;
        }

        $sql = file_get_contents($file);
        if ($sql === false) {
            $results[] = [
                'filename' => $filename,
                'status' => 'failed',
                'message' => __('Unable to read migration file.'),
            ];
            $failedCount++;
            $encounteredFailure = true;
            continue;
        }

        try {
            $pdo->exec($sql);
            if ($insertStmt instanceof PDOStatement) {
                $insertStmt->execute([$filename]);
            } else {
                $fallback = $pdo->prepare(
                    'INSERT INTO schema_migrations (filename, run_at) VALUES (?, NOW()) ON CONFLICT (filename) DO NOTHING'
                );
                $fallback->execute([$filename]);
            }

            $results[] = [
                'filename' => $filename,
                'status' => 'applied',
            ];
            $appliedCount++;
        } catch (Throwable $e) {
            $results[] = [
                'filename' => $filename,
                'status' => 'failed',
                'message' => $e->getMessage(),
            ];
            $failedCount++;
            $encounteredFailure = true;
        }
    }

    return [
        'directory' => $dir,
        'results' => $results,
        'summary' => [
            'total' => count($files),
            'applied' => $appliedCount,
            'skipped' => $skippedCount,
            'failed' => $failedCount,
            'pending' => $pendingCount,
        ],
    ];
}
