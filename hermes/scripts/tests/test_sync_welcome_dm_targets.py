from __future__ import annotations

import importlib.util
import json
import sqlite3
import tempfile
import unittest
from collections.abc import Mapping
from pathlib import Path
from unittest.mock import patch

SCRIPT = Path(__file__).resolve().parents[1] / "sync-welcome-dm-targets.py"
spec = importlib.util.spec_from_file_location("sync_welcome_dm_targets", SCRIPT)
assert spec and spec.loader
syncer = importlib.util.module_from_spec(spec)
spec.loader.exec_module(syncer)


class SyncWelcomeTargetsTest(unittest.TestCase):
    def setUp(self) -> None:
        self.temporary = tempfile.TemporaryDirectory()
        self.database = Path(self.temporary.name) / "state.db"
        with sqlite3.connect(self.database) as connection:
            connection.execute(
                "CREATE TABLE gateway_routing "
                "(scope TEXT, session_key TEXT, entry_json TEXT, updated_at REAL)"
            )

    def tearDown(self) -> None:
        self.temporary.cleanup()

    def add_route(self, session_key: str, origin: Mapping[str, object]) -> None:
        with sqlite3.connect(self.database) as connection:
            connection.execute(
                "INSERT INTO gateway_routing VALUES (?, ?, ?, ?)",
                ("default", session_key, json.dumps({"origin": origin}), 0),
            )

    def test_collects_only_unique_telegram_dm_routes(self) -> None:
        dm = {
            "platform": "telegram",
            "chat_type": "dm",
            "chat_id": "200",
            "user_id": "200",
        }
        self.add_route("agent:main:telegram:dm:200", dm)
        self.add_route("agent:other:telegram:dm:200", dm)
        self.add_route(
            "agent:main:telegram:group:-100",
            {
                "platform": "telegram",
                "chat_type": "group",
                "chat_id": "-100",
                "user_id": "200",
            },
        )
        self.assertEqual(syncer.telegram_dm_ids(self.database), ["200"])

    def test_sync_updates_one_job_with_all_dm_targets(self) -> None:
        for chat_id in ("300", "100"):
            self.add_route(
                f"agent:main:telegram:dm:{chat_id}",
                {
                    "platform": "telegram",
                    "chat_type": "dm",
                    "chat_id": chat_id,
                    "user_id": chat_id,
                },
            )
        completed = type("Result", (), {"returncode": 0, "stdout": "", "stderr": ""})()
        with patch.object(syncer.subprocess, "run", return_value=completed) as run:
            self.assertEqual(syncer.sync("job123", self.database), 0)
        command = run.call_args.args[0]
        self.assertEqual(
            command,
            [
                "hermes",
                "cron",
                "edit",
                "job123",
                "--deliver",
                "telegram:100,telegram:300",
                "--failure-deliver",
                "local",
            ],
        )

    def test_empty_route_set_preserves_existing_targets(self) -> None:
        with patch.object(syncer.subprocess, "run") as run:
            self.assertEqual(syncer.sync("job123", self.database), 0)
        run.assert_not_called()


if __name__ == "__main__":
    unittest.main()
