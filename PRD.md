# dn CLI -- Product Requirements Document

tl;dr: `dn` is a command-line tool that lets any script, app, or AI agent register domains through WordPress.com. Phase 1 shipped 17 commands and 181 tests. Phase 2 is about removing the last friction: checkout still requires a browser, and server-side agents don't have one.

## Context

### The problem

There is no way to register a domain from a shell command. Developers who build apps that need domains (site builders, deploy scripts, hosting tools) have two options: integrate directly with a registrar API (complex, poorly documented, different for every registrar) or redirect users to a browser. Neither is great.

This matters more now than it did a year ago. Vibe-coders are shipping entire applications in a single AI-assisted session, from scaffolding to deployment, in hours. These apps often need custom domains, and the developer has to break out of their flow, open a browser, and register manually. That's friction we can remove.

For server-side AI agents, the problem is worse. An agent running on a remote server, like an OpenClaw instance managing tasks via chat, has no browser at all. It can run shell commands, but the WordPress.com checkout flow ends with "open this URL in your browser." That's a dead end. Agents running on a user's machine (Claude Code, Cursor) can open browsers just fine, but server-hosted agents cannot.

For context, Automattic operates two domain APIs, the Domain Services API (for registrar partners) and the WordPress.com REST API (for end users), and neither has a CLI.

### Why this matters

For vibe-coders, `dn register` becomes a building block. Their app shells out to it. Their deploy script calls it. Their bot uses it. Domain registration becomes a line of code instead of a browser workflow.

For AI agents, this is about completing the loop. An agent that can scaffold an app, deploy it, and configure DNS but can't register the domain is 90% of the way there. We can close that gap.

For Automattic, I think this is a real opportunity to capture domain registrations that currently go elsewhere. Can we agree that if registering a domain through WordPress.com is easier than any alternative, people (and their agents) will do it?

For partners and resellers, `dn` also supports a secondary partner mode with direct API access. This PRD focuses on user mode, which is the primary path for vibe-coders and agents.

## Recommendation

### What we're building

`dn` is a command-line tool for domain management via the WordPress.com REST API. Any developer, script, or AI agent with a WordPress.com account can check availability, register domains, and manage DNS from the shell.

It's built with PHP (Symfony Console), works for both humans and agents, and uses WordPress.com OAuth for authentication. Credentials never appear in CLI flags (no shell history or `ps` exposure), config files are stored with 0600 permissions, and error messages redact tokens automatically.

> Partner mode note: `dn` also has a partner mode for registrar partners with Domain Services API credentials. This PRD focuses on user mode.

We shipped Phase 1: 17 commands covering domain discovery, registration, cart/checkout, DNS, and lifecycle management. 181 tests, all passing.

Phase 2 is about removing agent friction. The big one is headless checkout (no browser required), followed by OAuth token management, deeper WordPress.com API coverage, and reduced-step registration for non-interactive contexts.

## User Stories

### The vibe-coder

A developer who builds apps, bots, and tools with AI assistants. They're not just typing `dn register` themselves. They're building products that call `dn register` as part of their workflow. Their app, their deploy script, their AI-powered site builder uses `dn` as infrastructure.

> "I'm building a site-builder tool with Claude Code. Users pick a template, customize it, and deploy. The last step should be registering a custom domain, automatically. I need a CLI I can shell out to from my app. `dn check`, `dn register`, done."

How they use it:
- `dn configure` once on the server to set up credentials
- Their app calls `dn check` / `dn suggest` to show domain options to users
- Their app calls `dn register` to add the domain to cart and trigger checkout
- Their app calls `dn dns:set` to wire up DNS

### Totoro (the AI agent)

Totoro is an OpenClaw agent running on a home server. It manages tasks via WhatsApp and Slack, executes shell commands autonomously, and has no browser. It's headless.

> "My human asked me on WhatsApp to set up a new blog. I've spun up a WordPress site on their server. Now I need to register a domain. I can run shell commands, so `dn check blogname.com` works great. `dn register blogname.com` adds it to the cart, but then it says 'Complete checkout at this URL.' I can't open URLs. I need checkout to happen right here, in the terminal."

How Totoro uses it:
- `dn check <domain>` to verify availability and pricing
- `dn register <domain>` to start registration
- Complete checkout without a browser (Phase 2)
- `dn dns:set` to point the domain at the new site

## Goals

### Business goals

1. Increase domain registrations through WordPress.com by making registration something a script can do in one line.
2. Capture agentic registrations. I believe we can be the first major platform where AI agents register domains. That's a category worth owning.
3. Open up new product shapes: vibe-coded site builders, deploy tools, and bots that include "register a domain" as a built-in step. Today this requires custom registrar integrations. `dn` makes it four shell commands.

