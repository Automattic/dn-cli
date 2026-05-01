# dn — Domain Name CLI

Manage domains from your terminal. Two modes: Automattic Domain Services API (partner) or WordPress.com (user).

## Installation

### Composer (global)

```bash
composer global require automattic/dn-cli
```

Add `~/.composer/vendor/bin` (or `~/.config/composer/vendor/bin`) to your `PATH`:

```bash
export PATH="$HOME/.composer/vendor/bin:$PATH"
```

### From source

```bash
git clone https://github.com/Automattic/dn-cli.git
cd dn-cli
composer install
```

Run `./bin/dn` or symlink into PATH:

```bash
ln -s "$(pwd)/bin/dn" /usr/local/bin/dn
```

## Setup

Run `dn configure` to pick a mode and authenticate:

- **User mode** — WordPress.com OAuth. Requires a WordPress.com account.
- **Partner mode** — Automattic Domain Services API. Requires an API key and API user.

Mode determines available commands. User mode: search, purchase, transfer via WordPress.com checkout. Partner mode: full set — registration, DNS, contacts, privacy, transfers.

### Non-interactive setup

Pipe credentials via stdin for scripts and CI/CD:

```bash
# Partner mode
printf '%s\n%s\n' "$DN_API_KEY" "$DN_API_USER" | dn configure --mode=partner --stdin

# User mode (pipe an OAuth token)
printf '%s\n' "$DN_OAUTH_TOKEN" | dn configure --mode=user --stdin
```

### Environment variables

Environment variables override the config file:

```bash
# Partner mode
export DN_API_KEY="your-api-key"
export DN_API_USER="your-api-user"
export DN_API_URL="https://custom-endpoint.example.com/command"  # optional

# User mode
export DN_OAUTH_TOKEN="your-oauth-token"

# Override mode regardless of config file
export DN_MODE="user"  # or "partner"

# Auto-checkout preference (user mode)
export DN_AUTO_CHECKOUT="both"  # "credits", "card", or "both"
```

### Config file

Stored at `~/.config/dn/config.json` with `0600` permissions:

```json
{
    "mode": "partner",
    "api_key": "your-api-key",
    "api_user": "your-api-user"
}
```

```json
{
    "mode": "user",
    "oauth_token": "your-oauth-token",
    "auto_checkout": "both"
}
```

Remove stored credentials:

```bash
dn reset
```

## Commands

### Check domain availability

Both modes.

```bash
dn check example.com
dn check example.com example.net example.org
```

### Get domain suggestions

Both modes.

```bash
dn suggest "coffee shop"

# Filter by TLDs and limit results
dn suggest "coffee" --tlds=com,net,io --count=20

# Exact match only
dn suggest "mycoffee" --exact
```

### Register a domain

**Partner mode** — registers directly:

```bash
# Interactive — prompts for contact details
dn register newdomain.com

# Non-interactive with all options
dn register newdomain.com \
  --first-name=Jane \
  --last-name=Doe \
  --email=jane@example.com \
  --phone=+1.5551234567 \
  --address="123 Main St" \
  --city="San Francisco" \
  --state=CA \
  --postal-code=94110 \
  --country=US \
  --period=2 \
  --privacy=on
```

**User mode** — adds to your WordPress.com cart and prints a checkout link:

```bash
dn register newdomain.com

# With a specific site
dn register newdomain.com --site=mysite.wordpress.com
```

#### Auto-checkout (user mode)

Complete purchases without opening a browser. Requires a saved payment method or credits, plus contact info on file from a previous purchase.

```bash
# Auto-checkout: try credits first, then stored card
dn register newdomain.com --auto-checkout

# Use account credits only
dn register newdomain.com --auto-pay-credits

# Use a stored payment method only
dn register newdomain.com --auto-pay-card

# Skip confirmation prompt (for scripts)
dn register newdomain.com --auto-checkout --yes
```

