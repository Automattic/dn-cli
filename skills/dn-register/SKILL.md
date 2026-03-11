---
name: dn-register
description: Register a new domain name using the dn CLI. Use when the user wants to purchase or register a domain. In partner mode, performs direct registration with contact info. In user mode, adds the domain to a WordPress.com shopping cart for browser checkout.
---

# dn register

Register a new domain name.

## Usage

```bash
# Partner mode: register with contact details (interactive prompts for missing fields)
dn register example.com
dn register example.com --period=2 --privacy=on

# Partner mode: full non-interactive registration
dn register example.com \
  --first-name=John --last-name=Doe \
  --email=john@example.com --phone=+1.5551234567 \
  --address="123 Main St" --city=Springfield --state=IL \
  --postal-code=62701 --country=US

# Premium domain (partner mode)
dn register premium.com --price=50000

# User mode: adds domain to cart (no direct registration)
dn register example.com

# User mode with site association
dn register example.com --site=mysite.wordpress.com
```

## Options

| Option | Description |
|--------|-------------|
| `--period`, `-p` | Registration period in years (default: 1) |
| `--privacy` | `on` (default), `off`, or `redact` (partner mode) |
| `--price` | Price in cents for premium domains (partner mode) |
| `--site`, `-s` | WordPress.com site slug (user mode) |
| `--first-name`, `--last-name`, etc. | Contact details (partner mode, prompted if missing) |

## Behavior by Mode

- **Partner mode**: Registers directly via API. Prompts for contact info if not provided via flags. Asks for confirmation before proceeding.
- **User mode**: Checks availability first, then adds to WPCOM shopping cart. Outputs a checkout URL. Use `dn checkout` to complete purchase in browser.

## Notes

- In user mode, defaults to domain-only siteless checkout (`no-site`)
- Product slug mapping: `.com` = `domain_reg`, others = `dot{tld}_domain`
