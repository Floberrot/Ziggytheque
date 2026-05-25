# Plan — Refonte éditions + recherche multi-pays

## TL;DR

> [!NOTE]
> Aujourd'hui, un manga = une seule édition implicite. Il est impossible d'avoir simultanément Berserk *Prestige* et Berserk *Classique* (tous deux Glénat) dans sa collection : ils écrasent le même enregistrement. Toute la recherche externe est de plus verrouillée sur la France (Jikan ignore la langue, Google Books force `langRestrict=fr`, MangaDex n'est utilisé que pour récupérer les jaquettes), donc on ne peut pas découvrir une édition japonaise, italienne ou anglaise sans bricoler des URLs.
>
> On introduit une nouvelle entité **Edition** entre `Manga` (la *série*) et `Volume` (le *tome*). Une édition porte les attributs marketing (nom, éditeur, format, pays, langue, nombre de tomes, identifiants externes par source) qui aujourd'hui sont dispersés ou inexistants. La collection et la wishlist pointent désormais sur une édition, ce qui permet d'avoir plusieurs éditions d'une même série côte à côte. La recherche externe accepte un paramètre `country` (FR par défaut) qui est propagé à chaque client (Jikan, Google Books, MangaDex) et un endpoint dédié *importe une édition complète* — tous ses tomes, leurs jaquettes et ISBN — en une seule action, ce qui élimine les copiés-collés d'URL.
>
> Côté UX, la page d'ajout passe d'un « formulaire à compléter » à un assistant en trois écrans : (1) choix du pays + recherche de série, (2) liste des éditions disponibles pour cette série dans ce pays, (3) prévisualisation + import en un clic de l'édition entière. La page collection est élargie pour afficher l'édition (éditeur + format + drapeau pays) et permettre de filtrer par pays / éditeur, sans changer la structure d'affichage existante.

---

## Implementation

### Contexte projet rappelé

- Backend Symfony 8 / PHP 8.4, hexagonal + CQRS via Messenger.
- Doctrine : `make migration` génère les noms FK/index — **interdiction de les écrire à la main**, sinon `doctrine:schema:validate` casse.
- Tests : règle non négociable du projet — tout endpoint ajouté/modifié reçoit un test fonctionnel, tout VO/entité reçoit un test unitaire.
- Front Vue 3 + TS (`<script setup>`), DaisyUI, Pinia, vue-i18n (fr/en).

### État actuel (résumé de l'audit)

```
                            CollectionEntry
                            unique(owner_id, manga_id)
                                 │
                                 │ manga_id (FK)
                                 ▼
   Manga(id, title, edition?, language, author, summary,
         coverUrl, genre, externalId, totalVolumes)
                                 │
                                 │ 1..*
                                 ▼
                     Volume(id, number, coverUrl, price,
                            releaseDate, isbn, spineUrl)
                                 ▲
                                 │ volume_id
                            VolumeEntry
                            unique(collection_entry_id, volume_id)
```

| Client externe | Interface | Pays/langue géré ? | Rôle aujourd'hui |
|---|---|---|---|
| `JikanMangaApiClient` (alias prod) | `ExternalApiClientInterface` | Non — `'fr'` est codé en dur en sortie | Recherche série + tomes |
| `GoogleBooksMangaApiClient` | `ExternalApiClientInterface` + `MangaCoverProviderInterface` | Non — `langRestrict=fr` codé en dur | Recherche + couvertures |
| `MangaDexMangaApiClient` | `MangaCoverProviderInterface` uniquement | Oui (`availableTranslatedLanguage[]`) — mais non utilisé en search | Couvertures par tome |
| `OpenLibraryCoversApiClient` | `MangaCoverProviderInterface` | Non | Couvertures par ISBN |
| `NullMangaApiClient` | `ExternalApiClientInterface` | n/a | Stub `when@test` |

Endpoints actuels concernés :

- `GET /api/manga/external?q=&type=&page=` — recherche série
- `GET /api/manga/volume-search?q=&page=&volumeNumber=&edition=&provider=` — déjà multi-provider pour couvertures de tomes
- `POST /api/manga` — import (créé Manga + volumes via `totalVolumes`)
- `POST /api/manga/{id}/volumes`, `PATCH /api/manga/{id}/volumes/{volumeId}`

Frontend : `AddMangaPage.vue` en 3 étapes (recherche → formulaire → destination), `useExternalSearch.ts` n'envoie que `q,type,page`, `BaseEditionSelector.vue` propose une liste hard-codée de 18 éditeurs français. Aucun sélecteur de pays.

### Cible

```
                         CollectionEntry
                         unique(owner_id, edition_id)
                              │
                              │ edition_id (FK, NEW)
                              ▼
   Manga(id, title, originalTitle?, author, summary,
         genre, originalLanguage?)
                              │
                              │ 1..*
                              ▼
   Edition(id, manga_id, name, publisher, format, country,
           language, totalVolumes?, coverUrl?, externalIds_json)
           unique(manga_id, name, publisher, country)
                              │
                              │ 1..*
                              ▼
   Volume(id, edition_id, number, coverUrl, price,
          releaseDate, isbn, spineUrl, externalIds_json)
          unique(edition_id, number)
                              ▲
                              │ volume_id
                         VolumeEntry  (inchangée fonctionnellement)
                         unique(collection_entry_id, volume_id)

   WishlistItem(id, owner_id, edition_id) — bascule sur edition_id
```

Recap des dimensions ajoutées :

| Champ ajouté | Porté par | Exemple | Source |
|---|---|---|---|
| `name` | `Edition` | « Prestige », « Maximum », « Édition originale » | Saisie ou détecté via `volumeInfo.publisher`/`subtitle` |
| `publisher` | `Edition` | « Glénat », « Pika », « Viz » | Détecté via Google Books / MangaDex |
| `format` | `Edition` | « Tankobon », « A4 », « Big », « Couleur » | Saisie ou via `format` MangaDex |
| `country` | `Edition` | `FR` (ISO 3166-1 alpha-2) | Choix utilisateur, FR par défaut |
| `language` | `Edition` | `fr` (ISO 639-1) | Dérivé du `country` mais stocké explicitement |
| `externalIds` (JSON) | `Edition`, `Volume` | `{"jikan":"...", "mangadex":"...", "googlebooks":"..."}` | Agrégé à l'import |

### Choix techniques majeurs

1. **Entité Edition séparée**, *pas* champs élargis sur Manga.
   - Trade-off comparé à l'alternative « N rows `Manga` avec même `title` mais éditions différentes » :
     - **Pour Edition séparée (retenu)** : sémantique DDD claire (Berserk *série* ≠ Berserk *Prestige Glénat FR*), agrégation native « toutes mes éditions de Berserk », évite la duplication des champs descriptifs (`author`, `summary`, `genre`) sur chaque édition.
     - **Contre** : refactor non trivial de `CollectionEntry`, `WishlistItem` et stats — la FK passe de `manga_id` à `edition_id`. Migration de données obligatoire. Coût encaissable car l'app est jeune.

2. **`country` est l'axe utilisateur, `language` est stocké en doublon pour le moteur de recherche externe.**
   - L'utilisateur final raisonne en « éditions françaises / japonaises / anglaises ». `country` est dans l'URL (`?country=FR`).
   - Les clients HTTP attendent une langue ISO 639 — on dérive `language` du `country` via un mapping centralisé `CountryLanguage` (`FR→fr`, `JP→ja`, `US/GB→en`, `IT→it`, `DE→de`, `ES→es`).
   - Le couple (`country`, `language`) est stocké sur `Edition` pour permettre des cas hors-mapping (édition canadienne francophone, suisse alémanique, etc.).

3. **`externalIds` JSON par source au lieu d'un seul `externalId` string.**
   - Aujourd'hui `Manga.externalId` est un scalaire — on perd l'info dès qu'on enrichit depuis une deuxième source. Avec `{"jikan": "..."}` on peut compléter au fil des passes (ex. ré-enrichir une jaquette MangaDex sans casser l'identifiant Jikan d'origine).
   - Stocké en `JSONB` (Postgres) sur `Edition` et `Volume`.

4. **Endpoint dédié « import édition complète » plutôt qu'enchaînement client.**
   - `POST /api/editions/import` reçoit `{ provider, externalId, country }` et orchestre côté serveur : appel API externe → création de Manga (ou lookup si existant via `originalTitle`) → création d'Edition → création des N Volumes avec leurs jaquettes individuelles.
   - L'utilisateur n'a plus à cliquer 30 fois pour 30 tomes.

5. **`ExternalApiClientInterface` étendue, pas remplacée.**
   - On ajoute `?string $country = 'FR'` aux signatures existantes — chaque client passe le param ou l'ignore selon sa capacité (Jikan l'ignorera dans la requête mais l'utilisera pour mapper le résultat ; Google Books le traduira en `langRestrict` ; MangaDex en `availableTranslatedLanguage[]`).
   - Nouvelle méthode `listEditions(string $title, string $country): EditionDiscoveryDto[]` côté `ExternalApiClientInterface` pour ramener les *variantes d'éditions* (Prestige, Classique, Maximum…) d'une même série dans un pays donné.

6. **Découverte d'éditions = stratégie composite.**
   - Jikan ne distingue pas les éditions FR ; il sert à identifier la **série canonique** (un manga = un ID MAL).
   - Google Books + MangaDex ramènent ensuite les *variantes par publisher/format*.
   - Un service Domain `EditionDiscoveryService` combine les sources et déduplique sur `(publisher, name, format)`.

### Scope

**Inclus dans ce plan :**
- Nouvelle entité `Edition`, migration de données, refacto `CollectionEntry`/`WishlistItem`/`Stats` pour pointer sur `edition_id`.
- Multi-pays sur `ExternalApiClientInterface` + ajustements des 3 clients (Jikan, Google Books, MangaDex).
- Endpoints `/api/manga/external` (param `country`), `POST /api/editions/import`, `GET /api/editions/:id`, `GET /api/manga/:id/editions`.
- Refonte de `AddMangaPage.vue` : sélecteur de pays, écran « éditions disponibles », import en un clic.
- Affichage de l'édition (éditeur + format + pays) sur les vues `/collection`, `/collection/:id` et `/wishlist` + filtres pays/éditeur.
- Tests fonctionnels pour chaque endpoint, tests unitaires pour `Edition`, `CountryLanguage` mapper, `EditionDiscoveryService`.

**Hors scope (à traiter dans un plan ultérieur si besoin) :**
- Vue de regroupement « toutes les éditions de la série X » (équivalent d'une fiche série).
- Synchronisation périodique automatique des nouvelles parutions par édition.
- Détection automatique d'édition à partir d'un ISBN scanné.
- i18n des labels Country (drapeaux/noms localisés dans `fr.json`/`en.json`) → on prévoit la clé, on traduit FR + EN sans plus pour l'instant.

### Stratégie de migration de données

La migration Doctrine générée par `make migration` doit aussi inclure le **backfill SQL** des données existantes (en `addSql()` dans le `up()`) :

1. Créer table `editions` + colonnes JSONB `external_ids` sur `editions` et `volumes`.
2. Pour chaque `Manga` existant, créer une `Edition` par défaut :
   - `name = manga.edition` (ou `'Standard'` si NULL),
   - `country = 'FR'`, `language = manga.language ?? 'fr'`,
   - `total_volumes = (SELECT COUNT(*) FROM volumes WHERE manga_id = manga.id)`,
   - `external_ids = '{}'::jsonb` ou `{"<source>": manga.external_id}` si on peut deviner la source.
3. Ajouter colonne `edition_id` sur `volumes`, la peupler via JOIN sur `manga_id`, puis :
   - DROP `manga_id` sur `volumes` (FK + colonne),
   - Recréer la contrainte unique sur `(edition_id, number)`.
4. Idem pour `collection_entries` et `wishlist_items` : ajouter `edition_id`, peupler via la `Edition` par défaut du `Manga`, recréer les uniques sur `(owner_id, edition_id)`, DROP `manga_id`.
5. DROP `Manga.edition`, `Manga.language`, `Manga.external_id`, `Manga.total_volumes` (déplacés sur `Edition`).

Le `down()` est implémenté mais documenté comme *destructeur si plusieurs éditions par série existent* — il choisit la plus ancienne et abandonne les autres (acceptable pour rollback dev).

**Important** : on génère la migration via `make migration` puis on ajoute manuellement les `addSql()` de backfill **dans le même fichier** ; on ne crée pas de seconde migration. Avant commit : `make migrate` puis `bin/console doctrine:schema:validate` doit être vert.

### Risques et mitigations

| Risque | Mitigation |
|---|---|
| `schema:validate` casse à cause d'un mismatch enum / FK / default | Toujours passer par `make migration` ; ne jamais écrire un nom de FK à la main. Tester `validate` avant commit. |
| Migration de données échoue en prod si un Manga a `language` NULL | Le SQL utilise `COALESCE(manga.language, 'fr')`. Test couvert en functional avec fixtures représentatives. |
| Régression collection : un utilisateur perd l'accès à son Berserk | La migration garantit qu'il y a *toujours* exactement une `Edition` par `Manga` existant, donc une `CollectionEntry` ré-câblée sur cette Edition. Test fonctionnel sur le scénario de bascule. |
| Jikan ne supporte pas `country` mais utilisé en alias prod | On le garde comme source de **série canonique** (auteur, genre, synopsis) ; on délègue la liste d'éditions à Google Books/MangaDex via `EditionDiscoveryService`. Documenté dans le code et dans `CLAUDE.md`. |
| L'import d'édition complète peut être lent (N appels HTTP pour N tomes) | L'endpoint `POST /api/editions/import` est synchrone côté HTTP mais dispatch en interne un `ImportEditionCommand` sur le bus → le handler peut paralléliser les `findByContext` via `HttpClient::stream()` ; max 30 tomes par défaut, configurable. |

### Code retiré

- **Champs Doctrine** : `Manga.edition`, `Manga.language`, `Manga.externalId`, `Manga.totalVolumes`, `Volume.manga_id` (FK), `CollectionEntry.manga_id` (FK), `WishlistItem.manga_id` (FK).
- **Fichiers à supprimer** : aucun fichier complet (toutes les classes restent, juste recâblées). `BaseEditionSelector.vue` n'est pas supprimé mais sa liste hard-codée FR est remplacée par un appel API `GET /api/publishers?country=`.
- **Helpers de test** : le helper `MangaControllerTest::importManga()` doit être complété pour ne plus passer `language` à la racine du payload (déplacé dans `edition`).
- **Endpoints inchangés mais payload muté** : `POST /api/manga` accepte désormais un sous-objet `edition: { name, publisher, format, country, language, totalVolumes }`. Le test fonctionnel existant est mis à jour, pas dupliqué.

---

### Tasks

- Task 1: Domain — entités `Edition`, `Manga`, `Volume`, mapping `CountryLanguage`.
- Task 2: Migration Doctrine + backfill des données existantes.
- Task 3: Refacto `CollectionEntry`, `WishlistItem`, `Stats` pour pointer sur `Edition`.
- Task 4: Extension `ExternalApiClientInterface` + ajustement Jikan / Google Books / MangaDex.
- Task 5: Service Domain `EditionDiscoveryService` et `ImportEditionHandler`.
- Task 6: Endpoints HTTP `/api/editions/import`, `/api/editions/:id`, `/api/manga/:id/editions`, paramètre `country` sur `/api/manga/external`.
- Task 7: Tests fonctionnels sur les nouveaux endpoints + mise à jour des tests existants.
- Task 8: Tests unitaires sur `Edition`, `CountryLanguage`, `EditionDiscoveryService`.
- Task 9: Frontend — API client, composable `useExternalSearch` multi-pays, store `useSearchFiltersStore`.
- Task 10: Frontend — refonte `AddMangaPage.vue` (assistant 3 écrans, import édition en un clic).
- Task 11: Frontend — affichage et filtres édition sur `CollectionPage` / `CollectionDetailPage` / `WishlistPage`.
- Task 12: Mise à jour `CLAUDE.md` (nouvelles règles édition + multi-pays).
- Task 13: Final lint, test, et review loop.

---

#### Task 1 : Domain — entités `Edition`, `Manga`, `Volume`, mapping `CountryLanguage`

Introduire l'entité `Edition` et déplacer les attributs « édition » de `Manga` vers `Edition`. Ajouter le VO/enum `Country` et le mapper `CountryLanguage`.

**Skills and docs to load:**
- `/project-quality-setup` — règles hexagonales, naming `final readonly`, Deptrac.
- `.claude/backend.md` — R10 (variables), R11 (n/a ici), section Doctrine (enum length, defaults).
- `.claude/CLAUDE.md` — règle « never FQCN built-ins », section Doctrine.

**Files:**
- Create `back/src/Manga/Domain/Edition.php`
- Create `back/src/Manga/Domain/Country.php` (enum string-backed : `FR, JP, US, GB, IT, DE, ES, BE, CH, CA`)
- Create `back/src/Manga/Domain/CountryLanguage.php` (service Domain `final readonly`, méthode `forCountry(Country $country): string`)
- Create `back/src/Manga/Domain/EditionFormat.php` (enum string : `Tankobon, A4, Big, Color, Deluxe, Omnibus, Unknown`)
- Modify `back/src/Manga/Domain/Manga.php` — retirer `edition`, `language`, `externalId`, `totalVolumes` ; ajouter relation `OneToMany editions` (`orphanRemoval: true`) ; conserver `title`, `originalTitle?`, `author`, `summary`, `genre`, `createdAt`.
- Modify `back/src/Manga/Domain/Volume.php` — remplacer `ManyToOne manga` par `ManyToOne edition` (`onDelete: CASCADE`) ; ajouter `externalIds` (JSONB `array` Doctrine) ; recréer unique `(edition_id, number)`.

**Implementation**

Signature `Edition` (pseudo) :

```php
#[ORM\Entity, ORM\Table(name: 'editions')]
final class Edition
{
    public function __construct(
        #[ORM\Id, ORM\Column] public string $id,
        #[ORM\ManyToOne(inversedBy: 'editions'), ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public Manga $manga,
        #[ORM\Column(length: 100)] public string $name,
        #[ORM\Column(length: 100, nullable: true)] public ?string $publisher,
        #[ORM\Column(enumType: EditionFormat::class)] public EditionFormat $format,
        #[ORM\Column(enumType: Country::class, length: 2)] public Country $country,
        #[ORM\Column(length: 5)] public string $language,
        #[ORM\Column(nullable: true)] public ?int $totalVolumes,
        #[ORM\Column(type: 'string', nullable: true)] public ?string $coverUrl,
        #[ORM\Column(type: 'json')] public array $externalIds = [],
        #[ORM\OneToMany(mappedBy: 'edition', targetEntity: Volume::class, orphanRemoval: true)]
        public Collection $volumes = new ArrayCollection(),
        #[ORM\Column] public DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {}

    public function addVolume(Volume $volume): void { ... }
    public function toArray(): array { ... }
    public function toDetailArray(): array { ... }
}
```

- Contrainte unique : `#[ORM\UniqueConstraint(fields: ['manga', 'name', 'publisher', 'country'])]`. Si `publisher` est NULL, Postgres considère les NULL comme distincts — c'est le comportement voulu (un éditeur inconnu n'empêche pas un autre).
- `Country` enum string-backed length 2 (ISO 3166-1 alpha-2). Le mapping `CountryLanguage::forCountry()` est purement domaine : retourne `'fr'` pour FR/BE/CH/CA, `'en'` pour US/GB, etc.
- Le champ `language` reste explicite sur `Edition` (et non dérivé à la volée) pour autoriser les cas hors-mapping (édition belge en néerlandais par ex.) — défaut renseigné via le mapper à l'instanciation depuis l'application layer.
- `externalIds` Doctrine type `json` (mappé en `JSONB` par DBAL Postgres) — pas besoin de type custom.

**Tests**

Test unitaire `back/tests/Unit/Manga/Domain/EditionTest.php` :
- Construction OK avec tous les champs.
- `addVolume()` rattache bien, ne duplique pas un `Volume` déjà présent.
- `toArray()` expose les champs en snake_case (cohérent avec `Manga::toArray()` existant).
- `toDetailArray()` inclut les volumes sérialisés triés par `number ASC`.

Test unitaire `back/tests/Unit/Manga/Domain/CountryLanguageTest.php` :
- `forCountry(Country::FR) === 'fr'`, `forCountry(Country::JP) === 'ja'`, `forCountry(Country::US) === 'en'`, etc. Une assertion par valeur de l'enum.

Test unitaire `back/tests/Unit/Manga/Domain/CountryTest.php` :
- `Country::from('FR')` OK, `Country::tryFrom('XX') === null`.

**Verify**

```bash
make test-php
# Attendre : tests Edition + Country + CountryLanguage en vert, aucun test régressif.
```

---

#### Task 2 : Migration Doctrine + backfill des données existantes

Générer la migration via `make migration`, puis enrichir le fichier avec le `addSql()` de backfill (création des `Edition` par défaut, recâblage des FK).

**Skills and docs to load:**
- `.claude/CLAUDE.md` — section « Doctrine Mapping Rules », « FK / index names ».
- `.claude/backend.md` — R10 (naming).

**Files:**
- Create `back/migrations/VersionYYYYMMDDHHMMSS.php` (généré par `make migration`, puis édité)
- Modify le fichier généré : ajouter le SQL de backfill dans `up()`, le SQL de rollback dans `down()`.

**Implementation**

1. Avant `make migration` : vérifier que la Task 1 est mergée localement et que les entités sont à jour. Sinon Doctrine ne « voit » pas la nouvelle entité.
2. `make migration` génère le diff (CREATE TABLE editions, ADD COLUMN edition_id sur volumes, etc.). Les noms d'index/FK Doctrine sont OK *par construction*.
3. Ajouter dans `up()`, **après** les `CREATE TABLE`/`ADD COLUMN` et **avant** les `DROP COLUMN`, le SQL suivant (ordre critique) :

```sql
-- Étape A : créer une Edition par défaut pour chaque Manga existant
INSERT INTO editions (id, manga_id, name, publisher, format, country, language,
                      total_volumes, cover_url, external_ids, created_at)
SELECT
  gen_random_uuid()::text,
  m.id,
  COALESCE(NULLIF(m.edition, ''), 'Standard'),
  NULL,
  'Unknown',
  'FR',
  COALESCE(m.language, 'fr'),
  m.total_volumes,
  m.cover_url,
  CASE WHEN m.external_id IS NOT NULL
       THEN jsonb_build_object('legacy', m.external_id)
       ELSE '{}'::jsonb END,
  m.created_at
FROM mangas m;

-- Étape B : recâbler volumes -> editions
UPDATE volumes v
SET edition_id = (SELECT e.id FROM editions e WHERE e.manga_id = v.manga_id);

-- Étape C : recâbler collection_entries -> editions
UPDATE collection_entries ce
SET edition_id = (SELECT e.id FROM editions e WHERE e.manga_id = ce.manga_id);

-- Étape D : recâbler wishlist_items -> editions
UPDATE wishlist_items wi
SET edition_id = (SELECT e.id FROM editions e WHERE e.manga_id = wi.manga_id);
```

Puis Doctrine génère seul les `ALTER TABLE … SET NOT NULL` sur `edition_id` et les `DROP COLUMN` sur `manga_id`.

4. `down()` : symétrique mais lossy → ajouter un commentaire `// WARNING: drops all editions except the first per manga`.

**Tests**

Aucun test propre à la migration (les fonctionnels valident son effet via `setUp()` qui rejoue les migrations). Voir Task 7.

**Verify**

```bash
# Sur une base contenant les données de test/dev :
make migrate
docker compose exec back php bin/console doctrine:schema:validate
# Sortie attendue : « [OK] The mapping files are correct. » + « [OK] The database schema is in sync with the mapping files. »

# Vérifier qu'aucune entrée orpheline ne subsiste :
docker compose exec db psql -U app -d app -c "SELECT COUNT(*) FROM volumes WHERE edition_id IS NULL;"
# Attendu : 0
```

---

#### Task 3 : Refacto `CollectionEntry`, `WishlistItem`, `Stats`

Faire pointer la collection et la wishlist sur `Edition` (FK `edition_id`), adapter les requêtes des stats pour agréger sur `Edition` (genre vient de `Manga`, valeur vient des `Volume` d'une `Edition`).

**Skills and docs to load:**
- `.claude/backend.md` — R3 (handlers pure orchestrators), R4 (Domain interfaces), R6 (Shared layer).
- `.claude/CLAUDE.md` — Doctrine defaults.

**Files:**
- Modify `back/src/Collection/Domain/CollectionEntry.php` — remplacer `ManyToOne Manga $manga` par `ManyToOne Edition $edition` ; unique `(owner_id, edition_id)` ; ajuster `toArray()` (expose `edition` + `manga` via `edition.manga`).
- Modify `back/src/Collection/Domain/VolumeEntry.php` — inchangé structurellement (toujours `Volume`), vérifier que la sérialisation inclut l'`Edition` parente.
- Modify `back/src/Wishlist/Domain/WishlistItem.php` — `manga_id` → `edition_id`.
- Modify `back/src/Collection/Application/AddCollectionEntry/AddCollectionEntryHandler.php` — reçoit `editionId` au lieu de `mangaId`.
- Modify `back/src/Collection/Infrastructure/Http/AddCollectionEntryRequest.php` — payload `editionId` à la place de `mangaId`.
- Modify `back/src/Wishlist/Application/*` et `back/src/Wishlist/Infrastructure/Http/*` — idem.
- Modify `back/src/Stats/Application/GetStatsHandler.php` — `collectionValue` agrège `SUM(volumes.price)` filtré sur les `VolumeEntry.isOwned = true` ; `genreBreakdown` joint `volume → edition → manga.genre`.
- Modify `back/src/Collection/Shared/CollectionReaderInterface.php` — la méthode `findById` renvoie un DTO qui expose `edition` + sa `manga`.

**Implementation**

- Pour `genreBreakdown` côté `Stats`, la requête passe par `JOIN editions e ON ce.edition_id = e.id JOIN mangas m ON e.manga_id = m.id GROUP BY m.genre`. Le test fonctionnel `StatsControllerTest` est mis à jour pour refléter ce shape.
- `CollectionEntry::toArray()` continue d'exposer un `manga` racine (champs `title`, `author`, `summary`, `genre` lus depuis `edition.manga`) **plus** un objet `edition` (`name`, `publisher`, `format`, `country`, `language`, `totalVolumes`). Cela évite de casser le front avant la Task 10 — la clé `manga` reste, juste enrichie.
- `WishlistItem::toArray()` suit la même logique.

**Tests**

Modifier les tests unitaires existants :
- `back/tests/Unit/Collection/Domain/CollectionEntryTest.php` — construire avec une `Edition`, vérifier `toArray()['edition']` et `toArray()['manga']`.
- `back/tests/Unit/Wishlist/Domain/WishlistItemTest.php` — idem.

**Verify**

```bash
make test-php
docker compose exec back php bin/console doctrine:schema:validate
```

---

#### Task 4 : Extension `ExternalApiClientInterface` + ajustement des clients

Ajouter le paramètre `country` (FR par défaut) à toutes les méthodes de recherche, et adapter chaque adapter Infrastructure pour le respecter.

**Skills and docs to load:**
- `.claude/backend.md` — R4 (Domain interfaces).

**Files:**
- Modify `back/src/Manga/Domain/ExternalApiClientInterface.php` — signatures :
  - `searchByTitle(string $query, string $type = 'manga', int $page = 1, Country $country = Country::FR): array`
  - `getMangaById(string $externalId, Country $country = Country::FR): ?ExternalMangaDto`
  - Nouvelle : `listEditions(string $title, Country $country): array<EditionDiscoveryDto>`
- Create `back/src/Manga/Domain/EditionDiscoveryDto.php` (`final readonly`, champs : `externalId`, `source`, `name`, `publisher?`, `format`, `country`, `language`, `totalVolumes?`, `coverUrl?`, `mangaTitle`).
- Modify `back/src/Manga/Infrastructure/ExternalApi/JikanMangaApiClient.php` — accepte `Country`, mappe la valeur en sortie (`language: CountryLanguage::forCountry($country)`). `listEditions()` y retourne `[]` (Jikan ne discrimine pas les éditions).
- Modify `back/src/Manga/Infrastructure/ExternalApi/GoogleBooksMangaApiClient.php` — remplacer `langRestrict=fr` codé en dur par `langRestrict=` + langue dérivée de `country`. `listEditions()` y est implémenté : il interroge `q="<title>"+inauthor:` puis groupe par `publisher` + `subtitle` pour renvoyer une variante par combinaison.
- Modify `back/src/Manga/Infrastructure/ExternalApi/MangaDexMangaApiClient.php` — utilise déjà `availableTranslatedLanguage[]` ; ajouter une implémentation de `searchByTitle()` et `listEditions()` (jusqu'ici stubs).
- Modify `back/src/Manga/Infrastructure/ExternalApi/NullMangaApiClient.php` — méthodes nouvelles retournent `[]`/`null` pour respecter le contrat.

**Implementation**

- `EditionDiscoveryDto` est un VO `final readonly`, source = `'jikan' | 'mangadex' | 'googlebooks'`, `format = EditionFormat`. Une factory statique `EditionDiscoveryDto::fromGoogleBooks(array $volumeInfo, Country $country)` centralise le mapping HTTP→Domain.
- Le mapping `Country → langRestrict` (Google Books) et `Country → availableTranslatedLanguage[]` (MangaDex) est délégué à `CountryLanguage::forCountry()`.

**Tests**

Tests unitaires `back/tests/Unit/Manga/Infrastructure/`:
- `GoogleBooksMangaApiClientTest` (existant) — ajouter cas `searchByTitle('berserk', 'manga', 1, Country::JP)` mocke la réponse HTTP avec `langRestrict=ja` ; vérifier `language='ja'` sur les DTOs.
- `MangaDexMangaApiClientTest` (existant) — idem avec `availableTranslatedLanguage[]=ja`.
- Nouveau : `EditionDiscoveryDtoTest` — `fromGoogleBooks()` mappe correctement publisher + subtitle.

**Verify**

```bash
make test-php
```

---

#### Task 5 : `EditionDiscoveryService` Domain + `ImportEditionHandler` Application

Logique métier pure pour découvrir les variantes d'éditions et importer une édition complète.

**Skills and docs to load:**
- `.claude/backend.md` — R3, R4.

**Files:**
- Create `back/src/Manga/Domain/EditionDiscoveryService.php` (`final readonly`, dépend de `ExternalApiClientInterface` injecté nominalement et d'un `iterable $editionDiscoveryClients` taggé `manga.edition_discovery`).
- Create `back/src/Manga/Application/ImportEdition/ImportEditionCommand.php` (`final readonly`, champs : `provider` (string), `externalId` (string), `country` (Country), `mangaId?` (string pour rattacher à une série existante)).
- Create `back/src/Manga/Application/ImportEdition/ImportEditionHandler.php` (`final readonly`, pure orchestrator selon R3).
- Modify `back/config/services.yaml` — taguer Google Books et MangaDex avec `manga.edition_discovery` ; ajouter un service locator si nécessaire.

**Implementation**

`EditionDiscoveryService::discover(string $title, Country $country): array<EditionDiscoveryDto>` :
1. Appelle `listEditions()` sur chaque client taggé.
2. Déduplique sur la clé `(publisher, name, format)` (case-insensitive trim).
3. Trie par « pertinence » : nombre de volumes connu décroissant, puis nom alphabétique.

`ImportEditionHandler::__invoke(ImportEditionCommand)` :
1. Charge la `Manga` par `mangaId` si fourni, sinon lookup par `originalTitle` (créé si inexistant) → délègue à `ImportMangaHandler` existant (pattern *séries d'abord*).
2. Récupère via le bon `ExternalApiClient` (clé `command.provider`) le détail complet (`getMangaById` + `searchByTitle` pour volumes).
3. Crée `Edition` avec les champs récupérés.
4. Pour chaque tome découvert, instancie un `Volume` (réutilise `MangaCoverProviderInterface` composite pour les jaquettes manquantes).
5. Dispatch un `EditionImportedEvent` (event bus, R1).

**Tests**

Test unitaire `back/tests/Unit/Manga/Domain/EditionDiscoveryServiceTest.php` :
- Avec deux clients fakes qui renvoient des DTOs en partie chevauchants, le service déduplique sur la triple clé.
- Tri respecte `totalVolumes DESC`.

Test unitaire `back/tests/Unit/Manga/Application/ImportEdition/ImportEditionHandlerTest.php` :
- Avec un `ExternalApiClient` mock qui renvoie 12 volumes, le handler crée 1 `Manga` (si absent) + 1 `Edition` + 12 `Volume`.
- Dispatch `EditionImportedEvent` une fois.

**Verify**

```bash
make test-php
```

---

#### Task 6 : Endpoints HTTP

Exposer les nouveaux endpoints et étendre les existants pour le `country`.

**Skills and docs to load:**
- `.claude/CLAUDE.md` — `#[MapRequestPayload]`, pas de try/catch dans les controllers.
- `.claude/backend.md` — R11 (pagination si besoin).

**Files:**
- Modify `back/src/Manga/Infrastructure/Http/MangaController.php`:
  - `GET /api/manga/external` — accepte `country` query param (défaut `FR`), validé via `Country::tryFrom()`.
  - Nouveau `GET /api/manga/{id}/editions` — renvoie la liste des `Edition` rattachées à un `Manga`.
- Create `back/src/Manga/Infrastructure/Http/EditionController.php`:
  - `POST /api/editions/import` (payload `ImportEditionRequest`) — dispatch `ImportEditionCommand` sur le command.bus.
  - `GET /api/editions/{id}` — détail d'une édition (avec tous ses volumes).
  - `GET /api/editions/discover?title=&country=FR` — appelle `EditionDiscoveryService::discover()` et renvoie les `EditionDiscoveryDto`.
- Create `back/src/Manga/Infrastructure/Http/ImportEditionRequest.php` (`final readonly` + `#[MapRequestPayload]`, validation : `provider` requis (`Choice` parmi providers actifs), `externalId` requis, `country` requis (`Country`)).
- Modify `back/src/Manga/Infrastructure/Http/ImportMangaRequest.php` — accepter un sous-objet optionnel `edition: {...}` ; sans lui, comportement legacy → crée 1 `Edition` par défaut.

**Implementation**

- Les routes utilisent `#[Route]` PHP attributes (cohérent avec l'existant).
- Sérialisation : `Edition::toDetailArray()` inclut `manga: { id, title, ... }` + ses `volumes[]`.
- Filtres : `GET /api/manga/{id}/editions?country=FR` autorisé.

**Tests**

Couverts dans Task 7.

**Verify**

```bash
make php-qa
# Doit passer : PHPStan + PHPCS + Deptrac + tests.
```

---

#### Task 7 : Tests fonctionnels

Ajouter ou mettre à jour les tests Functional pour couvrir tous les nouveaux comportements HTTP.

**Skills and docs to load:**
- `.claude/CLAUDE.md` — règle testing mandatory + DAMA savepoints + `NullMangaApiClient` en `when@test`.

**Files:**
- Modify `back/tests/Functional/Manga/MangaControllerTest.php` :
  - `testSearchExternalAcceptsCountryParam` — `GET /api/manga/external?q=berserk&country=JP` → 200, les DTOs ont `language='ja'`.
  - `testSearchExternalRejectsInvalidCountry` — `country=ZZ` → 400.
  - `testImportMangaWithLegacyPayloadStillCreatesDefaultEdition` — payload sans `edition` → la `Manga` créée a 1 `Edition` `Standard FR`.
  - `testImportMangaWithEditionPayload` — payload avec `edition: { name, publisher, format, country }` → `Edition` créée avec ces valeurs.
  - `testListEditionsForManga` — `GET /api/manga/{id}/editions` → 200 avec la liste.
- Create `back/tests/Functional/Manga/EditionControllerTest.php` :
  - `testImportEditionCreatesMangaIfMissing` — `POST /api/editions/import` avec `provider=null`, série inconnue → 201 + Manga + Edition + 0 Volume (NullClient ne ramène rien).
  - `testImportEditionRequiresJwt` — sans Bearer → 401.
  - `testImportEditionRejectsUnknownProvider` — `provider=xxx` → 400.
  - `testImportEditionRejectsInvalidCountry` — `country=ZZ` → 400.
  - `testGetEditionByIdReturns404IfMissing`.
  - `testGetEditionByIdReturnsDetailWithVolumes`.
  - `testDiscoverEditionsAcceptsCountryParam` — `GET /api/editions/discover?title=berserk&country=FR` → 200, `country=JP` → 200 avec un autre set.
- Modify `back/tests/Functional/Collection/CollectionControllerTest.php` :
  - `testAddCollectionEntryNowAcceptsEditionId` — `POST /api/collection { editionId }` → 201.
  - `testAddCollectionEntryRejectsLegacyMangaIdPayload` — `POST /api/collection { mangaId }` → 400.
  - Vérifier que `GET /api/collection` renvoie bien l'`edition` enrichie.
- Modify `back/tests/Functional/Wishlist/WishlistControllerTest.php` — idem (`editionId`).
- Modify `back/tests/Functional/Stats/StatsControllerTest.php` — vérifier que `collectionValue` et `genreBreakdown` restent corrects après bascule.

**Implementation**

- Réutiliser le helper `importManga()` existant — l'étendre pour produire des `Edition` paramétrables (signature : `importManga(array $overrides = [], array $editionOverrides = [])`).
- Les tests utilisent `NullMangaApiClient` (déjà câblé via `when@test`).
- Pour les scénarios « JP renvoie des résultats différents de FR », créer dans `back/tests/Resources/` un `FakeJikanResponses.php` qui mocke les réponses HTTP via `MockHttpClient` — pas via Internet.

**Verify**

```bash
make test-php
# Tous les tests fonctionnels passent.
docker compose exec back ./vendor/bin/phpunit --filter Manga --testdox
docker compose exec back ./vendor/bin/phpunit --filter Edition --testdox
docker compose exec back ./vendor/bin/phpunit --filter Collection --testdox
```

---

#### Task 8 : Tests unitaires complémentaires

Compléter la couverture sur les pures Domain pieces ajoutées (déjà partiellement traitées dans les tasks 1, 4, 5 — cette tâche est un filet de sécurité pour combler les manques).

**Skills and docs to load:**
- `.claude/CLAUDE.md` — règle testing mandatory.

**Files:**
- Create `back/tests/Unit/Manga/Domain/EditionFormatTest.php` — toutes les valeurs de l'enum.
- Create `back/tests/Unit/Manga/Domain/EditionDiscoveryDtoTest.php` — factories `fromGoogleBooks`, `fromMangaDex`.
- Modify `back/tests/Unit/Manga/Domain/MangaTest.php` — la méthode `toArray()` ne contient plus `edition`, `language`, `externalId`, `totalVolumes`.
- Modify `back/tests/Unit/Manga/Domain/VolumeTest.php` — `volume.edition` au lieu de `volume.manga`.

**Tests**

Scénarios listés ci-dessus, un test par méthode publique.

**Verify**

```bash
make test-php
```

---

#### Task 9 : Frontend — API client, composable `useExternalSearch` multi-pays, store `useSearchFiltersStore`

Étendre la couche API et le composable pour porter le `country`, et introduire un store Pinia qui retient le pays sélectionné (persistance localStorage, défaut `FR`).

**Skills and docs to load:**
- `/vue-best-practices` — Composition API + `<script setup>`, TS.
- `/vue-pinia-best-practices` — store setup, persistance.
- `/create-adaptable-composable` — `useExternalSearch` accepte `MaybeRefOrGetter` pour `country`.

**Files:**
- Modify `front/src/api/manga.ts` :
  - `searchExternalManga(q, page, country)` → GET `/manga/external?q=&page=&country=`.
  - Nouveau `discoverEditions(title, country)` → GET `/editions/discover?title=&country=`.
  - Nouveau `importEdition(payload)` → POST `/editions/import`.
  - Nouveau `getEdition(editionId)` → GET `/editions/:id`.
  - Nouveau `listEditionsForManga(mangaId)` → GET `/manga/:id/editions`.
- Modify `front/src/composables/useExternalSearch.ts` — accepte `country: MaybeRefOrGetter<Country>` ; debounce et infinite scroll inchangés ; nouvelle clé de cache par `country`.
- Create `front/src/composables/useEditionDiscovery.ts` — wrap `discoverEditions`, expose `{ editions, loading, error, fetch(title) }`.
- Create `front/src/composables/useImportEdition.ts` — wrap `importEdition`, expose `mutate(payload)` + état.
- Create `front/src/stores/useSearchFiltersStore.ts` — Pinia setup store, state `country: Ref<Country>` persisté en localStorage sous `ziggytheque:searchCountry`, défaut `'FR'`.
- Create `front/src/types/country.ts` — type union `Country = 'FR' | 'JP' | 'US' | 'GB' | 'IT' | 'DE' | 'ES' | 'BE' | 'CH' | 'CA'`, fonction `flagEmoji(country)` et `labelFor(country, locale)`.
- Modify `front/src/i18n/fr.json` et `en.json` — clés `country.FR`, `country.JP`, etc., et clés `add.countryLabel`, `add.editionsForCountry`, `add.importEditionCta`.

**Implementation**

- Le store est un setup-store Pinia (pas un options-store) :

```ts
export const useSearchFiltersStore = defineStore('searchFilters', () => {
  const country = useLocalStorage<Country>('ziggytheque:searchCountry', 'FR')
  function setCountry(next: Country) { country.value = next }
  return { country, setCountry }
})
```

- `useExternalSearch` lit `toValue(country)` à chaque change et reset la liste de résultats.

**Tests**

Aucun test front exigé par les règles projet (le repo n'a pas de setup Vitest visible côté composables). Si Vitest est déjà configuré (à vérifier en exécutant `make test-vue`), ajouter :
- `front/src/composables/__tests__/useExternalSearch.test.ts` — vérifier que `country` est bien dans la query string.
- `front/src/stores/__tests__/useSearchFiltersStore.test.ts` — persistance localStorage.

**Verify**

```bash
make vue-qa
# lint + type-check + (test-vue si configuré) en vert.
```

---

#### Task 10 : Frontend — refonte `AddMangaPage.vue` (assistant 3 écrans)

Remplacer le formulaire actuel par un assistant guidé : (1) pays + recherche série, (2) liste des éditions disponibles, (3) prévisualisation + import en un clic.

**Skills and docs to load:**
- `/vue-best-practices` — Composition API, props/emits typés.
- `/vue-pinia-best-practices` — consommation du `useSearchFiltersStore`.

**Files:**
- Modify `front/src/pages/AddMangaPage.vue` — refondre la structure en 3 sections (router-style state via `currentStep` ref). Plus de formulaire libre — tout vient des résultats API.
- Create `front/src/components/molecules/CountrySelector.vue` — DaisyUI dropdown, options = toutes les valeurs de `Country`, affiche drapeau + libellé i18n.
- Create `front/src/components/organisms/MangaSearchResults.vue` — liste de séries (résultats de `searchExternalManga`), bouton « Voir les éditions ».
- Create `front/src/components/organisms/EditionDiscoveryList.vue` — liste de `EditionDiscoveryDto` groupés par publisher, affiche `name`, `publisher`, `format`, `totalVolumes`, cover ; bouton « Importer cette édition ».
- Create `front/src/components/molecules/EditionImportPreview.vue` — modal de confirmation avec aperçu de tous les tomes (covers en grille) avant l'import.
- Modify `front/src/components/atoms/BaseEditionSelector.vue` — la liste hard-codée FR devient un fallback offline ; le composant accepte une prop `country` et lit `useSearchFiltersStore` par défaut. (Garde une utilité dans les écrans d'édition manuelle.)

**Implementation**

Flow utilisateur cible :

```
┌────────── Step 1 : Pays + recherche ────────────┐
│  [🇫🇷 France ▼]   [berserk             🔍]     │
│  ─────────────────────────────────────────────  │
│  Berserk     · Kentaro Miura · MAL #2  → [Voir éditions]│
│  Berserk : Maximum   ...                        │
└─────────────────────────────────────────────────┘
                       │
                       ▼
┌────────── Step 2 : Éditions disponibles 🇫🇷 ────┐
│  Glénat                                         │
│    ● Édition Classique  · Tankobon · 41 tomes  [Importer]│
│    ● Édition Prestige   · A4 large · 14 tomes   [Importer]│
│  Pika                                           │
│    ● Édition Maximum    · Big       · 21 tomes  [Importer]│
└─────────────────────────────────────────────────┘
                       │
                       ▼
┌────────── Step 3 : Aperçu + Confirmation ───────┐
│  Berserk — Prestige (Glénat, A4 large, 🇫🇷)     │
│  Volumes : 14 tomes, jaquettes individuelles    │
│  [▢ Ajouter à ma collection] [▢ Wishlist]       │
│                              [Importer]         │
└─────────────────────────────────────────────────┘
```

- Aucune saisie d'URL n'est plus nécessaire : les covers (série + chaque tome) viennent toutes des DTOs API.
- Si l'utilisateur veut compléter manuellement (cas hors catalogue), un bouton « Saisie manuelle » garde l'ancien formulaire derrière `<details>` collapse, mais ce n'est plus le chemin par défaut.
- À l'étape 3, l'utilisateur choisit la destination (collection ou wishlist) — appelle ensuite `POST /api/collection` ou `/api/wishlist` avec l'`editionId` retourné par l'import.

**Verify**

```bash
make vue-qa
# Tester manuellement la golden path en local :
make dev
# Puis ouvrir http://localhost:5173/add :
#   1. changer pays → JP, taper « berserk » → résultats JP
#   2. cliquer « Voir éditions » sur Berserk → liste avec >=2 éditions
#   3. importer Prestige → modal aperçu → confirm → redirection vers /collection/:id
# Vérifier que la nouvelle entrée collection affiche le bon publisher + format + drapeau JP.
```

---

#### Task 11 : Frontend — affichage et filtres édition sur Collection / Wishlist

Afficher l'édition (éditeur + format + drapeau pays) partout où les entrées de collection / wishlist sont listées, et ajouter des filtres par pays et par éditeur sur la liste.

**Skills and docs to load:**
- `/vue-best-practices` — props typées.
- `/vue-pinia-best-practices` — consommation store filtres.

**Files:**
- Modify `front/src/pages/CollectionPage.vue` — ajouter un panneau de filtres latéral (DaisyUI drawer) : pays (checkboxes multi-select), éditeur (dropdown), format (chips). Filtre côté client sur la liste retournée par `GET /api/collection`.
- Modify `front/src/pages/CollectionDetailPage.vue` — afficher l'édition (`{publisher} · {format} · {flagEmoji(country)} {name}`) sous le titre.
- Modify `front/src/pages/WishlistPage.vue` — idem.
- Modify `front/src/components/organisms/CollectionList.vue` (ou nom équivalent — vérifier) — chaque carte affiche la pastille édition.
- Modify `front/src/types/index.ts` — interface `CollectionEntry` enrichie avec `edition: { id, name, publisher, format, country, language, totalVolumes }`.

**Implementation**

- Le filtre par pays consomme `useSearchFiltersStore` pour pré-cocher le pays courant.
- Le filtre par éditeur est dérivé dynamiquement de la liste (`uniqueBy(entry.edition.publisher)`), pas hard-codé.
- Pour les entrées historiques migrées (`edition.name === 'Standard'`), afficher juste le drapeau FR sans éditeur (publisher = NULL).

**Verify**

```bash
make vue-qa
# Test manuel :
#   - importer 2 éditions de Berserk (Prestige FR + Maximum FR)
#   - aller sur /collection : les deux apparaissent comme cartes distinctes
#   - cocher filtre publisher=Glénat → seule la Prestige reste
#   - cocher pays=JP → liste vide (ou contient seulement les imports JP)
```

---

#### Task 12 : Mise à jour `CLAUDE.md`

Formaliser les nouvelles règles : « toute série a au moins une édition », « `country` est l'axe de recherche externe », « `language` est dérivé de `country` mais stocké explicitement ». Documenter aussi le contrat des nouveaux endpoints.

**Skills and docs to load:**
- `/update-coding-rules` — pour suivre le format CLAUDE.md.

**Files:**
- Modify `.claude/CLAUDE.md` :
  - Section « Bounded Contexts » : ajouter `Edition` sous `Manga/`.
  - Section « API Endpoints » : ajouter `POST /api/editions/import`, `GET /api/editions/:id`, `GET /api/editions/discover`, `GET /api/manga/:id/editions`, et le param `country` sur `/api/manga/external`.
  - Section « External API » : remplacer le bloc actuel par une description multi-clients (Jikan série canonique, Google Books + MangaDex éditions par pays, mapping `Country → langue`).
  - Nouvelle sous-section « Editions Rule » : « Une `Manga` (série) a 1..N `Edition` (édition publiée dans un pays). `CollectionEntry` et `WishlistItem` pointent sur `Edition`, jamais sur `Manga`. »

**Verify**

```bash
# Lecture diff manuelle.
git diff .claude/CLAUDE.md
```

---

#### Task 13 : Final lint, test, and review loop.

Once finished, run lint and test for the affected sub-directories, dump the git diff in a tmp file with `git diff HEAD > /tmp/last-review.diff`, then spawn a `file-reviewer` subagent with the prompt: *"Review the diff at /tmp/last-review.diff. Invoke the `code-review` skill, follow its 'Load thematic rules by file type' step using the diff's file paths, and write structured findings."*
Fix every finding, then redo all three steps. Repeat until lint passes, tests pass, and the subagent returns no findings (max 5 iterations).
