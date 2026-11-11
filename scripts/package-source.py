"""Package source without credentials, business data, backups, or dependencies."""
from pathlib import Path
import zipfile
root=Path(__file__).resolve().parents[1]
out=root.parent/'ae-ae-source.zip'
excluded={'node_modules','vendor','dist','test-results','playwright-report','.git','backups','__pycache__'}
with zipfile.ZipFile(out,'w',zipfile.ZIP_DEFLATED) as archive:
    for path in root.rglob('*'):
        if not path.is_file():continue
        rel=path.relative_to(root)
        if any(part in excluded for part in rel.parts):continue
        if path.name.startswith('.env') and path.name not in {'.env.example','.env.docker.example','.env.production.example'}:continue
        if '.sqlite' in path.name or path.name.endswith(('.log','.sql','.sql.gz','.pyc')):continue
        if str(rel).startswith(('backend/bootstrap/cache/','backend/storage/')) and path.name!='.gitignore':continue
        if rel.parent==Path('.') and path.suffix=='.png':continue
        archive.write(path,Path('ae-ae')/rel)
print(out)
