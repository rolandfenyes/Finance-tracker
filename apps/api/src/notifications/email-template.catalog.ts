export type EmailLocale = 'en' | 'es' | 'hu';
export type EmailClassification = 'transactional' | 'educational';

export interface CatalogTemplate {
  code: string;
  classification: EmailClassification;
  contract: readonly string[];
  subjects: Record<EmailLocale, string>;
  bodies: Record<EmailLocale, string>;
}

const localized = (
  code: string,
  classification: EmailClassification,
  contract: readonly string[],
  en: [string, string],
  es: [string, string],
  hu: [string, string],
): CatalogTemplate => ({
  code,
  classification,
  contract,
  subjects: { en: en[0], es: es[0], hu: hu[0] },
  bodies: { en: en[1], es: es[1], hu: hu[1] },
});

export const EMAIL_TEMPLATE_CATALOG: readonly CatalogTemplate[] = [
  localized(
    'feedback_new',
    'transactional',
    ['feedback_title', 'feedback_kind', 'feedback_severity', 'feedback_url'],
    [
      'New MyMoneyMap feedback',
      'New {{feedback_kind}} feedback: “{{feedback_title}}” (severity: {{feedback_severity}}). {{feedback_url}}',
    ],
    [
      'Nuevos comentarios de MyMoneyMap',
      'Nuevo comentario {{feedback_kind}}: «{{feedback_title}}» (gravedad: {{feedback_severity}}). {{feedback_url}}',
    ],
    [
      'Új MyMoneyMap-visszajelzés',
      'Új {{feedback_kind}} visszajelzés: „{{feedback_title}}” (súlyosság: {{feedback_severity}}). {{feedback_url}}',
    ],
  ),
  localized(
    'registration_validation',
    'transactional',
    ['user_first_name', 'verification_link'],
    [
      'Verify your email address',
      'Hello {{user_first_name}}, verify your email: {{verification_link}}',
    ],
    [
      'Verifica tu correo electrónico',
      'Hola {{user_first_name}}, verifica tu correo: {{verification_link}}',
    ],
    [
      'Erősítsd meg az e-mail-címed',
      'Szia {{user_first_name}}! Erősítsd meg az e-mail-címed: {{verification_link}}',
    ],
  ),
  localized(
    'welcome',
    'transactional',
    ['user_first_name'],
    [
      'Welcome to MyMoneyMap',
      'Welcome {{user_first_name}}. Start by recording your finances and setting your own goals.',
    ],
    [
      'Te damos la bienvenida a MyMoneyMap',
      'Hola {{user_first_name}}. Empieza registrando tus finanzas y definiendo tus objetivos.',
    ],
    [
      'Üdvözöl a MyMoneyMap',
      'Szia {{user_first_name}}! Kezdd a pénzügyeid rögzítésével és a saját céljaid beállításával.',
    ],
  ),
  localized(
    'password_reset',
    'transactional',
    ['user_first_name', 'reset_link'],
    [
      'Reset your MyMoneyMap password',
      'Hello {{user_first_name}}, reset your password: {{reset_link}}',
    ],
    [
      'Restablece tu contraseña de MyMoneyMap',
      'Hola {{user_first_name}}, restablece tu contraseña: {{reset_link}}',
    ],
    [
      'MyMoneyMap-jelszó visszaállítása',
      'Szia {{user_first_name}}! Itt állíthatod vissza a jelszavad: {{reset_link}}',
    ],
  ),
  localized(
    'email_change',
    'transactional',
    ['user_first_name', 'change_link', 'pending_email'],
    [
      'Confirm your email change',
      'Hello {{user_first_name}}, confirm {{pending_email}} as your new address: {{change_link}}',
    ],
    [
      'Confirma el cambio de correo',
      'Hola {{user_first_name}}, confirma {{pending_email}} como tu nueva dirección: {{change_link}}',
    ],
    [
      'E-mail-cím módosításának megerősítése',
      'Szia {{user_first_name}}! Erősítsd meg az új címed ({{pending_email}}): {{change_link}}',
    ],
  ),
  localized(
    'feedback_resolved',
    'transactional',
    ['user_first_name', 'feedback_title', 'feedback_url'],
    [
      'Your feedback was resolved',
      'Hello {{user_first_name}}, we resolved “{{feedback_title}}”. Details: {{feedback_url}}',
    ],
    [
      'Tus comentarios se han resuelto',
      'Hola {{user_first_name}}, resolvimos «{{feedback_title}}». Detalles: {{feedback_url}}',
    ],
    [
      'Megoldottuk a visszajelzésed',
      'Szia {{user_first_name}}! Megoldottuk ezt: „{{feedback_title}}”. Részletek: {{feedback_url}}',
    ],
  ),
  localized(
    'goal_congratulations',
    'transactional',
    ['user_first_name', 'achievement_summary', 'cta_url'],
    [
      'Congratulations on your milestone',
      'Hello {{user_first_name}}, {{achievement_summary}} Review it: {{cta_url}}',
    ],
    [
      'Enhorabuena por tu logro',
      'Hola {{user_first_name}}, {{achievement_summary}} Revísalo: {{cta_url}}',
    ],
    [
      'Gratulálunk a mérföldkőhöz',
      'Szia {{user_first_name}}! {{achievement_summary}} Megtekintés: {{cta_url}}',
    ],
  ),
  ...['report_weekly', 'report_monthly', 'report_yearly'].map((code) =>
    localized(
      code,
      'educational',
      ['user_first_name', 'report_period', 'total_spent', 'total_income', 'net_change', 'app_url'],
      [
        'Your MyMoneyMap report',
        'Hello {{user_first_name}}, your report for {{report_period}}: spent {{total_spent}}, income {{total_income}}, net {{net_change}}. {{app_url}}',
      ],
      [
        'Tu informe de MyMoneyMap',
        'Hola {{user_first_name}}, informe de {{report_period}}: gastos {{total_spent}}, ingresos {{total_income}}, neto {{net_change}}. {{app_url}}',
      ],
      [
        'MyMoneyMap-jelentésed',
        'Szia {{user_first_name}}! {{report_period}} összesítése: kiadás {{total_spent}}, bevétel {{total_income}}, nettó {{net_change}}. {{app_url}}',
      ],
    ),
  ),
  localized(
    'cashflow_overspend',
    'educational',
    ['user_first_name', 'rule_label', 'over_amount', 'cta_url'],
    [
      'A cashflow rule is over budget',
      'Hello {{user_first_name}}, {{rule_label}} is over by {{over_amount}}. Review: {{cta_url}}',
    ],
    [
      'Una regla de flujo de caja excede el presupuesto',
      'Hola {{user_first_name}}, {{rule_label}} excede {{over_amount}}. Revisa: {{cta_url}}',
    ],
    [
      'Egy cashflow-szabály túllépte a keretet',
      'Szia {{user_first_name}}! A(z) {{rule_label}} túllépése {{over_amount}}. Megtekintés: {{cta_url}}',
    ],
  ),
  localized(
    'emergency_motivation',
    'educational',
    ['user_first_name', 'ef_current', 'ef_target', 'cta_url'],
    [
      'Keep building your emergency fund',
      'Hello {{user_first_name}}, your reserve is {{ef_current}} toward {{ef_target}}. {{cta_url}}',
    ],
    [
      'Sigue construyendo tu fondo de emergencia',
      'Hola {{user_first_name}}, tu reserva es {{ef_current}} de {{ef_target}}. {{cta_url}}',
    ],
    [
      'Folytasd a vésztartalék építését',
      'Szia {{user_first_name}}! A tartalékod {{ef_current}} / {{ef_target}}. {{cta_url}}',
    ],
  ),
  localized(
    'emergency_withdrawal',
    'transactional',
    ['user_first_name', 'withdrawal_amount', 'remaining_amount', 'cta_url'],
    [
      'Emergency reserve withdrawal recorded',
      'Hello {{user_first_name}}, withdrawal {{withdrawal_amount}}; remaining {{remaining_amount}}. {{cta_url}}',
    ],
    [
      'Retiro del fondo de emergencia registrado',
      'Hola {{user_first_name}}, retiro {{withdrawal_amount}}; restante {{remaining_amount}}. {{cta_url}}',
    ],
    [
      'Vésztartalék-kivét rögzítve',
      'Szia {{user_first_name}}! Kivét: {{withdrawal_amount}}; maradék: {{remaining_amount}}. {{cta_url}}',
    ],
  ),
  localized(
    'tips_and_tricks',
    'educational',
    ['user_first_name', 'tip_title', 'tip_body', 'tip_link'],
    [
      'MyMoneyMap tips & tricks',
      'Hello {{user_first_name}}, {{tip_title}}: {{tip_body}} {{tip_link}}',
    ],
    [
      'Consejos de MyMoneyMap',
      'Hola {{user_first_name}}, {{tip_title}}: {{tip_body}} {{tip_link}}',
    ],
    ['MyMoneyMap tippek', 'Szia {{user_first_name}}! {{tip_title}}: {{tip_body}} {{tip_link}}'],
  ),
] as const;

export function catalogTemplate(code: string): CatalogTemplate | undefined {
  return EMAIL_TEMPLATE_CATALOG.find((template) => template.code === code);
}
