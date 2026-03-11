# dn CLI — Development Guide

## Quick Reference

```bash
composer install          # Install dependencies
composer test             # Run test suite (alias for phpunit)
vendor/bin/phpunit        # Run tests directly
vendor/bin/phpunit --filter=CheckCommand  # Run specific test class
```

## Project Structure

```
src/
  Application.php              # Symfony Console app, registers all 17 commands
  Api/WPcomClient.php          # Thin Guzzle wrapper for WordPress.com REST API
  Auth/OAuthFlow.php           # Browser OAuth flow with localhost callback
  Command/
    BaseCommand.php            # Abstract base: auth guard, API creation, mode dispatch, error sanitization
    ConfigureCommand.php       # dn configure (--mode, --stdin, OAuth flow)
    CheckCommand.php           # dn check <domain>... (dual-mode: partner API or WPCOM API)
    SuggestCommand.php         # dn suggest <query> (dual-mode)
    RegisterCommand.php        # dn register <domain> (dual-mode: direct register or add to cart)
    CartCommand.php            # dn cart (user mode only — view shopping cart)
    CheckoutCommand.php        # dn checkout (user mode only — open browser checkout)
    ... (17 commands total)
  Config/ConfigManager.php     # Credential + mode resolution: env vars → config file
  Factory/
    ApiClientFactory.php       # Wires Api client from ConfigManager (partner mode)
    WPcomClientFactory.php     # Wires WPcomClient from ConfigManager (user mode)
  Service/
    CheckoutService.php        # WPCOM payment methods, domain contacts, transaction submission
  Util/Browser.php             # Cross-platform browser-open helper
tests/
  Api/WPcomClientTest.php
  Command/CommandTestCase.php  # Shared test base: mock API + WPcomClient, env var management
  Command/*Test.php            # One test file per command
  Config/ConfigManagerTest.php # Config file I/O, env var priority, mode + OAuth + auto-checkout
  Factory/ApiClientFactoryTest.php
  Factory/WPcomClientFactoryTest.php
  Service/CheckoutServiceTest.php
```

## Namespace

`DnCli\` → `src/`, `DnCli\Tests\` → `tests/` (PSR-4)

## Architecture Conventions

### Dual-Mode Design
The CLI supports two authentication modes, selected during `dn configure`:
- **Partner mode** (default): API key + user auth, direct domain registration via Automattic Domain Services API
- **User mode**: WordPress.com OAuth, domain search/purchase via WPCOM REST APIs + browser checkout

Mode is stored in `config.json` (`"mode": "partner"` or `"mode": "user"`) and can be overridden with `DN_MODE` env var. Commands dispatch to the right backend based on mode.

### Command Pattern
Every command extends `BaseCommand` and implements `handle()`. The base class:
- Guards against missing credentials via `requiresConfig()` (override to return `false` for `ConfigureCommand`)
- Provides `createApi()` for partner mode (constructor-injected `?Api` for tests, or `ApiClientFactory` in production)
- Provides `createWPcomClient()` for user mode (constructor-injected `?WPcomClient` for tests, or `WPcomClientFactory` in production)
- Provides `isUserMode()` to check current mode
- Provides `redirectToWordPressCom()` for management commands that redirect to wordpress.com in user mode
- Provides `sanitizeErrorMessage()` to redact API key/user/OAuth token from exception output

### API Mocking in Tests
- Commands accept `?Api $api` as second and `?WPcomClient $wpcomClient` as third constructor parameter
- `CommandTestCase::createTester()` injects mock API (partner mode)
- `CommandTestCase::createUserModeTester()` injects mock WPcomClient (user mode)
- Response objects are constructed directly with fixture arrays (the library's `Data_Trait` supports this)
- Date format in fixtures must be `'Y-m-d H:i:s'` (not ISO 8601)

### Config & Credentials
- Resolution order: `DN_API_KEY`/`DN_API_USER`/`DN_MODE`/`DN_OAUTH_TOKEN`/`DN_AUTO_CHECKOUT` env vars → `~/.config/dn/config.json`
- Config file created with `0600` permissions (chmod before write to avoid TOCTOU race)
- `ConfigureCommand` uses `askHidden()` for interactive input, `--stdin` for piped input, `--mode` to select mode
- No CLI flags for credentials (prevents `ps aux` / shell history exposure)

### Auto-Checkout (User Mode)
`RegisterCommand` supports opt-in headless checkout via `CheckoutService`:
- Flags: `--auto-checkout` (both), `--auto-pay-credits`, `--auto-pay-card`, `--yes` (skip confirm)
- Config: `auto_checkout` in config.json, `DN_AUTO_CHECKOUT` env var (values: `credits`, `card`, `both`)
- Flow: add to cart → fetch `/me/domain-contact-information` → validate required fields → submit `POST /me/transactions`
- Always falls back to checkout URL on any failure (missing contacts, no payment methods, transaction error)
- `stored_details_id` must never appear in output — only card type and last 4 digits shown
- `ConfigureCommand` prompts for auto-checkout preference after OAuth (interactive mode only)

### Security Patterns
- All catch blocks use `sanitizeErrorMessage()` to redact credentials (API key, user, OAuth token)
- `ApiClientFactory` rejects non-HTTPS custom API URLs
- `.gitignore` includes `.env*` to prevent accidental credential commits
- Success messages never reveal config file paths

## Testing Conventions

- Test classes mirror source structure: `src/Command/FooCommand.php` → `tests/Command/FooCommandTest.php`
- Each command test covers: success path, API error, exception handling, unconfigured state, plus command-specific edge cases
- Dual-mode commands also test user mode paths
- Management commands test user-mode redirect behavior
- Use PHPUnit 11 attributes (`#[DataProvider(...)]`), not doc-comment annotations
- Env vars are saved/restored in setUp/tearDown to avoid test pollution (`DN_MODE`, `DN_OAUTH_TOKEN`, `DN_AUTO_CHECKOUT` included)
- Tests that don't use auto-checkout should set `putenv('DN_AUTO_CHECKOUT=off')` to prevent config file leakage
- `ConfigManagerTest` uses real temp directories (no mocking for file I/O)

## Dependencies

- `automattic/domain-services-client` ^1.6 — Domain Services API client
- `symfony/console` ^6.0 || ^7.0 — CLI framework
- `guzzlehttp/guzzle` ^7.0 — HTTP client (PSR-18)
- `phpunit/phpunit` ^10.0 || ^11.0 — Testing (dev)