Set a persistent preference during `dn configure` or with the `DN_AUTO_CHECKOUT` environment variable (`credits`, `card`, or `both`).

### Cart and checkout (user mode)

```bash
dn cart
dn checkout
dn checkout --site=mysite.wordpress.com
```

`dn register` adds to cart, `dn cart` views it, `dn checkout` opens WordPress.com checkout in your browser.

### Domain information

Partner mode. In user mode, points you to WordPress.com.

```bash
dn info example.com
```

### Transfer a domain

Both modes. Transfers from another registrar. Domain must be unlocked; you need the EPP auth code.

**Partner mode** — submits directly:

```bash
dn transfer example.com --auth-code=ABC123XYZ
```

**User mode** — validates the auth code, adds to your WordPress.com cart, prints a checkout link:

```bash
dn transfer example.com

# With a specific site
dn transfer example.com --site=mysite.wordpress.com
```

Auto-checkout works like `dn register`:

```bash
dn transfer example.com --auto-checkout
dn transfer example.com --auto-pay-credits --yes
```

### Partner mode commands

Partner mode only. In user mode, these point to WordPress.com.

```bash
dn renew example.com --expiration-year=2026 --period=1
dn delete example.com
dn restore example.com
```

#### DNS

```bash
dn dns:get example.com
dn dns:set example.com --type=A --name=@ --value=1.2.3.4 --ttl=3600
dn dns:set example.com --type=A --name=@ --value=1.2.3.4 --value=5.6.7.8
```

Supported record types: `A`, `AAAA`, `ALIAS`, `CAA`, `CNAME`, `MX`, `NS`, `PTR`, `TXT`, `SRV`.

#### Contacts, privacy, transfer lock

```bash
dn contacts:set example.com
dn contacts:set example.com --type=admin --first-name=Jane --last-name=Doe --email=admin@example.com

dn privacy example.com on      # enable privacy service
dn privacy example.com off     # disclose contact info
dn privacy example.com redact  # redact contact info

dn transferlock example.com on
dn transferlock example.com off
```

## Command reference

| Command | Mode | Description |
|---|---|---|
| `dn configure` | — | Set up credentials and select mode |
| `dn reset` | — | Remove stored configuration |
| `dn check <domain>...` | both | Check availability and pricing |
| `dn suggest <query>` | both | Get domain name suggestions |
| `dn register <domain>` | both | Register a domain (partner) or add to cart (user) |
| `dn cart` | user | View your shopping cart |
| `dn checkout` | user | Open WordPress.com checkout in browser |
| `dn info <domain>` | partner | Domain details and status |
| `dn renew <domain>` | partner | Renew registration |
| `dn delete <domain>` | partner | Delete a domain |
| `dn restore <domain>` | partner | Restore a deleted domain |
| `dn transfer <domain>` | both | Transfer a domain in (partner: direct, user: add to cart) |
| `dn dns:get <domain>` | partner | View DNS records |
| `dn dns:set <domain>` | partner | Set DNS records |
| `dn contacts:set <domain>` | partner | Update contact information |
| `dn privacy <domain> <on\|off\|redact>` | partner | WHOIS privacy settings |
| `dn transferlock <domain> <on\|off>` | partner | Transfer lock control |

## Claude Code plugin

For [Claude Code](https://claude.com/claude-code) users, install the `domain-names` plugin for guided domain management skills:

```
/plugin marketplace add Automattic/dn-cli
/plugin install domain-names
```

Run `/domain-names:setup` to verify install and config. Then use skills like `/domain-names:dn-check`, `/domain-names:dn-register`.

## Shell completion

```bash
# Bash
dn completion bash | sudo tee /etc/bash_completion.d/dn

# Zsh
dn completion zsh | sudo tee /usr/local/share/zsh/site-functions/_dn

# Fish
dn completion fish | tee ~/.config/fish/completions/dn.fish
```

## License

GPL-2.0-or-later
