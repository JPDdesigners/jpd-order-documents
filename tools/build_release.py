"""Build an uploadable plugin ZIP with Python 3.9+; no dependency installation needed."""
import argparse
import hashlib
from pathlib import Path
import re
from zipfile import ZipFile, ZipInfo, ZIP_DEFLATED

ROOT = Path(__file__).resolve().parents[1]


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', type=Path, help='ZIP output; defaults to releases/')
    parser.add_argument('--force', action='store_true', help='Replace an existing ZIP')
    args = parser.parse_args()
    header = (ROOT / 'jpd-order-documents.php').read_text(encoding='utf-8')
    version = re.search(r'^ \* Version: (\d+\.\d+\.\d+)$', header, re.M).group(1)
    output = args.output or ROOT / 'releases' / f'jpd-order-documents-{version}.zip'
    if output.exists() and not args.force:
        parser.error(f'{output} already exists; use --force to replace it')
    sources = [ROOT / name for name in ['jpd-order-documents.php', 'uninstall.php', 'README.md', 'LICENSE']]
    for folder in ['assets', 'includes', 'lib']:
        sources.extend(sorted((ROOT / folder).rglob('*')))
    entries = {}
    for source in sources:
        if source.is_symlink():
            raise ValueError(f'Symlink is not a release input: {source}')
        if source.is_dir():
            continue
        if not source.is_file():
            raise ValueError(f'Missing release input: {source}')
        entries['jpd-order-documents/' + source.relative_to(ROOT).as_posix()] = source.read_bytes()
    output.parent.mkdir(parents=True, exist_ok=True)
    with ZipFile(output, 'w', compression=ZIP_DEFLATED, compresslevel=9) as archive:
        for name, data in entries.items():
            entry = ZipInfo(name, date_time=(1980, 1, 1, 0, 0, 0))
            entry.compress_type = ZIP_DEFLATED
            entry.external_attr = 0o100644 << 16
            archive.writestr(entry, data)
    with ZipFile(output) as archive:
        assert set(archive.namelist()) == set(entries)
        assert archive.testzip() is None
        for name, data in entries.items():
            assert archive.read(name) == data, name
    print(f'{output}: {len(entries)} verified runtime files')
    print(f'SHA256 {hashlib.sha256(output.read_bytes()).hexdigest()}')


if __name__ == '__main__':
    main()
