# Bundled dependency isolation

Source: official `dompdf-3.1.6.zip` release package at https://github.com/dompdf/dompdf/releases/tag/v3.1.6 . Original license notices are preserved.

Changes: PHP namespaces and namespace references for Dompdf, FontLib, Svg, Masterminds and Sabberworm are prefixed with `JPD_Order_Documents_Vendor`. FontLib TrueType File's `getFontType()` index is adjusted for that additional namespace segment. The plugin uses its own loader and does not load bundled Composer classes. No rendering algorithms have been changed.

The reproducible namespace transform is kept in the development source at `tools/prepare-vendor.cjs`. Run it once against a fresh official package, never against an already scoped tree.

FPDI 2.6.8 (`Setasign/FPDI` release v2.6.8) and FPDF 1.9.0 (`Setasign/FPDF` release 1.9.0) are also included with their original MIT/permissive licenses. FPDI namespace references and its FPDF base class point to the scoped classes; FPDF is placed in our vendor namespace and its optional global font path constant is renamed. The transform is in `tools/prepare-merge.cjs`.
