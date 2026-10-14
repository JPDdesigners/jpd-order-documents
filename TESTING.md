# Standalone order documents verification

Commands (development source, not shipped in the plugin ZIP):

```sh
php tests/backend.php
php tests/pdf-layout.php
php tests/pdf.php 1000
php -S 127.0.0.1:8093 tests/router.php
```

The router uses isolated WordPress/WooCommerce doubles and **never sends real email**. Browser tests used `/?jpd_send_documents=300&_wpnonce=nonce-jpd_od_page_300`.

58 backend checks cover original-user authentication, order/customer permissions, nonce failure, session/user/site binding, live pricing and fallback, cancellation filter, malformed formats, no-photo format, CSV BOM and formula protection, frozen cross-file values, file size guard, locking, file cleanup, duplicate send, SMTP failure/uncertainty, orphaned send, expiry, order changes, and email disabled settings.

Actual PDF rendering uses the bundled libraries. A synthetic case with **1,000 distinct product/color groups** produced 85 pages, 638,417 bytes, in 7.2 seconds with 40 MiB peak PHP memory (local PHP 8.0.30, no WordPress loaded). All 1,000 SKUs were extracted exactly once, Greek text extracted correctly, and the grand total appeared once. First, chunk boundary and last pages were rendered with Poppler and visually inspected; Greek rendering was also inspected using PDFium. These are local fixture results, not a timing guarantee for hosting.

Browser verification: PDF selected by default, CSV optional; PDF-only and PDF+CSV creation; manual mock email acceptance; reload before/after dispatch without resending; selected-format restoration; cancellation/new selection; tablet viewport 800×1280. All PHP files passed syntax checks and the browser script passed `node --check`.

Runtime integration with actual WordPress/WooCommerce/User Switching/SMTP still needs an installation test on the user's site. No live order was changed and no real email was sent during development.

## 1.1.3 badge corner checks

The white size cell has 2px left inner radii inside the badge's 3px outer radius and 1px border. A real mock PDF was rasterized at 3,000px page height and its badge corners inspected at additional magnification: the left border is continuous and the quantity background has no white strip.

## 1.1.2 badge rendering checks

The size and quantity cells use aligned inline blocks, equal line heights, and a solid black badge background to eliminate the white inline-baseline strip. Small (2 groups) and multi-chunk (121 groups, 968 variation rows, 11 pages) PDFs were rendered and visually checked, including wrapping badges and a chunk boundary. All 121 SKUs and every size quantity remain present exactly once; the header and terminal totals remain unique. The existing 31 PDF layout/logo checks and PHP syntax checks passed.

## 1.1.1 compact PDF checks

`php tests/pdf-layout.php` has 31 checks for the compact layout, WooCommerce currency formatting, safe local SVG logo embedding, standard SVG DTD removal, escaped product fields, absent technical explanations, single header and terminal totals, rejected remote/traversal logo URLs, and rejected external SVG resources/entities.

The PDF fixture now uses eight sizes per product/color group, long Greek product names, and a synthetic SVG logo (not the production logo). Tests at 2, 121, and 1,000 groups exercise small orders, chunk boundaries, and large orders. The 1,000-group fixture has 8,000 variation rows: local PHP 8.0.30 rendered 83 pages in 16.47 seconds, with 86 MiB peak memory and an 8,003,266-byte PDF. All SKUs appear exactly once, all eight size quantities are present, technical explanations are absent, and the header and grand totals appear once. Small, chunk-boundary, category-transition and final pages were visually inspected. These measurements exclude WordPress/WooCommerce bootstrapping and real catalog queries.

The production SVG is resolved at installation from the existing uploads path/filter. Its appearance and real email delivery still need a site test. Runtime generation uses no remote resource fetching.

## 1.1.0 frontend and appearance checks

Run `node --test tests/frontend.test.cjs` and `php tests/appearance.php` from the standalone repository (use `standalone/tests/` paths from the combined workspace). Eight JS flow tests cover focus transitions, compact selection, reset, lost send response, retained recovery token, expiry and terminal receipts and malformed status responses. Five PHP checks cover independent defaults, optional Matrix token integration and CSS validation. Browser checks at 390×844 confirmed no horizontal overflow, visible focused Send, terminal progress focus and clean reset. These tests use mock orders and do not send real email.
