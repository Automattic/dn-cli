---
name: dn-dns-get
description: Retrieve DNS records for a domain using the dn CLI. Use when the user wants to view current DNS records (A, AAAA, CNAME, MX, TXT, etc.) for a domain. Partner mode only.
---

# dn dns:get

Get DNS records for a domain.

## Usage

```bash
dn dns:get example.com
```

## Output

Table columns: Type, Name, Value, TTL

Shows "No DNS records found." if empty.

## Notes

- **Partner mode only** - user mode shows redirect to `wordpress.com/domains/manage`
- The command name uses a colon: `dns:get` (Symfony Console command naming convention)
