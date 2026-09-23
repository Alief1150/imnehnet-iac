import importlib.machinery
import importlib.util
import pathlib
import sys
import time
import unittest

ROOT = pathlib.Path(__file__).resolve().parents[1]
SCRIPT = ROOT / "scripts" / "imnehnet-audit-report-all"


def load_aggregator():
    loader = importlib.machinery.SourceFileLoader("imnehnet_audit_aggregator", str(SCRIPT))
    spec = importlib.util.spec_from_loader(loader.name, loader)
    if spec is None:
        raise RuntimeError("unable to create aggregator module spec")
    module = importlib.util.module_from_spec(spec)
    loader.exec_module(module)
    return module


class AuditAggregatorTimeoutTest(unittest.TestCase):
    def test_subprocess_timeout_is_bounded_and_reported(self):
        aggregator = load_aggregator()
        started = time.monotonic()
        with self.assertRaisesRegex(RuntimeError, "timed out"):
            aggregator.run(
                [sys.executable, "-c", "import time; time.sleep(2)"],
                timeout=0.1,
            )
        self.assertLess(time.monotonic() - started, 1.0)

    def test_node_collection_runs_in_parallel(self):
        aggregator = load_aggregator()
        original = getattr(aggregator, "node_events")

        def fake_node_events(node, timeout):
            time.sleep(0.2)
            return [{"node": node["name"], "action": "TEST", "time": "2026-09-24T00:00:00+07:00"}]

        setattr(aggregator, "node_events", fake_node_events)
        try:
            nodes = [{"name": "one"}, {"name": "two"}, {"name": "three"}]
            started = time.monotonic()
            events = aggregator.collect_node_events(nodes, timeout=1.0, workers=3)
            elapsed = time.monotonic() - started
        finally:
            setattr(aggregator, "node_events", original)

        self.assertEqual({event["node"] for event in events}, {"one", "two", "three"})
        self.assertLess(elapsed, 0.45)


if __name__ == "__main__":
    unittest.main()
