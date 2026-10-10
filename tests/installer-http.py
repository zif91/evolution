#!/usr/bin/env python3
"""Exercise the real installer against disposable MySQL databases.

Usage: python3 tests/installer-http.py /private/path/config.json
Config: base_url, host, username, password, existing_database, empty_database,
new_database, prefix. new_database must not exist before the test. Databases must
use the evo_lab_ prefix. Never use production.
This checks validation endpoints; successful installations are tested in a browser.
"""
import json
import sys
from urllib.error import HTTPError
from urllib.parse import urlencode, urlparse
from urllib.request import Request, urlopen

config = json.load(open(sys.argv[1]))
assert urlparse(config['base_url']).hostname in ('127.0.0.1', 'localhost')
for key in ('existing_database', 'empty_database', 'new_database'):
    assert config[key].startswith('evo_lab_')

base = dict(host=config['host'], uid=config['username'], pwd=config['password'],
            method='mysql', database_name=config['existing_database'],
            tableprefix=config['prefix'], database_collation='utf8mb4_general_ci',
            database_connection_method='SET NAMES', language='ru', installMode=2)
passed = 0

def request(action, data, expected_status=200):
    req = Request(config['base_url'].rstrip('/') + '/install/index.php?' + action,
                  data=urlencode(data).encode())
    try:
        response = urlopen(req, timeout=15)
    except HTTPError as error:
        response = error
    body = response.read().decode()
    assert response.status == expected_status, f'Unexpected HTTP {response.status}'
    assert 'Fatal error' not in body and 'Warning:' not in body, 'PHP diagnostic in response'
    return body

for label, overrides, marker in [
    ('new rejects occupied prefix', {'installMode': 0}, 'database_fail'),
    ('normal upgrade accepts existing tables', {'installMode': 1}, 'database_pass'),
    ('advanced upgrade accepts existing tables', {'installMode': 2}, 'database_pass'),
    ('normal upgrade rejects missing prefix', {'installMode': 1, 'tableprefix': 'missing_'}, 'database_fail'),
    ('advanced upgrade rejects missing prefix', {'tableprefix': 'missing_'}, 'database_fail'),
    ('new accepts empty database', {'installMode': 0, 'database_name': config['empty_database']}, 'database_pass'),
    ('upgrade rejects empty database', {'database_name': config['empty_database']}, 'database_fail'),
    ('wrong password fails cleanly', {'pwd': 'deliberately-incorrect'}, 'database_fail'),
    ('upgrade rejects missing database', {'database_name': config['new_database']}, 'database_fail'),
    ('new can create database', {'installMode': 0, 'database_name': config['new_database']}, 'database_pass'),
    ('created database has no upgrade tables', {'database_name': config['new_database']}, 'database_fail'),
    ('SET NAMES permits another collation', {'database_collation': 'utf8mb4_unicode_ci'}, 'database_pass'),
    ('SET CHARACTER SET rejects another collation', {'database_collation': 'utf8mb4_unicode_ci', 'database_connection_method': 'SET CHARACTER SET'}, 'database_fail'),
]:
    body = request('s=1&action=connection/databasetest', base | overrides)
    assert f'id="{marker}"' in body, label
    if label == 'new can create database':
        assert 'база данных создана' in body, 'Use an absent new_database for this test'
    passed += 1
    print('PASS', label)

for label, fields in [
    ('new rejects empty administrator', dict(cmsadmin='', cmspassword='test', cmspasswordconfirm='test')),
    ('new rejects empty password', dict(cmsadmin='test', cmspassword='', cmspasswordconfirm='')),
    ('new rejects password mismatch', dict(cmsadmin='test', cmspassword='test', cmspasswordconfirm='other')),
]:
    request('action=install', dict(installmode=0, language='ru') | fields, 400)
    passed += 1
    print('PASS', label)
print(f'INSTALLER_HTTP_COMPLETE {passed} checks')
