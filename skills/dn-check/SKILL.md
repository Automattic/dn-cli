---
name: dn-check
description: Check domain name availability and pricing using the dn CLI. Use when the user wants to know if a domain is available, see domain pricing, or verify multiple domains at once. Supports both partner mode (Domain Services API) and user mode (WordPress.com API).
---

# dn check

Check availability and pricing for one or more domain names.

## Usage

```bash
# Check a single domain
dn check example.com

# Check multiple domains at once
dn check example.com example.net example.org
```

## Output

**Partner mode** table columns: Domain, Available, Fee Class, Price, Zone Active, TLD Maintenance

**User mode** table columns: Domain, Available, Status, Cost, Privacy

## Behavior by Mode

- **Partner mode**: Queries the Domain Services API. Returns pricing in dollars, fee class, zone/TLD status.
- **User mode**: Queries WPCOM `rest/v1.3/domains/{domain}/is-available`. Availability is based on `status === "available"` (string, not boolean).

## Notes

- Accepts multiple domain arguments (space-separated)
- Requires prior `dn configure` setup
