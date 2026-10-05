const fs = require('fs'), path = require('path');
const base = path.resolve(__dirname, '../lib');
if (process.argv.length !== 4) throw new Error('Usage: node tools/prepare-merge.cjs <official-FPDI-source-directory> <official-FPDF-source-directory>');
const fpdiSource = path.resolve(process.argv[2]), fpdfSource = path.resolve(process.argv[3]);
for (const source of [fpdiSource, fpdfSource]) if (source === base || source.startsWith(base + path.sep)) throw new Error('Input must be a fresh upstream source outside lib/.');
if (fs.readFileSync(path.join(fpdiSource, 'src/Fpdi.php'),'utf8').includes('JPD_Order_Documents_Vendor')) throw new Error('FPDI input is already scoped.');
fs.cpSync(path.join(fpdiSource, 'src'), path.join(base, 'fpdi/src'), {recursive:true});
fs.copyFileSync(path.join(fpdiSource, 'LICENSE.txt'), path.join(base, 'fpdi/LICENSE.txt'));
fs.cpSync(path.join(fpdfSource, 'font'), path.join(base, 'fpdf/font'), {recursive:true});
fs.copyFileSync(path.join(fpdfSource, 'license.txt'), path.join(base, 'fpdf/license.txt'));
let fpdf = fs.readFileSync(path.join(fpdfSource, 'fpdf.php'), 'utf8');
fpdf = fpdf.replace('<?php', '<?php\nnamespace JPD_Order_Documents_Vendor;\nuse \\Exception;');
fpdf = fpdf.replace(/FPDF_FONTPATH/g, 'JPD_OD_FPDF_FONTPATH');
fs.writeFileSync(path.join(base, 'fpdf/fpdf.php'), fpdf);
function walk(dir) {for(const e of fs.readdirSync(dir,{withFileTypes:true})){
 const file=path.join(dir,e.name); if(e.isDirectory()) walk(file);
 else if(e.name.endsWith('.php')) fs.writeFileSync(file, fs.readFileSync(file,'utf8')
 .replace(/\bsetasign(\\{1,2})Fpdi/g, (_,slashes)=>'JPD_Order_Documents_Vendor'+slashes+'setasign'+slashes+'Fpdi')
 .replace(/extends \\FPDF/g,'extends \\JPD_Order_Documents_Vendor\\FPDF')
 .replace(/\\FPDF::/g,'\\JPD_Order_Documents_Vendor\\FPDF::'));
}}
walk(path.join(base,'fpdi/src'));
