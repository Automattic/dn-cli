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
                              ↓                    ↓
                        OAuthFlow              CheckoutService  →  /me/transactions
                              ↓                                    /me/payment-methods
                        Browser  →  WordPress.com OAuth            /me/domain-contact-information
```

- **Entry point** (`bin/dn`): Finds Composer autoloader (local or global install), creates and runs the Application.
- **Application**: Registers all 17 commands.
- **BaseCommand**: Abstract base providing auth guard, dual-mode API creation, mode dispatch (`isUserMode()`), and error message sanitization.
- **ConfigManager**: Resolves credentials, mode, and auto-checkout preference from environment variables (`DN_API_KEY`, `DN_API_USER`, `DN_MODE`, `DN_OAUTH_TOKEN`, `DN_AUTO_CHECKOUT`) or `~/.config/dn/config.json`. Env vars take priority.
- **ApiClientFactory**: Static factory for the Domain Services `Api` client. Enforces HTTPS on custom URLs.
- **WPcomClientFactory**: Static factory for `WPcomClient` from OAuth token.
- **WPcomClient**: Thin Guzzle wrapper for WordPress.com REST API with Bearer token auth.
- **OAuthFlow**: Browser-based OAuth implicit grant flow with localhost callback server (port 19851, client ID 134319).
- **CheckoutService**: Wraps WPCOM payment and transaction APIs (`/me/payment-methods`, `/me/domain-contact-information`, `/me/transactions`). Used by RegisterCommand for auto-checkout.

## Commands (17)

| Command | Class | Modes | Description |
|---|---|---|---|
| `configure` | ConfigureCommand | both | Set up credentials + mode (`--mode partner\|user`, `--stdin`, OAuth flow). Splash screen with Automattic ASCII logo |
| `reset` | ResetCommand | both | Clear stored configuration and credentials |
| `check` | CheckCommand | both | Check domain availability and pricing |
| `suggest` | SuggestCommand | both | Get domain name suggestions |
| `register` | RegisterCommand | both | Register a domain (partner: direct, user: add to cart or auto-checkout). `--site`, `--auto-checkout`, `--auto-pay-credits`, `--auto-pay-card`, `--yes` |
| `cart` | CartCommand | user | View WordPress.com shopping cart |
| `checkout` | CheckoutCommand | user | Open WordPress.com checkout in browser. `--site` for site-bound checkout |
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

- **214 tests, 458 assertions** — all passing, zero deprecations
- **Fully mocked** — no API credentials needed to run tests
- **Coverage**: every command (success, API error, exception, unconfigured state, user-mode paths), ConfigManager (env vars, file I/O, permissions, caching, mode + OAuth, auto-checkout), ApiClientFactory, WPcomClientFactory, CheckoutService, Application (command registration)
- **Security tests**: credential redaction (API key, user, OAuth token), TOCTOU permission fix, HTTP URL rejection, config path non-disclosure, stored_details_id never in output

## Security Measures

1. **Credential input**: `askHidden()` for interactive, `--stdin` for scripted, OAuth browser flow — no CLI flags that appear in `ps` or shell history
2. **Config file permissions**: `chmod 0600` applied before writing content (TOCTOU-safe)
3. **Error sanitization**: `BaseCommand::sanitizeErrorMessage()` redacts API key, user, and OAuth token from exception messages before display
4. **HTTPS enforcement**: `ApiClientFactory` rejects custom API URLs that don't use HTTPS
5. **Immutable API injection**: Constructor parameter, not a mutable setter
6. **gitignore**: Covers `.env*`, `vendor/`, `composer.lock`, IDE files

## Distribution

- **Composer global**: `composer global require automattic/dn-cli`
- **From source**: `git clone` + `composer install` + `./bin/dn`
- **Claude Code plugin**: `/plugin marketplace add Automattic/dn-cli` then `/plugin install domain-names`
- Entry point `bin/dn` handles both autoloader paths

## Current Status

- All 17 commands implemented and tested (214 tests, 458 assertions)
- Dual-mode architecture: partner mode (Domain Services API) and user mode (WordPress.com OAuth)
- Auto-checkout: opt-in headless domain purchase via credits or stored payment methods
- OAuth flow working with client ID 134319, fixed port 19851
- Cart POST body matches Calypso expectations (correct product slugs, domain-only flags)
- Security review completed with all findings resolved
- GPL-2.0 license file added, repo at Automattic/dn-cli
- Claude Code plugin (`domain-names`) with 18 skills in `skills/` (17 commands + setup)

### Known Issues / Next Steps

- **Token expiry**: WPCOM implicit grant tokens expire; follow-up: detect 401 in WPcomClient and suggest re-running `dn configure`
- **Auto-checkout contact info**: Requires prior domain purchase to have cached contact details at `/me/domain-contact-information`. First-time users must complete checkout in browser.
