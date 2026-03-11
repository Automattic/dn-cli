---
name: dn-configure
description: Set up credentials for the dn CLI tool. Use when the user needs to authenticate, switch between partner and user mode, set up API keys, or connect their WordPress.com account via OAuth. Also use when the user mentions "dn configure" or needs to reconfigure their dn CLI setup.
---

# dn configure

Set up authentication credentials for the dn CLI.

## Modes

- **User mode** (default): WordPress.com OAuth. Opens browser for authentication.
- **Partner mode**: Direct API access via API key + API user credentials.

## Usage

```bash
# Interactive setup (shows splash screen, mode selection)
dn configure

# Specify mode directly
dn configure --mode=user
dn configure --mode=partner

# Pipe credentials (CI/automation)
echo -e "token" | dn configure --mode=user --stdin
echo -e "api-key\napi-user" | dn configure --mode=partner --stdin

# Custom API URL (partner mode only)
dn configure --mode=partner --api-url=https://custom.api.example.com
```

## Options

| Option | Description |
|--------|-------------|
| `--mode` | `partner` or `user` (interactive if omitted) |
| `--stdin` | Read credentials from stdin instead of prompts |
| `--api-url` | Custom API base URL (partner mode only) |

## Notes

- Credentials stored in `~/.config/dn/config.json` with `0600` permissions
- User mode triggers browser-based OAuth flow (client ID 134319, port 19851)
- Partner mode prompts for API key and user with hidden input
- Can override with env vars: `DN_API_KEY`, `DN_API_USER`, `DN_MODE`, `DN_OAUTH_TOKEN`
