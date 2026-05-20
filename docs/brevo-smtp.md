# Brevo as SMTP provider

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

Two processes send mail, so **both** must receive the Brevo DSN:

| Process | Container | Sends |
|---|---|---|
| `back`   | FrankenPHP | verification / password-reset / account-approved emails (synchronous, during the HTTP request) |
| `worker` | Messenger consumer | "new chapters" follow notifications (from the crawl) |

Default (local): `MAILER_DSN=smtp://mailer:1025` → Mailpit, UI at <http://localhost:8025>.

---

## 2. Get your Brevo SMTP credentials

1. Create a free account at <https://www.brevo.com> (the free plan allows 300
   emails/day).
2. In the dashboard open **SMTP & API → SMTP**.
3. Note the connection details shown there:
   - **Server:** `smtp-relay.brevo.com`
   - **Port:** `587` (STARTTLS — recommended)
   - **Login:** your Brevo login — an email address.
4. Click **Generate a new SMTP key** and copy it. This key is the SMTP
   *password* — it is shown only once.

---

## 3. Build the `MAILER_DSN`

Symfony's generic SMTP transport needs no extra package:

```
smtp://<LOGIN>:<SMTP_KEY>@smtp-relay.brevo.com:587
```

> **URL-encode special characters.** The login is an email address, so its `@`
> must be written as `%40`. Encode any reserved character (`@ : / # ? %`) in the
> login or key as well.

Example — login `mailer@ziggytheque.com`, key `xsmtpsib-abc123`:

```
MAILER_DSN=smtp://mailer%40ziggytheque.com:xsmtpsib-abc123@smtp-relay.brevo.com:587
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
  MAILER_DSN: "smtp://mailer%40ziggytheque.com:xsmtpsib-abc123@smtp-relay.brevo.com:587"
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
MAILER_DSN=smtp://mailer%40ziggytheque.com:xsmtpsib-abc123@smtp-relay.brevo.com:587
```

### Bare-metal / non-Docker backend

Put it in `back/.env.local` (git-ignored, overrides `back/.env`):

```dotenv
MAILER_DSN=smtp://mailer%40ziggytheque.com:xsmtpsib-abc123@smtp-relay.brevo.com:587
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
| `Connection could not be established` | Wrong host/port, or the platform blocks port 587 — try `2525`. |
| `535 Authentication failed` | Wrong login or SMTP key; regenerate the key. Check the `@` in the login is `%40` in the DSN. |
| Email accepted by Brevo but never delivered | Sender not verified — see section 5. Check the Brevo logs. |
| Emails work locally but not in Docker | `MAILER_DSN` is still the Mailpit value in `docker-compose.yml` — see section 4. |
| Auth emails silently missing | `AuthEmailListener` catches and logs delivery errors so a mail failure never breaks signup — check the `back` container logs. |

---

## 8. Optional — the Brevo API transport

For webhooks and richer delivery tracking you can use Brevo's HTTP API instead
of SMTP via the official Symfony bridge:

```bash
docker compose exec back composer require symfony/brevo-mailer
```

```dotenv
# KEY = a Brevo API v3 key from SMTP & API → API Keys
MAILER_DSN=brevo+api://KEY@default
```

The generic `smtp://` transport in section 3 is sufficient for this project;
the bridge is only worth it if you later need delivery webhooks.
