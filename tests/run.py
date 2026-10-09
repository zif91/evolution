#!/usr/bin/env python3
"""Run integration tests and require success markers (Evo may exit 0 on errors)."""
from pathlib import Path
import json
import subprocess
import sys

root=Path(__file__).resolve().parents[1]
(root/'reports').mkdir(exist_ok=True)
results=[]
for name, command, marker in [
    ('classes',['php','tests/load-classes.php'],'CLASS_LOAD_OK'),
    ('compatibility',['php','tests/compatibility.php'],'COMPATIBILITY_COMPLETE 31 checks, 0 failed'),
    ('console',['php','tests/console.php'],'CONSOLE_INTEGRATION_OK'),
    ('cache',['php','core/artisan','cache:clear-full'],'Cache clear'),
    ('packages',['php','core/artisan','package:discover'],None),
]:
    result=subprocess.run(command,cwd=root,capture_output=True,text=True)
    output=result.stdout+result.stderr
    (root/'reports'/f'{name}.log').write_text(output)
    ok=result.returncode==0 and (marker is None or marker in output)
    if marker is None:
        ok=ok and not any(x in output for x in ['Fatal error','Source: Parser','Uncaught','Exception:'])
    results.append(dict(test=name,status='PASS' if ok else 'FAIL',exitCode=result.returncode))
    print(results[-1]['status'],name)
    if not ok:print(output)
(root/'reports/test-run.json').write_text(json.dumps(results,indent=2))
sys.exit(0 if all(x['status']=='PASS' for x in results) else 1)
