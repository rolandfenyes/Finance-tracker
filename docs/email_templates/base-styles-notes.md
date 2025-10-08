# MyMoneyMap Email System — Style Notes

- **Color palette**
  - Primary CTA / accents: `#2F8857` (MyMoneyMap Green)
  - Text / headings: `#1F1F1F`
  - Light background: `#F5F5F5`
  - Card / neutral background: `#FFFFFF`
  - Dividers / borders: `#E5E7EB`
- **Typography**
  - Font stack: `-apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif`
  - Base font size: 16px, line-height 24px (1.5x)
  - Headings use 28–22px, bold weight for hierarchy.
- **Layout**
  - Outer wrapper max-width: 640px, centered using a full-width table with background color.
  - Cards use 10–12px corner radius simulated with `border-radius` (supported clients) and fallback rounded corners minimal effect.
  - KPI cards stack two-per-row on mobile via `display:block` on table cells when width drops below 480px using attribute selectors.
- **Dark mode approach**
  - Inline colors favor medium-dark text (#1F1F1F) on light backgrounds to maintain contrast even when clients auto-invert colors.
  - Backgrounds avoid pure white/black to reduce inversion severity; accent color offers sufficient contrast at 4.5:1 on both light/dark.
  - VML buttons specify both `fillcolor` and text color for Outlook consistency.
- **Accessibility**
  - All actionable elements have descriptive link text.
  - Alt text provided for the logo placeholder.
  - Table headers include `scope="col"` where appropriate.
- **Optional tracking**
  - Include the commented 1×1 pixel placeholder block near the footer when a transparent tracker is required.

