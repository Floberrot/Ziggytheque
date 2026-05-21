# Brevo as email provider

This document explains how to send Ziggytheque's transactional emails (email
verification, password reset, account-approved, and the "new chapters" follow
notifications) through **Brevo** (formerly Sendinblue) instead of the local
Mailpit catcher.

> Keep **Mailpit** for local development — you don't want real emails sent from
> your machine. Use **Brevo** for staging / production deployments.

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
— auth (verification / password-reset / account-approved) and the "new
chapters" follow notifications — is delivered by the **worker** container, never
during the HTTP request. The `worker` service therefore needs the same
`MAILER_DSN` as `back`.

Default (local): `MAILER_DSN=smtp://mailer:1025` → Mailpit, UI at <http://localhost:8025>.

Brevo offers two transports. **Prefer the HTTP API transport** — it talks to
Brevo over HTTPS (port 443), which no cloud platform blocks. The SMTP transport
is a fallback for when the API cannot be used.

---

## 2. Get your Brevo credentials

1. Create a free account at <https://www.brevo.com> (the free plan allows 300
   emails/day).
2. In the dashboard open **SMTP & API**:
   - **API Keys** tab → **Generate a new API key** → copy the **API v3 key**.
     This is what the recommended HTTP API transport uses.
   - **SMTP** tab → note the server `smtp-relay.brevo.com` and your login (an
     email address), then **Generate a new SMTP key**. Only the fallback SMTP
     transport needs this. The key is shown once.

---

## 3. Build the `MAILER_DSN`

### Recommended — Brevo HTTP API

The `symfony/brevo-mailer` bridge is already a project dependency, so no extra
install is needed. Use the **API v3 key** from step 2:

```dotenv
MAILER_DSN=brevo+api://KEY@default
```

This delivers over HTTPS (port 443). It is immune to the SMTP-port blocking
that produces `Connection could not be established` / connection-timeout
errors on platforms such as Railway.

### Fallback — generic SMTP

If you must use SMTP, point at `smtp-relay.brevo.com`. **Use port `2525`, not
`587`** — many platforms (Railway included) block outbound `587`, which
surfaces as a connection timeout. Port `2525` is Brevo's documented
alternative and carries the same STARTTLS traffic.

```
smtp://<LOGIN>:<SMTP_KEY>@smtp-relay.brevo.com:2525
```

> **URL-encode special characters.** The login is an email address, so its `@`
> must be written as `%40`. Encode any reserved character (`@ : / # ? %`) in the
> login or key as well.

Example — login `mailer@ziggytheque.com`, key `xsmtpsib-abc123`:

```
MAILER_DSN=smtp://mailer%40ziggytheque.com:xsmtpsib-abc123@smtp-relay.brevo.com:2525
```

---

## 4. Set it for your environment

### Railway / production

`MAILER_DSN` is a plain environment variable. In the Railway dashboard, for
**both** the `back` service and the `worker` service:

- **Variables → New Variable**
- Name `MAILER_DSN`, value the DSN from step 3.

Redeploy both services after saving.

### Local Docker (staging-like test)

`docker-compose.yml` hard-codes `MAILER_DSN` for the `back` and `worker`
services — that value wins over `back/.env`. To use Brevo, edit **both**
services:

```yaml
# docker-compose.yml — back service AND worker service
environment:
  MAILER_DSN: "brevo+api://KEY@default"
```

Then `docker compose up -d back worker` (or `make dev`).

To keep the secret out of the compose file, make it interpolate from the
project-root `.env` instead:

```yaml
environment:
  MAILER_DSN: ${MAILER_DSN:-smtp://mailer:1025}
```

```dotenv
# .env  (project root, git-ignored)
MAILER_DSN=brevo+api://KEY@default
```

### Bare-metal / non-Docker backend

Put it in `back/.env.local` (git-ignored, overrides `back/.env`):

```dotenv
MAILER_DSN=brevo+api://KEY@default
```

---

## 5. Verify the sender address

Brevo only delivers mail **from a sender it has verified**. Out of the box the
app sends from placeholder `.local` addresses, which Brevo will reject:

| File | Sender |
|---|---|
| `back/src/Auth/Infrastructure/Listener/AuthEmailListener.php` | `Ziggytheque <noreply@ziggytheque.local>` (the `FROM` constant) |
| `back/src/Notification/Application/Email/SendFollowingNotificationHandler.php` | `ziggytheque@noreply.local` |

Do both of the following:

1. **In Brevo** — open **Senders, Domains & Dedicated IPs** and either verify a
   single sender email, or (recommended) authenticate your domain by adding the
   DKIM/SPF DNS records Brevo gives you. Domain authentication greatly improves
   deliverability.
2. **In the code** — change the two sender addresses above to the verified
   sender (e.g. `noreply@your-domain.com`).

---

## 6. Test it

After deploying with the Brevo DSN, trigger an email and confirm it arrives:

```bash
# Registering a new account sends a verification email
curl -X POST https://<your-host>/api/auth/register \
  -H 'Content-Type: application/json' \
  -d '{"email":"you@example.com","password":"Password1!","displayName":"Test"}'
```

Check the recipient inbox, and the **Brevo dashboard → Transactional → Logs**,
which shows every accepted/blocked message.

---

## 7. Troubleshooting

| Symptom | Cause / fix |
|---|---|
| `Connection could not be established` / `Connection timed out` | The platform blocks the SMTP port. Switch to the **HTTP API** transport (`brevo+api://KEY@default`), or change the SMTP port from `587` to `2525`. |
| `535 Authentication failed` | Wrong credentials. For SMTP: regenerate the SMTP key and check the `@` in the login is `%40`. For the API: check the **API v3** key (the SMTP key will not work for `brevo+api://`). |
| Email accepted by Brevo but never delivered | Sender not verified — see section 5. Check the Brevo logs. |
| Emails work locally but not in Docker | `MAILER_DSN` is still the Mailpit value in `docker-compose.yml` — see section 4. |
| Auth emails silently missing | `AuthEmailListener` catches and logs delivery errors so a mail failure never breaks signup. The actual send runs in the `worker`; check its logs, and inspect failed sends with `php bin/console messenger:failed:show`. |

---

## 8. The Brevo API transport — details

The HTTP API transport (`brevo+api://`, recommended above) is provided by the
official `symfony/brevo-mailer` bridge, which is already declared in
`back/composer.json`. Besides sidestepping SMTP-port blocking, it also unlocks
Brevo's webhooks and richer delivery tracking should you need them later.

No code or configuration change is required to switch transports — only the
`MAILER_DSN` environment variable.
