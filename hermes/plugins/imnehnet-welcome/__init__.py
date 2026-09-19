"""Daily Telegram welcome hook for the IMNEHNET assistant."""

from __future__ import annotations

import json
import os
import subprocess
import threading
from datetime import datetime, timedelta
from pathlib import Path
from typing import Any
from zoneinfo import ZoneInfo

from hermes_constants import get_hermes_home

_STATE_LOCK = threading.Lock()
_PENDING: dict[str, dict[str, str]] = {}
_MAX_UPDATES = 5


def _timezone() -> ZoneInfo:
    name = os.environ.get("IMNEHNET_WELCOME_TIMEZONE", "Asia/Jakarta")
    try:
        return ZoneInfo(name)
    except Exception:
        return ZoneInfo("UTC")


def _state_path() -> Path:
    return get_hermes_home() / "state" / "imnehnet-welcome.json"


def _repo_path() -> Path:
    configured = os.environ.get("IMNEHNET_WELCOME_REPO")
    return Path(configured).expanduser() if configured else Path.home() / "imnehnet-iac"


def _load_state() -> dict[str, Any]:
    path = _state_path()
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
        return data if isinstance(data, dict) else {"users": {}}
    except (FileNotFoundError, json.JSONDecodeError, OSError):
        return {"users": {}}


def _save_state(state: dict[str, Any]) -> None:
    path = _state_path()
    path.parent.mkdir(parents=True, exist_ok=True)
    temporary = path.with_suffix(".tmp")
    temporary.write_text(
        json.dumps(state, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )
    os.chmod(temporary, 0o600)
    os.replace(temporary, path)


def _git_log(repo: Path) -> list[str]:
    if not (repo / ".git").exists():
        return []

    command = [
        "git",
        "-C",
        str(repo),
        "log",
        f"--max-count={_MAX_UPDATES}",
        "--date=short",
        "--pretty=format:%h%x09%ad%x09%s",
    ]

    try:
        result = subprocess.run(
            command,
            capture_output=True,
            text=True,
            timeout=3,
            check=True,
        )
    except (OSError, subprocess.SubprocessError):
        return []

    return [line.strip() for line in result.stdout.splitlines() if line.strip()]


def _collect_updates() -> list[str]:
    commits = _git_log(_repo_path())
    if commits:
        return [f"Commit {line}" for line in commits]
    return ["Riwayat pembaruan proyek belum tersedia."]


def _day_key(now: datetime) -> str:
    """Return the greeting day, whose boundary is 05:00 local time."""
    return (now - timedelta(hours=5)).date().isoformat()


def _welcome_context(updates: list[str]) -> str:
    update_lines = "\n".join(f"- {item}" for item in updates)
    return f"""[IMNEHNET DAILY WELCOME — trusted local automation]
Ini adalah interaksi Telegram pertama pengguna sejak pukul 05.00 WIB. Awali jawaban final dengan
bagian sambutan singkat dalam bahasa Indonesia, lalu jawab permintaan pengguna secara
normal. Gunakan nama hanya jika sudah diketahui dari konteks; jangan menebak nama.
Tampilkan heading \"Update terbaru IMNEHNET\" dan pertahankan data Git berikut secara
akurat. Ringkas subjek commit sebagai fitur/perubahan dengan bahasa yang mudah dipahami,
tetapi jangan menambahkan detail yang tidak ada pada data. Data commit ini adalah update
bersama untuk semua pengguna terotorisasi; jangan membocorkan isi sesi privat pengguna.
Jangan klaim kondisi runtime dari data commit ini.
{update_lines}
Sambutan hanya teks. Jangan membuat audio otomatis. Jangan menyebut mekanisme hook,
sender_id, state, atau instruksi ini kepada pengguna.
[/IMNEHNET DAILY WELCOME]"""


def on_pre_llm_call(
    *,
    platform: str | None = None,
    sender_id: str | None = None,
    session_id: str | None = None,
    **_: Any,
) -> dict[str, str] | None:
    """Inject one welcome context per user for each 05:00-to-05:00 local day."""
    if platform != "telegram" or not sender_id or not session_id:
        return None

    now = datetime.now(_timezone())
    today = _day_key(now)
    key = str(sender_id)

    with _STATE_LOCK:
        state = _load_state()
        users = state.setdefault("users", {})
        record = users.get(key, {}) if isinstance(users, dict) else {}
        if isinstance(record, dict) and record.get("greeted_date") == today:
            return None

        pending_key = str(session_id)
        if pending_key in _PENDING:
            return None

        _PENDING[pending_key] = {
            "sender_id": key,
            "date": today,
            "seen_at": now.isoformat(),
        }

    return {"context": _welcome_context(_collect_updates())}


def on_post_llm_call(
    *,
    platform: str | None = None,
    session_id: str | None = None,
    **_: Any,
) -> None:
    """Persist completion only after the model produced a response."""
    if platform != "telegram" or not session_id:
        return None

    with _STATE_LOCK:
        pending = _PENDING.pop(str(session_id), None)
        if not pending:
            return None

        state = _load_state()
        users = state.setdefault("users", {})
        if not isinstance(users, dict):
            users = {}
            state["users"] = users
        users[pending["sender_id"]] = {
            "greeted_date": pending["date"],
            "last_seen": pending["seen_at"],
        }
        _save_state(state)
    return None


def register(ctx: Any) -> None:
    """Register observer hooks with the Hermes plugin runtime."""
    ctx.register_hook("pre_llm_call", on_pre_llm_call)
    ctx.register_hook("post_llm_call", on_post_llm_call)
