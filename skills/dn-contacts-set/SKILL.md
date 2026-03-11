---
name: dn-contacts-set
description: Update domain contact information (WHOIS contacts) using the dn CLI. Use when the user wants to change owner, admin, tech, or billing contacts for a domain. Partner mode only.
---

# dn contacts:set

Update contact information for a domain.

## Usage

```bash
# Interactive (prompts for all fields)
dn contacts:set example.com

# Specify contact type
dn contacts:set example.com --type=admin

# Non-interactive
dn contacts:set example.com --type=owner \
  --first-name=John --last-name=Doe \
  --email=john@example.com --phone=+1.5551234567 \
  --address="123 Main St" --city=Springfield --state=IL \
  --postal-code=62701 --country=US

# Opt out of automatic transfer lock after contact change
dn contacts:set example.com --transferlock-opt-out
```

## Options

| Option | Description |
|--------|-------------|
| `--type` | Contact type: `owner`, `admin`, `tech`, `billing` (default: owner) |
| `--transferlock-opt-out` | Skip automatic transfer lock after contact change |
| `--first-name`, `--last-name`, etc. | Contact details (prompted if missing) |

## Notes

- **Partner mode only** - user mode shows redirect to `wordpress.com/domains/manage`
- ICANN requires a 60-day transfer lock after owner contact changes (use `--transferlock-opt-out` to skip)
- The command name uses a colon: `contacts:set`
