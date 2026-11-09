"""Run server tests and prepare a dedicated database for browser QA."""
import os,pathlib,subprocess
root=pathlib.Path(__file__).resolve().parents[1]
env=os.environ.copy()
for line in (root/'backend/.env').read_text().splitlines():
    if line.startswith('DB_') and '=' in line:
        key,value=line.split('=',1)
        env[key]=value.strip('"\'')
env.update(DB_CONNECTION='mysql',DB_DATABASE='aeae_testing',DB_URL='')
php=os.environ.get('AEAE_PHP','php')
subprocess.run([php,'artisan','test','--compact'],cwd=root/'backend',env=env,check=True)
subprocess.run([php,'artisan','migrate:fresh','--seed','--force'],cwd=root/'backend',env=env,check=True)
subprocess.run([php,'tests/Support/audit-fixtures.php'],cwd=root/'backend',env=env,check=True)
print('Dedicated aeae_testing database is ready for browser QA.')
