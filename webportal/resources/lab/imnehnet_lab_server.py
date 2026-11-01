"""The IMNEHNET lab web server: a deliberately vulnerable target, on purpose.

This replaces python3 -m http.server on the victim, which cannot express the
course. Three objectives need behaviour a static file server does not have:

  objective 3  /robots.txt exists and volunteers the hidden directory
  objective 4  /n0te-4-admin/ exists behind it
  objective 5  /admin?file= reads a file without confining it to the web root
  objective 6  the flag is only reachable through objective 5

The traversal is real. It is confined to files the service user can read, so a
student cannot walk to /etc/shadow, and it logs every request to stdout, which is
what the objective validator reads. Both of those are deliberate: the flaw has to be
exploitable by the intended solution and no further, and the evidence has to exist
somewhere the validator can reach.

The flag is generated per lab so that no two sessions share one, and it is written
here only. It is never sent to the browser by the portal and never stored in the
portal database; the validator reads it from this container at submission time.
"""

import html
import os
import secrets
import sys
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import parse_qs, urlparse

WEBROOT = "/opt/ctf"
FLAG_PATH = os.path.join(WEBROOT, "n0te-4-admin", "flag.html")
HIDDEN_DIR = "n0te-4-admin"

FLAG_FORMAT = "IMNEHNET{{{lab}-{token}}}"

# The directory the flag lives in, as served. Kept as a constant because objectives
# four and five are defined in terms of it, and a seeder that had to agree with a
# hard-coded path in two places would eventually disagree with itself.
PAGES = {
    "/": """<!doctype html>
<html><head><title>Internal Web Portal</title></head>
<body>
<h1>Northwind Internal Services</h1>
<p>Authorised staff only.</p>
<ul>
  <li><a href="/status">Service status</a></li>
  <li><a href="/admin">Reports</a></li>
</ul>
<footer>maintained by infrastructure</footer>
</body></html>
""",
    "/robots.txt": "User-agent: *\nDisallow: /n0te-4-admin/\n",
    "/status": """<!doctype html>
<html><head><title>Status</title></head>
<body><h1>Service status</h1>
<p>All systems nominal.</p></body></html>
""",
    "/admin": """<!doctype html>
<html><head><title>Reports</title></head>
<body>
<h1>Report viewer</h1>
<p>Select a monthly report to download.</p>
<form method="get" action="/admin">
  <input type="text" name="file" placeholder="report name" value="">
  <button type="submit">Open</button>
</form>
</body></html>
""",
}


def ensure_flag(lab):
    """Create the flag file if it is not already there.

    Reset must return the lab to its initial state without invalidating a flag a
    student has already submitted, so the file is only written when missing. The
    flag a session was validated against stays the flag it was validated against.
    """
    if os.path.exists(FLAG_PATH):
        return

    token = secrets.token_hex(4)
    flag = FLAG_FORMAT.format(lab=lab, token=token)

    os.makedirs(os.path.dirname(FLAG_PATH), exist_ok=True)

    with open(FLAG_PATH, "w", encoding="utf-8") as handle:
        handle.write(
            "<html><head><title>Private note</title></head><body>"
            "<h1>Private note</h1>"
            "<p>Nice work. Keep this to yourself.</p>"
            f"<p><b>{flag}</b></p>"
            "</body></html>\n"
        )


def note_page():
    """The note inside the hidden directory.

    It confirms the student found the directory and points at the reports viewer,
    which is where the traversal in objective five lives. It does not show the flag:
    reading the flag is objective six, and the point of the course is that reaching
    it takes the traversal rather than a lucky guess at a URL.
    """
    return """<!doctype html>
<html><head><title>Private note</title></head>
<body>
<h1>Internal note</h1>
<p>If you are reading this, the robots file gave too much away.</p>
<p>The flag is not kept here. It is in the reports viewer, which will read any
path you give it.</p>
</body></html>
"""


def directory_page(name):
    return f"""<!doctype html>
<html><head><title>{html.escape(name)}</title></head>
<body><h1>{html.escape(name)}/</h1>
<ul><li><a href="flag.html">flag.html</a></li></ul>
</body></html>
"""


def resolve_report(requested):
    """Turn a report name into a path, without confining it.

    This is the vulnerability. The name is joined to the reports directory and
    nothing checks that the result is still inside it, so a value beginning with
    ../ walks out and ../../etc/passwd resolves. That is the whole flaw, and it is
    the flaw the course teaches, so it is not patched here and must not be.

    What bounds it is not this function but the account the service runs as. The
    unit is unprivileged, so a traversal reaches every file that account can read
    and stops at the ones it cannot, including /etc/shadow. That is the honest
    description of the exposure, and it is the reason a student who solves this
    gets the flag and not the host.
    """
    base = os.path.join(WEBROOT, "reports")
    resolved = os.path.normpath(os.path.join(base, requested))

    if not os.path.isfile(resolved) or not os.access(resolved, os.R_OK):
        return None

    return resolved


class Handler(BaseHTTPRequestHandler):
    server_version = "nginx/1.18.0"
    sys_version = ""

    def log_message(self, fmt, *args):
        """Every request to stdout, which is what journald keeps.

        Deliberately not the parent's version: the parent suppresses output unless a
        flag is passed, and the objective validator reads this stream. Silence here
        would mean no evidence at all, which is the one failure mode this service
        cannot have.
        """
        sys.stdout.write(
            "%s - - [%s] %s\n"
            % (self.address_string(), self.log_date_time_string(), fmt % args)
        )
        sys.stdout.flush()

    def _send(self, status, body, content_type="text/html; charset=utf-8"):
        payload = body.encode("utf-8")

        self.send_response(status)
        self.send_header("Content-Type", content_type)
        self.send_header("Content-Length", str(len(payload)))
        self.end_headers()
        self.wfile.write(payload)

    def do_GET(self):
        parsed = urlparse(self.path)
        route = parsed.path

        if route == "/robots.txt":
            return self._send(200, PAGES["/robots.txt"], "text/plain; charset=utf-8")

        if route == f"/{HIDDEN_DIR}":
            return self._send(200, directory_page(HIDDEN_DIR))

        if route == f"/{HIDDEN_DIR}/":
            return self._send(200, directory_page(HIDDEN_DIR))

        if route == f"/{HIDDEN_DIR}/note.html":
            return self._send(200, note_page())

        if route == f"/{HIDDEN_DIR}/flag.html":
            with open(FLAG_PATH, encoding="utf-8") as handle:
                return self._send(200, handle.read())

        if route == "/admin":
            query = parse_qs(parsed.query)
            requested = (query.get("file") or [""])[0]

            if not requested:
                return self._send(200, PAGES["/admin"])

            resolved = resolve_report(requested)

            if resolved is None:
                return self._send(
                    404, "<html><body><h1>404</h1><p>No such report.</p></body></html>"
                )

            with open(resolved, encoding="utf-8", errors="replace") as handle:
                return self._send(200, handle.read())

        if route in PAGES:
            return self._send(200, PAGES[route])

        return self._send(404, "<html><body><h1>404</h1><p>Not found.</p></body></html>")

    def do_HEAD(self):
        self.do_GET()


def main():
    lab = os.environ.get("IMNEHNET_LAB", "lab")
    port = int(os.environ.get("IMNEHNET_PORT", "80"))

    ensure_flag(lab)

    ThreadingHTTPServer(("0.0.0.0", port), Handler).serve_forever()


if __name__ == "__main__":
    main()