<?php

function more_show(PDO $pdo): void
{
    $userId = uid();

    $stmt = $pdo->prepare('SELECT full_name, email FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['full_name' => '', 'email' => ''];

    $displayName = trim($user['full_name'] ?? '') ?: ($user['email'] ?? '');

    $navSections = [
        [
            'title' => __('Money & budgeting'),
            'items' => [
                [
                    'label' => __('Current month'),
                    'description' => __('Review this month\'s spending, income, and budgets.'),
                    'href' => '/current-month',
                    'icon' => 'calendar-check',
                ],
                [
                    'label' => __('Scheduled payments'),
                    'description' => __('Track upcoming bills and automatic payments.'),
                    'href' => '/scheduled',
                    'icon' => 'calendar-clock',
                ],
                [
                    'label' => __('Basic incomes'),
                    'description' => __('Manage recurring income sources and salary updates.'),
                    'href' => '/settings/basic-incomes',
                    'icon' => 'coins',
                ],
                [
                    'label' => __('Cashflow rules'),
                    'description' => __('Adjust envelope allocations and automation preferences.'),
                    'href' => '/settings/cashflow',
                    'icon' => 'sliders-horizontal',
                ],
            ],
        ],
        [
            'title' => __('Planning & goals'),
            'items' => [
                [
                    'label' => __('Goals'),
                    'description' => __('Set, fund, and celebrate your savings goals.'),
                    'href' => '/goals',
                    'icon' => 'goal',
                ],
                [
                    'label' => __('Loans'),
                    'description' => __('Manage debts, schedules, and payoff progress.'),
                    'href' => '/loans',
                    'icon' => 'landmark',
                ],
                [
                    'label' => __('Emergency fund'),
                    'description' => __('Grow and protect your safety net for surprises.'),
                    'href' => '/emergency',
                    'icon' => 'lifebuoy',
                ],
            ],
        ],
        [
            'title' => __('Insights & reports'),
            'items' => [
                [
                    'label' => __('Dashboard'),
                    'description' => __('Snapshot of balances, trends, and recent activity.'),
                    'href' => '/',
                    'icon' => 'layout-dashboard',
                ],
                [
                    'label' => __('Months & years'),
                    'description' => __('Browse historical months and yearly summaries.'),
                    'href' => '/years',
                    'icon' => 'calendar-range',
                ],
                [
                    'label' => __('Stocks'),
                    'description' => __('Track portfolio performance and trades.'),
                    'href' => '/stocks',
                    'icon' => 'line-chart',
                ],
                [
                    'label' => __('Feedback'),
                    'description' => __('Share product ideas or report an issue.'),
                    'href' => '/feedback',
                    'icon' => 'message-circle',
                ],
            ],
        ],
        [
            'title' => __('Settings & personalisation'),
            'items' => [
                [
                    'label' => __('Settings overview'),
                    'description' => __('Review currencies, incomes, and automation shortcuts.'),
                    'href' => '/settings',
                    'icon' => 'settings',
                ],
                [
                    'label' => __('Profile settings'),
                    'description' => __('Update your personal details and contact info.'),
                    'href' => '/settings/profile',
                    'icon' => 'user-cog',
                ],
                [
                    'label' => __('Theme & appearance'),
                    'description' => __('Fine-tune colours, typography, and look & feel.'),
                    'href' => '/settings/theme',
                    'icon' => 'palette',
                ],
                [
                    'label' => __('Currencies'),
                    'description' => __('Choose the currencies you budget and report in.'),
                    'href' => '/settings/currencies',
                    'icon' => 'wallet',
                ],
                [
                    'label' => __('Categories'),
                    'description' => __('Organise income and spending categories.'),
                    'href' => '/settings/categories',
                    'icon' => 'tags',
                ],
            ],
        ],
    ];

    view('more/index', [
        'user' => $user,
        'displayName' => $displayName,
        'navSections' => $navSections,
    ]);
}
