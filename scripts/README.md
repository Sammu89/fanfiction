# Scripts

This folder contains local developer helpers for live inspection and automation. These scripts are for local use and rely on repo-specific environment details.

## Prerequisites

- Run `npm install` from the plugin root before using these scripts.
- Keep `.codex/local-auth.json` present for local authentication details. This file is gitignored.
- Have a visible Chrome or Chromium installation available.
- Keep the local WordPress database reachable. The helper tries port `10005` first and asks for a different port if that connection fails.

## `live-session.mjs`

Run with:

```bash
npm run live:session
```

What it does:

- Prompts for the local site URL and login path.
- Reuses or launches a visible Chrome session with remote debugging enabled.
- Logs into `wp-admin` using `.codex/local-auth.json`.
- Reads Fanfiction URL settings from the WordPress database so it can understand the active slug structure.
- Reads the logged-in user's WordPress role and derived Fanfiction access level.
- Detects the Fanfiction admin menu in the WordPress backend.
- Optionally resolves a story by ID or slug and builds its front-end URL from the saved plugin settings.

## Notes

- The helper is intended for local debugging, not CI.
- `node_modules/` and `.codex/local-auth.json` must remain uncommitted.
- If you change the live-session helper, validate it with at least:

```bash
node --check scripts/live-session.mjs
```

and:

```bash
$env:LIVE_SESSION_SELF_TEST='1'; node scripts/live-session.mjs
```
