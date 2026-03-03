# dn — Domain Name CLI

Manage domains from your terminal. `dn` works in two modes: directly through the Automattic Domain Services API (partner mode), or through WordPress.com (user mode).

## Installation

### Composer (global)

Since this package is not yet on Packagist, first add the repository to your global Composer configuration:

```bash
composer global config repositories.dn-cli vcs https://github.com/p3ob7o/dn-cli
```

Then install:

```bash
composer global require p3ob7o/dn-cli
```

Make sure `~/.composer/vendor/bin` (or `~/.config/composer/vendor/bin`) is in your `PATH`:

```bash
export PATH="$HOME/.composer/vendor/bin:$PATH"
```

### From source

```bash
git clone https://github.com/p3ob7o/dn-cli.git
cd dn-cli
composer install
```

Then run with `./bin/dn` or symlink it into your PATH:

```bash
ln -s "$(pwd)/bin/dn" /usr/local/bin/dn
```

## Setup

Run `dn configure` to pick a mode and authenticate:

- **User mode** — WordPress.com OAuth. Requires a WordPress.com account.
- **Partner mode** — Automattic Domain Services API. Requires an API key and API user.

Your mode determines which commands are available. User mode covers domain search and purchase through WordPress.com checkout. Partner mode gives you the full set: registration, DNS, contacts, privacy, transfers.

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
    "oauth_token": "your-oauth-token"
}
```

To remove stored credentials:

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

In **partner mode**, registers the domain directly:

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

In **user mode**, adds the domain to your WordPress.com cart and prints a checkout link:

```bash
dn register newdomain.com

# With a specific site
dn register newdomain.com --site=mysite.wordpress.com
```

### Cart and checkout (user mode)

```bash
dn cart
dn checkout
dn checkout --site=mysite.wordpress.com
```

`dn register` adds to cart, `dn cart` shows what's in it, `dn checkout` opens WordPress.com checkout in your browser.

### Domain information

Partner mode. In user mode, points you to WordPress.com.

```bash
dn info example.com
```

### Partner mode commands

The remaining commands are partner mode only. In user mode, they'll point you to WordPress.com where you can manage these settings.

```bash
dn renew example.com --expiration-year=2026 --period=1
dn delete example.com
dn restore example.com
dn transfer example.com --auth-code=ABC123XYZ
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
| `dn transfer <domain>` | partner | Transfer a domain in |
| `dn dns:get <domain>` | partner | View DNS records |
| `dn dns:set <domain>` | partner | Set DNS records |
| `dn contacts:set <domain>` | partner | Update contact information |
| `dn privacy <domain> <on\|off\|redact>` | partner | WHOIS privacy settings |
| `dn transferlock <domain> <on\|off>` | partner | Transfer lock control |

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
