---
name: dn-transfer
description: Transfer a domain into the registrar using the dn CLI. Use when the user wants to transfer a domain from another registrar. Requires an EPP authorization code and contact details. Partner mode only.
---

# dn transfer

Transfer a domain in from another registrar.

## Usage

```bash
# Interactive (prompts for auth code and contact info)
dn transfer example.com

# Non-interactive
dn transfer example.com \
  --auth-code=ABC123 \
  --first-name=John --last-name=Doe \
  --email=john@example.com --phone=+1.5551234567 \
  --address="123 Main St" --city=Springfield --state=IL \
  --postal-code=62701 --country=US
```

## Options

| Option | Description |
|--------|-------------|
| `--auth-code` | EPP authorization code (prompted with hidden input if missing) |
| `--first-name`, `--last-name`, etc. | Contact details (prompted if missing) |

## Notes

- **Partner mode only** - user mode shows redirect to `wordpress.com/domains/manage`
- EPP auth code is entered via hidden input for security
- Contact fields mirror those in `dn register`
- Transfer must be approved at the losing registrar
