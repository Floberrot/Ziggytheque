# Resend as email provider

This document explains how to send Ziggytheque's transactional emails (email
verification, password reset, account-approved, and "new chapters" follow
notifications) through **Resend** instead of the local Mailpit catcher.

> Keep **Mailpit** for local development — you don't want real emails sent from
> your machine. **Resend is wired up in production** (`ziggytheque.fr` domain
> verified, DKIM + MX records live in the OVH DNS zone).

---

## 1. How email works in Ziggytheque

Emails are sent with **Symfony Mailer**. The transport is driven entirely by one
environment variable, `MAILER_DSN`:

```yaml
# back/config/packages/mailer.yaml
framework:
    mailer:
        dsn: '%env(MAILER_DSN)%'
```

`SendEmailMessage` is routed to the `async` Messenger transport, so every email
is delivered by the **worker** container, never during the HTTP request. The
`worker` service therefore needs the same `MAILER_DSN` as `back`.

Default (local): `MAILER_DSN=smtp://mailer:1025` → Mailpit, UI at <http://localhost:8025>.

---

## 2. Create a Resend account and API key

1. Sign up at <https://resend.com> (free tier: 3,000 emails/month, 100/day).
2. In the dashboard go to **API Keys** → **Create API Key** → copy it.
3. Go to **Domains** → **Add Domain** and verify your domain (or verify a single
   sender email address if you don't have a custom domain yet).

> **Ziggytheque prod state:** the domain `ziggytheque.fr` is already verified
> in Resend. The DNS records (`resend._domainkey` TXT, `send` MX / TXT) are in
> the OVH zone — do not touch them.

---

## 3. Build the `MAILER_DSN`

```dotenv
MAILER_DSN=resend+api://YOUR_API_KEY@default
```

Resend communicates over HTTPS (port 443) — no SMTP port blocking issues.

---

## 4. Set it for your environment

### Railway / production

In the Railway dashboard, for **both** the `back` service and the `worker` service:

- **Variables → New Variable**
- `MAILER_DSN` = `resend+api://YOUR_API_KEY@default`
- `NOTIFICATION_EMAIL` = the verified sender address from your Resend domain

Redeploy both services after saving.

### Local Docker (staging-like test)

To keep the secret out of the compose file, interpolate from the project-root `.env`:

```yaml
# docker-compose.yml — back service AND worker service
environment:
  MAILER_DSN: ${MAILER_DSN:-smtp://mailer:1025}
```

```dotenv
# .env  (project root, git-ignored)
MAILER_DSN=resend+api://YOUR_API_KEY@default
```

### Bare-metal / non-Docker backend

Put it in `back/.env.local` (git-ignored, overrides `back/.env`):

```dotenv
MAILER_DSN=resend+api://YOUR_API_KEY@default
```

---

## 5. Sender address

The `NOTIFICATION_EMAIL` env var controls the **From** address for all emails.
It must match a sender or domain verified in Resend.

For Ziggytheque production, set it in Railway alongside `MAILER_DSN`:

```
NOTIFICATION_EMAIL=notifications@ziggytheque.fr
```

Any local-part on the verified `ziggytheque.fr` domain works
(`hello@`, `noreply@`, `notifications@`, …) — Resend does not require the
mailbox to actually exist, only the domain to be verified.

---

## 6. Test it

After deploying with the Resend DSN, trigger an email and confirm it arrives:

```bash
curl -X POST https://www.ziggytheque.fr/api/auth/register \
  -H 'Content-Type: application/json' \
  -d '{"email":"you@example.com","password":"Password1!","displayName":"Test"}'
```

Check the recipient inbox, and **Resend dashboard → Emails** which shows every
sent / failed message with full logs.

---

## 7. Troubleshooting

| Symptom | Cause / fix |
|---|---|
| `401 Unauthorized` | Wrong or expired API key — regenerate in Resend dashboard. |
| Email sent but not delivered | Sender domain not verified — check Resend → Domains. |
| Auth emails silently missing | `AuthEmailListener` catches delivery errors so a mail failure never breaks signup. The actual send runs in the `worker`; check its logs and inspect failed sends with `php bin/console messenger:failed:show`. |
