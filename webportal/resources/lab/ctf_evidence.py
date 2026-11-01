#!/usr/bin/python3
"""Root-owned NFLOG observer. No packet injection, forwarding or shell-history trust."""
import ipaddress
import json
import os
from pathlib import Path
import selectors
import signal
from urllib.parse import urlsplit, parse_qs, unquote
import socket
import struct
import subprocess
import time

BASE = Path('/var/lib/imnehnet-ctf')
GROUP = 16384
MARK = 0x494d4e

class Observer:
    def __init__(self, config, previous=None):
        self.config = config
        self.flows = {}
        self.state = {'lab_id': config['lab_id'], 'epoch': config['epoch'],
                      'host': 0, 'ports': {}, 'http': {}, 'heartbeat': 0}
        if previous and all(previous.get(k) == config[k] for k in ('lab_id', 'epoch')):
            self.state.update(previous)

    def packet(self, packet, endian='<', now=None):
        now = time.time() if now is None else now
        if len(packet) < 4 or packet[0] != 2 or packet[1] != 0:
            return
        attrs = {}; pos = 4
        while pos + 4 <= len(packet):
            length, kind = struct.unpack_from(endian+'HH', packet, pos)
            if length < 4 or pos+length > len(packet): return
            attrs[kind & 0x3fff] = packet[pos+4:pos+length]
            pos += (length+3) & ~3
        prefix = attrs.get(10, b'').rstrip(b'\0')
        if prefix not in (b'imn-ctf-out', b'imn-ctf-in'): return
        raw = attrs.get(9, b'')
        if len(raw) < 40 or raw[0] >> 4 != 4 or raw[9] != 6: return
        iplen = (raw[0] & 15)*4
        total = struct.unpack_from('!H', raw, 2)[0]
        if iplen < 20 or len(raw) < total or total < iplen+20: return
        if struct.unpack_from('!H', raw, 6)[0] & 0x3fff: return
        src, dst = socket.inet_ntoa(raw[12:16]), socket.inet_ntoa(raw[16:20])
        tcp = raw[iplen:total]
        sport, dport, seq, ack = struct.unpack_from('!HHII', tcp)
        header = (tcp[12] >> 4)*4
        if header < 20 or header > len(tcp): return
        flags, payload = tcp[13], tcp[header:]
        outgoing = prefix == b'imn-ctf-out'
        if outgoing:
            if src != self.config['attacker_ip'] or dst != self.config['victim_ip']: return
            if 11 in attrs and struct.unpack('!I', attrs[11])[0] != self.config['uid']: return
            local, port = sport, dport
        else:
            if src != self.config['victim_ip'] or dst != self.config['attacker_ip']: return
            local, port = dport, sport
        if port not in (22, 80): return
        key = (local, port)
        if outgoing and flags & 2 and not flags & 16:
            self.flows = {k:v for k,v in self.flows.items() if now-v['seen'] < 90}
            if len(self.flows) >= 4096: self.flows.pop(next(iter(self.flows)))
            self.flows[key] = {'seen': now, 'syn': seq, 'out_seq': (seq+1)&0xffffffff,
                              'in_seq': None, 'request': b'', 'response': b'',
                              'connected': False, 'recorded': False}
            return
        flow = self.flows.get(key)
        if not flow or now-flow['seen'] > 90: return
        flow['seen'] = now
        if not outgoing and flags & 16 and ack == ((flow['syn']+1)&0xffffffff):
            if flags & 2 or flags & 4: self.state['host'] = now
            if flags & 2:
                flow['connected'] = True
                flow['in_seq'] = (seq+1)&0xffffffff
        if not flow['connected'] or not payload: return
        side = 'out_seq' if outgoing else 'in_seq'
        if flow[side] != seq: return
        flow[side] = (seq+len(payload))&0xffffffff
        field, limit = ('request',8192) if outgoing else ('response',1024)
        flow[field] = (flow[field]+payload)[:limit]
        if port == 22 and not outgoing and flow['response'].startswith(b'SSH-'):
            self.state['ports']['22'] = now
        if port != 80 or flow['recorded']: return
        request, response = flow['request'], flow['response']
        if b'\r\n' not in request or b'\r\n' not in response: return
        first = request.split(b'\r\n',1)[0].split(b' ')
        status = response.split(b'\r\n',1)[0].split(b' ')
        if len(first)!=3 or first[0] not in (b'GET',b'HEAD') or not first[2].startswith(b'HTTP/1.'): return
        if len(status)<2 or not status[0].startswith(b'HTTP/1.') or not status[1].isdigit(): return
        code = int(status[1]); flow['recorded'] = True
        self.state['ports']['80'] = now
        if not 200 <= code < 400: return
        # Persist only fixed objective keys; never request bodies, flags or arbitrary URLs.
        target = unquote(first[1].decode('ascii',errors='ignore'))
        if target.split('?',1)[0] == '/robots.txt': self.state['http']['robots'] = now
        if target.split('?',1)[0].rstrip('/') == '/n0te-4-admin': self.state['http']['directory'] = now
        parsed = urlsplit(target)
        files = parse_qs(parsed.query).get('file', [])
        if parsed.path == '/admin' and any('../' in f for f in files) and code == 200:
            self.state['http']['traversal'] = now


