#!/usr/bin/env python3
"""Create an isolated, loopback-only demo. Requires local mysql -uroot access."""
from pathlib import Path
import json
import os
import re
import secrets
import shutil
import subprocess

ROOT = Path(__file__).resolve().parents[1]
LOCAL = ROOT / '.local'
LOCAL.mkdir(exist_ok=True)
(ROOT / 'reports').mkdir(exist_ok=True)
DB_HOST = os.environ.get('EVO_TEST_DB_HOST', 'localhost')
DB_ACCOUNT_HOST = os.environ.get('EVO_TEST_DB_ACCOUNT_HOST', 'localhost')
if not re.fullmatch(r'[a-zA-Z0-9_.%:-]+', DB_ACCOUNT_HOST):
    raise SystemExit('Invalid test database account host')
MYSQL = ['mysql', '-uroot', '-h', DB_HOST]
DB_NAME = 'evolution_lara13_test'
EXTRAS = {
    'DocLister': ('https://github.com/AgelxNash/DocLister.git', 'da0584fd459f401be179fe1bbe95cbb605d47f32'),
    'FormLister': ('https://github.com/Pathologic/FormLister.git', '41e4a34d25606074b5e7c75a3ddb6eecfd144957'),
}

def run(args, **kwargs):
    return subprocess.run(args, cwd=ROOT, check=True, **kwargs)

credentials_file = LOCAL / 'credentials.json'
if not credentials_file.exists():
    if (ROOT / 'core/.install').exists():
        raise SystemExit('Existing CMS installation found. Refusing to change its database.')
    existing = subprocess.check_output(MYSQL + ['-N', '-e',
        "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='" + DB_NAME + "'"], text=True).strip()
    if existing != '0':
        raise SystemExit('Database already exists. Refusing to reuse unowned test data.')
    c = dict(database=DB_NAME, user='evo_lara13', password=secrets.token_hex(16),
             admin='admin', admin_password=secrets.token_urlsafe(18))
    query = f"CREATE DATABASE `{DB_NAME}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER '{c['user']}'@'{DB_ACCOUNT_HOST}' IDENTIFIED BY '{c['password']}'; GRANT ALL ON `{DB_NAME}`.* TO '{c['user']}'@'{DB_ACCOUNT_HOST}';"
    # SQL goes over stdin; credentials are not printed.
    run(MYSQL, input=query, text=True)
    credentials_file.write_text(json.dumps(c, indent=2))
    credentials_file.chmod(0o600)
else:
    c = json.loads(credentials_file.read_text())
    if c['database'] != DB_NAME:
        raise SystemExit('Unexpected database in local credentials.')

run(['composer','install','--working-dir=core','--no-scripts','--no-interaction'])
if not (ROOT / 'core/.install').exists():
    args = ['php','cli-install.php','--typeInstall=1','--databaseType=mysql',
            '--databaseServer='+DB_HOST,'--database='+DB_NAME,'--databaseUser='+c['user'],
            '--databasePassword='+c['password'],'--tablePrefix=evo_','--cmsAdmin='+c['admin'],
            '--cmsAdminEmail=admin@example.test','--cmsPassword='+c['admin_password'],
            '--language=en','--removeInstall=n']
    result = subprocess.run(args, cwd=ROOT/'install', stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True)
    (LOCAL/'install.log').write_text(result.stdout)
    if result.returncode or not (ROOT/'core/.install').exists():
        raise SystemExit('Installation failed. See .local/install.log.')

# Copy only absent files so downloaded fixtures cannot overwrite CMS sources.
for name, (url, commit) in EXTRAS.items():
    checkout = LOCAL/name
    if not checkout.exists():
        run(['git','clone',url,str(checkout)])
    run(['git','-C',str(checkout),'checkout','--detach',commit])
    actual = subprocess.check_output(['git','-C',str(checkout),'rev-parse','HEAD'],text=True).strip()
    if actual != commit:
        raise SystemExit('Extra revision mismatch: '+name)
    for source in (checkout/'assets').rglob('*'):
        target = ROOT/'assets'/source.relative_to(checkout/'assets')
        if source.is_file() and not target.exists():
            target.parent.mkdir(parents=True,exist_ok=True)
            shutil.copy2(source,target)

run(['php','tools/seed-local-demo.php'])
run(['php','core/artisan','package:discover'])
print('Demo ready. Start: php -S 127.0.0.1:8133 tools/dev-router.php')
print('Admin credentials: .local/credentials.json (local only).')
