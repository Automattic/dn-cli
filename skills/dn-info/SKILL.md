---
name: dn-info
description: Get detailed information about a registered domain using the dn CLI. Use when the user wants to see domain details like expiration date, nameservers, contacts, EPP status, privacy settings, or transfer lock status. Partner mode only - user mode redirects to WordPress.com.
---

# dn info

Get detailed information about a registered domain.

## Usage

```bash
dn info example.com
```

## Output

Displays a property table with: Created, Expires, Updated, Paid Until, Auth Code, Renewal Mode, Transfer Mode, RGP Status, Transfer Lock, Privacy, DNSSEC.

Also shows sections for Nameservers, EPP Status Codes, and Contacts (owner, admin, tech, billing) when available.

## Notes

- **Partner mode only** - user mode shows redirect to `wordpress.com/domains/manage`
- Useful for checking expiration dates, auth codes for transfers, and current domain status
