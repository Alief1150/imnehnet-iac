#!/usr/bin/env python3
"""Synchronize the IMNEHNET welcome cron target with Telegram DM routes."""

from __future__ import annotations

import argparse
import json
import os
import sqlite3
import subprocess
from pathlib import Path

DEFAULT_JOB_ID = "280c96a85890"


def hermes_home() -> Path:
    configured = os.environ.get("HERMES_HOME", "").strip()
    return Path(configured).expanduser() if configured else Path.home() / ".hermes"


def telegram_dm_ids(database: Path) -> list[str]:
    uri = f"file:{database}?mode=ro"
    with sqlite3.connect(uri, uri=True, timeout=5) as connection:
        rows = connection.execute(
            "SELECT session_key, entry_json FROM gateway_routing "
            "WHERE session_key LIKE ? ORDER BY session_key",
            ("agent:%:telegram:dm:%",),
        ).fetchall()

    chat_ids: set[str] = set()
    for session_key, raw_entry in rows:
        try:
            entry = json.loads(raw_entry)
        except (TypeError, json.JSONDecodeError):
            continue
        origin = entry.get("origin") if isinstance(entry, dict) else None
        if not isinstance(origin, dict):
            continue
        chat_id = str(origin.get("chat_id", "")).strip()
        user_id = str(origin.get("user_id", "")).strip()
        if (
            origin.get("platform") == "telegram"
            and origin.get("chat_type") == "dm"
            and chat_id.isdecimal()
            and chat_id == user_id
        ):
            chat_ids.add(chat_id)

    return sorted(chat_ids, key=int)


def sync(job_id: str, database: Path) -> int:
    chat_ids = telegram_dm_ids(database)
    if not chat_ids:
        print("No Telegram DM routes found; existing delivery targets were preserved.")
        return 0

    delivery = ",".join(f"telegram:{chat_id}" for chat_id in chat_ids)
    result = subprocess.run(
        [
            "hermes",
            "cron",
            "edit",
            job_id,
            "--deliver",
            delivery,
            "--failure-deliver",
            "local",
        ],
        capture_output=True,
        text=True,
        timeout=30,
        check=False,
    )
    if result.returncode != 0:
        message = (result.stderr or result.stdout).strip()
        raise RuntimeError(f"Could not update welcome delivery targets: {message}")

    print(f"Synchronized {len(chat_ids)} Telegram DM delivery target(s).")
    return 0


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--job-id", default=DEFAULT_JOB_ID)
    parser.add_argument("--database", type=Path, default=hermes_home() / "state.db")
    args = parser.parse_args()
    return sync(args.job_id, args.database)


if __name__ == "__main__":
    raise SystemExit(main())