### User goals

1. `dn register` should work as a building block inside apps, scripts, and agent workflows. Not just a human typing in a terminal.
2. Every operation, including checkout, should be completable without interactive input. If a headless agent can't finish the flow, we haven't shipped it.
3. Credentials should be handled safely (no shell history exposure, encrypted config, OAuth) without adding steps.

### Non-goals

- Replacing the web UI. `dn` is a complement to WordPress.com domain management, not a replacement.
- Supporting non-Automattic registrars. This is built for WordPress.com and the Domain Services API.
- Building a GUI. No TUI, no web dashboard. Terminal only.
- Hosting management. `dn` manages domains, not sites or servers.
- Bulk operations (for now). Portfolio-scale management is a future consideration.

## Functional Requirements

### Shipped (Phase 1): 17 commands

Core commands (user mode):

| Category | Commands | Description |
|----------|----------|-------------|
| Setup | `configure`, `reset` | WordPress.com OAuth flow, credential storage, mode selection. |
| Discovery | `check`, `suggest` | Check domain availability with pricing. Get name suggestions from a query. |
| Registration | `register` | Add domain to WordPress.com cart + print checkout link. `--site` for site-bound cart. |
| Shopping | `cart`, `checkout` | View cart contents. Open WordPress.com checkout in browser. |

Partner mode commands (available with Domain Services API credentials):

| Category | Commands | Description |
|----------|----------|-------------|
| Registration | `register` | Direct API registration, no cart/checkout step. |
| Lifecycle | `renew`, `delete`, `restore`, `transfer` | Renew, delete, restore, and transfer-in domains. |
| DNS | `dns:get`, `dns:set` | View and update DNS records (A, AAAA, CNAME, MX, TXT, etc.). |
| Configuration | `contacts:set`, `privacy`, `transferlock` | Update contacts, set WHOIS privacy, toggle transfer lock. |

All commands guard against missing credentials and dispatch to the correct backend based on mode. Partner-only commands redirect to WordPress.com management pages in user mode. Error messages redact API keys, usernames, and OAuth tokens before display.

### Phase 2: removing agent friction

| Feature | Priority | Description |
|---------|----------|-------------|
| Headless checkout | P0 | Complete a domain purchase without opening a browser. This is the single biggest blocker for agents. |
| Token refresh | P1 | Detect expired OAuth tokens (401 responses) and prompt re-authentication or refresh automatically. |
| Cart persistence fix | P1 | Resolve domain-only (`no-site`) cart showing as empty after adding items. |
| WPCOM DNS management | P2 | DNS get/set via WordPress.com APIs in user mode (currently partner-only). |
| WPCOM contact editing | P2 | Contact management via WordPress.com APIs in user mode. |
| Non-interactive OAuth | P2 | Token-based auth that doesn't require a browser, so agents can run `dn configure` without one. |
| Structured output | P3 | JSON output mode for all commands, so agents can parse results instead of scraping text. |

## User Experience

### First-time setup

Install: `composer global require automattic/dn-cli`

Run `dn configure`:
1. Splash screen with Automattic branding and a brief description.
2. Mode selection (most users will choose user mode for WordPress.com OAuth).
3. Browser opens to WordPress.com OAuth consent screen. User approves. Token is captured via a localhost callback on port 19851.
4. Credentials are stored in `~/.config/dn/config.json` with 0600 permissions.
5. A success message suggests what to try next.

For headless environments (OpenClaw, CI, server-side apps), the OAuth token can be set via the `DN_OAUTH_TOKEN` environment variable, bypassing the browser flow entirely. Phase 2 will add a proper non-interactive auth path.

Running any command before configuring produces a clear error directing the user to `dn configure`.

### Day-to-day use

Domain discovery:
```
$ dn check coolsaas.com
coolsaas.com is available -- $12.00/year
```

Registration (user mode):
```
$ dn register coolsaas.com
Added coolsaas.com to your cart.
Complete checkout: https://wordpress.com/checkout/...
```

DNS (partner mode today, WPCOM planned for Phase 2):
```
$ dn dns:set coolsaas.com A 76.76.21.21
DNS record updated.
```

Inside an app or deploy script:
```bash
# A site builder registers a domain for the user
DOMAIN="freshbakes.com"
dn check "$DOMAIN"
dn register "$DOMAIN"
# Phase 2: dn checkout --headless completes purchase without a browser
```

