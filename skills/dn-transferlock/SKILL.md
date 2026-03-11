---
name: dn-transferlock
description: Set or remove the transfer lock on a domain using the dn CLI. Use when the user wants to lock or unlock a domain to prevent or allow transfers to another registrar. Partner mode only.
---

# dn transferlock

Set or remove the transfer lock on a domain.

## Usage

```bash
# Lock domain (prevent transfers)
dn transferlock example.com on

# Unlock domain (allow transfers)
dn transferlock example.com off
```

## Arguments

| Argument | Description |
|----------|-------------|
| `domain` | Domain name |
| `state` | `on` (lock) or `off` (unlock) |

## Notes

- **Partner mode only** - user mode shows redirect to `wordpress.com/domains/manage`
- Transfer lock should be disabled before initiating an outbound transfer
- Use `dn info <domain>` to check current lock status
