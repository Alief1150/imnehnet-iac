# Deploying IMNEHNET

Everything here needs root. Nothing in this directory is executed by the
application: a web application that can rewrite the web server's configuration
is a web application that can hand itself root. These files exist so a human can
review exactly what will change, and then run it deliberately.

## Current state, as measured

Apache is installed and running, and it is **not serving the portal**:

| URL | Result |
| --- | --- |
| `http://127.0.0.1:80/` | 200, "Apache2 Ubuntu Default Page: It works" |
| `http://127.0.0.1:80/up` | 404 |
| `http://127.0.0.1:80/admin/login` | 404 |
| `http://127.0.0.1:8000/up` | 200 |
| `http://127.0.0.1:8000/admin/login` | 200 |

The portal is served by `php artisan serve` on port 8000. That is PHP's
built-in **development** server: single threaded, no WebSocket support, and it
does not return after a reboot. It was a reasonable placeholder; it should not
be the production arrangement.

Apache is also missing every module the terminal would need. `proxy`,
`proxy_http`, `proxy_wstunnel`, `headers`, `rewrite` and `ssl` are all disabled,
and `AllowOverride None` means an `.htaccess` cannot substitute for them.

## Installing

```bash
cd /home/iaas/projects/webportal-iaas
sudo bash deploy/install-apache-vhost.sh
```

Then check:

```bash
curl -I http://127.0.0.1/up
```

Port 8000 can keep running until you stop it; there is no clash.

## What the script changes, and why each change is needed

1. **`a2enmod proxy proxy_http proxy_wstunnel headers`** — `proxy_wstunnel` is
   what forwards the WebSocket upgrade. Without it the terminal page loads and
   then hangs, which reads as a bug in the portal rather than a missing module.

2. **`chmod o+x /home/iaas /home/iaas/projects`** — `/home/iaas` is mode 0750,
   so `www-data` cannot traverse into it. Without this the vhost fails with a
   403 and nothing in the error log that explains it. Only the traverse bit is
   granted; the home directory's contents stay private.

3. **`chgrp -R www-data storage bootstrap` and mode 2775** — Laravel has to
   write compiled views, sessions and logs through php-fpm, which runs as
   `www-data`, while the files belong to `iaas`. The setgid bit keeps new files
   in the same group so this does not silently regress.

4. **`install -d -o www-data -g iaas -m 0770 /var/lib/imnehnet/terminal`** —
   the per-slot password files. The portal writes them as `iaas`, Apache reads
   them as `www-data`.

5. **`a2dissite 000-default`** — stops Apache answering on port 80 with the
   stock Ubuntu page.

## Terminal slots

A `ttyd` process serves exactly one shell, so every terminal session needs its
own listener. Apache resolves `ProxyPass` targets statically and cannot look a
port up in the portal's database, so the ports are declared in advance: the
vhost lists slots `38000`–`38007`, and the portal lends them out one at a time.

**Raising the limit means changing two places.** The vhost, and
`infrastructure.terminal.port_range_min` / `_max` in `config/infrastructure.php`.
They have to agree, and a slot in the vhost with no ttyd behind it is a 502.

Each slot is protected by HTTP basic auth whose password file the portal writes
when a session starts and deletes when it ends, so a slot nobody has leased has
no valid user. This is in addition to `ttyd`'s own credential, not instead of
it: two independent gates rather than one.

## TLS is deliberately not enabled here

Everything currently serves plain HTTP, including the portal itself. For a
static page that is a weakness. For a **shell** it is worse: every keystroke,
and the basic-auth password, crosses the LAN in the clear, and a captured
password plus a captured session would let anyone who can sniff the wire take
over a container.

So the terminal should not be put in front of students before TLS exists. The
vhost installs plain HTTP because that is where the portal is today, and adding
HTTPS is a separate piece of work with its own decision about certificate
issuance.

## Related configuration

`.env` currently has `APP_ENV=local` and `APP_DEBUG=true`. Both are right for
development and wrong for a portal that is meant to be used. Turning debug off
before this is reachable by anything but the lab network is worth doing, and
`APP_URL` will need to change when the port and hostname settle.