def write_state(observer):
    observer.state['heartbeat'] = time.time()
    temp = BASE/'state.tmp'
    temp.write_text(json.dumps(observer.state))
    temp.chmod(0o600)
    temp.replace(BASE/'state.json')


def main():
    os.umask(0o077)
    config = json.loads((BASE/'config.json').read_text())
    for key in ('attacker_ip','victim_ip'): ipaddress.IPv4Address(config[key])
    if int(config['uid']) <= 0: raise ValueError('Invalid CTF UID')
    previous = json.loads((BASE/'state.json').read_text()) if (BASE/'state.json').exists() else None
    observer = Observer(config, previous)
    subprocess.run(['nft','add','table','ip','imnehnet_ctf'],check=True)
    child = None
    def stop(signum, frame):
        raise SystemExit(0)
    signal.signal(signal.SIGTERM, stop)
    signal.signal(signal.SIGINT, stop)
    try:
        rules = f'''add chain ip imnehnet_ctf out {{ type filter hook output priority -140; policy accept; }}
add chain ip imnehnet_ctf incoming {{ type filter hook input priority -140; policy accept; }}
add rule ip imnehnet_ctf out meta skuid {int(config['uid'])} ip daddr {config['victim_ip']} tcp dport {{ 22, 80 }} log prefix "imn-ctf-out" group {GROUP} snaplen 16384 queue-threshold 1
add rule ip imnehnet_ctf incoming ip saddr {config['victim_ip']} tcp sport {{ 22, 80 }} log prefix "imn-ctf-in" group {GROUP} snaplen 16384 queue-threshold 1
'''
        subprocess.run(['nft','-f','-'],input=rules,text=True,check=True)
        child = subprocess.Popen(['stdbuf','-o0','tcpdump','-Z','root','-nn','-U','-s','16384','-i',f'nflog:{GROUP}','-w','-'],stdout=subprocess.PIPE)
        selector = selectors.DefaultSelector(); selector.register(child.stdout,selectors.EVENT_READ)
        buffer = b''; endian = None; last = 0
        while child.poll() is None:
            if time.time()-last >= 2 and endian is not None:
                write_state(observer); last = time.time()
            for key,_ in selector.select(timeout=1):
                chunk = os.read(key.fileobj.fileno(),65536)
                if not chunk: raise RuntimeError('NFLOG capture ended')
                buffer += chunk
                if endian is None and len(buffer)>=24:
                    endian = {b'\xd4\xc3\xb2\xa1':'<',b'\xa1\xb2\xc3\xd4':'>'}.get(buffer[:4])
                    if not endian or struct.unpack_from(endian+'I',buffer,20)[0] != 239:
                        raise RuntimeError('NFLOG capture format unsupported')
                    buffer=buffer[24:]
                while endian and len(buffer)>=16:
                    length=struct.unpack_from(endian+'I',buffer,8)[0]
                    if length>65536: raise RuntimeError('Capture size invalid')
                    if len(buffer)<16+length: break
                    observer.packet(buffer[16:16+length],endian)
                    buffer=buffer[16+length:]
        raise RuntimeError('tcpdump stopped')
    finally:
        if child is not None and child.poll() is None:
            child.terminate()
            try: child.wait(timeout=3)
            except subprocess.TimeoutExpired: child.kill(); child.wait()
        subprocess.run(['nft','delete','table','ip','imnehnet_ctf'],check=False)

if __name__=='__main__': main()
