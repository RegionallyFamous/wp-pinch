# WP-CLI Commands

For the terminal-dwelling lobsters among us: WP Pinch includes a full set of WP-CLI commands for scripting, automation, and debugging. Pipe it, script it, cron it. Your shell, your rules.

---

## Commands

### `wp pinch status`

Show connection status, registered abilities, and gateway health.

```bash
wp pinch status
wp pinch status --format=json
```

### `wp pinch abilities list`

List all registered abilities with their categories and enabled status.

```bash
wp pinch abilities list
wp pinch abilities list --format=json
wp pinch abilities list --format=csv
```

### `wp pinch webhook-test`

Fire a test webhook to verify your OpenClaw connection. Poke the lobster and see if it pinches back.

```bash
wp pinch webhook-test
```

### `wp pinch governance run`

Trigger governance tasks manually (useful for testing or one-off runs).

```bash
wp pinch governance run                    # Run all tasks
wp pinch governance run --task=seo_health  # Run a specific task
```

### `wp pinch audit list`

Browse audit log entries.

```bash
wp pinch audit list
wp pinch audit list --format=json
wp pinch audit list --format=csv
wp pinch audit list --format=yaml
```

---

## Output Formats

All `list` commands support the `--format` flag:

| Format | Description |
|---|---|
| `table` | Human-readable table (default) |
| `json` | JSON array |
| `csv` | Comma-separated values |
| `yaml` | YAML format |

---

## Scripting Examples

### Check gateway health in a cron job

```bash
#!/bin/bash
STATUS=$(wp pinch status --format=json | jq -r '.gateway.connected')
if [ "$STATUS" != "true" ]; then
    echo "Gateway disconnected!" | mail -s "WP Pinch Alert" admin@example.com
fi
```

### Export audit log for compliance

```bash
wp pinch audit list --format=csv > audit-$(date +%Y-%m-%d).csv
```

### Run governance and pipe findings to a file

```bash
wp pinch governance run 2>&1 | tee governance-$(date +%Y-%m-%d).log
```
