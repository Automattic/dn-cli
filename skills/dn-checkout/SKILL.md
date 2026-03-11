---
name: dn-checkout
description: Open WordPress.com checkout in the browser using the dn CLI. Use when the user wants to complete a domain purchase after adding domains to cart. User mode only.
---

# dn checkout

Open the WordPress.com checkout page in your default browser.

## Usage

```bash
# Domain-only checkout (default)
dn checkout

# Checkout for a specific site
dn checkout --site=mysite.wordpress.com
```

## Options

| Option | Description |
|--------|-------------|
| `--site`, `-s` | Site slug to checkout for |

## Notes

- **User mode only** - returns error in partner mode
- Without `--site`, opens domain-only siteless checkout at `wordpress.com/checkout/no-site`
- With `--site`, opens site-specific checkout
- Typical flow: `dn register example.com` -> `dn cart` -> `dn checkout`
