# Environnement Staging (Railway)

Staging est un **réplica isolé de la production** sur le même projet Railway :
les mêmes 4 services (backend FrankenPHP, worker Messenger, frontend nginx,
PostgreSQL), déployés en continu à chaque merge sur `main`, avec une copie des
**données de prod anonymisées** (RGPD).

| | Production | Staging |
|---|---|---|
| Déclencheur de déploiement | Tag + dispatch manuel (`deploy-production.yml`) | Merge sur `main` (`deploy-staging.yml`) |
| Données | Réelles | Copie de prod **anonymisée** |
| Base de données | Postgres prod | Postgres staging (séparée) |
| Domaine | `www.ziggytheque.fr` | domaine staging dédié |

> Railway « duplique » un environnement en copiant **services + variables +
> config**. La base reçoit une **DB fraîche vide** (les données ne sont pas
> copiées) et les variables **sealed** ne sont pas copiées — il faut les
> re-saisir. La copie de données réelles se fait séparément (étape 4).

---

## 1. Créer l'environnement staging (dashboard, une fois)

1. Dashboard Railway → projet Ziggytheque → menu déroulant d'environnement (en
   haut) → **New Environment → Duplicate** → source **production**, nom
   **`staging`**.
2. Railway « stage » les 4 services + leurs variables. Clique **Deploy** pour
   matérialiser l'environnement.
3. Récupère l'**id de l'environnement staging** : il est dans l'URL du
   dashboard (`…?environmentId=<ID>`) ou via `railway environment` après
   `railway link`.

### Alternative CLI (en local, CLI déjà authentifiée)

```bash
railway link --project <Ziggytheque>
railway environment new staging --duplicate production   # copie services+vars+config
railway environment                                       # récupère l'id de staging
```

---

## 2. Régler les variables propres au staging

La duplication copie les variables non-sealed. À ajuster / re-saisir **par
service** dans le dashboard (onglet *Variables* de chaque service, env staging) :

> 💡 Le job `sync-env` du déploiement crée automatiquement, **vides**, les clés
> attendues qui manquent sur l'environnement ciblé (dont les **sealed** non
> copiées) — il ne reste qu'à **saisir leur valeur**. Voir
> [`docs/railway-env-sync.md`](./railway-env-sync.md). Le tableau ci-dessous
> reste la référence des valeurs à fournir.

| Service | Variable | Pourquoi |
|---|---|---|
| backend | `CORS_ALLOW_ORIGIN` | origine du domaine staging, ex. `^https://staging\.ziggytheque\.fr$` |
| backend | `MERCURE_PUBLIC_URL` | URL publique du hub Mercure côté staging |
| backend | `MERCURE_URL` | hub interne (si *reference variable*, se ré-résout seul) |
| backend | `GATE_PASSWORD` | **sealed** → re-saisir |
| backend | `GOOGLE_BOOKS_API_KEY` | **sealed** → re-saisir |
| backend | `MAILER_DSN` (Resend) | **sealed** → re-saisir (ou un DSN de test) |
| backend | `MERCURE_PUBLISHER_JWT_KEY` / `MERCURE_SUBSCRIBER_JWT_KEY` | **sealed** → re-saisir |
| backend | clés/passphrase JWT (`JWT_*`) | **sealed** → re-saisir ou régénérer |
| backend | `MONITOR_USER` / `MONITOR_PASSWORD` | **sealed** → re-saisir |
| frontend | `BACKEND_URL` | URL interne du backend staging (si `${{backend.RAILWAY_PRIVATE_DOMAIN}}`, auto) |
| Postgres | `DATABASE_URL` | nouvelle DB auto-provisionnée (reference → auto) |

**Domaines** : génère un domaine Railway pour le frontend staging (et le backend
si besoin) ou ajoute un sous-domaine `staging.ziggytheque.fr`, puis aligne
`CORS_ALLOW_ORIGIN` et `MERCURE_*` dessus.

**Migrations** : staging utilise la même image Docker que prod — les migrations
s'appliquent donc de la même façon qu'en prod (au boot du backend). Au besoin,
en manuel :

```bash
railway run --environment staging --service ziggytheque-back -- \
  php bin/console doctrine:migrations:migrate --no-interaction
```

---

## 3. Auto-déploiement au merge sur `main`

Le workflow [`.github/workflows/deploy-staging.yml`](../.github/workflows/deploy-staging.yml)
déploie staging **à chaque merge sur `main`** : il rejoue **les mêmes gates QA
que la prod** (PHP_CodeSniffer, Deptrac, PHPStan, migrations, PHPUnit, frontend
vue-tsc + ESLint, build Docker), puis `railway up` des 3 services vers
l'environnement staging (worker → backend, frontend en parallèle). Seules
différences avec la prod : le déclencheur est un merge sur `main` (pas un tag)
et il n'y a pas de gate d'approbation manuelle (staging déploie en continu). La
prod reste tag-gated et manuelle.

