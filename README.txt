HERO INDUSTRIAL PARK – 3 | GONDAL INFRA

CONTENTS
- index.html: single-page landing page
- style.css / script.js: styling and lightweight interactions
- contact.php: server-side enquiry handler (SMTP via PHPMailer)
- assets/: supplied project photos and brand logos, optimized for web

DEPLOYMENT
1. Upload the complete folder contents to a PHP-enabled hosting account (PHP 8.1+). Keep index.html as the homepage.
2. On a computer with Composer, run `composer install --no-dev --optimize-autoloader` in this folder, then upload the generated vendor/ folder too. Alternatively run Composer in your hosting terminal.
3. Configure these environment variables in your hosting panel (recommended):
   SMTP_HOST=smtp.gmail.com
   SMTP_PORT=587
   SMTP_USERNAME=your-sending-mailbox@gmail.com
   SMTP_PASSWORD=your-Google-App-Password
   SMTP_FROM_EMAIL=your-sending-mailbox@gmail.com
   SMTP_FROM_NAME=Hero Industrial Park – 3 Enquiries
4. For Gmail, use a Google App Password (with 2-Step Verification enabled), not your normal account password. The sender address must be the authenticated mailbox or an authorized alias. Do not put SMTP credentials in public HTML or commit them to a public repository.
5. Ensure outbound SMTP on port 587 is permitted by the host. Submit a test enquiry and verify delivery and spam folder.

SECURITY / CONTENT NOTES
- The enquiry destination is configured server-side and is not displayed in the page UI.
- The form includes server-side validation and a basic honeypot. For high-volume public traffic, add a rate limit and CAPTCHA.
- The supplied gate and construction photographs are used locally; no external image hosting is required. Google Fonts is optional; fallback fonts are defined.
- Update project facts, exact address, approved uses, approvals, plot sizes, prices and legal disclosures only after verifying them with the project team. This page intentionally avoids unverified promises.
- This package is a starter deployment; SMTP delivery will not work until PHPMailer is installed and valid SMTP environment variables are configured.
