# Standalone order documents verification

Commands (development source, not shipped in the plugin ZIP):

```sh
php tests/backend.php
php tests/pdf.php 1000
php -S 127.0.0.1:8093 tests/router.php
```

The router uses isolated WordPress/WooCommerce doubles and **never sends real email**. Browser tests used `/?jpd_send_documents=300&_wpnonce=nonce-jpd_od_page_300`.

58 backend checks cover original-user authentication, order/customer permissions, nonce failure, session/user/site binding, live pricing and fallback, cancellation filter, malformed formats, no-photo format, CSV BOM and formula protection, frozen cross-file values, file size guard, locking, file cleanup, duplicate send, SMTP failure/uncertainty, orphaned send, expiry, order changes, and email disabled settings.

Actual PDF rendering uses the bundled libraries. A synthetic case with **1,000 distinct product/color groups** produced 85 pages, 638,417 bytes, in 7.2 seconds with 40 MiB peak PHP memory (local PHP 8.0.30, no WordPress loaded). All 1,000 SKUs were extracted exactly once, Greek text extracted correctly, and the grand total appeared once. First, chunk boundary and last pages were rendered with Poppler and visually inspected; Greek rendering was also inspected using PDFium. These are local fixture results, not a timing guarantee for hosting.

Browser verification: PDF selected by default, CSV optional; PDF-only and PDF+CSV creation; manual mock email acceptance; reload before/after dispatch without resending; selected-format restoration; cancellation/new selection; tablet viewport 800×1280. All PHP files passed syntax checks and the browser script passed `node --check`.

Runtime integration with actual WordPress/WooCommerce/User Switching/SMTP still needs an installation test on the user's site. No live order was changed and no real email was sent during development.

## 1.1.0 frontend and appearance checks

Run `node --test tests/frontend.test.cjs` and `php tests/appearance.php` from the standalone repository (use `standalone/tests/` paths from the combined workspace). Eight JS flow tests cover focus transitions, compact selection, reset, lost send response, retained recovery token, expiry and terminal receipts and malformed status responses. Five PHP checks cover independent defaults, optional Matrix token integration and CSS validation. Browser checks at 390×844 confirmed no horizontal overflow, visible focused Send, terminal progress focus and clean reset. These tests use mock orders and do not send real email.
