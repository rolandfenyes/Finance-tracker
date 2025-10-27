(function (global) {
    const bases = global.__MYMONEYMAP_THEME_BASES || {};

    function clamp01(value) {
        if (!Number.isFinite(value)) return 0;
        return Math.min(1, Math.max(0, value));
    }

    function normalizeHex(value, fallback = '#4b966e') {
        if (typeof value !== 'string') return fallback;
        let hex = value.trim();
        if (!hex) return fallback;
        if (hex.startsWith('#')) hex = hex.slice(1);
        if (hex.length === 3) {
            hex = hex
                .split('')
                .map(c => c + c)
                .join('');
        }
        if (hex.length !== 6 || /[^0-9a-f]/i.test(hex)) {
            return fallback;
        }
        return `#${hex.toLowerCase()}`;
    }

    function hexToRgb(hex) {
        const normalized = normalizeHex(hex);
        const value = normalized.slice(1);
        return {
            r: parseInt(value.slice(0, 2), 16),
            g: parseInt(value.slice(2, 4), 16),
            b: parseInt(value.slice(4, 6), 16),
        };
    }

    function rgbToHex({ r, g, b }) {
        const toHex = component => {
            const clamped = Math.max(0, Math.min(255, Math.round(component)));
            return clamped.toString(16).padStart(2, '0');
        };
        return `#${toHex(r)}${toHex(g)}${toHex(b)}`;
    }

    function getBrightness(color) {
        const { r, g, b } = hexToRgb(color);
        return (0.299 * r + 0.587 * g + 0.114 * b) / 255;
    }

    function mix(colorA, colorB, amount) {
        const ratio = clamp01(amount);
        const rgbA = hexToRgb(colorA);
        const rgbB = hexToRgb(colorB);
        return rgbToHex({
            r: rgbA.r * (1 - ratio) + rgbB.r * ratio,
            g: rgbA.g * (1 - ratio) + rgbB.g * ratio,
            b: rgbA.b * (1 - ratio) + rgbB.b * ratio,
        });
    }

    function lighten(color, amount) {
        return mix(color, '#ffffff', clamp01(amount));
    }

    function darken(color, amount) {
        return mix(color, '#000000', clamp01(amount));
    }

    function toRgba(color, alpha) {
        const { r, g, b } = hexToRgb(color);
        return `rgba(${r}, ${g}, ${b}, ${clamp01(alpha)})`;
    }

    function createPalette(base) {
        const color = normalizeHex(base);
        return {
            50: lighten(color, 0.92),
            100: lighten(color, 0.82),
            200: lighten(color, 0.7),
            300: lighten(color, 0.52),
            400: lighten(color, 0.32),
            500: color,
            600: darken(color, 0.12),
            700: darken(color, 0.24),
            800: darken(color, 0.36),
            900: darken(color, 0.52),
            950: darken(color, 0.68),
        };
    }

    function buildVariables(theme) {
        if (!theme) return '';
        const palette = theme.brand.palette || {};
        const primaryRgb = hexToRgb(theme.brand.primary || '#4b966e');
        const rootLines = [
            ':root {',
            `  --mm-font-family: ${theme.typography.fontStack.join(', ')};`,
            `  --mm-brand-primary: ${theme.brand.primary};`,
            `  --mm-brand-accent: ${theme.brand.accent};`,
            `  --mm-brand-muted: ${theme.brand.muted};`,
            `  --mm-brand-deep: ${theme.brand.deep};`,
            `  --mm-brand-primary-rgb: ${primaryRgb.r}, ${primaryRgb.g}, ${primaryRgb.b};`,
            `  --mm-text-color: ${theme.neutrals.text.light};`,
            `  --mm-subtle-text: ${theme.neutrals.subtle.light};`,
        ];

        Object.keys(palette).forEach(key => {
            rootLines.push(`  --mm-brand-${key}: ${palette[key]};`);
        });

        rootLines.push(
            `  --mm-shadow-glass: ${theme.shadows.glass};`,
            `  --mm-shadow-brand-glow: ${theme.shadows.brandGlow};`,
            `  --mm-radius-xl: ${theme.radii.xl};`,
            `  --mm-radius-3xl: ${theme.radii['3xl']};`,
            `  --mm-radius-4xl: ${theme.radii['4xl']};`,
            `  --mm-blur-xs: ${theme.blur.xs};`,
            `  --mm-blur-md: ${theme.blur.md};`,
            `  --mm-blur-xl: ${theme.blur.xl};`,
            `  --mm-mesh-background: ${theme.gradients.mesh.light};`,
            `  --mm-mesh-background-dark: ${theme.gradients.mesh.dark};`,
            `  --mm-body-glow: ${theme.gradients.bodyGlow.light};`,
            `  --mm-body-glow-dark: ${theme.gradients.bodyGlow.dark};`,
            `  --mm-card-surface: ${theme.surfaces.card.light};`,
            `  --mm-card-border: ${theme.surfaces.card.borderLight};`,
            `  --mm-tile-surface: ${theme.surfaces.tile.light};`,
            `  --mm-tile-border: ${theme.surfaces.tile.borderLight};`,
            `  --mm-panel-surface: ${theme.surfaces.panel.light};`,
            `  --mm-panel-border: ${theme.surfaces.panel.borderLight};`,
            `  --mm-panel-ghost-surface: ${theme.surfaces.panelGhost.light};`,
            `  --mm-panel-ghost-border: ${theme.surfaces.panelGhost.borderLight};`,
            `  --mm-list-surface: ${theme.surfaces.list.containerLight};`,
            `  --mm-list-item: ${theme.surfaces.list.itemLight};`,
            `  --mm-list-item-alt: ${theme.surfaces.list.itemAltLight};`,
            `  --mm-list-border: ${theme.surfaces.list.borderLight};`,
            `  --mm-list-divider: ${theme.surfaces.list.dividerLight};`,
            `  --mm-modal-surface: ${theme.surfaces.modal.panelLight};`,
            `  --mm-modal-border: ${theme.surfaces.modal.borderLight};`,
            `  --mm-modal-backdrop: ${theme.surfaces.modal.backdrop};`,
            `  --mm-icon-bg: ${theme.icons.neutral.bgLight};`,
            `  --mm-icon-border: ${theme.icons.neutral.borderLight};`,
            `  --mm-icon-color: ${theme.icons.neutral.colorLight};`,
            `  --mm-icon-hover: ${theme.icons.neutral.hoverLight};`,
            `  --mm-icon-primary-bg: ${theme.icons.primary.bgLight};`,
            `  --mm-icon-primary-border: ${theme.icons.primary.borderLight};`,
            `  --mm-icon-primary-color: ${theme.icons.primary.colorLight};`,
            `  --mm-icon-primary-hover: ${theme.icons.primary.hoverLight};`,
            `  --mm-icon-danger-bg: ${theme.icons.danger.bgLight};`,
            `  --mm-icon-danger-border: ${theme.icons.danger.borderLight};`,
            `  --mm-icon-danger-color: ${theme.icons.danger.colorLight};`,
            `  --mm-icon-danger-hover: ${theme.icons.danger.hoverLight};`,
            `}`
        );

        const darkLines = [
            ':root[data-theme="dark"] {',
            `  --mm-text-color: ${theme.neutrals.text.dark};`,
            `  --mm-subtle-text: ${theme.neutrals.subtle.dark};`,
            `  --mm-card-surface: ${theme.surfaces.card.dark};`,
            `  --mm-card-border: ${theme.surfaces.card.borderDark};`,
            `  --mm-tile-surface: ${theme.surfaces.tile.dark};`,
            `  --mm-tile-border: ${theme.surfaces.tile.borderDark};`,
            `  --mm-panel-surface: ${theme.surfaces.panel.dark};`,
            `  --mm-panel-border: ${theme.surfaces.panel.borderDark};`,
            `  --mm-panel-ghost-surface: ${theme.surfaces.panelGhost.dark};`,
            `  --mm-panel-ghost-border: ${theme.surfaces.panelGhost.borderDark};`,
            `  --mm-list-surface: ${theme.surfaces.list.containerDark};`,
            `  --mm-list-item: ${theme.surfaces.list.itemDark};`,
            `  --mm-list-item-alt: ${theme.surfaces.list.itemAltDark};`,
            `  --mm-list-border: ${theme.surfaces.list.borderDark};`,
            `  --mm-list-divider: ${theme.surfaces.list.dividerDark};`,
            `  --mm-modal-surface: ${theme.surfaces.modal.panelDark};`,
            `  --mm-modal-border: ${theme.surfaces.modal.borderDark};`,
            `  --mm-icon-bg: ${theme.icons.neutral.bgDark};`,
            `  --mm-icon-border: ${theme.icons.neutral.borderDark};`,
            `  --mm-icon-color: ${theme.icons.neutral.colorDark};`,
            `  --mm-icon-hover: ${theme.icons.neutral.hoverDark};`,
            `  --mm-icon-primary-bg: ${theme.icons.primary.bgDark};`,
            `  --mm-icon-primary-border: ${theme.icons.primary.borderDark};`,
            `  --mm-icon-primary-color: ${theme.icons.primary.colorDark};`,
            `  --mm-icon-primary-hover: ${theme.icons.primary.hoverDark};`,
            `  --mm-icon-danger-bg: ${theme.icons.danger.bgDark};`,
            `  --mm-icon-danger-border: ${theme.icons.danger.borderDark};`,
            `  --mm-icon-danger-color: ${theme.icons.danger.colorDark};`,
            `  --mm-icon-danger-hover: ${theme.icons.danger.hoverDark};`,
            `  --mm-body-glow: ${theme.gradients.bodyGlow.dark};`,
            `}`,
        ];

        return rootLines.concat(darkLines).join('\n');
    }

    function injectCSSVariables(theme, doc) {
        if (!doc || !theme) return;
        const existing = doc.getElementById('mymoneymap-theme-variables');
        if (existing) existing.remove();
        const style = doc.createElement('style');
        style.id = 'mymoneymap-theme-variables';
        style.textContent = buildVariables(theme);
        doc.head.appendChild(style);
    }

    function createTheme(slug, meta = {}) {
        const base = normalizeHex(meta.base || '#4b966e');
        const accent = normalizeHex(meta.accent || darken(base, 0.18));
        const muted = normalizeHex(meta.muted || lighten(base, 0.85));
        const deep = normalizeHex(meta.deep || darken(base, 0.55));
        const palette = createPalette(base);
        const primary = palette[500];
        const surfaceDeep = darken(base, 0.72);
        const surfaceGhost = lighten(base, 0.82);
        const borderLight = lighten(base, 0.72);
        const borderDark = darken(base, 0.6);
        const baseBrightness = getBrightness(base);
        const textLightOverride = meta.text_light ? normalizeHex(meta.text_light) : null;
        const textDarkOverride = meta.text_dark ? normalizeHex(meta.text_dark) : null;
        const subtleLightOverride = meta.subtle_light ? normalizeHex(meta.subtle_light) : null;
        const subtleDarkOverride = meta.subtle_dark ? normalizeHex(meta.subtle_dark) : null;
        const isHighKeyBase = baseBrightness >= 0.8;
        const fallbackLightText = isHighKeyBase
            ? normalizeHex(meta.deep || '#1f2937')
            : darken(base, 0.58);
        const lightTextBase = textLightOverride || fallbackLightText;
        const lightTextAlpha = textLightOverride || isHighKeyBase ? 0.98 : 0.82;
        const darkTextBase = textDarkOverride || lighten(base, 0.74);
        const darkTextAlpha = textDarkOverride ? 0.98 : 0.94;
        const subtleLight =
            subtleLightOverride ||
            (isHighKeyBase ? lighten(lightTextBase, 0.55) : lighten(base, 0.4));
        const subtleDark = subtleDarkOverride || lighten(base, 0.64);
        const textDeep = toRgba(lightTextBase, lightTextAlpha);
        const textBright = toRgba(darkTextBase, darkTextAlpha);

        return {
            slug,
            name: meta.name || slug,
            brand: {
                primary,
                accent,
                muted,
                deep,
                palette,
            },
            neutrals: {
                text: {
                    light: textDeep,
                    dark: textBright,
                },
                subtle: {
                    light: subtleLight,
                    dark: subtleDark,
                },
            },
            typography: {
                fontFamily: '"IBM Plex Sans"',
                fontStack: [
                    '"IBM Plex Sans"',
                    'Inter',
                    'system-ui',
                    '-apple-system',
                    'BlinkMacSystemFont',
                    '"Segoe UI"',
                    'sans-serif',
                ],
            },
            shadows: {
                glass: `0 30px 60px -25px ${toRgba(darken(base, 0.75), 0.45)}`,
                brandGlow: `0 20px 45px -20px ${toRgba(primary, 0.65)}`,
            },
            radii: {
                xl: '1.5rem',
                '3xl': '1.75rem',
                '4xl': '2.5rem',
            },
            blur: {
                xs: '4px',
                md: '12px',
                xl: '22px',
            },
            gradients: {
                mesh: {
                    light: [
                        `radial-gradient(120% 120% at 10% 20%, ${toRgba(
                            lighten(base, 0.15),
                            0.18
                        )} 0%, transparent 55%)`,
                        `radial-gradient(90% 90% at 90% 0%, ${toRgba(
                            accent,
                            0.12
                        )} 0%, transparent 65%)`,
                        `linear-gradient(135deg, ${lighten(base, 0.9)} 0%, ${lighten(
                            base,
                            0.74
                        )} 50%, ${lighten(base, 0.96)} 100%)`,
                    ].join(', '),
                    dark: [
                        `radial-gradient(120% 120% at 0% 0%, ${toRgba(
                            primary,
                            0.38
                        )} 0%, transparent 60%)`,
                        `radial-gradient(90% 90% at 100% 20%, ${toRgba(
                            deep,
                            0.5
                        )} 0%, transparent 70%)`,
                        `linear-gradient(160deg, ${darken(base, 0.84)} 0%, ${darken(
                            base,
                            0.62
                        )} 55%, ${darken(base, 0.76)} 100%)`,
                    ].join(', '),
                },
                bodyGlow: {
                    light: [
                        `radial-gradient(32% 32% at 18% 18%, ${toRgba(
                            primary,
                            0.35
                        )}, transparent 65%)`,
                        `radial-gradient(45% 45% at 82% 4%, ${toRgba(
                            accent,
                            0.24
                        )}, transparent 70%)`,
                    ].join(', '),
                    dark: [
                        `radial-gradient(40% 40% at 12% 18%, ${toRgba(
                            primary,
                            0.45
                        )}, transparent 70%)`,
                        `radial-gradient(40% 40% at 88% 12%, ${toRgba(
                            deep,
                            0.55
                        )}, transparent 75%)`,
                    ].join(', '),
                },
            },
            surfaces: {
                card: {
                    light: 'rgba(255, 255, 255, 0.82)',
                    dark: toRgba(surfaceDeep, 0.78),
                    borderLight: 'rgba(255, 255, 255, 0.45)',
                    borderDark: toRgba(darken(base, 0.4), 0.55),
                },
                tile: {
                    light: 'rgba(255,255,255,0.68)',
                    dark: toRgba(darken(base, 0.7), 0.6),
                    borderLight: 'rgba(255,255,255,0.5)',
                    borderDark: toRgba(darken(base, 0.55), 0.6),
                },
                panel: {
                    light: 'rgba(255,255,255,0.74)',
                    dark: toRgba(darken(base, 0.65), 0.68),
                    borderLight: 'rgba(255,255,255,0.4)',
                    borderDark: toRgba(darken(base, 0.48), 0.55),
                },
                panelGhost: {
                    light: toRgba(surfaceGhost, 0.55),
                    dark: toRgba(darken(base, 0.55), 0.6),
                    borderLight: toRgba(lighten(base, 0.76), 0.65),
                    borderDark: toRgba(darken(base, 0.5), 0.7),
                },
                list: {
                    containerLight: 'rgba(255,255,255,0.72)',
                    containerDark: toRgba(darken(base, 0.78), 0.72),
                    itemLight: 'rgba(255,255,255,0.95)',
                    itemAltLight: toRgba(lighten(base, 0.88), 0.92),
                    itemDark: toRgba(darken(base, 0.72), 0.85),
                    itemAltDark: toRgba(darken(base, 0.86), 0.82),
                    borderLight: toRgba(borderLight, 0.7),
                    borderDark: toRgba(borderDark, 0.65),
                    dividerLight: toRgba(lighten(base, 0.7), 0.55),
                    dividerDark: toRgba(darken(base, 0.65), 0.55),
                },
                modal: {
                    panelLight: 'rgba(255,255,255,0.85)',
                    panelDark: toRgba(darken(base, 0.76), 0.92),
                    borderLight: 'rgba(255,255,255,0.25)',
                    borderDark: toRgba(darken(base, 0.45), 0.45),
                    backdrop: 'rgba(6, 13, 11, 0.6)',
                },
            },
            icons: {
                neutral: {
                    bgLight: 'rgba(255,255,255,0.75)',
                    borderLight: toRgba(lighten(base, 0.68), 0.7),
                    colorLight: darken(base, 0.3),
                    hoverLight: toRgba(primary, 0.15),
                    bgDark: toRgba(darken(base, 0.82), 0.85),
                    borderDark: toRgba(darken(base, 0.55), 0.65),
                    colorDark: lighten(base, 0.68),
                    hoverDark: toRgba(darken(base, 0.42), 0.4),
                },
                primary: {
                    bgLight: toRgba(primary, 0.18),
                    borderLight: toRgba(primary, 0.35),
                    colorLight: darken(base, 0.25),
                    hoverLight: toRgba(primary, 0.25),
                    bgDark: toRgba(primary, 0.28),
                    borderDark: toRgba(lighten(base, 0.4), 0.45),
                    colorDark: lighten(base, 0.62),
                    hoverDark: toRgba(primary, 0.35),
                },
                danger: {
                    bgLight: 'rgba(244, 63, 94, 0.12)',
                    borderLight: 'rgba(244, 63, 94, 0.35)',
                    colorLight: '#b91c1c',
                    hoverLight: 'rgba(244, 63, 94, 0.2)',
                    bgDark: 'rgba(244, 63, 94, 0.18)',
                    borderDark: 'rgba(248, 113, 113, 0.42)',
                    colorDark: '#fca5a5',
                    hoverDark: 'rgba(244, 63, 94, 0.3)',
                },
            },
        };
    }

    function buildThemes(definitions) {
        const themes = {};
        Object.keys(definitions).forEach(slug => {
            themes[slug] = createTheme(slug, definitions[slug]);
        });
        return themes;
    }

    function updateMetaThemeColors(theme) {
        if (!theme || !global.document) return;
        const brand = theme.brand || {};
        const surfaces = theme.surfaces || {};
        const lightColor =
            brand.muted || (surfaces.panelGhost && surfaces.panelGhost.light) || '#f8fbf9';
        const darkColor = brand.deep || (surfaces.panel && surfaces.panel.dark) || '#0f1e18';
        const defaultMeta = global.document.querySelector(
            'meta[name="theme-color"][data-theme-color="default"]'
        );
        const lightMeta = global.document.querySelector(
            'meta[name="theme-color"][data-theme-color="light"]'
        );
        const darkMeta = global.document.querySelector(
            'meta[name="theme-color"][data-theme-color="dark"]'
        );

        if (defaultMeta) defaultMeta.setAttribute('content', lightColor);
        if (lightMeta) lightMeta.setAttribute('content', lightColor);
        if (darkMeta) darkMeta.setAttribute('content', darkColor);
    }

    const themeDefinitions = Object.keys(bases).length
        ? bases
        : {
              'verdant-horizon': {
                  name: 'Verdant Horizon',
                  base: '#4b966e',
                  accent: '#3c7b5b',
                  muted: '#e6f1eb',
                  deep: '#163428',
              },
          };

    const themes = buildThemes(themeDefinitions);
    const slugs = Object.keys(themes);

    function resolveInitialSlug() {
        const serverSelected = global.__MYMONEYMAP_SELECTED_THEME;
        if (serverSelected && themes[serverSelected]) {
            return serverSelected;
        }

        try {
            const stored =
                global.localStorage && global.localStorage.getItem('mymoneymap-brand-theme');
            if (stored && themes[stored]) {
                return stored;
            }
        } catch (error) {
            // ignore storage errors
        }

        return slugs[0] || 'verdant-horizon';
    }

    function applyTheme(slug, options = {}) {
        if (!themes || !Object.keys(themes).length) return null;
        const selected = themes[slug] || themes[resolveInitialSlug()];
        if (!selected) return null;

        injectCSSVariables(selected, global.document);
        updateMetaThemeColors(selected);

        if (global.document && global.document.documentElement) {
            global.document.documentElement.setAttribute('data-brand-theme', selected.slug);
        }

        global.MyMoneyMapTheme = selected;
        global.MyMoneyMapThemeSlug = selected.slug;

        if (!options.silent) {
            const event = new CustomEvent('brandthemechange', {
                detail: { slug: selected.slug, theme: selected },
            });
            if (global.document) {
                global.document.dispatchEvent(event);
            }
        }

        return selected;
    }

    const initialSlug = resolveInitialSlug();
    const initialTheme = applyTheme(initialSlug, { silent: true });

    if (initialTheme && initialSlug) {
        try {
            if (global.localStorage) {
                global.localStorage.setItem('mymoneymap-brand-theme', initialSlug);
            }
        } catch (error) {
            // ignore storage errors
        }
    }

    global.MyMoneyMapThemes = themes;
    global.MyMoneyMapThemeMeta = themeDefinitions;
    global.MyMoneyMapTheme = initialTheme;
    global.MyMoneyMapThemeSlug = initialTheme ? initialTheme.slug : initialSlug;
    global.MyMoneyMapApplyTheme = function (slug) {
        const applied = applyTheme(slug);
        if (applied) {
            try {
                if (global.localStorage) {
                    global.localStorage.setItem('mymoneymap-brand-theme', applied.slug);
                }
            } catch (error) {
                // ignore storage errors
            }
        }
        return applied;
    };
})(window);
