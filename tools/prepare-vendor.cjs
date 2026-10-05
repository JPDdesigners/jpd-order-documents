// Scope only the five bundled libraries; use our own loader instead of Composer's global classes.
const fs = require('fs'), path = require('path');
const base = path.resolve(__dirname, '../lib/vendor');
if (fs.readFileSync(path.join(base, 'dompdf/dompdf/src/Dompdf.php'), 'utf8').includes('JPD_Order_Documents_Vendor')) throw new Error('Already scoped: restore the official package before running this transform.');
function walk(dir) { for (const e of fs.readdirSync(dir, {withFileTypes:true})) {
  const p = path.join(dir, e.name);
  if (e.isDirectory()) { if (e.name !== 'composer') walk(p); }
  else if (e.name.endsWith('.php')) {
    const s = fs.readFileSync(p, 'utf8').replace(/\b(Dompdf|FontLib|Svg|Masterminds|Sabberworm)(\\{1,2})/g,
      (_, root, slashes) => 'JPD_Order_Documents_Vendor' + slashes + root + slashes)
      .replace(/\b(namespace|use) (Dompdf|FontLib|Svg|Masterminds|Sabberworm)(?=[; ])/g, '$1 JPD_Order_Documents_Vendor\\$2');
    fs.writeFileSync(p, s);
  }
}}
walk(base);
const fontFile = path.join(base, 'dompdf/php-font-lib/src/FontLib/TrueType/File.php');
fs.writeFileSync(fontFile, fs.readFileSync(fontFile, 'utf8').replace('return $class_parts[1];', '// JPD namespace isolation adds one segment before the original FontLib namespace.\n    return $class_parts[2];'));
for (const file of fs.readdirSync(path.join(base, 'composer'))) if (file.endsWith('.php')) fs.unlinkSync(path.join(base, 'composer', file));
fs.unlinkSync(path.join(base, 'autoload.php'));
fs.unlinkSync(path.join(base, '../autoload.inc.php'));
