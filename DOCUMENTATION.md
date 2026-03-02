# dn CLI — Project Documentation

## Overview

`dn` is a command-line tool for managing domains. It supports two modes:
- **Partner mode**: Direct domain management via the Automattic Domain Services API (for registrars/resellers)
- **User mode**: Domain search and purchase via WordPress.com OAuth + REST APIs (for end users)

## Architecture

```
bin/dn  →  Application  →  Command  →  ApiClientFactory  →  Api  →  DSAPI          (partner mode)
                              ↑
                        ConfigManager (env vars / config file)
                              ↓
           Command  →  WPcomClientFactory  →  WPcomClient  →  WPCOM REST API  (user mode)
                              ↓
                        OAuthFlow  →  Browser  →  WordPress.com OAuth
```

- **Entry point** (`bin/dn`): Finds Composer autoloader (local or global install), creates and runs the Application.
- **Application**: Registers all 16 commands.
- **BaseCommand**: Abstract base providing auth guard, dual-mode API creation, mode dispatch (`isUserMode()`), and error message sanitization.
- **ConfigManager**: Resolves credentials and mode from environment variables (`DN_API_KEY`, `DN_API_USER`, `DN_MODE`, `DN_OAUTH_TOKEN`) or `~/.config/dn/config.json`. Env vars take priority.
- **ApiClientFactory**: Static factory for the Domain Services `Api` client. Enforces HTTPS on custom URLs.
- **WPcomClientFactory**: Static factory for `WPcomClient` from OAuth token.
- **WPcomClient**: Thin Guzzle wrapper for WordPress.com REST API with Bearer token auth.
- **OAuthFlow**: Browser-based OAuth implicit grant flow with localhost callback server (port 19851, client ID 134319).

## Commands (16)

| Command | Class | Modes | Description |
|---|---|---|---|
| `configure` | ConfigureCommand | both | Set up credentials + mode (`--mode partner\|user`, `--stdin`, OAuth flow) |
| `check` | CheckCommand | both | Check domain availability and pricing |
| `suggest` | SuggestCommand | both | Get domain name suggestions |
| `register` | RegisterCommand | both | Register a domain (partner: direct, user: add to cart + checkout) |
| `cart` | CartCommand | user | View WordPress.com shopping cart |
| `checkout` | CheckoutCommand | user | Open WordPress.com checkout (`--site` option) |
| `info` | InfoCommand | partner | Domain details: dates, contacts, nameservers, EPP status |
| `renew` | RenewCommand | partner | Renew a domain registration |
| `delete` | DeleteCommand | partner | Delete a domain (with confirmation) |
| `restore` | RestoreCommand | partner | Restore a deleted domain |
| `transfer` | TransferCommand | partner | Transfer a domain in (hidden auth code input) |
| `dns:get` | DnsGetCommand | partner | View DNS records |
| `dns:set` | DnsSetCommand | partner | Set DNS records (supports multiple values) |
| `contacts:set` | ContactsSetCommand | partner | Update contact information |
| `privacy` | PrivacySetCommand | partner | Set WHOIS privacy (on/off/redact) |
| `transferlock` | TransferlockCommand | partner | Set transfer lock (on/off) |

Partner-only commands redirect to wordpress.com/domains in user mode.

## Test Suite

- **167 tests, 328 assertions** — all passing, zero deprecations
- **Fully mocked** — no API credentials needed to run tests
- **Coverage**: every command (success, API error, exception, unconfigured state, user-mode paths), ConfigManager (env vars, file I/O, permissions, caching, mode + OAuth), ApiClientFactory, WPcomClientFactory, Application (command registration)
- **Security tests**: credential redaction (API key, user, OAuth token), TOCTOU permission fix, HTTP URL rejection, config path non-disclosure

## Security Measures

1. **Credential input**: `askHidden()` for interactive, `--stdin` for scripted, OAuth browser flow — no CLI flags that appear in `ps` or shell history
2. **Config file permissions**: `chmod 0600` applied before writing content (TOCTOU-safe)
3. **Error sanitization**: `BaseCommand::sanitizeErrorMessage()` redacts API key, user, and OAuth token from exception messages before display
4. **HTTPS enforcement**: `ApiClientFactory` rejects custom API URLs that don't use HTTPS
5. **Immutable API injection**: Constructor parameter, not a mutable setter
6. **gitignore**: Covers `.env*`, `vendor/`, `composer.lock`, IDE files

## Distribution

- **Composer global**: `composer global require p3ob7o/dn-cli`
- **From source**: `git clone` + `composer install` + `./bin/dn`
- Entry point `bin/dn` handles both autoloader paths

## Current Status

- All 16 commands implemented and tested (167 tests passing)
- Dual-mode architecture: partner mode (Domain Services API) and user mode (WordPress.com OAuth)
- OAuth flow working with client ID 134319, fixed port 19851
- Security review completed with all findings resolved
- GPL-2.0 license file added, package published as p3ob7o/dn-cli

### Known Issues / Next Steps

- **Cart checkout flow**: The `dn register` (user mode) cart POST body needs fixes — missing `blog_id: 0`, `is_domain_registration: true`, `extra.isDomainOnlySitelessCheckout: true`, and wrong product slug. See memory file `wpcom-cart-checkout.md` for Calypso research findings.
- **UX preference**: `dn register` should show checkout URL to user instead of auto-opening browser. `dn checkout` same — print link, let user decide.
