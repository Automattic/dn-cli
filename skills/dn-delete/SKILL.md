---
name: dn-delete
description: Delete a domain registration using the dn CLI. Use when the user wants to remove or cancel a domain. Shows a confirmation prompt before proceeding. Partner mode only - user mode redirects to WordPress.com.
---

# dn delete

Delete a domain registration.

## Usage

```bash
dn delete example.com
```

## Notes

- **Partner mode only** - user mode shows redirect to `wordpress.com/domains/manage`
- Shows a caution message and confirmation prompt (default: No)
- Deleted domains may be restorable within the RGP (Redemption Grace Period) using `dn restore`
- This is a destructive operation - use with care
