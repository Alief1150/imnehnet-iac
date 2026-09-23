import pathlib
import unittest


ROOT = pathlib.Path(__file__).resolve().parents[1]
ROLE = ROOT / "ansible" / "roles" / "imnehnet_audit"


class AuditDeploymentContract(unittest.TestCase):
    def test_required_deployment_files_exist(self):
        required = [
            ROOT / "ansible" / "playbooks" / "imnehnet-audit.yml",
            ROOT / "ansible" / "playbooks" / "imnehnet-audit-rollback.yml",
            ROLE / "defaults" / "main.yml",
            ROLE / "tasks" / "main.yml",
            ROLE / "tasks" / "rollback.yml",
            ROLE / "handlers" / "main.yml",
            ROLE / "templates" / "imnehnet-session-audit.j2",
            ROLE / "templates" / "imnehnet-audit-report.py.j2",
            ROLE / "templates" / "imnehnet-audit.rules.j2",
            ROLE / "templates" / "imnehnet-audit-sshd.conf.j2",
            ROOT / "scripts" / "imnehnet-audit-report-all",
        ]
        missing = [str(path.relative_to(ROOT)) for path in required if not path.is_file()]
        self.assertEqual(missing, [])

    def test_role_validates_access_configuration_and_never_restarts_ssh(self):
        tasks = (ROLE / "tasks" / "main.yml").read_text(encoding="utf-8")
        handlers = (ROLE / "handlers" / "main.yml").read_text(encoding="utf-8")
        self.assertIn("sshd -t", tasks)
        self.assertIn("visudo -cf", tasks)
        self.assertIn("state: reloaded", handlers)
        self.assertIn("listen: Validate and reload ssh", handlers)
        self.assertNotIn("state: restarted", handlers)

    def test_defaults_do_not_require_inventory_ansible_user(self):
        defaults = (ROLE / "defaults" / "main.yml").read_text(encoding="utf-8")
        self.assertIn("ansible_env.SUDO_USER", defaults)
        self.assertNotIn('"{{ ansible_user }}"', defaults)
        self.assertNotIn('/home/{{ ansible_user }}', defaults)

    def test_session_hook_derives_fingerprint_from_expose_auth_info(self):
        hook = (ROLE / "templates" / "imnehnet-session-audit.j2").read_text(encoding="utf-8")
        tasks = (ROLE / "tasks" / "main.yml").read_text(encoding="utf-8")
        key_task = tasks.split("- name: Install SSH-key fingerprint actor mapping", 1)[1].split("- name:", 1)[0]
        config_dir_task = tasks.split("- name: Create audit configuration directory", 1)[1].split("- name:", 1)[0]
        self.assertIn("ssh-keygen -lf -", hook)
        self.assertIn('group: "{{ imnehnet_audit_linux_user }}"', key_task)
        self.assertIn('mode: "0640"', key_task)
        self.assertIn('group: "{{ imnehnet_audit_linux_user }}"', config_dir_task)
        self.assertIn('mode: "0750"', config_dir_task)

    def test_check_mode_does_not_require_uninstalled_audit_service(self):
        tasks = (ROLE / "tasks" / "main.yml").read_text(encoding="utf-8")
        service_start = tasks.split("- name: Enable and start auditd", 1)[1]
        self.assertIn("when: not ansible_check_mode", service_start.split("- name:", 1)[0])

    def test_role_has_backup_and_rollback_paths(self):
        tasks = (ROLE / "tasks" / "main.yml").read_text(encoding="utf-8")
        rollback = (ROLE / "tasks" / "rollback.yml").read_text(encoding="utf-8")
        self.assertIn("backup: true", tasks)
        self.assertIn("imnehnet_audit_backup_dir", tasks)
        self.assertIn("Remove IMNEHNET audit SSH drop-in", rollback)
        self.assertIn("Remove IMNEHNET audit rules", rollback)

    def test_report_and_aggregator_support_machine_readable_merge(self):
        report = (ROLE / "templates" / "imnehnet-audit-report.py.j2").read_text(encoding="utf-8")
        aggregator = (ROOT / "scripts" / "imnehnet-audit-report-all").read_text(encoding="utf-8")
        self.assertIn('"--json"', report)
        self.assertIn("json.loads", aggregator)
        self.assertIn("events.sort", aggregator)
        self.assertIn("UNKNOWN / LOCAL", aggregator)

    def test_reports_expose_centralized_identity_fields(self):
        host_report = (ROOT / "scripts" / "imnehnet-audit-report").read_text(encoding="utf-8")
        vm_report = (ROLE / "templates" / "imnehnet-audit-report.py.j2").read_text(encoding="utf-8")
        aggregator = (ROOT / "scripts" / "imnehnet-audit-report-all").read_text(encoding="utf-8")
        for field in ("node", "source_ip", "fingerprint", "tailnet_user", "linux_user"):
            self.assertIn(field, host_report)
            self.assertIn(field, vm_report)
        self.assertIn('"--json"', host_report)
        self.assertIn("SOURCE IP", aggregator)
        self.assertIn("source_ip", aggregator)
        self.assertIn("ZoneInfo", aggregator)
        self.assertIn("Asia/Jakarta", aggregator)
        self.assertIn("NOACTIVITY", aggregator)
        self.assertIn('"--all-returned"', aggregator)

    def test_rules_and_report_share_the_same_keys(self):
        rules = (ROLE / "templates" / "imnehnet-audit.rules.j2").read_text(encoding="utf-8")
        report = (ROLE / "templates" / "imnehnet-audit-report.py.j2").read_text(encoding="utf-8")
        for key in [
            "imnehnet_project",
            "imnehnet_ssh",
            "imnehnet_auditconfig",
            "imnehnet_systemd",
            "imnehnet_admincmd",
        ]:
            self.assertIn(key, rules)
            self.assertIn(key, report)

    def test_repository_contains_no_real_identity_mapping(self):
        combined = "\n".join(
            path.read_text(encoding="utf-8", errors="ignore")
            for path in [
                ROLE / "defaults" / "main.yml",
                ROOT / "ansible" / "playbooks" / "imnehnet-audit.yml",
            ]
        )
        self.assertNotIn("SHA256:", combined)
        self.assertNotIn("BEGIN OPENSSH PRIVATE KEY", combined)
        self.assertIn("imnehnet_audit_key_actors: {}", combined)


if __name__ == "__main__":
    unittest.main()
