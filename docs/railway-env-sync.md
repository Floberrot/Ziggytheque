# Synchronisation des variables d'env Railway au déploiement

À chaque déploiement (**prod** ou **staging**), un job `sync-env` détecte les
**nouvelles** variables d'environnement attendues par l'app et crée sur
l'environnement Railway ciblé un **placeholder** pour chacune. Il ne reste qu'à
**remplacer la valeur** dans le dashboard Railway.

- Script : [`scripts/railway-sync-env-keys.sh`](../scripts/railway-sync-env-keys.sh)
- Wiring CI : job `sync-env` dans
  [`deploy-staging.yml`](../.github/workflows/deploy-staging.yml) et
  [`deploy-production.yml`](../.github/workflows/deploy-production.yml), exécuté
  **après** les gates QA (et l'approbation en prod) et **avant** `railway up`.

## Valeur des placeholders : `CHANGEME` (pas vide)

Le CLI Railway **refuse une valeur vide** (`railway variables --set "KEY="` →
`Invalid variable format: KEY=`). Les placeholders sont donc créés avec une
**valeur sentinelle visible**, `CHANGEME` par défaut (modifiable via la variable
d'env `PLACEHOLDER_VALUE`). Tu la remplaces par la vraie valeur dans Railway —
tant qu'elle vaut `CHANGEME`, la fonctionnalité concernée ne marche pas (comme
avec une valeur vide), c'est volontairement visible.

## Comment ça marche

1. **Source de vérité = les fichiers `.env` du repo.** Les clés attendues sont
   extraites de `back/.env` (contrat Symfony, partagé par les services
   `ziggytheque-back` et `ziggytheque-worker`) et de `front/.env` (service
   `ziggytheque-front`).
2. Le script liste les variables **déjà présentes** sur l'environnement Railway
   ciblé (`railway variables --json`).
3. Pour chaque clé attendue **absente** de Railway (et non ignorée), il crée la
   variable avec la valeur sentinelle et `--skip-deploys` (la création ne
   déclenche donc pas de déploiement supplémentaire ; c'est le `railway up`
   suivant qui la prend en compte).

Le script est **non destructif** : il ne fait que **créer** des clés manquantes.
Il ne modifie ni ne supprime jamais une variable existante — une valeur déjà
saisie, ou une référence Railway (`${{Postgres.DATABASE_URL}}`), n'est jamais
écrasée. Il **n'interrompt jamais un déploiement** : un appel Railway en échec
est signalé puis ignoré, et le script **sort toujours en succès** (`exit 0`).
Les clés créées sont signalées en `::notice::` et dans le *job summary*.

## Clés ignorées (jamais créées sur Railway)

Définies en tête du script via `IGNORE_KEYS` (liste exacte) et `IGNORE_PATTERNS`
(globs) :

| Clé(s) / motif | Raison |
|---|---|
| `APP_ENV`, `APP_DEBUG` | figées dans l'image (`ENV APP_ENV=prod`) |
| `JWT_SECRET_KEY`, `JWT_PUBLIC_KEY` | chemins de fichiers ; la paire de clés est générée au boot (`docker-entrypoint.sh`) |
| `JWT_TTL`, `MESSENGER_TRANSPORT_DSN` | la valeur par défaut commitée dans `back/.env` **est** la valeur de prod |
| `DATABASE_URL` | fournie par Railway en référence du service Postgres |
| `*_BASE_URL` (motif) | URLs de base d'API publiques (`MANGADEX_BASE_URL`, `OPEN_LIBRARY_COVERS_BASE_URL`, `BNF_BASE_URL`, …) dont le défaut commité **est** la valeur de prod |
| `VITE_API_BASE_URL`, `VITE_EXTERNAL_API_URL` | build arg volontairement vide / override optionnel du front |

> Le motif `*_BASE_URL` ne matche **pas** `MERCURE_PUBLIC_URL`/`MERCURE_URL`
> (spécifiques à l'env → gérés), `DATABASE_URL` (déjà explicite) ni
> `DISCORD_WEBHOOK_URL` (secret → géré).

> ⚠️ **Règle de maintenance** : quand tu ajoutes une variable à `back/.env` dont
> la valeur par défaut commitée **est aussi** la valeur de production
> (non-secrète, non spécifique à l'environnement) et qui ne matche pas déjà un
> motif, **ajoute sa clé à `IGNORE_KEYS`**. Sinon le script créerait un
> placeholder `CHANGEME` sur Railway qui **masquerait** la valeur par défaut au
> prochain déploiement.
>
> À l'inverse, une nouvelle variable **secrète ou spécifique à l'environnement**
> (ex. `change_me`, clé d'API, URL publique) ne doit **pas** être ignorée : le
> déploiement crée son placeholder automatiquement.

## Exécuter / prévisualiser en local

```bash
# Aperçu (aucun appel Railway) : montre ce qui serait créé sur un env vide.
DRY_RUN=1 ./scripts/railway-sync-env-keys.sh

# Tests unitaires des helpers (extraction de clés, ignore-list + motifs, diff).
./scripts/railway-sync-env-keys.test.sh

# Run réel (ex. en local, CLI authentifiée) contre un environnement précis.
RAILWAY_TOKEN=... ./scripts/railway-sync-env-keys.sh <environment-id>

# Changer la valeur sentinelle.
PLACEHOLDER_VALUE=__FILL_ME__ ./scripts/railway-sync-env-keys.sh <environment-id>
```
