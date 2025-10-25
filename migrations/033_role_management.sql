CREATE TABLE IF NOT EXISTS roles (
    id SERIAL PRIMARY KEY,
    slug TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    description TEXT,
    is_system BOOLEAN NOT NULL DEFAULT FALSE,
    capabilities JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

INSERT INTO roles (slug, name, description, is_system, capabilities)
VALUES
    (
        'free',
        'Free user',
        'Default plan with limited access',
        TRUE,
        '{
            "currencies_limit": 1,
            "goals_limit": 2,
            "loans_limit": 2,
            "categories_limit": 10,
            "scheduled_payments_limit": 2,
            "cashflow_rules_edit": false
        }'
    ),
    (
        'premium',
        'Premium user',
        'Full access to financial planning tools',
        TRUE,
        '{
            "currencies_limit": null,
            "goals_limit": null,
            "loans_limit": null,
            "categories_limit": null,
            "scheduled_payments_limit": null,
            "cashflow_rules_edit": true
        }'
    ),
    (
        'admin',
        'Administrator',
        'Administrative access to manage the platform',
        TRUE,
        '{
            "currencies_limit": null,
            "goals_limit": null,
            "loans_limit": null,
            "categories_limit": null,
            "scheduled_payments_limit": null,
            "cashflow_rules_edit": false
        }'
    )
ON CONFLICT (slug) DO UPDATE
SET
    name = EXCLUDED.name,
    description = EXCLUDED.description,
    is_system = EXCLUDED.is_system,
    capabilities = EXCLUDED.capabilities,
    updated_at = NOW();

CREATE OR REPLACE FUNCTION roles_updated_at_trigger()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trigger_roles_updated_at ON roles;
CREATE TRIGGER trigger_roles_updated_at
BEFORE UPDATE ON roles
FOR EACH ROW
EXECUTE FUNCTION roles_updated_at_trigger();