Totoro on WhatsApp:
```
User: "Set up a blog at freshbakes.com"
Totoro: Checking domain... running dn check freshbakes.com
        freshbakes.com is available -- $12.00/year.
        Registering... running dn register freshbakes.com
        Added to cart, but I can't open the checkout URL.
        Here's the link: https://wordpress.com/checkout/...
        (Phase 2: headless checkout handles this automatically)
```

## Narrative

### Journey 1: the vibe-coder

Mia is building a site-builder app with Claude Code. Users pick a template, customize it, and deploy. She wants the last step to be "pick a domain," with no redirects, no registrar visits.

She adds `dn` as a dependency on the build server and sets `DN_OAUTH_TOKEN` in the environment. Her app shells out to `dn suggest` to show domain options, `dn check` to confirm pricing, and `dn register` to add the domain to a WordPress.com cart. Today, her app prints the checkout link for the user to complete payment. With Phase 2, `dn checkout --headless` will handle payment in the background, and the user never leaves Mia's app.

Mia didn't build a domain registrar integration. She added four shell commands and got one for free.

### Journey 2: Totoro

I message Totoro on WhatsApp: "Set up a blog for the bakery project."

Totoro, an OpenClaw agent running on my home server, spins up a WordPress instance, picks a theme, and configures the basics. Time to register a domain. Totoro runs `dn suggest "artisan bakery blog"` and sends me three options. I reply "freshbakes.com." Totoro runs `dn check freshbakes.com` (available, $12/year) and `dn register freshbakes.com`. The domain is in the cart.

But Totoro is headless. It can't open the checkout URL. It sends me the link: "I've added freshbakes.com to your cart. Tap here to complete checkout." I tap, pay in 30 seconds, and the blog is live.

With Phase 2, Totoro runs `dn checkout --headless`, the payment completes server-side, and I get a WhatsApp message: "freshbakes.com is live." No link to tap. No browser. Done.

## Success Metrics

### User-centric

| KPI | Definition | Target |
|-----|-----------|--------|
| Time-to-register | Seconds from `dn register` to domain confirmed | < 2min (browser checkout), < 30s (headless, Phase 2) |
| Registration completion rate | % of `dn register` invocations that result in a registered domain | > 50% pre-headless, > 80% post-headless |
| First-command success | % of users who successfully run their first command after `dn configure` | > 90% |

### Business

| KPI | Definition | Target |
|-----|-----------|--------|
| Domains registered via CLI | Monthly count of domains registered through `dn` | Track growth MoM |
| Agent-driven registrations | % of registrations where the invoking process is a script or agent | Track and grow |
| Composer installs | Monthly install count from Packagist | Track growth MoM |

### Technical

| KPI | Definition | Target |
|-----|-----------|--------|
| User mode API coverage | % of WordPress.com domain operations available via `dn` vs. web UI | Growing toward parity |
| Test coverage | Number of tests, all passing, zero deprecations | >= 181 tests |
| Error rate | % of command invocations resulting in unhandled exceptions | < 1% |

## Tracking Plan

| Event | Properties | Trigger |
|-------|-----------|---------|
| `dn_configure_completed` | `mode` (partner/user), `auth_method` (apikey/oauth), `is_reconfigure` | User completes `dn configure` |
| `dn_command_executed` | `command`, `mode`, `success` (bool), `duration_ms`, `source` (tty/pipe/agent) | Any command finishes |
| `dn_domain_checked` | `domain`, `tld`, `available` (bool), `price`, `mode` | `dn check` returns result |
| `dn_domain_registered` | `domain`, `tld`, `mode`, `source`, `time_to_register_ms` | Domain registration confirmed |
| `dn_cart_updated` | `action` (add/view), `domain`, `cart_size` | Item added to cart or cart viewed |
| `dn_checkout_initiated` | `mode`, `cart_size`, `source`, `method` (browser/headless) | Checkout flow started |
| `dn_checkout_completed` | `domain`, `source`, `method`, `duration_ms` | Payment confirmed (requires callback/webhook) |
| `dn_dns_updated` | `domain`, `record_type`, `mode` | DNS record set successfully |
| `dn_auth_error` | `command`, `error_type` (expired_token/invalid_key/missing_config) | Authentication failure |
| `dn_error_occurred` | `command`, `error_class`, `sanitized_message` | Unhandled exception caught |

We can differentiate human vs. agent invocations by checking `isatty(STDIN)`. A TTY means interactive human use; a pipe or non-TTY means a script or agent is calling us. It's a rough heuristic, but it works for i1.

No PII in events. Domain names are tracked (we need them for registration metrics) but are never associated with user identity beyond the anonymous session.
