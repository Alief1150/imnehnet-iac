#!/usr/bin/python3
"""Install the observer inside a verified owned attacker; receives trusted portal JSON."""
import base64
import ipaddress
import json
import os
from pathlib import Path
import pwd
import grp
import shutil
import stat
import subprocess
import sys
import time
import uuid

os.umask(0o077)
cfg=json.load(sys.stdin)
assert os.geteuid()==0
uuid.UUID(cfg['epoch'])
for key in ('attacker_ip','victim_ip'): ipaddress.IPv4Address(cfg[key])
assert isinstance(cfg['lab_id'],int) and cfg['lab_id']>0
source=base64.b64decode(cfg.pop('collector'),validate=True)
compile(source,'ctf_evidence.py','exec')
def run(args, **kw):
    return subprocess.run(args,check=True,**kw)
if any(not shutil.which(x) for x in ('nft','tcpdump','setpriv')):
    env=dict(os.environ,DEBIAN_FRONTEND='noninteractive')
    run(['apt-get','-o','Acquire::Retries=2','-o','Acquire::http::Timeout=20','update'],env=env,timeout=180)
    run(['apt-get','-o','DPkg::Lock::Timeout=60','-y','install','--no-install-recommends','nftables','tcpdump','util-linux'],env=env,timeout=240)
for name in ('student','ctfstudent'):
    try: account=pwd.getpwnam(name)
    except KeyError:
        run(['useradd','-m','-U','-s','/bin/bash',name]); account=pwd.getpwnam(name)
    assert account.pw_uid>0 and account.pw_gid>0 and account.pw_dir=='/home/'+name
    groups={grp.getgrgid(g).gr_name for g in os.getgrouplist(name,account.pw_gid)}
    assert not groups.intersection({'root','sudo','wheel','docker','lxd','incus','incus-admin','adm','systemd-journal'}), 'Privileged account groups'
    home=Path(account.pw_dir)
    assert home.is_dir() and not home.is_symlink() and home.stat().st_uid==account.pw_uid
    home.chmod(0o700)
    run(['usermod','-L',name])
    run(['runuser','-u',name,'--','setpriv','--no-new-privs','sh','-ec','nmap --version >/dev/null; curl --version >/dev/null; gobuster version >/dev/null'])
a=pwd.getpwnam('ctfstudent'); cfg['uid']=a.pw_uid
assert a.pw_uid != pwd.getpwnam('student').pw_uid
work=Path('/home/ctfstudent/ctf')
if not work.exists(): work.mkdir(mode=0o700); os.chown(work,a.pw_uid,a.pw_gid)
assert work.is_dir() and not work.is_symlink() and work.stat().st_uid==a.pw_uid
base=Path('/var/lib/imnehnet-ctf')
if not base.exists(): base.mkdir(mode=0o700)
assert not base.is_symlink() and base.stat().st_uid==0
base.chmod(0o700)
for p in (base/'config.json',base/'state.json',base/'state.tmp'):
    assert not p.is_symlink()
previous=json.loads((base/'config.json').read_text()) if (base/'config.json').exists() else None
program=Path('/usr/local/lib/imnehnet-ctf-evidence.py')
assert not program.is_symlink()
changed=previous != cfg or not program.exists() or program.read_bytes()!=source
unit=Path('/etc/systemd/system/imnehnet-ctf-evidence.service')
assert not unit.is_symlink()
unit_text='''[Unit]
Description=IMNEHNET CTF UID evidence observer
After=network.target
[Service]
Type=simple
User=root
UMask=0077
ExecStartPre=-/usr/sbin/nft delete table ip imnehnet_ctf
ExecStart=/usr/bin/python3 /usr/local/lib/imnehnet-ctf-evidence.py
ExecStopPost=-/usr/sbin/nft delete table ip imnehnet_ctf
Restart=on-failure
RestartSec=3
NoNewPrivileges=yes
ProtectHome=yes
ProtectSystem=strict
ReadWritePaths=/var/lib/imnehnet-ctf
StandardOutput=null
StandardError=journal
[Install]
WantedBy=multi-user.target
'''
changed=changed or not unit.exists() or unit.read_text()!=unit_text
if changed:
    subprocess.run(['systemctl','stop','imnehnet-ctf-evidence'],check=False,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    if previous and (previous.get('epoch'),previous.get('lab_id')) != (cfg['epoch'],cfg['lab_id']):
        subprocess.run(['pkill','-KILL','-u',str(a.pw_uid)],check=False)
        (base/'state.json').unlink(missing_ok=True)
    if (base/'state.json').exists():
        state=json.loads((base/'state.json').read_text()); state['heartbeat']=0
        (base/'state.json').write_text(json.dumps(state))
    (base/'config.json').write_text(json.dumps(cfg)); (base/'config.json').chmod(0o600)
    program.write_bytes(source); program.chmod(0o700)
    unit.write_text(unit_text); unit.chmod(0o644)
    run(['systemctl','daemon-reload'])
run(['systemctl','enable','--now','imnehnet-ctf-evidence'])
for _ in range(30):
    try:
        state=json.loads((base/'state.json').read_text())
        if state['epoch']==cfg['epoch'] and state['lab_id']==cfg['lab_id'] and 0<=time.time()-state['heartbeat']<5:
            run(['systemctl','is-active','--quiet','imnehnet-ctf-evidence'])
            print('PASS: observer ready; no challenge traffic generated.'); break
    except (FileNotFoundError,KeyError,json.JSONDecodeError): pass
    time.sleep(0.5)
else: raise RuntimeError('Observer not ready; inspect journalctl -u imnehnet-ctf-evidence. No grading enabled.')