### Déployer une branche sur staging *avant* de merger sur `main`

Pour tester une branche sur staging avant le merge :

1. GitHub → onglet **Actions** → workflow **« Deploy — Staging »** → **Run workflow**.
2. Dans **« Use workflow from »**, choisis ta branche *(et/ou renseigne le champ
   `ref`)*.
3. Lance : le smoke gate s'exécute sur cette branche, puis les 3 services sont
   déployés sur staging (même environnement → le déploiement le plus récent
   écrase le précédent).

> Le workflow n'est dispatchable que s'il existe sur la branche par défaut
> (`main`) : cette PR doit donc être mergée une première fois pour que l'option
> « Run workflow » apparaisse.

**Secrets GitHub à ajouter** (Settings → Secrets and variables → Actions) :

| Secret | Valeur |
|---|---|
| `RAILWAY_STAGING_TOKEN` | token Railway **scopé staging** (project token créé pour l'env staging, ou token compte/team). **Jamais** le project token de prod. |
| `RAILWAY_STAGING_ENVIRONMENT_ID` | id de l'environnement staging (étape 1.3) |

`RAILWAY_PROJECT_ID` et `RAILWAY_PRODUCTION_ENVIRONMENT_ID` existent déjà (ce
dernier sert au garde-fou ci-dessous).

> 🛡️ **Garde-fou anti-prod** : un job `guard` en tête du workflow **refuse de
> déployer** si `RAILWAY_STAGING_TOKEN` ou `RAILWAY_STAGING_ENVIRONMENT_ID` est
> absent, ou si l'id staging = id prod. Tant que ces secrets ne sont pas
> configurés, chaque merge sur `main` fait **échouer** le workflow (croix rouge)
> sans rien déployer — il ne peut donc **plus** retomber sur la production.

> Pour ajuster le déclencheur (ex. déployer depuis une branche `staging` plutôt
> que `main`), modifie le bloc `on:` du workflow.

---

## 4. Copier les données de prod (anonymisées RGPD)

⚠️ À faire depuis une **machine de confiance**. Ne **jamais** committer un dump.

```bash
# 1) Récupère les URLs de connexion (onglet "Connect" du service Postgres,
#    ou `railway variables`), pour prod ET staging.
export PROD_DATABASE_URL="postgresql://…@…prod…/railway"
export STAGING_DATABASE_URL="postgresql://…@…staging…/railway"

# 2) Dump de prod (lecture seule)
pg_dump "$PROD_DATABASE_URL" --no-owner --no-privileges -Fc -f /tmp/prod.dump

# 3) Restaure dans la base staging (vide)
pg_restore --clean --if-exists --no-owner --no-privileges \
  -d "$STAGING_DATABASE_URL" /tmp/prod.dump

# 4) Anonymise IMMÉDIATEMENT (voir back/scripts/anonymize-staging.sql)
psql "$STAGING_DATABASE_URL" -f back/scripts/anonymize-staging.sql

# 5) Supprime le dump local
shred -u /tmp/prod.dump 2>/dev/null || rm -f /tmp/prod.dump
```

Le script [`back/scripts/anonymize-staging.sql`](../back/scripts/anonymize-staging.sql)
scrub : `users.email`, `display_name`, `notification_email`, `discord_webhook_url`,
rend les mots de passe inutilisables, et purge `auth_tokens` + `activity_logs`.

**Connexion à staging après anonymisation** (les mots de passe sont neutralisés) :
crée un admin de staging avec l'outil existant —

```bash
railway run --environment staging --service ziggytheque-back -- \
  php bin/console app:bootstrap-admin   # suis les options de la commande
```

> **Note RGPD** : avec cette procédure, des données réelles transitent
> brièvement par la base staging (entre la restauration et l'anonymisation).
> Pour une conformité stricte, anonymise le dump **avant** restauration (ou
> dumpe depuis un replica), et n'expose jamais staging publiquement avec des
> données réelles.

---

## Récapitulatif des actions manuelles (hors repo)

- [ ] Dupliquer l'env `production` → `staging` (dashboard ou CLI).
- [ ] Créer un **token Railway scopé staging** → secret GitHub `RAILWAY_STAGING_TOKEN`.
- [ ] Ajouter le secret GitHub `RAILWAY_STAGING_ENVIRONMENT_ID` (id de l'env staging).
- [ ] Re-saisir les variables sealed + régler CORS/Mercure/domaine staging.
- [ ] (optionnel) Copier + anonymiser les données de prod.
- [ ] Vérifier un premier déploiement (`workflow_dispatch`, puis merge sur `main`).
