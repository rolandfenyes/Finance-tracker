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
            'title' => __('Overview & money'),
            'items' => [
                [
                    'label' => __('Dashboard'),
                    'description' => __('Snapshot of balances, trends, and recent activity.'),
                    'href' => '/',
                    'icon' => 'layout-dashboard',
                ],
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
                    'icon' => 'life-buoy',
                ],
            ],
        ],
        [
            'title' => __('Settings & personalisation'),
            'items' => [
                [
                    'label' => __('Theme & appearance'),
                    'description' => __('Fine-tune colours, typography, and look & feel.'),
                    'href' => '/settings/theme',
                    'icon' => 'palette',
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
        [
            'title' => __('Help & feedback'),
            'items' => [
                [
                    'label' => __('Tutorial'),
                    'description' => __('Learn the ropes of goals, loans, and budgeting.'),
                    'href' => '/tutorial',
                    'icon' => 'graduation-cap',
                ],
                [
                    'label' => __('Feedback'),
                    'description' => __('Share product ideas or report an issue.'),
                    'href' => '/feedback',
                    'icon' => 'message-circle',
                ],
            ],
        ],
    ];

    $localeOptions = available_locales();
    $currentLocale = app_locale();
    $localeFlags = [
        'en' => '🇺🇸',
        'hu' => '🇭🇺',
        'es' => '🇪🇸',
    ];

    view('more/index', [
        'user' => $user,
        'displayName' => $displayName,
        'navSections' => $navSections,
        'localeOptions' => $localeOptions,
        'currentLocale' => $currentLocale,
        'localeFlags' => $localeFlags,
    ]);
}
