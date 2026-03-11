---
name: dn-dns-set
description: Set a DNS record for a domain using the dn CLI. Use when the user wants to add or update DNS records (A, AAAA, CNAME, MX, TXT, etc.). Partner mode only.
---

# dn dns:set

Set a DNS record for a domain.

## Usage

```bash
# Interactive (prompts for type, name, value)
dn dns:set example.com

# Non-interactive
dn dns:set example.com --type=A --name=@ --value=192.0.2.1

# Custom TTL
dn dns:set example.com --type=CNAME --name=www --value=example.com --ttl=7200

# Multiple values (e.g., round-robin A records)
dn dns:set example.com --type=A --name=@ --value=192.0.2.1 --value=192.0.2.2
```

## Options

| Option | Description |
|--------|-------------|
| `--type` | Record type: A, AAAA, CNAME, MX, TXT, etc. |
| `--name` | Record name (`@` for root, or subdomain) |
| `--value` | Record value(s) - repeatable for multiple values |
| `--ttl` | TTL in seconds (default: 3600) |

## Notes

- **Partner mode only** - user mode shows redirect to `wordpress.com/domains/manage`
- The command name uses a colon: `dns:set`
- Record type is auto-uppercased
- Use `dn dns:get` first to see existing records
