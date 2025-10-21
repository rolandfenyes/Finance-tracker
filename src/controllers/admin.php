<?php

declare(strict_types=1);

function admin_dashboard_index(PDO $pdo): void
{
    // KPI summary values can be wired to real metrics later; use illustrative data for now.
    $kpis = [
        [
            'label' => 'Total Users',
            'value' => '18,420',
            'change' => '+4.2% vs. last month',
            'trend' => 'up',
        ],
        [
            'label' => 'Active Paid Plans',
            'value' => '6,872',
            'change' => '+2.1% new upgrades',
            'trend' => 'up',
        ],
        [
            'label' => 'MRR / ARR',
            'value' => '$128.5k / $1.54M',
            'change' => '+$4.2k new MRR',
            'trend' => 'up',
        ],
        [
            'label' => 'Net Churn',
            'value' => '1.8%',
            'change' => '-0.6 pts vs. last cycle',
            'trend' => 'down',
        ],
    ];

    $recentActivity = [
        [
            'type' => 'Signup',
            'title' => 'emma.w upgraded to Premium',
            'meta' => 'Annual · Stripe · Europe',
            'time' => '2m ago',
            'status' => 'success',
        ],
        [
            'type' => 'Payment',
            'title' => 'Invoice #INV-24401 collected',
            'meta' => 'Pro · $89 · PayPal',
            'time' => '14m ago',
            'status' => 'success',
        ],
        [
            'type' => 'Support',
            'title' => 'Refund requested by akira.n',
            'meta' => 'Ticket #4119 · Pending review',
            'time' => '21m ago',
            'status' => 'warning',
        ],
        [
            'type' => 'System',
            'title' => 'Background job queue recovered',
            'meta' => 'Cron: nightly-ledger-sync',
            'time' => '43m ago',
            'status' => 'info',
        ],
        [
            'type' => 'Error',
            'title' => 'Failed payment retry (Stripe)',
            'meta' => 'User: carla.b · Card expired',
            'time' => '1h ago',
            'status' => 'error',
        ],
    ];

    $quickActions = [
        [
            'label' => 'Process Refund',
            'description' => 'Issue a partial or full refund, with optional credit note.',
            'href' => '#',
            'icon' => '💸',
        ],
        [
            'label' => 'Override Plan',
            'description' => 'Grant temporary premium access or extend a trial period.',
            'href' => '#',
            'icon' => '🎛️',
        ],
        [
            'label' => 'Create Coupon',
            'description' => 'Generate single-use or campaign discount codes.',
            'href' => '#',
            'icon' => '🏷️',
        ],
        [
            'label' => 'Log Support Note',
            'description' => 'Attach internal notes to a customer profile for the team.',
            'href' => '#',
            'icon' => '🗒️',
        ],
    ];

    $systemHealth = [
        [
            'label' => 'Application Uptime',
            'value' => '99.98%',
            'detail' => 'Past 90 days',
            'status' => 'operational',
        ],
        [
            'label' => 'Database Cluster',
            'value' => 'Healthy',
            'detail' => 'Primary + 2 replicas',
            'status' => 'operational',
        ],
        [
            'label' => 'Cron Schedulers',
            'value' => '3 delayed jobs',
            'detail' => 'Retrying automatically',
            'status' => 'warning',
        ],
        [
            'label' => 'Queues',
            'value' => 'Email queue 86%',
            'detail' => 'Throughput normal',
            'status' => 'operational',
        ],
    ];

    $featureSections = [
        [
            'title' => 'User Management',
            'description' => 'A command center for the entire customer lifecycle.',
            'items' => [
                ['title' => 'User directory', 'summary' => 'Search, sort, and filter across all accounts. Export results to CSV for audits.'],
                ['title' => 'Customer profile', 'summary' => 'View plan, usage metrics, invoices, login history, and internal notes in one place.'],
                ['title' => 'Manual overrides', 'summary' => 'Extend trials, apply complimentary plans, or impersonate users for debugging.'],
                ['title' => 'Account controls', 'summary' => 'Suspend, reactivate, or delete accounts with full audit logging.'],
                ['title' => 'Session insights', 'summary' => 'Inspect login history, active sessions, and security events.'],
            ],
        ],
        [
            'title' => 'Plans & Subscriptions',
            'description' => 'Design, launch, and monitor every plan variant.',
            'items' => [
                ['title' => 'Plan builder', 'summary' => 'Create Free, Pro, Premium, or bespoke custom plans with feature limits.'],
                ['title' => 'Feature limits', 'summary' => 'Fine-tune allowances for goals, currencies, automation rules, and more.'],
                ['title' => 'Lifecycle controls', 'summary' => 'Upgrade or downgrade users, manage plan overrides, and set expiry reminders.'],
                ['title' => 'Coupons & promos', 'summary' => 'Configure promotional codes and track redemptions.'],
                ['title' => 'Activity log', 'summary' => 'Audit every upgrade, downgrade, or override adjustment.'],
                ['title' => 'Trial management', 'summary' => 'Extend, revoke, or auto-expire trial plans with a click.'],
            ],
        ],
        [
            'title' => 'Billing & Invoices',
            'description' => 'Finance-grade tooling for reconciliation and revenue ops.',
            'items' => [
                ['title' => 'Invoice desk', 'summary' => 'Browse paid, pending, or failed invoices with advanced filters.'],
                ['title' => 'Manual issuances', 'summary' => 'Send ad-hoc invoices, credit notes, or adjustments.'],
                ['title' => 'Refund workflows', 'summary' => 'Trigger instant refunds and tie them to gateway events.'],
                ['title' => 'Gateway monitoring', 'summary' => 'Track Stripe, Revolut, and PayPal events with failure alerts.'],
                ['title' => 'Collections', 'summary' => 'Retry failed payments, monitor dunning, and review VAT by country.'],
                ['title' => 'Exports & reporting', 'summary' => 'Export invoice data to CSV or PDF and view revenue by plan or region.'],
            ],
        ],
        [
            'title' => 'Error & System Logs',
            'description' => 'Pinpoint issues fast with unified log streams.',
            'items' => [
                ['title' => 'Application errors', 'summary' => 'Filter PHP, API, or database errors by severity and user impact.'],
                ['title' => 'Email delivery', 'summary' => 'Inspect sent, opened, bounced, or failed transactional mail.'],
                ['title' => 'API analytics', 'summary' => 'Per-endpoint performance metrics, response times, and quotas.'],
                ['title' => 'Job monitoring', 'summary' => 'Warnings for failed background jobs or low quota thresholds.'],
            ],
        ],
        [
            'title' => 'Analytics & Insights',
            'description' => 'Stay ahead of growth, retention, and product engagement.',
            'items' => [
                ['title' => 'User growth', 'summary' => 'Charts by day, week, or month with cohort comparisons.'],
                ['title' => 'Subscription metrics', 'summary' => 'MRR, ARR, net churn, and lifetime value trends.'],
                ['title' => 'Feature usage', 'summary' => 'Understand which rules, goals, or reports drive adoption.'],
                ['title' => 'Geographic mix', 'summary' => 'Map users by region to localize pricing and messaging.'],
                ['title' => 'Conversion funnel', 'summary' => 'Track trial-to-paid conversion and identify drop-off points.'],
                ['title' => 'Account activity', 'summary' => 'Spot top power users and accounts trending inactive.'],
            ],
        ],
        [
            'title' => 'Support & Communication',
            'description' => 'Delight customers and keep teams in sync.',
            'items' => [
                ['title' => 'Support desk', 'summary' => 'Manage internal tickets and threaded conversations.'],
                ['title' => 'Broadcast center', 'summary' => 'Send announcements via email or in-app notices.'],
                ['title' => 'Template studio', 'summary' => 'Edit lifecycle emails like welcomes or failed payment alerts.'],
                ['title' => 'Notification settings', 'summary' => 'Control in-app, email, and push notifications per segment.'],
                ['title' => 'Feedback loops', 'summary' => 'Collect survey responses and track sentiment over time.'],
            ],
        ],
        [
            'title' => 'Developer & Maintenance Tools',
            'description' => 'Operational tooling for engineers and technical support.',
            'items' => [
                ['title' => 'SQL console', 'summary' => 'Run read-only queries for quick diagnostics and counts.'],
                ['title' => 'API key vault', 'summary' => 'Issue, rotate, or revoke access tokens securely.'],
                ['title' => 'Feature toggles', 'summary' => 'Enable beta features and coordinate rollouts.'],
                ['title' => 'Configuration editor', 'summary' => 'Adjust limits, defaults, or currency displays on the fly.'],
                ['title' => 'Maintenance tools', 'summary' => 'Trigger data recalculations, rebuild caches, or manage backups.'],
                ['title' => 'Deploy tracker', 'summary' => 'Track versions, migrations, cron health, and queue status.'],
            ],
        ],
        [
            'title' => 'Security & Access Control',
            'description' => 'Guardrails for admins, finance, and support teams.',
            'items' => [
                ['title' => 'Role-based access', 'summary' => 'Superadmin, Support, Finance, and Developer roles with scoped permissions.'],
                ['title' => 'Audit trail', 'summary' => 'Comprehensive logs of every admin action and change.'],
                ['title' => 'Strong authentication', 'summary' => 'Require 2FA, enforce session timeouts, and detect suspicious activity.'],
                ['title' => 'Network controls', 'summary' => 'IP whitelisting and geofencing for sensitive access.'],
            ],
        ],
        [
            'title' => 'Automation',
            'description' => 'Hands-off workflows that keep revenue and data flowing.',
            'items' => [
                ['title' => 'Scheduled job monitor', 'summary' => 'Daily syncs, cleanups, and email dispatches with alerting.'],
                ['title' => 'Payment recovery', 'summary' => 'Automatic retries and proactive churn alerts.'],
                ['title' => 'Cache orchestration', 'summary' => 'Clear or refresh caches from a single pane.'],
                ['title' => 'Lifecycle automation', 'summary' => 'Auto-deactivate expired plans and notify stakeholders.'],
            ],
        ],
        [
            'title' => 'Optional & Future Enhancements',
            'description' => 'Forward-looking capabilities on the roadmap.',
            'items' => [
                ['title' => 'AI anomaly detection', 'summary' => 'Spot suspicious spending patterns or login behavior automatically.', 'badge' => 'Exploring'],
                ['title' => 'Revenue forecasting', 'summary' => 'Predict cash flow with machine learning on historicals.', 'badge' => 'Planned'],
                ['title' => 'Refund automation', 'summary' => 'Pro-rate unused days and trigger credits instantly.', 'badge' => 'Design'],
                ['title' => 'Integration manager', 'summary' => 'Self-serve connectors for Revolut, FX, and partner APIs.', 'badge' => 'Planned'],
                ['title' => 'Experiment suite', 'summary' => 'Run A/B tests and manage feature experiments.', 'badge' => 'Research'],
                ['title' => 'Admin notifications', 'summary' => 'Send alerts to Slack or Telegram channels.', 'badge' => 'Backlog'],
                ['title' => 'Data migration tools', 'summary' => 'Bulk CSV import/export for onboarding legacy customers.', 'badge' => 'Backlog'],
            ],
        ],
    ];

    view('admin/dashboard', [
        'pageTitle' => 'Admin Control Center',
        'fullWidthMain' => true,
        'kpis' => $kpis,
        'recentActivity' => $recentActivity,
        'quickActions' => $quickActions,
        'systemHealth' => $systemHealth,
        'featureSections' => $featureSections,
    ]);
}
