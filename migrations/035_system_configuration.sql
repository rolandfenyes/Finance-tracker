CREATE TABLE IF NOT EXISTS system_settings (
  id                   INT PRIMARY KEY DEFAULT 1,
  site_name            TEXT NOT NULL DEFAULT 'MyMoneyMap',
  primary_url          TEXT NULL,
  support_email        TEXT NULL,
  contact_email        TEXT NULL,
  logo_url             TEXT NULL,
  favicon_url          TEXT NULL,
  maintenance_mode     BOOLEAN NOT NULL DEFAULT FALSE,
  maintenance_message  TEXT NULL,
  created_at           TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at           TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

INSERT INTO system_settings (id, site_name, maintenance_mode)
VALUES (1, 'MyMoneyMap', FALSE)
ON CONFLICT (id) DO NOTHING;

CREATE OR REPLACE FUNCTION system_settings_touch_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trigger_system_settings_updated_at ON system_settings;
CREATE TRIGGER trigger_system_settings_updated_at
BEFORE UPDATE ON system_settings
FOR EACH ROW
EXECUTE FUNCTION system_settings_touch_updated_at();

CREATE TABLE IF NOT EXISTS api_integrations (
  id                 BIGSERIAL PRIMARY KEY,
  name               TEXT NOT NULL,
  service            TEXT NULL,
  api_key_encrypted  TEXT NULL,
  status             TEXT NOT NULL DEFAULT 'active',
  metadata           JSONB NOT NULL DEFAULT '{}'::jsonb,
  last_used_at       TIMESTAMPTZ NULL,
  created_at         TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at         TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS api_integrations_status_idx
    ON api_integrations (LOWER(status));

CREATE OR REPLACE FUNCTION api_integrations_touch_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trigger_api_integrations_updated_at ON api_integrations;
CREATE TRIGGER trigger_api_integrations_updated_at
BEFORE UPDATE ON api_integrations
FOR EACH ROW
EXECUTE FUNCTION api_integrations_touch_updated_at();

CREATE TABLE IF NOT EXISTS email_templates (
  id               BIGSERIAL PRIMARY KEY,
  code             TEXT NOT NULL,
  name             TEXT NOT NULL,
  subject          TEXT NOT NULL,
  body             TEXT NOT NULL,
  locale           TEXT NOT NULL DEFAULT 'en',
  last_tested_at   TIMESTAMPTZ NULL,
  created_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at       TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX IF NOT EXISTS email_templates_code_locale_idx
    ON email_templates (LOWER(code), LOWER(locale));

CREATE OR REPLACE FUNCTION email_templates_touch_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trigger_email_templates_updated_at ON email_templates;
CREATE TRIGGER trigger_email_templates_updated_at
BEFORE UPDATE ON email_templates
FOR EACH ROW
EXECUTE FUNCTION email_templates_touch_updated_at();

INSERT INTO email_templates (code, name, subject, body, locale)
VALUES
    ('welcome', 'Welcome email', 'Welcome to MyMoneyMap', 'Hi {{name}},\n\nThanks for joining MyMoneyMap! Get started by connecting your accounts and setting financial goals.\n\n— The MyMoneyMap Team', 'en'),
    ('password_reset', 'Password reset', 'Reset your MyMoneyMap password', 'Hi {{name}},\n\nUse the link below to choose a new password.\n\n{{reset_link}}\n\nIf you did not request this, you can ignore this email.', 'en')
ON CONFLICT (LOWER(code), LOWER(locale)) DO NOTHING;

CREATE TABLE IF NOT EXISTS notification_channels (
  id           BIGSERIAL PRIMARY KEY,
  channel      TEXT NOT NULL UNIQUE,
  name         TEXT NOT NULL,
  is_enabled   BOOLEAN NOT NULL DEFAULT TRUE,
  config       JSONB NOT NULL DEFAULT '{}'::jsonb,
  created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at   TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE OR REPLACE FUNCTION notification_channels_touch_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trigger_notification_channels_updated_at ON notification_channels;
CREATE TRIGGER trigger_notification_channels_updated_at
BEFORE UPDATE ON notification_channels
FOR EACH ROW
EXECUTE FUNCTION notification_channels_touch_updated_at();

INSERT INTO notification_channels (channel, name, is_enabled, config)
VALUES
    ('email', 'Transactional email', TRUE, '{"provider":"log"}'::jsonb),
    ('sms', 'SMS alerts', FALSE, '{"provider":"twilio"}'::jsonb),
    ('push', 'Push notifications', TRUE, '{"provider":"pusher"}'::jsonb)
ON CONFLICT (channel) DO NOTHING;
