import pathlib
import unittest

ROOT = pathlib.Path(__file__).resolve().parents[1]
REPAIR = ROOT / "ansible/playbooks/repair-tailscale-apt-keyring.yml"
ROLLBACK = ROOT / "ansible/playbooks/rollback-tailscale-apt-keyring.yml"


class TailscaleAptKeyringRepairContract(unittest.TestCase):
    def test_repair_and_rollback_exist(self):
        self.assertTrue(REPAIR.is_file())
        self.assertTrue(ROLLBACK.is_file())

    def test_repair_pins_official_fingerprint_and_preserves_source_list(self):
        text = REPAIR.read_text(encoding="utf-8")
        self.assertIn("2596A99EAAB33821893C0A79458CA832957F5868", text)
        self.assertIn("tailscale-archive-keyring.gpg.pre-repair", text)
        self.assertIn("backup: true", text)
        self.assertIn("update_cache: true", text)
        self.assertNotIn("trusted=yes", text)
        self.assertNotIn("allow_unauthenticated", text)
        self.assertNotIn("apt_repository", text)
        self.assertNotIn("state: restarted", text)

    def test_failed_validation_restores_backup(self):
        text = REPAIR.read_text(encoding="utf-8")
        self.assertIn("rescue:", text)
        self.assertIn("remote_src: true", text)
        self.assertIn("Restore original keyring after failed validation", text)
        backup_task = text.split("- name: Preserve original keyring exactly once", 1)[1].split("- name:", 1)[0]
        self.assertIn("not ansible_check_mode", backup_task)

    def test_rollback_requires_and_restores_fixed_backup(self):
        text = ROLLBACK.read_text(encoding="utf-8")
        self.assertIn("tailscale-archive-keyring.gpg.pre-repair", text)
        self.assertIn("remote_src: true", text)
        self.assertIn("assert:", text)


if __name__ == "__main__":
    unittest.main()
