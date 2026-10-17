"""Build the deployable source archive without runtime messages or development dependencies."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import json
import hashlib
from datetime import datetime, timezone
import re
import sys

ROOT = Path(__file__).resolve().parent.parent
OUTPUT = Path(sys.argv[1]).resolve() if len(sys.argv) > 1 else ROOT / 'negah-media-source.zip'
FILES = [ROOT / name for name in (
    'index.php', 'works.php', 'project.php', 'admin.php', 'admin-projects.php',
    'core.php', 'views.php', 'router.php', 'web.config', 'README.md',
    'updater.php', 'admin-updates.php',
    'data/site.json', 'data/auth.json', 'uploads/web.config',
)]
FILES += [p for p in (ROOT / 'assets').iterdir() if p.is_file()]
FILES += [ROOT / 'tools' / name for name in ('preview.mjs', 'smoke.py', 'package.py')]


def include_referenced_media(value):
    if isinstance(value, dict):
        for item in value.values():
            include_referenced_media(item)
    elif isinstance(value, list):
        for item in value:
            include_referenced_media(item)
    elif isinstance(value, str) and re.fullmatch(r'uploads/[a-f0-9]+\.(?:jpg|png|webp|woff2?|ttf)', value):
        file = ROOT / value
        if file.is_file():
            FILES.append(file)


include_referenced_media(json.loads((ROOT / 'data/site.json').read_text()))
with ZipFile(OUTPUT, 'w', ZIP_DEFLATED) as archive:
    manifest = {'format': 'negah-media-source', 'schema': 1, 'built_at': datetime.now(timezone.utc).isoformat(), 'files': {}}
    for file in sorted(set(FILES)):
        relative = file.relative_to(ROOT).as_posix()
        manifest['files'][relative] = hashlib.sha256(file.read_bytes()).hexdigest()
        archive.write(file, Path('negah-media') / relative)
    archive.writestr('negah-media/negah-update.json', json.dumps(manifest, ensure_ascii=False, indent=2))
with ZipFile(OUTPUT) as archive:
    assert archive.testzip() is None
    assert not any('node_modules' in name or name.endswith(('messages.json', 'attempts.json')) for name in archive.namelist())
    print(f'{OUTPUT.name}: {len(archive.namelist())} files, {OUTPUT.stat().st_size:,} bytes')
