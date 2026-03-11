---
name: dn-suggest
description: Get domain name suggestions based on a search query using the dn CLI. Use when the user wants creative domain name ideas, alternative TLDs, or domain brainstorming. Supports filtering by TLD and count.
---

# dn suggest

Get domain name suggestions based on a keyword or phrase.

## Usage

```bash
# Basic suggestions (default 10 results)
dn suggest "cool startup"

# Limit results
dn suggest "photography" --count=5

# Filter by TLDs (partner mode only)
dn suggest "blog" --tlds=com,net,org

# Exact match only (partner mode only)
dn suggest "example" --exact
```

## Options

| Option | Description |
|--------|-------------|
| `--count`, `-c` | Number of suggestions (default: 10) |
| `--tlds`, `-t` | Comma-separated TLDs to filter (partner mode only) |
| `--exact` | Exact match only (partner mode only) |

## Output

**Partner mode** columns: Domain, Available, Register Fee, Renewal Fee, Premium

**User mode** columns: Domain, Cost, Premium, Relevance

## Notes

- Partner mode fees are in cents (displayed as dollars)
- User mode uses WPCOM `rest/v1.1/domains/suggestions` endpoint
- `--tlds` and `--exact` flags are only effective in partner mode
