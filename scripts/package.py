from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import json

root = Path(__file__).resolve().parent.parent
manifest = json.loads((root / 'extension.json').read_text())
version = manifest['version']
assert json.loads((root / 'package.json').read_text())['version'] == version
assert json.loads((root / 'package-lock.json').read_text())['version'] == version
assert (root / manifest['ui']['entry']).is_file(), 'Build before packaging'
output = root / 'release' / f"{manifest['id']}-{version}.pteroext"
output.parent.mkdir(exist_ok=True)
with ZipFile(output, 'w', ZIP_DEFLATED) as archive:
    for name in ['extension.json', 'README.md', 'src', 'routes', 'database', 'resources', 'dist']:
        path = root / name
        for file in ([path] if path.is_file() else sorted(path.rglob('*'))):
            if file.is_file() and not str(file.relative_to(root)).startswith('src/client/'):
                archive.write(file, file.relative_to(root))
    assert 'extension.json' in archive.namelist()
print(output)

download = output.with_suffix('.zip')
with ZipFile(download, 'w', compression=ZIP_DEFLATED, compresslevel=9) as archive:
    archive.write(output, output.name)
    archive.writestr('README.txt', (
        'Thank you for downloading User Register!\n\n'
        'To install, upload the included .pteroext file in Admin > Extensions,\n'
        'enable User Register, and reload your browser.\n'
    ))
print(download)
