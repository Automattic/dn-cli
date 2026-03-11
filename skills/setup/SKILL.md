---
name: setup
description: Verify that the dn CLI is installed and configured. Use when the user first installs the domain-names plugin, or when a dn command fails because the CLI is missing or not set up.
---

# Setup

Check that the dn CLI is ready to use.

## Steps

1. Run `which dn` to check if the CLI is on PATH.
2. If `dn` is not found:
   - Tell the user to install it: `composer global require automattic/dn-cli`
   - Remind them that `~/.composer/vendor/bin` (or `~/.config/composer/vendor/bin`) must be in their PATH
   - Stop here until they install it
3. If `dn` is found, run `dn list` to verify it works.
4. If the output looks normal, tell the user they're ready and can run `dn configure` to set up credentials (or `/domain-names:dn-configure` for guided setup).
