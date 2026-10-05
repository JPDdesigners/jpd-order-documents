# Packaging and upstream library preparation

Normal builds use the complete, already scoped library source committed under `lib/`. Run `python tools/build_release.py` from a clone; no network, npm or Composer is needed. `--output <zip>` selects another destination and `--force` replaces an existing archive.

The namespace transformation scripts are maintenance tools, not normal build steps. They must only run on fresh official unscoped source. Keep the bundled upstream license files and inspect/test any dependency update.

## Dompdf 3.1.6

Official distribution: https://github.com/dompdf/dompdf/releases/tag/v3.1.6 . Its release ZIP includes all transitive dependencies; the exact versions/source references are recorded in `lib/vendor/composer/installed.json`.

In a separate development copy, extract the official release package, copy its `vendor/` into `lib/vendor/`, and its `autoload.inc.php` into `lib/autoload.inc.php`. Then run:

```sh
node tools/prepare-vendor.cjs
```

It prefixes Dompdf, FontLib, Svg, Masterminds and Sabberworm, adjusts FontLib's namespace depth and removes the unused Composer loader. It refuses an already scoped tree. The runtime uses `includes/vendor-loader.php` instead of the upstream autoloader. Node.js 22.12+ is recommended for these optional maintenance transforms.

## FPDI 2.6.8 and FPDF 1.9.0

Official sources:

- https://github.com/Setasign/FPDI/releases/tag/v2.6.8
- https://github.com/Setasign/FPDF/releases/tag/1.9.0

Extract each archive into a separate directory outside `lib/`, and pass its actual root containing `src/` or `fpdf.php`:

```sh
node tools/prepare-merge.cjs /path/to/FPDI /path/to/FPDF
```

The script copies source, fonts and licenses, prefixes the FPDI/FPDF references, and renames FPDF's optional font path constant. Original library copyright and license notices are retained. See `lib/JPD-MODIFICATIONS.md` for the changes. After maintenance, run the PHP backend/PDF tests and inspect generated PDFs before packaging.
