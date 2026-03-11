---
name: dn-renew
description: Renew a domain registration using the dn CLI. Use when the user wants to extend a domain's registration period. Partner mode only - user mode redirects to WordPress.com.
---

# dn renew

Renew a domain registration.

## Usage

```bash
# Interactive (prompts for expiration year)
dn renew example.com

# Non-interactive
dn renew example.com --expiration-year=2025 --period=1

# Premium domain renewal
dn renew premium.com --expiration-year=2025 --fee=15.99
```

## Options

| Option | Description |
|--------|-------------|
| `--period`, `-p` | Renewal period in years (default: 1) |
| `--expiration-year` | Current expiration year (required, prompted if missing) |
| `--fee` | Fee amount for premium domains |

## Notes

- **Partner mode only** - user mode shows redirect to `wordpress.com/domains/manage`
- The `--expiration-year` is the domain's current expiration year, not the desired new one
- Use `dn info <domain>` first to check the current expiration date
