# Synchronisation des variables d'env Railway au déploiement

À chaque déploiement (**prod** ou **staging**), un job `sync-env` détecte les
**nouvelles** variables d'environnement attendues par l'app et les crée
**vides** sur l'environnement Railway ciblé. Il ne reste qu'à **saisir leur
valeur** dans le dashboard Railway.

- Script : [`scripts/railway-sync-env-keys.sh`](../scripts/railway-sync-env-keys.sh)
- Wiring CI : job `sync-env` dans
  [`deploy-staging.yml`](../.github/workflows/deploy-staging.yml) et
  [`deploy-production.yml`](../.github/workflows/deploy-production.yml), exécuté
  **après** les gates QA (et l'approbation en prod) et **avant** `railway up`.

## Comment ça marche

1. **Source de vérité = les fichiers `.env` du repo.** Les clés attendues sont
   extraites de `back/.env` (contrat Symfony, partagé par les services
   `ziggytheque-back` et `ziggytheque-worker`) et de `front/.env` (service
   `ziggytheque-front`).
2. Le script liste les variables **déjà présentes** sur l'environnement Railway
   ciblé (`railway variables --json`).
3. Pour chaque clé attendue **absente** de Railway (et non ignorée), il crée la
   variable **vide** avec `--skip-deploys` (la création ne déclenche donc pas de
   déploiement supplémentaire ; c'est le `railway up` suivant qui la prend en
   compte).

Le script est **non destructif** : il ne fait que **créer** des clés manquantes.
Il ne modifie ni ne supprime jamais une variable existante — une valeur déjà
saisie, ou une référence Railway (`${{Postgres.DATABASE_URL}}`), n'est jamais
écrasée. Il **sort toujours en succès** : il ne peut pas bloquer un déploiement.
Les clés créées sont signalées en `::notice::` et dans le *job summary*.

## Clés ignorées (jamais créées sur Railway)

Définies dans `IGNORE_KEYS` en tête du script :

| Clé(s) | Raison |
|---|---|
| `APP_ENV`, `APP_DEBUG` | figées dans l'image (`ENV APP_ENV=prod`) |
| `JWT_SECRET_KEY`, `JWT_PUBLIC_KEY` | chemins de fichiers ; la paire de clés est générée au boot (`docker-entrypoint.sh`) |
| `JWT_TTL`, `MESSENGER_TRANSPORT_DSN`, `MANGADEX_BASE_URL`, `OPEN_LIBRARY_COVERS_BASE_URL` | la valeur par défaut commitée dans `back/.env` **est** la valeur de prod |
| `DATABASE_URL` | fournie par Railway en référence du service Postgres |
| `VITE_API_BASE_URL`, `VITE_EXTERNAL_API_URL` | build arg volontairement vide / override optionnel du front |

> ⚠️ **Règle de maintenance** : quand tu ajoutes une variable à `back/.env` dont
> la valeur par défaut commitée **est aussi** la valeur de production
> (non-secrète, non spécifique à l'environnement), **ajoute sa clé à
> `IGNORE_KEYS`**. Sinon le script créerait une variable **vide** sur Railway qui
> masquerait la valeur par défaut au prochain déploiement.
>
> À l'inverse, une nouvelle variable **secrète ou spécifique à l'environnement**
> (ex. `change_me`, clé d'API, URL publique) ne doit **pas** être ignorée : le
> déploiement créera son placeholder vide automatiquement.

## Exécuter / prévisualiser en local

```bash
# Aperçu (aucun appel Railway) : montre ce qui serait créé sur un env vide.
DRY_RUN=1 ./scripts/railway-sync-env-keys.sh

# Tests unitaires des helpers (extraction de clés, ignore-list, diff).
./scripts/railway-sync-env-keys.test.sh

# Run réel (ex. en local, CLI authentifiée) contre un environnement précis.
RAILWAY_TOKEN=... ./scripts/railway-sync-env-keys.sh <environment-id>
```
