---
name: dn-restore
description: Restore a previously deleted domain using the dn CLI. Use when the user wants to recover a domain that was deleted and is still within the redemption grace period. Partner mode only.
---

# dn restore

Restore a previously deleted domain.

## Usage

```bash
dn restore example.com
```

## Notes

- **Partner mode only** - user mode shows redirect to `wordpress.com/domains/manage`
- Only works during the Redemption Grace Period (RGP) after deletion
- Use `dn info <domain>` to check the RGP status before attempting restore
