---
name: dn-privacy
description: Set WHOIS privacy for a domain using the dn CLI. Use when the user wants to enable, disable, or change the privacy/redaction settings for domain WHOIS records. Partner mode only.
---

# dn privacy

Set WHOIS privacy for a domain.

## Usage

```bash
# Enable privacy (proxy contact info)
dn privacy example.com on

# Disable privacy (show real contact info)
dn privacy example.com off

# Redact contact info (minimal disclosure)
dn privacy example.com redact
```

## Arguments

| Argument | Description |
|----------|-------------|
| `domain` | Domain name |
| `setting` | `on`, `off`, or `redact` |

## Privacy Settings

- **on**: Enable privacy service (proxy contact information)
- **off**: Disclose real contact information in WHOIS
- **redact**: Redact contact information (minimal GDPR-compliant disclosure)

## Notes

- **Partner mode only** - user mode shows redirect to `wordpress.com/domains/manage`
- Use `dn info <domain>` to check current privacy setting
