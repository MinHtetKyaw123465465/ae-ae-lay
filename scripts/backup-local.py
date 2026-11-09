"""Back up this Docker development install; keep backups outside the web root."""
import argparse, datetime, gzip, os, pathlib, shutil, subprocess, tarfile, tempfile
parser=argparse.ArgumentParser()
parser.add_argument('directory',type=pathlib.Path)
args=parser.parse_args()
root=pathlib.Path(__file__).resolve().parents[1]
out=args.directory.resolve()
public=(root/'backend/public').resolve()
if out==public or public in out.parents:
    raise SystemExit('Backups must be outside the public web directory.')
os.umask(0o077)
out.mkdir(parents=True,exist_ok=True)
stamp=datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%d-%H%M%S')
db=out/f'aeae-{stamp}.sql.gz'
uploads=out/f'aeae-uploads-{stamp}.tar.gz'
with tempfile.TemporaryFile() as errors:
    process=subprocess.Popen(['docker','--host=unix:///var/run/docker.sock','exec','aeae-mysql','sh','-c','MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysqldump -uroot --single-transaction --quick --no-tablespaces --set-gtid-purged=OFF --routines --triggers "$MYSQL_DATABASE"'],stdout=subprocess.PIPE,stderr=errors)
    with gzip.open(db,'wb') as target:
        shutil.copyfileobj(process.stdout,target)
    if process.wait()!=0:
        db.unlink(missing_ok=True)
        raise SystemExit('Database backup failed; no complete backup was created.')
with tarfile.open(uploads,'w:gz') as archive:
    private=root/'backend/storage/app/private'
    if private.exists():archive.add(private,arcname='private')
os.chmod(db,0o600);os.chmod(uploads,0o600)
print(db)
print(uploads)
