---
name: dn-reset
description: Remove stored dn CLI configuration and credentials. Use when the user wants to clear their saved credentials, start fresh, or switch between authentication modes. Does not require existing configuration.
---

# dn reset

Remove all stored configuration and credentials.

## Usage

```bash
# Interactive (confirmation prompt, default: No)
dn reset

# Skip confirmation
dn reset --force
```

## Options

| Option | Description |
|--------|-------------|
| `--force`, `-f` | Skip confirmation prompt |

## Notes

- Deletes `~/.config/dn/config.json`
- Shows "No configuration found" if already clean
- After reset, run `dn configure` to set up new credentials
- Does not require existing configuration to run
