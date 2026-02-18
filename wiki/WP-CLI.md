# WP-CLI Commands

For the terminal-dwelling lobsters among us: WP Pinch includes a full set of WP-CLI commands for scripting, automation, and debugging. Pipe it, script it, cron it. Your shell, your rules.

---

## Commands

### `wp pinch status`

Show connection status, gateway health, and circuit breaker state.

```bash
wp pinch status
wp pinch status --format=json
```

### `wp pinch webhook-test`

Fire a test webhook to verify your OpenClaw connection.

```bash
wp pinch webhook-test
wp pinch webhook-test --message="Hello from WP-CLI"
```

### `wp pinch governance`

List governance tasks or run them on demand.

```bash
wp pinch governance list
wp pinch governance run content_freshness
wp pinch governance run seo_health
wp pinch governance run --all
wp pinch governance list --format=json
```

### `wp pinch audit list`

Browse audit log entries.

```bash
wp pinch audit list
wp pinch audit list --event_type=webhook_sent --source=wp-cli
wp pinch audit list --per_page=50 --page=2
wp pinch audit list --format=json
```

### `wp pinch abilities list`

List all registered abilities with category and enabled/disabled status.

```bash
wp pinch abilities list
wp pinch abilities list --format=json
wp pinch abilities list --format=csv
```

### `wp pinch features`

List feature flags or get, enable, or disable a flag.

```bash
wp pinch features list
wp pinch features list --format=json
wp pinch features get molt
wp pinch features enable molt
wp pinch features disable ghost_writer
```

### `wp pinch config`

Get or set safe WP Pinch options (gateway URL, session idle, ability cache TTL). Secrets (e.g. API token) are not exposed.

```bash
wp pinch config get
wp pinch config get wp_pinch_gateway_url
wp pinch config set wp_pinch_ability_cache_ttl 600
```

### `wp pinch molt`

Repackage a post into multiple formats (summary, social, newsletter, etc.). Requires the **molt** feature flag.

```bash
wp pinch molt 123
wp pinch molt 123 --output-types=summary,meta_description,social
wp pinch molt 123 --format=json
```

### `wp pinch ghostwrite`

List abandoned drafts or run Ghost Writer on a draft. Requires the **ghost_writer** feature flag.

```bash
wp pinch ghostwrite list
wp pinch ghostwrite list --format=json
wp pinch ghostwrite run 456
```

### `wp pinch cache flush`

Invalidate the ability cache so the next ability run fetches fresh data.

```bash
wp pinch cache flush
```

### `wp pinch approvals`

List pending approval-queue items or approve/reject by ID. Requires the **approval_workflow** feature flag.

```bash
wp pinch approvals list
wp pinch approvals list --format=json
wp pinch approvals approve aq_xxxx
wp pinch approvals reject aq_xxxx
```

---

## Output Formats

Commands that list data support the `--format` flag where noted:

| Format | Description |
|--------|-------------|
| `table` | Human-readable table (default) |
| `json`  | JSON |
| `csv`   | Comma-separated values |
| `yaml`  | YAML |

---

## Scripting Examples

### Check gateway health in a cron job

```bash
#!/bin/bash
STATUS=$(wp pinch status --format=json | jq -r '.["gateway-connected"]')
if [ "$STATUS" != "Yes" ]; then
    echo "Gateway disconnected!" | mail -s "WP Pinch Alert" admin@example.com
fi
```

### Export audit log for compliance

```bash
wp pinch audit list --format=csv > audit-$(date +%Y-%m-%d).csv
```

### Run all governance tasks and log output

```bash
wp pinch governance run --all 2>&1 | tee governance-$(date +%Y-%m-%d).log
```

### Enable Molt and run it on a post

```bash
wp pinch features enable molt
wp pinch molt 123 --output-types=summary,newsletter
```

### Flush ability cache after a bulk import

```bash
wp pinch cache flush
```
