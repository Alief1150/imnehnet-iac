from __future__ import annotations

import importlib.util
import os
import subprocess
import sys
import tempfile
import unittest
from datetime import datetime
from pathlib import Path
from zoneinfo import ZoneInfo

PLUGIN_DIR = Path(__file__).resolve().parents[1]
AGENT_ROOT = Path.home() / ".hermes" / "hermes-agent"
sys.path.insert(0, str(AGENT_ROOT))
spec = importlib.util.spec_from_file_location("imnehnet_welcome", PLUGIN_DIR / "__init__.py")
assert spec and spec.loader
welcome = importlib.util.module_from_spec(spec)
spec.loader.exec_module(welcome)


def _init_repo(path: Path) -> None:
    subprocess.run(["git", "init", "-q", str(path)], check=True)
    subprocess.run(["git", "-C", str(path), "config", "user.name", "Test"], check=True)
    subprocess.run(["git", "-C", str(path), "config", "user.email", "test@example.invalid"], check=True)
    (path / "status.txt").write_text("ready\n", encoding="utf-8")
    subprocess.run(["git", "-C", str(path), "add", "status.txt"], check=True)
    subprocess.run(["git", "-C", str(path), "commit", "-qm", "docs: publish verified status"], check=True)


class WelcomeHookTest(unittest.TestCase):
    def setUp(self) -> None:
        self.temporary = tempfile.TemporaryDirectory()
        self.root = Path(self.temporary.name)
        self.home = self.root / "hermes"
        self.repo = self.root / "repo"
        self.home.mkdir()
        self.repo.mkdir()
        _init_repo(self.repo)
        os.environ["HERMES_HOME"] = str(self.home)
        os.environ["IMNEHNET_WELCOME_REPO"] = str(self.repo)
        os.environ["IMNEHNET_WELCOME_TIMEZONE"] = "Asia/Jakarta"
        welcome._PENDING.clear()

    def tearDown(self) -> None:
        welcome._PENDING.clear()
        os.environ.pop("HERMES_HOME", None)
        os.environ.pop("IMNEHNET_WELCOME_REPO", None)
        os.environ.pop("IMNEHNET_WELCOME_TIMEZONE", None)
        self.temporary.cleanup()

    def test_first_telegram_turn_gets_welcome_and_git_update(self) -> None:
        result = welcome.on_pre_llm_call(
            platform="telegram", sender_id="101", session_id="telegram:101"
        )
        self.assertIsNotNone(result)
        assert result is not None
        self.assertIn("IMNEHNET DAILY WELCOME", result["context"])
        self.assertIn("docs: publish verified status", result["context"])

    def test_always_includes_latest_commits_even_after_previous_visit(self) -> None:
        state = self.home / "state" / "imnehnet-welcome.json"
        state.parent.mkdir(parents=True)
        state.write_text(
            '{"users":{"101":{"greeted_date":"2000-01-01",'
            '"last_seen":"2999-01-01T00:00:00+07:00"}}}',
            encoding="utf-8",
        )
        result = welcome.on_pre_llm_call(
            platform="telegram", sender_id="101", session_id="telegram:101"
        )
        self.assertIsNotNone(result)
        assert result is not None
        self.assertIn("docs: publish verified status", result["context"])

    def test_daily_boundary_is_five_am_jakarta(self) -> None:
        tz = ZoneInfo("Asia/Jakarta")
        before = datetime(2026, 9, 18, 4, 59, tzinfo=tz)
        boundary = datetime(2026, 9, 18, 5, 0, tzinfo=tz)
        self.assertEqual(welcome._day_key(before), "2026-09-17")
        self.assertEqual(welcome._day_key(boundary), "2026-09-18")

    def test_successful_response_suppresses_repeat_for_same_day(self) -> None:
        kwargs = {"platform": "telegram", "sender_id": "101", "session_id": "telegram:101"}
        self.assertIsNotNone(welcome.on_pre_llm_call(**kwargs))
        welcome.on_post_llm_call(platform="telegram", session_id="telegram:101")
        self.assertIsNone(welcome.on_pre_llm_call(**kwargs))
        state = self.home / "state" / "imnehnet-welcome.json"
        self.assertTrue(state.exists())
        self.assertEqual(state.stat().st_mode & 0o777, 0o600)

    def test_other_users_receive_their_own_welcome(self) -> None:
        first = welcome.on_pre_llm_call(
            platform="telegram", sender_id="101", session_id="telegram:101"
        )
        second = welcome.on_pre_llm_call(
            platform="telegram", sender_id="202", session_id="telegram:202"
        )
        self.assertIsNotNone(first)
        self.assertIsNotNone(second)

    def test_non_telegram_and_missing_identity_are_ignored(self) -> None:
        self.assertIsNone(
            welcome.on_pre_llm_call(platform="local", sender_id="101", session_id="local")
        )
        self.assertIsNone(
            welcome.on_pre_llm_call(platform="telegram", session_id="telegram:unknown")
        )

    def test_corrupt_state_fails_open(self) -> None:
        state = self.home / "state" / "imnehnet-welcome.json"
        state.parent.mkdir(parents=True)
        state.write_text("not-json", encoding="utf-8")
        result = welcome.on_pre_llm_call(
            platform="telegram", sender_id="101", session_id="telegram:101"
        )
        self.assertIsNotNone(result)


if __name__ == "__main__":
    unittest.main()
