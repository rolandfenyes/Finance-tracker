(function(global){
  const theme = {
    name: 'MyMoneyMap Jade',
    brand: {
      primary: '#4b966e',
      accent: '#3c7b5b',
      muted: '#e6f1eb',
      deep: '#163428',
      palette: {
        50: '#f1f7f4',
        100: '#dcece2',
        200: '#c0ddcc',
        300: '#94c3aa',
        400: '#69a986',
        500: '#4b966e',
        600: '#3c7b5b',
        700: '#32644b',
        800: '#2b513f',
        900: '#234234',
        950: '#11241d'
      }
    },
    neutrals: {
      text: {
        light: 'rgba(35, 66, 52, 0.82)',
        dark: 'rgba(226, 244, 236, 0.95)'
      },
      subtle: {
        light: '#5a7466',
        dark: '#9fb6a9'
      }
    },
    typography: {
      fontFamily: '"IBM Plex Sans"',
      fontStack: ['"IBM Plex Sans"', 'Inter', 'system-ui', '-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'sans-serif']
    },
    shadows: {
      glass: '0 30px 60px -25px rgba(17, 36, 29, 0.45)',
      brandGlow: '0 20px 45px -20px rgba(75, 150, 110, 0.65)'
    },
    radii: {
      xl: '1.5rem',
      '3xl': '1.75rem',
      '4xl': '2.5rem'
    },
    blur: {
      xs: '4px',
      md: '12px',
      xl: '22px'
    },
    gradients: {
      mesh: {
        light: 'radial-gradient(120% 120% at 10% 20%, rgba(75,150,110,0.18) 0%, transparent 55%), radial-gradient(90% 90% at 90% 0%, rgba(42,94,70,0.12) 0%, transparent 65%), linear-gradient(135deg, #f8fbf9 0%, #eef5f1 50%, #f6faf6 100%)',
        dark: 'radial-gradient(120% 120% at 0% 0%, rgba(75,150,110,0.35) 0%, transparent 60%), radial-gradient(90% 90% at 100% 20%, rgba(28,66,50,0.5) 0%, transparent 70%), linear-gradient(160deg, #060d0b 0%, #0f1e18 55%, #0a1612 100%)'
      },
      bodyGlow: {
        light: 'radial-gradient(30% 30% at 20% 20%, rgba(75, 150, 110, 0.35), transparent 65%), radial-gradient(45% 45% at 80% 0%, rgba(48, 104, 75, 0.25), transparent 70%)',
        dark: 'radial-gradient(40% 40% at 12% 18%, rgba(75, 150, 110, 0.45), transparent 70%), radial-gradient(40% 40% at 85% 10%, rgba(18, 36, 29, 0.55), transparent 75%)'
      }
    },
    surfaces: {
      card: {
        light: 'rgba(255, 255, 255, 0.82)',
        dark: 'rgba(14, 27, 23, 0.78)',
        borderLight: 'rgba(255, 255, 255, 0.45)',
        borderDark: 'rgba(30, 64, 54, 0.55)'
      },
      tile: {
        light: 'rgba(255,255,255,0.68)',
        dark: 'rgba(18,30,26,0.6)',
        borderLight: 'rgba(255,255,255,0.5)',
        borderDark: 'rgba(43, 60, 53, 0.6)'
      },
      panel: {
        light: 'rgba(255,255,255,0.74)',
        dark: 'rgba(18, 30, 26, 0.68)',
        borderLight: 'rgba(255,255,255,0.4)',
        borderDark: 'rgba(43, 60, 53, 0.55)'
      },
      panelGhost: {
        light: 'rgba(230, 241, 235, 0.55)',
        dark: 'rgba(24, 39, 34, 0.6)',
        borderLight: 'rgba(214, 229, 222, 0.65)',
        borderDark: 'rgba(46, 72, 62, 0.7)'
      },
      list: {
        containerLight: 'rgba(255,255,255,0.72)',
        containerDark: 'rgba(16, 27, 23, 0.72)',
        itemLight: 'rgba(255,255,255,0.95)',
        itemAltLight: 'rgba(242, 249, 245, 0.92)',
        itemDark: 'rgba(18,28,24,0.85)',
        itemAltDark: 'rgba(11,22,19,0.82)',
        borderLight: 'rgba(220, 235, 228, 0.7)',
        borderDark: 'rgba(48, 66, 59, 0.65)',
        dividerLight: 'rgba(211, 226, 220, 0.55)',
        dividerDark: 'rgba(41, 58, 52, 0.55)'
      },
      modal: {
        panelLight: 'rgba(255,255,255,0.85)',
        panelDark: 'rgba(15,30,26,0.92)',
        borderLight: 'rgba(255,255,255,0.25)',
        borderDark: 'rgba(30,64,54,0.45)',
        backdrop: 'rgba(6, 13, 11, 0.6)'
      }
    },
    icons: {
      neutral: {
        bgLight: 'rgba(255,255,255,0.75)',
        borderLight: 'rgba(223, 235, 229, 0.7)',
        colorLight: '#32644b',
        hoverLight: 'rgba(75,150,110,0.15)',
        bgDark: 'rgba(16, 26, 23, 0.85)',
        borderDark: 'rgba(51, 72, 64, 0.65)',
        colorDark: '#d3efe1',
        hoverDark: 'rgba(56, 94, 75, 0.4)'
      },
      primary: {
        bgLight: 'rgba(75,150,110,0.18)',
        borderLight: 'rgba(75,150,110,0.35)',
        colorLight: '#2f6e54',
        hoverLight: 'rgba(75,150,110,0.25)',
        bgDark: 'rgba(75,150,110,0.28)',
        borderDark: 'rgba(119, 201, 158, 0.45)',
        colorDark: '#a0f0ce',
        hoverDark: 'rgba(75,150,110,0.35)'
      },
      danger: {
        bgLight: 'rgba(244, 63, 94, 0.12)',
        borderLight: 'rgba(244, 63, 94, 0.35)',
        colorLight: '#b91c1c',
        hoverLight: 'rgba(244, 63, 94, 0.2)',
        bgDark: 'rgba(244, 63, 94, 0.18)',
        borderDark: 'rgba(248, 113, 113, 0.42)',
        colorDark: '#fca5a5',
        hoverDark: 'rgba(244, 63, 94, 0.3)'
      }
    }
  };

  function buildVariables(){
    const palette = theme.brand.palette;
    const rootLines = [
      ':root {',
      `  --mm-font-family: ${theme.typography.fontStack.join(', ')};`,
      `  --mm-brand-primary: ${theme.brand.primary};`,
      `  --mm-brand-accent: ${theme.brand.accent};`,
      `  --mm-brand-muted: ${theme.brand.muted};`,
      `  --mm-brand-deep: ${theme.brand.deep};`,
      `  --mm-text-color: ${theme.neutrals.text.light};`,
      `  --mm-subtle-text: ${theme.neutrals.subtle.light};`
    ];
    Object.keys(palette).forEach((key) => {
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
      `}`
    ];

    return rootLines.concat(darkLines).join('\n');
  }

  function injectCSSVariables(doc){
    if (!doc) return;
    const existing = doc.getElementById('mymoneymap-theme-variables');
    if (existing) existing.remove();
    const style = doc.createElement('style');
    style.id = 'mymoneymap-theme-variables';
    style.textContent = buildVariables();
    doc.head.appendChild(style);
  }

  injectCSSVariables(global.document);
  global.MyMoneyMapTheme = theme;
})(window);
