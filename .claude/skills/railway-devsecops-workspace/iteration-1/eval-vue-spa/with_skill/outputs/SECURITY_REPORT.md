# Security Report — Vue 3 SPA

## Findings

**[INFO] No server-side secrets detected**
- This is a static SPA. No secrets, API keys, or credentials were found in project files. ✓

**[INFO] API base URL / env vars**
- If you have a `VITE_API_BASE_URL` or similar build-time env var, it gets baked into the
  bundle at build time. Do not put secrets (tokens, passwords) in `VITE_*` variables — they
  will be visible in the built JS.
- Use Railway env vars for server-side secrets only; keep frontend vars to public config.

**[INFO] Nginx runs as built-in nginx user**
- The official nginx:alpine image runs the worker processes as `nginx` (non-root) by default. ✓

## Summary
0 critical findings, 0 warnings, 3 informational notes.
