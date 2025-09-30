<?php

declare(strict_types=1);

namespace MyMoneyMap\Stocks\Repositories;

use PDO;

final class SettingsRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function userSettings(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM user_settings_stocks WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$settings) {
            return [
                'cost_basis_unrealized' => 'AVERAGE',
                'realized_method' => 'FIFO',
                'target_allocations' => null,
            ];
        }

        return [
            'cost_basis_unrealized' => $settings['cost_basis_unrealized'] ?? 'AVERAGE',
            'realized_method' => $settings['realized_method'] ?? 'FIFO',
            'target_allocations' => $settings['target_allocations'] ?? null,
        ];
    }
}
