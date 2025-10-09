# Email Template Translation Requirements

To translate the transactional emails at `docs/email_templates/*.html` into each user's selected language, the application needs source material for every supported locale. At the moment only the English HTML versions exist, so the following items are required before the feature can be implemented:

1. **Translated copy for every template**  
   Provide localized text for each message (headings, paragraphs, button labels, footers, etc.) in all supported languages. Supplying a parallel HTML file per locale (for example `email_report_weekly.es.html`) is the simplest approach, but a sentence-by-sentence translation sheet would also work.

2. **Language coverage confirmation**  
   Confirm which locales should be supported (e.g., `en`, `es`, `hu`). If additional languages are needed, deliver the corresponding translations as well.

3. **Terminology guidance (optional but helpful)**  
   If specific phrases must remain untranslated (brand names, product terms, etc.), call them out so the implementation can keep them intact across locales.

With these assets on hand, the code can be extended to load the appropriate localized template or inject translated strings when generating the email before it is sent.
