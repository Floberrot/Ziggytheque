# Collections par édition + découverte d'éditions + scan ISBN

### TL;DR

> [!NOTE]
> Aujourd'hui, ajouter une série revient à enregistrer une **œuvre** (Berserk) avec une étiquette d'édition saisie en texte libre. On veut que chaque entrée de collection représente une **œuvre dans une édition précise** (éditeur + année + langue + visuel du tome 1), et pouvoir lister toutes ses collections par édition.
>
> Le plan se déroule en quatre phases livrables indépendamment (une phase = un PR = un commit). **(1)** On enrichit la fiche série avec un éditeur, une année d'édition et une clé d'œuvre pour regrouper les éditions d'une même œuvre, et on ajoute un nouvel écran « choisir l'édition » qui découvre automatiquement toutes les éditions existantes d'une œuvre dans une langue choisie (éditeur, année, couverture du tome 1, nombre de tomes), via Google Books en source principale et Open Library en complément. **(2)** On rend la recherche de couvertures fidèle à l'édition choisie : on transmet l'éditeur/l'année/la langue au moment de chercher une couverture, et on privilégie l'ancrage par ISBN (exact) plutôt que la recherche floue par titre. **(3)** On ajoute le scan d'un code-barres ISBN par tome : la caméra sur mobile, et sur ordinateur un QR code à scanner avec le téléphone qui renvoie l'ISBN en temps réel à l'écran (via Mercure, déjà en place pour la complétion de couvertures) ; saisie manuelle de l'ISBN en repli partout. **(4)** On câble tout le frontend : nouvel écran de choix d'édition dans le flux d'ajout, affichage éditeur/année sur la page série, et intégration du scan dans la fiche d'un tome.
>
> Aucune nouvelle dépendance d'infrastructure : Google Books, MangaDex, Open Library et le hub Mercure sont déjà configurés. Côté frontend on ajoute deux librairies clientes (lecture de code-barres + génération de QR code).

---

### Implementation

#### État actuel

```
CollectionEntry ──► Manga (= "l'œuvre", mais porte déjà edition + language + coverUrl)
                      └── Volume[] (number, coverUrl, price, releaseDate, isbn, spineUrl)
```

- `Manga` (`back/src/Manga/Domain/Manga.php`) : `title`, `edition` (texte libre, souvent un nom d'éditeur), `language`, `author`, `summary`, `coverUrl`, `genre`, `externalId`, `volumes[]`.
- Rien n'empêche déjà d'avoir deux `Manga` « Berserk / Glénat » et « Berserk / Deluxe » : ce sont deux lignes avec un `edition` différent. **Les manques sont l'UX de découverte, la structuration de l'édition (pas d'éditeur ni d'année distincts), la fidélité des couvertures, et l'absence de regroupement par œuvre.**
- Flux d'ajout (`front/src/pages/AddMangaPage.vue`) : étape 1 recherche d'œuvre via **Jikan** (`GET /api/manga/external`, aliasé sur `JikanMangaApiClient`) ; étape 2 formulaire avec `BaseEditionSelector` (liste d'éditeurs codée en dur dans `front/src/data/editions.ts`) ; étape 3 destination. À la validation : `POST /api/manga` (`ImportMangaHandler` crée le `Manga` + N tomes vierges depuis `totalVolumes`) puis `POST /api/collection`.
- Couvertures par tome (`front/src/components/organisms/EnrichVolumeModal.vue`) : `GET /api/manga/volume-search` → `SearchVolumeExternalHandler` → `MangaCoverProviderInterface::findByContext(title, edition, volumeNumber, language)`. Providers `app.manga_cover_provider` (priorité : MangaDex 100 > OpenLibrary 50 > GoogleBooks 10) agrégés par `CompositeMangaCoverApiClient`. Complétion en masse via `StartCoverBatchHandler` → message async → `CoverBatchResolver` → progression poussée par **Mercure** (topic `https://ziggytheque.app/cover-batch/{id}`, `MercureCoverBatchProgressPublisher` + `MercureCoverBatchSubscriberAuthorizer`, consommée côté front par `useCoverBatchProgress` via `EventSource`).
- `Isbn` (VO validé ISBN-10/13, `back/src/Manga/Domain/Isbn.php`) + type Doctrine `isbn` (`IsbnType`, colonne `VARCHAR(20)`). `Volume.isbn` existe déjà. `InvalidIsbnException extends DomainException` → **422** via `ExceptionListener`.

#### État cible

```
              ┌──────────────────────────────────────────────────────────┐
              │  Manga = "œuvre DANS son édition"                          │
CollectionEntry ─► │  title, externalWorkId (regroupe les éditions d'une œuvre)│
              │  publisher (éditeur), editionYear (année), edition (label  │
              │  variante: Deluxe/Perfect/null), language, coverUrl (T1)   │
              │  └── Volume[] (… isbn ← ancre l'édition exacte)            │
              └──────────────────────────────────────────────────────────┘

Flux d'ajout : [1] chercher l'ŒUVRE (Jikan) → [2] CHOISIR L'ÉDITION (découverte
Google Books + Open Library, filtrée par langue) → [3] formulaire pré-rempli →
[4] destination.

Couvertures : findByContext(EditionContext{title,publisher,label,year,lang,workId}, n)
+ recherche par ISBN prioritaire (exacte). Scan ISBN : caméra mobile OU QR→téléphone
→ ISBN poussé en temps réel via Mercure (topic scan-session/{id}).
```

#### Modèle de données (Phase 1)

Nouveaux champs sur `Manga` (tous nullable, pas de défaut DB) :

| Champ | Type | Sens |
|---|---|---|
| `publisher` | `?string` | Maison d'édition : « Glénat », « Ki-oon »… |
| `editionYear` | `?int` | Année de début de cette édition |
| `externalWorkId` | `?string` | Identifiant de l'œuvre abstraite (id Jikan/MAL ou slug normalisé) pour regrouper plusieurs éditions |

`edition` est **conservé** mais resémantisé en **label de variante** (« Édition Deluxe », « Perfect Edition », `null` = standard). Migration de données : pour les lignes existantes, `publisher = edition` puis `edition = NULL` lorsque `publisher IS NULL` (les valeurs actuelles d'`edition` sont des noms d'éditeurs). `Manga::toArray()` expose `publisher`, `editionYear`, `externalWorkId` ; le frontend compose l'affichage (`publisher` + label + année). Couverture du `Manga` = visuel de l'édition (tome 1).

#### Découverte d'éditions (Phase 1)

Nouveau port `EditionDiscoveryInterface::discoverEditions(string $workTitle, string $language): ExternalEditionDto[]`.

- `ExternalEditionDto` : `publisher`, `editionLabel`, `year`, `language`, `coverUrl` (tome 1), `volumeCount`, `sampleIsbn`, `source`.
- `EditionGrouper` (service Domain **pur**, unit-testable) : reçoit une liste normalisée `{publisher, year, volumeNumber, title, coverUrl, isbn}` et **regroupe par éditeur** ; par groupe : `year = min(année)`, `coverUrl` = couverture du tome 1 (sinon plus petit numéro avec cover), `volumeCount = max(numéro)` ou compte, `sampleIsbn` = un ISBN-13 du groupe, `editionLabel` = variante détectée dans les titres (mots-clés : « perfect », « deluxe », « édition originale », « double », « collector »… sinon `null`).
- `GoogleBooksEditionDiscoveryClient` (source principale) : `q={title}+manga`, `langRestrict={lang}`, parcourt quelques pages, normalise les items Google Books et délègue à `EditionGrouper`. Réutilise les patterns HTTP + l'apiKey existants.
- `OpenLibraryEditionDiscoveryClient` (complément) : exploite le modèle natif Work→Editions d'Open Library (`search.json` avec champs `editions.*`, ou `works/{id}/editions.json`), filtré par langue → `ExternalEditionDto`. Couvertures via `https://covers.openlibrary.org/b/isbn/{isbn}-L.jpg`.
- `CompositeEditionDiscoveryClient` (`!tagged_iterator app.edition_discovery_provider`) : fusionne les providers, **déduplique par (publisher + year)**, Google Books d'abord puis Open Library comble les trous.
- `NullEditionDiscoveryClient` : stub de test.
- Query/handler `DiscoverEditionsQuery` / `DiscoverEditionsHandler` → route `GET /api/manga/editions?title=…&lang=fr` (JWT requis).

#### Couvertures fidèles à l'édition (Phase 2)

- Nouveau VO `EditionContext{mangaTitle, publisher, editionLabel, year, language, externalWorkId}`.
- `MangaCoverProviderInterface::findByContext(EditionContext $context, int $volumeNumber)` remplace la signature `(title, edition, volumeNumber, language)`. Impacte Google/MangaDex/OpenLibrary/Composite/Null + `CoverBatchResolver` + `SearchVolumeExternalHandler` (un seul commit de refactor).
- Google Books `findByContext` : requête `"{title}" tome {n} {publisher}` + `langRestrict`, filtrage sur l'éditeur quand fourni.
- **Ancrage ISBN** : `SearchVolumeExternalQuery` + route `volume-search` acceptent un `isbn` optionnel ; si présent, `findByIsbn` (couverture exacte de l'édition) au lieu de `findByContext`. `CoverBatchResolver` privilégie déjà l'ISBN — on conserve.
- `UpdateVolume` (command/handler/request) accepte `isbn` pour **persister** l'ISBN scanné sur le `Volume` (re-résolutions futures exactes).

#### Scan ISBN (Phase 3)

Deux flux, repli saisie manuelle partout :

1. **Mobile (caméra)** — `useBarcodeScanner` (composable) : `BarcodeDetector` natif si présent, sinon ponyfill `barcode-detector` (ZXing-WASM, format `ean_13`). Gère le flux `getUserMedia({video:{facingMode:'environment'}})` et la boucle de détection. Composant `IsbnScanner.vue`.
2. **Desktop (QR → téléphone, via Mercure)** — l'écran ouvre une **session de scan** (`POST /api/manga/scan-session` → `{sessionId, mercureUrl, subscriberToken, topic}`, calqué sur `StartCoverBatchHandler`/`StartCoverBatchResult`) et s'abonne au topic Mercure `https://ziggytheque.app/scan-session/{id}` (`useScanSession`, calqué sur `useCoverBatchProgress`). Il affiche un QR encodant `${origin}/scan/{sessionId}`. Le téléphone (authentifié) ouvre `ScanRelayPage.vue`, scanne l'EAN-13, `POST /api/manga/scan-session/{sessionId}/isbn {isbn}` → `PublishScannedIsbnHandler` valide via `Isbn::fromString` (422 si invalide) et publie l'ISBN sur le topic. L'écran desktop reçoit l'ISBN en temps réel.

Ports Domain calqués sur l'existant : `ScanSessionAuthorizerInterface` (issue token/topic/hub) + `ScanSessionPublisherInterface` (`publishIsbn(sessionId, isbn)`), adaptateurs Mercure dédiés (topic `scan-session/{id}`). Une fois l'ISBN obtenu (n'importe quel flux), le front appelle `volume-search?isbn=…` puis `updateVolume(coverUrl, isbn)`.

#### Flux de données (ajout d'une série, cible)

```
[FE] AddMangaPage
  1. useExternalSearch ──► GET /api/manga/external (Jikan)         → choisir l'œuvre
  2. discoverEditions ──► GET /api/manga/editions?title&lang        → choisir l'édition
  3. importManga ──────► POST /api/manga {title,publisher,year,…}   → Manga + N tomes
     addToCollection ─► POST /api/collection {mangaId}              → CollectionEntry
  4. destination (collection | wishlist)
```

#### Découpage en phases / PRs (one commit per PR)

- **Phase 1** (T1–T4) : modèle + découverte d'éditions (backend) + endpoint `/api/manga/editions`.
- **Phase 2** (T5–T6) : couvertures fidèles (`EditionContext`, ISBN dans volume-search, ISBN persisté).
- **Phase 3** (T7–T10) : scan ISBN (relais backend + composables + composants + page mobile).
- **Phase 4** (T11–T13) : flux d'édition frontend (API/types, écran de choix d'édition, page série).
- **T14** : boucle finale lint/test/review.

Chaque phase est livrable seule ; les phases 2–4 dépendent de la 1 (champs + endpoint). Si tu préfères, chaque phase peut devenir un plan/PR séparé — le présent document les ordonne pour être exécutées séquentiellement.

#### Code retiré / remplacé (audit en un point)

- Signature `MangaCoverProviderInterface::findByContext(string, ?string, int, string)` → **supprimée** au profit de `findByContext(EditionContext, int)` (T5) : retire le paramètre `?string $edition` et `string $language` de toutes les implémentations + appels.
- `SearchVolumeExternalHandler::mapDtoToArray` : `'language' => 'fr'` codé en dur → dérivé de l'`EditionContext` (T5).
- Aucune suppression de fichier : `front/src/data/editions.ts` (logos éditeurs) et `BaseEditionSelector.vue` sont **conservés** comme repli de saisie manuelle et pour les logos d'éditeur.

#### Risques / notes

- **Qualité des données externes** : la « découverte d'éditions » est heuristique (regroupement par éditeur Google Books) ; couverture FR variable côté Open Library. `EditionGrouper` doit dégrader proprement (jamais d'exception ; éditions partielles acceptées). Le repli « saisie manuelle » reste disponible.
- **getUserMedia** exige un contexte sécurisé : OK en prod (HTTPS) et sur `http://localhost`. Sur desktop sans caméra, le bouton caméra affiche « indisponible » et l'utilisateur passe par le QR.
- **Auth du téléphone** : `ScanRelayPage` exige un JWT (`meta.requiresAuth`). Le QR ne transporte que le `sessionId` ; la publication ISBN est protégée par le JWT normal. Le token Mercure (desktop) reste scopé au topic de la session.
- `doctrine:schema:validate` doit rester vert : générer la migration via `make migration` (pas de noms d'index/FK écrits à la main).

---

### Tasks

- **T1** : enrichir l'entité `Manga` (publisher, editionYear, externalWorkId) + migration + import/update + tests.
- **T2** : `ExternalEditionDto` + service Domain `EditionGrouper` (pur) + tests unitaires.
- **T3** : `EditionDiscoveryInterface` + clients Google Books / Open Library / Composite / Null + câblage `services.yaml` + tests unitaires.
- **T4** : `DiscoverEditionsQuery`/`Handler` + route `GET /api/manga/editions` + test fonctionnel.
- **T5** : VO `EditionContext` + refactor `findByContext` + ancrage ISBN dans `volume-search` + tests.
- **T6** : `UpdateVolume` accepte/persiste `isbn` + test fonctionnel.
- **T7** : relais de session de scan backend (ports + adaptateurs Mercure + commands/handlers + routes + doubles de test) + tests.
- **T8** : dépendances front + composable `useBarcodeScanner` + tests.
- **T9** : composable `useScanSession` (EventSource desktop) + tests.
- **T10** : composants `IsbnScanner.vue` / `ScanViaPhone.vue` / page `ScanRelayPage.vue` + route `/scan/:sessionId` + intégration `EnrichVolumeModal` + i18n + tests.
- **T11** : couche API front (`discoverEditions`, `volume-search?isbn`, scan-session) + types.
- **T12** : composants `EditionCard.vue` / `EditionPicker.vue` + étape « choisir l'édition » dans `AddMangaPage` + i18n + tests.
- **T13** : page série (affichage éditeur/année + édition inline + passage de l'`EditionContext` à `EnrichVolumeModal`) + i18n.
- **T14** : boucle finale lint / test / file-reviewer.

---

#### Task 1 : Enrichir l'entité `Manga` (œuvre-en-édition)

Ajouter `publisher`, `editionYear`, `externalWorkId` à `Manga`, propager dans l'import et l'update, migrer les données existantes.

**Skills and docs to load:**
- `/project-quality-setup` — conventions DDD/naming + gate QA (PHPStan/Deptrac) sur l'entité et les handlers.
- `.claude/CLAUDE.md` — règles de mapping Doctrine (enum/length, défaut DB, `make migration`), tests obligatoires.
- `.claude/backend.md` — R3 (handler orchestrateur), R10 (nommage).

**Files:**
- Modify `back/src/Manga/Domain/Manga.php` — propriétés `?string $publisher`, `?int $editionYear`, `?string $externalWorkId` dans le constructeur (après `externalId`) ; ajouter au `toArray()`.
- Modify `back/src/Manga/Application/Import/ImportMangaCommand.php` — params `?string $publisher`, `?int $editionYear`, `?string $externalWorkId`.
- Modify `back/src/Manga/Application/Import/ImportMangaHandler.php` — passer les nouveaux champs au `new Manga(...)`.
- Modify `back/src/Manga/Infrastructure/Http/ImportMangaRequest.php` — champs `?string $publisher` (`Assert\Length(max:255)`), `?int $editionYear` (`Assert\Range(min:1900, max:2100)`), `?string $externalWorkId`.
- Modify `back/src/Manga/Infrastructure/Http/MangaController.php` — `import()` mappe les 3 nouveaux champs ; `update()` passe `publisher`/`editionYear` (voir UpdateMangaCommand).
- Modify `back/src/Manga/Application/Update/UpdateMangaCommand.php` + `UpdateMangaHandler.php` + `back/src/Manga/Infrastructure/Http/UpdateMangaRequest.php` — ajouter `?string $publisher`, `?int $editionYear` (appliqués si non-null).
- Create `back/migrations/Version<timestamp>.php` — généré par `make migration` (ALTER TABLE `mangas` ADD `publisher`, `edition_year`, `external_work_id`), puis ajouter à la main le backfill data : `UPDATE mangas SET publisher = edition, edition = NULL WHERE publisher IS NULL`.
- Modify `back/tests/Functional/Manga/MangaControllerTest.php` — import avec publisher/editionYear → vérifier exposition dans `GET /api/manga/{id}`.
- Modify `back/tests/Unit/Manga/Domain/MangaTest.php` — `toArray()` expose les nouveaux champs.

**Implementation**

Champs nullable sans défaut DB → pas d'`options:['default']`. `VARCHAR(255)` par défaut pour les strings (pas de `length:` sauf besoin). `editionYear` = `INT NULL`. Après ajout, lancer `make migration` puis vérifier `doctrine:schema:validate`. Le backfill est une migration de **données** (pas de nom d'index/FK à la main) — autorisé. Garder `edition` comme label de variante.

**Tests**

- Unit `MangaTest` : construire un `Manga` avec publisher='Glénat', editionYear=2019, externalWorkId='mal-42' → `toArray()` contient ces clés.
- Functional `MangaControllerTest` : `POST /api/manga {title, language, publisher:'Glénat', editionYear:2019}` (201) puis `GET /api/manga/{id}` → `publisher==='Glénat'`, `editionYear===2019`. Les cas 401/422/404 existants restent verts.

**Verify**

```bash
docker compose exec back php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec back php bin/console doctrine:schema:validate
docker compose exec back vendor/bin/phpunit tests/Functional/Manga/MangaControllerTest.php tests/Unit/Manga/Domain/MangaTest.php
```
Passe = migration appliquée, schéma valide, tests verts.

---

#### Task 2 : `ExternalEditionDto` + `EditionGrouper` (service Domain pur)

Modéliser une édition découverte et la logique de regroupement par éditeur, sans I/O.

**Skills and docs to load:**
- `/project-quality-setup` — service Domain pur, `final readonly`, naming.
- `.claude/backend.md` — R3 (logique métier hors handler, dans un service Domain), R10 (nommage).
- `.claude/CLAUDE.md` — tests unitaires obligatoires pour VO/service Domain.

**Files:**
- Create `back/src/Manga/Domain/ExternalEditionDto.php` — `final readonly` : `string $publisher`, `?string $editionLabel`, `?int $year`, `string $language`, `?string $coverUrl`, `?int $volumeCount`, `?string $sampleIsbn`, `string $source`. Méthode `toArray()`.
- Create `back/src/Manga/Domain/Service/EditionGrouper.php` — `final readonly`. Méthode `group(array $rawVolumes, string $language): array` (retourne `ExternalEditionDto[]`). `$rawVolumes` = liste de `array{publisher:string, year:?int, volumeNumber:?int, title:string, coverUrl:?string, isbn:?string}`.
- Create `back/tests/Unit/Manga/Domain/ExternalEditionDtoTest.php`.
- Create `back/tests/Unit/Manga/Domain/Service/EditionGrouperTest.php`.

**Implementation**

`EditionGrouper::group` : ignore les items sans `publisher` ; regroupe par `publisher` normalisé (trim + casse) ; par groupe → `year = min(year non-null)`, `coverUrl` = cover de l'item `volumeNumber===1` sinon plus petit `volumeNumber` ayant une cover sinon premier item avec cover, `volumeCount = max(volumeNumber)` sinon `count`, `sampleIsbn` = premier `isbn` non-null, `editionLabel` = détection de variante via `str_contains` (mots-clés `perfect|deluxe|originale|double|collector|ultimate`) sur les titres, sinon `null`. Aucune exception levée ; entrée vide → `[]`. Nommage complet des variables (R10).

**Tests**

- `ExternalEditionDtoTest` : construction + `toArray()`.
- `EditionGrouperTest` (toutes branches) : (a) 3 volumes même éditeur, années 2019/2020/2021 → 1 édition year=2019, volumeCount=3 ; (b) deux éditeurs distincts → 2 éditions ; (c) titre « Perfect Edition » → editionLabel='perfect' (ou libellé normalisé) ; (d) item sans publisher → ignoré ; (e) liste vide → `[]` ; (f) cover : tome 1 prioritaire sur tome 3.

**Verify**

```bash
docker compose exec back vendor/bin/phpunit tests/Unit/Manga/Domain/Service/EditionGrouperTest.php tests/Unit/Manga/Domain/ExternalEditionDtoTest.php
```

---

#### Task 3 : Clients de découverte d'éditions + câblage

Brancher la découverte sur Google Books (principal) et Open Library (complément), agrégés et dédupliqués.

**Skills and docs to load:**
- `/project-quality-setup` — hexagonal (port Domain, adaptateurs Infrastructure), tagged iterator.
- `.claude/backend.md` — R2/R4 (port Domain ↔ adaptateur Infrastructure), R6 (dépendances), R10.
- `.claude/CLAUDE.md` — `NullMangaApiClient`/`when@test` pour stubber l'HTTP, External API (Google Books).

**Files:**
- Create `back/src/Manga/Domain/EditionDiscoveryInterface.php` — `discoverEditions(string $workTitle, string $language = 'fr'): array` (`ExternalEditionDto[]`).
- Create `back/src/Manga/Infrastructure/ExternalApi/GoogleBooksEditionDiscoveryClient.php` — implémente `EditionDiscoveryInterface` ; injecte `HttpClientInterface`, `string $apiKey`, `EditionGrouper`, `LoggerInterface` ; parcourt 1–2 pages `/volumes` (`langRestrict`), normalise les items (`publisher`, année depuis `publishedDate`, `volumeNumber` depuis le titre/`seriesInfo`, `coverUrl` via la même extraction que `GoogleBooksMangaApiClient`, `isbn` depuis `industryIdentifiers`) → `EditionGrouper::group`.
- Create `back/src/Manga/Infrastructure/ExternalApi/OpenLibraryEditionDiscoveryClient.php` — implémente `EditionDiscoveryInterface` ; `HttpClientInterface` + `string $baseUrl` (réutiliser `OPEN_LIBRARY_COVERS_BASE_URL` ou ajouter `OPEN_LIBRARY_BASE_URL=https://openlibrary.org`) ; `search.json?q={title}&fields=…,editions,editions.*`, filtre `editions.language` sur la langue cible, map → `ExternalEditionDto` (cover par ISBN).
- Create `back/src/Manga/Infrastructure/ExternalApi/CompositeEditionDiscoveryClient.php` — `iterable $providers` (`!tagged_iterator app.edition_discovery_provider`) + logger ; concatène puis déduplique par `(publisher|year)`.
- Create `back/src/Manga/Infrastructure/ExternalApi/NullEditionDiscoveryClient.php` — retourne `[]`.
- Modify `back/config/services.yaml` — alias `App\Manga\Domain\EditionDiscoveryInterface: '@…CompositeEditionDiscoveryClient'` ; `CompositeEditionDiscoveryClient` `$providers: !tagged_iterator {tag: app.edition_discovery_provider}` ; taguer Google (priorité 100) et OpenLibrary (50) ; `GoogleBooksEditionDiscoveryClient` `$apiKey: '%env(GOOGLE_BOOKS_API_KEY)%'` ; `OpenLibraryEditionDiscoveryClient` `$baseUrl`. Dans `when@test:`, alias `EditionDiscoveryInterface → NullEditionDiscoveryClient`.
- Modify `back/.env` — ajouter `OPEN_LIBRARY_BASE_URL=https://openlibrary.org` si non réutilisation du covers base url.
- Create `back/tests/Unit/Manga/Infrastructure/GoogleBooksEditionDiscoveryClientTest.php`.
- Create `back/tests/Unit/Manga/Infrastructure/OpenLibraryEditionDiscoveryClientTest.php`.
- Create `back/tests/Unit/Manga/Infrastructure/CompositeEditionDiscoveryClientTest.php`.

**Implementation**

Suivre le style HTTP de `GoogleBooksMangaApiClient` (try/catch → `[]`, normalisation URL cover `http→https`, retrait `&edge=curl`). `EditionGrouper` est injecté dans le client Google (la grosse logique reste pure/testée en T2). Le Composite suit `CompositeMangaCoverApiClient` (itère le tagged iterator). `final readonly` partout. Pas de FQCN built-in (importer `Throwable`).

**Tests** (`MockHttpClient` + `MockResponse`, calqués sur `GoogleBooksMangaApiClientTest`)

- Google : réponse avec 2 éditeurs × 3 tomes → 2 `ExternalEditionDto` correctes ; HTTP 503 → `[]` ; items sans publisher → ignorés.
- OpenLibrary : réponse `search.json` avec éditions FR/EN, langue='fr' → ne garde que FR.
- Composite : deux providers renvoyant un doublon `(Glénat|2019)` → une seule édition (Google prioritaire).

**Verify**

```bash
docker compose exec back vendor/bin/phpunit tests/Unit/Manga/Infrastructure
docker compose exec back php bin/console lint:container
```

---

#### Task 4 : Endpoint `GET /api/manga/editions`

Exposer la découverte d'éditions au frontend.

**Skills and docs to load:**
- `/project-quality-setup` — CQRS query/handler, controller mince.
- `.claude/backend.md` — R3 (handler orchestrateur).
- `.claude/CLAUDE.md` — tests fonctionnels obligatoires (tous les codes HTTP).

**Files:**
- Create `back/src/Manga/Application/DiscoverEditions/DiscoverEditionsQuery.php` — `string $title`, `string $language = 'fr'`.
- Create `back/src/Manga/Application/DiscoverEditions/DiscoverEditionsHandler.php` — `#[AsMessageHandler(bus:'query.bus')]`, injecte `EditionDiscoveryInterface`, mappe `ExternalEditionDto[]` → `array` via `toArray()`.
- Modify `back/src/Manga/Infrastructure/Http/MangaController.php` — `#[Route('/editions', methods:['GET'])]` (placer **avant** `/{id}` pour éviter la capture par la route paramétrée) ; lit `title` + `lang` (défaut `fr`) → `DiscoverEditionsQuery`.
- Modify `back/tests/Functional/Manga/MangaControllerTest.php` — `/api/manga/editions`.

**Implementation**

Comme `searchExternal`/`searchVolumeExternal` : lecture des query params, `queryBus->ask(...)`, `JsonResponse`. En test, `NullEditionDiscoveryClient` renvoie `[]`. Attention à l'ordre des routes : `/editions` et `/external`/`/volume-search` sont déjà déclarées avant `/{id}` → garder ce placement.

**Tests**

- `testDiscoverEditionsReturnsEmpty` : `GET /api/manga/editions?title=Berserk&lang=fr` → 200 + `[]` (Null en test).
- `testDiscoverEditionsRequiresAuth` : sans token → 401.

**Verify**

```bash
docker compose exec back vendor/bin/phpunit tests/Functional/Manga/MangaControllerTest.php
```

---

#### Task 5 : `EditionContext` + couvertures fidèles à l'édition

Transmettre l'éditeur/année/langue à la résolution de couverture et permettre l'ancrage par ISBN.

**Skills and docs to load:**
- `/project-quality-setup` — refactor d'interface propre, VO `final readonly`.
- `.claude/backend.md` — R4 (port Domain), R10.
- `.claude/CLAUDE.md` — mettre à jour tous les tests des objets modifiés.

**Files:**
- Create `back/src/Manga/Domain/EditionContext.php` — `final readonly` : `string $mangaTitle`, `?string $publisher`, `?string $editionLabel`, `?int $year`, `string $language = 'fr'`, `?string $externalWorkId = null`.
- Modify `back/src/Manga/Domain/MangaCoverProviderInterface.php` — `findByContext(EditionContext $context, int $volumeNumber): ?MangaVolumeCoverDto`.
- Modify `back/src/Manga/Infrastructure/ExternalApi/GoogleBooksMangaApiClient.php` — nouvelle signature ; requête `"{title}" tome {n} {publisher}` + `langRestrict={context->language}` ; filtrage éditeur si fourni.
- Modify `back/src/Manga/Infrastructure/ExternalApi/MangaDexMangaApiClient.php` + `OpenLibraryCoversApiClient.php` + `CompositeMangaCoverApiClient.php` — nouvelle signature (MangaDex/OpenLibrary utilisent `context->language`/titre).
- Modify `back/src/Manga/Infrastructure/ExternalApi/NullMangaCoverApiClient.php` — nouvelle signature.
- Modify `back/src/Manga/Domain/Service/CoverBatchResolver.php` — `resolveCover` construit un `EditionContext` depuis le `Manga` (title, publisher, edition, editionYear, language, externalWorkId) ; conserve l'ISBN prioritaire.
- Modify `back/src/Manga/Application/SearchVolumeExternal/SearchVolumeExternalQuery.php` — ajouter `?string $isbn = null` ; champs `publisher`/`year`/`externalWorkId` optionnels (ou réutiliser `edition` comme label).
- Modify `back/src/Manga/Application/SearchVolumeExternal/SearchVolumeExternalHandler.php` — si `isbn` présent → `Isbn::tryFrom` puis `findByIsbn` ; sinon construire `EditionContext` et `findByContext`. `mapDtoToArray` : `language` issu du contexte (retirer le `'fr'` codé en dur).
- Modify `back/src/Manga/Infrastructure/Http/MangaController.php` — `searchVolumeExternal()` lit `isbn`, `publisher`, `year` et les passe à la query.
- Modify tests : `GoogleBooksMangaApiClientTest`, `MangaDexMangaApiClientTest`, `OpenLibraryCoversApiClientTest`, `CompositeMangaCoverApiClientTest`, `CoverBatchResolverTest` (nouvelle signature) ; `MangaControllerTest::testVolumeSearch*` (param `isbn`).

**Implementation**

Refactor en **un seul commit** (changement d'interface). Importer `EditionContext` partout. `findByContext` reçoit désormais `$context->mangaTitle`, `$context->publisher`, etc. Les implémentations MangaDex/OpenLibrary qui n'exploitaient que titre/volume restent fonctionnellement identiques (lisent `$context->mangaTitle`, `$context->language`). En test, `NullMangaCoverApiClient` renvoie `null` → `volume-search` reste `[]`.

**Tests**

- Providers : adapter les appels `findByContext(...)` à `new EditionContext(...)`. Ajouter pour Google : un cas où `publisher` est passé → la query contient l'éditeur (assert sur l'URL via `MockResponse` callback).
- `CoverBatchResolverTest` : signature mise à jour, comportement inchangé (ISBN prioritaire).
- Functional : `GET /api/manga/volume-search?q=x&isbn=9782344…` → 200 `[]` en test (Null) ; `isbn` invalide via volume-search ne doit pas crasher (tryFrom → ignore).

**Verify**

```bash
docker compose exec back vendor/bin/phpunit tests/Unit/Manga tests/Functional/Manga/MangaControllerTest.php
```

---

#### Task 6 : `UpdateVolume` persiste l'ISBN

Permettre d'enregistrer l'ISBN (scanné ou saisi) sur un tome.

**Skills and docs to load:**
- `/project-quality-setup` — command/handler, validation.
- `.claude/backend.md` — R3.
- `.claude/CLAUDE.md` — VO `Isbn`, mapping 422, mise à jour du test fonctionnel.

**Files:**
- Modify `back/src/Manga/Application/UpdateVolume/UpdateVolumeCommand.php` — ajouter `?string $isbn = null`.
- Modify `back/src/Manga/Application/UpdateVolume/UpdateVolumeHandler.php` — si `isbn` non-null → `$volume->isbn = Isbn::fromString($command->isbn)` (lève `InvalidIsbnException` → 422).
- Modify `back/src/Manga/Infrastructure/Http/UpdateVolumeRequest.php` — ajouter `?string $isbn = null`.
- Modify `back/src/Manga/Infrastructure/Http/MangaController.php` — `updateVolume()` passe `isbn`.
- Modify `back/tests/Functional/Manga/MangaControllerTest.php` — update volume avec isbn valide/invalide.

**Implementation**

`Isbn::fromString` côté handler garantit la validation (422 automatique via `ExceptionListener`). Ne pas écraser un ISBN existant par `null`.

**Tests**

- `testUpdateVolumeWithValidIsbn` : PATCH `{isbn:'9782344020812'}` → 204 ; `GET` détail → `isbn` persisté (forme canonique 13 chiffres).
- `testUpdateVolumeWithInvalidIsbn` : PATCH `{isbn:'123'}` → 422.

**Verify**

```bash
docker compose exec back vendor/bin/phpunit tests/Functional/Manga/MangaControllerTest.php
```

---

#### Task 7 : Relais de session de scan (backend)

Démarrer une session, recevoir un ISBN scanné par le téléphone, le pousser en temps réel via Mercure.

**Skills and docs to load:**
- `/project-quality-setup` — ports Domain + adaptateurs Mercure, command/handler.
- `.claude/backend.md` — R2/R4 (ports ↔ adaptateurs), R3, R10.
- `.claude/CLAUDE.md` — `when@test` + doubles, tests fonctionnels (tous codes HTTP), Mercure.

**Files:**
- Create `back/src/Manga/Domain/ScanSessionAuthorizerInterface.php` — `issueSubscriberToken(string $sessionId, int $ttlSeconds): string`, `topicFor(string $sessionId): string`, `publicHubUrl(): string`.
- Create `back/src/Manga/Domain/ScanSessionPublisherInterface.php` — `publishIsbn(string $sessionId, string $isbn): void`.
- Create `back/src/Manga/Infrastructure/Mercure/MercureScanSessionAuthorizer.php` — calqué sur `MercureCoverBatchSubscriberAuthorizer`, topic `https://ziggytheque.app/scan-session/{id}`.
- Create `back/src/Manga/Infrastructure/Mercure/MercureScanSessionPublisher.php` — calqué sur `MercureCoverBatchProgressPublisher` ; `publishIsbn` publie `json_encode(['type'=>'isbn_scanned','sessionId'=>…,'isbn'=>…])` (private=1).
- Create `back/src/Manga/Application/StartScanSession/StartScanSessionCommand.php` (vide) + `StartScanSessionHandler.php` (`#[AsMessageHandler(bus:'command.bus')]`, génère `sessionId` via `Uuid`, issue token/topic/hub) + `StartScanSessionResult.php` (`sessionId, mercureUrl, subscriberToken, topic` + `toArray()`).
- Create `back/src/Manga/Application/PublishScannedIsbn/PublishScannedIsbnCommand.php` (`string $sessionId`, `string $isbn`) + `PublishScannedIsbnHandler.php` (valide `Isbn::fromString`, appelle `publishIsbn`).
- Create `back/src/Manga/Infrastructure/Http/ScanIsbnRequest.php` — `#[Assert\NotBlank] string $isbn`.
- Modify `back/src/Manga/Infrastructure/Http/MangaController.php` — `POST /api/manga/scan-session` (202 → `result->toArray()`) ; `POST /api/manga/scan-session/{sessionId}/isbn` (`#[MapRequestPayload] ScanIsbnRequest`, 202).
- Modify `back/config/services.yaml` — aliases `ScanSessionAuthorizerInterface`/`ScanSessionPublisherInterface` → adaptateurs Mercure (mêmes `$…JwtKey`/`$hubUrl`/`$publicHubUrlValue` que cover-batch) ; `when@test:` → doubles.
- Create `back/tests/Doubles/Manga/StubScanSessionAuthorizer.php` + `InMemoryScanSessionPublisher.php` (calqués sur les doubles cover-batch existants).
- Create `back/tests/Functional/Manga/ScanSessionControllerTest.php`.
- Create `back/tests/Unit/Manga/Application/PublishScannedIsbnHandlerTest.php` (avec `InMemoryScanSessionPublisher`).

**Implementation**

Réutiliser intégralement le pattern cover-batch (déjà testé en prod). `StartScanSessionResult` a la même forme que `StartCoverBatchResult` + `sessionId`. `PublishScannedIsbnHandler` : `Isbn::fromString($command->isbn)` (422 si invalide), puis `publishIsbn($sessionId, $isbn->value)`. La publication est **synchrone** (comme l'issue de token cover-batch). Importer `Throwable`/`Uuid` proprement.

**Tests**

- Functional : `POST /api/manga/scan-session` → 202 + clés `sessionId,mercureUrl,subscriberToken,topic` (topic contient sessionId, via `StubScanSessionAuthorizer`) ; 401 sans token. `POST …/{id}/isbn {isbn:'9782344020812'}` → 202 ; `{isbn:'123'}` → 422 ; 401 sans token.
- Unit `PublishScannedIsbnHandlerTest` : ISBN valide → 1 event dans `InMemoryScanSessionPublisher` (sessionId+isbn canonique) ; ISBN invalide → `InvalidIsbnException`.

**Verify**

```bash
docker compose exec back vendor/bin/phpunit tests/Functional/Manga/ScanSessionControllerTest.php tests/Unit/Manga/Application/PublishScannedIsbnHandlerTest.php
docker compose exec back php bin/console lint:container
```

---

#### Task 8 : Dépendances front + composable `useBarcodeScanner`

Lecture d'un code-barres EAN-13 via la caméra, avec ponyfill desktop.

**Skills and docs to load:**
- `/create-adaptable-composable` — entrées `MaybeRefOrGetter`, normalisation `toValue()`, effets réactifs + nettoyage.
- `/vue-best-practices` — `<script setup>` + TS, lifecycle.
- `/vue-testing-best-practices` — mock de `BarcodeDetector`/`getUserMedia` sous jsdom.

**Files:**
- Modify `front/package.json` — deps `barcode-detector` (ponyfill) + `qrcode` ; dev `@types/qrcode`.
- Create `front/src/composables/useBarcodeScanner.ts`.
- Create `front/src/composables/__tests__/useBarcodeScanner.spec.ts`.

**Implementation**

`useBarcodeScanner(videoRef: MaybeRefOrGetter<HTMLVideoElement | null>)` : expose `{ isSupported, isScanning, error, start(), stop(), onDetected(cb) }`. Détecteur : `('BarcodeDetector' in globalThis)` → natif, sinon `import('barcode-detector/ponyfill')` → `new BarcodeDetector({formats:['ean_13']})`. `start()` : `getUserMedia({video:{facingMode:'environment'}})`, attache au `<video>`, boucle `requestAnimationFrame`/`setInterval(detect, 250ms)` ; au 1er code `ean_13` valide → callback + `stop()`. `stop()` coupe les `MediaStreamTrack` et la boucle. Nettoyage via `onScopeDispose` (cf. `useCoverBatchProgress`). `isSupported=false` si ni natif ni ponyfill ni caméra.

**Tests**

- Mock `navigator.mediaDevices.getUserMedia` + stub global `BarcodeDetector` renvoyant `[{rawValue:'9782344020812', format:'ean_13'}]` → `onDetected` reçoit la valeur, `stop()` libère les tracks (spy `track.stop`).
- Sans `getUserMedia` → `isSupported=false`, `start()` positionne `error`.

**Verify**

```bash
cd front && npm install && npm run test -- useBarcodeScanner && npm run type-check
```

---

#### Task 9 : Composable `useScanSession` (desktop)

Démarrer une session et recevoir l'ISBN poussé par le téléphone.

**Skills and docs to load:**
- `/create-adaptable-composable` — composable réactif + nettoyage `onScopeDispose`.
- `/vue-best-practices` — TS, `<script setup>`.
- `/vue-testing-best-practices` — mock `EventSource`.

**Files:**
- Create `front/src/composables/useScanSession.ts`.
- Create `front/src/composables/__tests__/useScanSession.spec.ts`.

**Implementation**

Calqué sur `useCoverBatchProgress`. `start(callbacks:{onIsbn:(isbn:string)=>void})` : appelle `startScanSession()` (api, T11) → `{sessionId,mercureUrl,subscriberToken,topic}` ; construit l'`EventSource(`${mercureUrl}?topic=…&authorization=token`)` ; `onmessage` → parse, si `type==='isbn_scanned'` → `onIsbn(isbn)`. Expose `{ sessionId, pairingUrl, start, close }` où `pairingUrl = `${location.origin}/scan/${sessionId}``. `close()` ferme l'`EventSource` ; `onScopeDispose(close)`.

**Tests**

- Mock global `EventSource` ; après `start`, simuler un message `{"type":"isbn_scanned","isbn":"9782344020812"}` → `onIsbn` appelé ; `pairingUrl` contient le sessionId ; `close()` appelle `eventSource.close`.

**Verify**

```bash
cd front && npm run test -- useScanSession && npm run type-check
```

---

#### Task 10 : Composants de scan + page mobile + intégration

UI de scan caméra (mobile), QR « envoyer sur le téléphone » (desktop), page de relais mobile, branchement dans la fiche tome.

**Skills and docs to load:**
- `/vue-best-practices` — composants `<script setup>` + TS, props/emits typés.
- `/vue-router-best-practices` — route `/scan/:sessionId` + `meta.requiresAuth` + param.
- `/vue-testing-best-practices` — montage @vue/test-utils, mock des composables.

**Files:**
- Create `front/src/components/molecules/IsbnScanner.vue` — utilise `useBarcodeScanner` ; `<video>` + bouton start/stop ; `@detected="isbn"` ; affiche « caméra indisponible » si `!isSupported`.
- Create `front/src/components/molecules/ScanViaPhone.vue` — utilise `useScanSession` + `qrcode` (rendu dans un `<canvas>`/`<img>`) ; émet `@isbn`.
- Create `front/src/pages/ScanRelayPage.vue` — plein écran ; lit `route.params.sessionId` ; `IsbnScanner` ; au scan → `postScannedIsbn(sessionId, isbn)` (T11) → toast « Envoyé » ; permet d'enchaîner les scans.
- Modify `front/src/router/index.ts` — route top-level `{ path:'/scan/:sessionId', name:'scan-relay', component: ScanRelayPage, meta:{ requiresAuth:true, title:'Scanner' } }`.
- Modify `front/src/components/organisms/EnrichVolumeModal.vue` — section « Scanner l'ISBN » : `IsbnScanner` (caméra) + `ScanViaPhone` (desktop) + champ ISBN manuel ; à l'obtention d'un ISBN → `searchVolumeExternal(q, 1, vol.number, edition, provider, isbn)` puis `updateVolume(mangaId, volumeId, {coverUrl, isbn})`.
- Modify `front/src/i18n/fr.json` + `front/src/i18n/en.json` — namespace `scan.*` (`title`, `cameraUnavailable`, `viaPhone`, `scanWithPhone`, `manualIsbn`, `sent`, `apply`…).
- Create `front/src/components/molecules/__tests__/IsbnScanner.spec.ts` + `__tests__/ScanViaPhone.spec.ts`.

**Implementation**

`EnrichVolumeModal` reçoit déjà `mangaEdition` ; lui ajouter `mangaPublisher`/`mangaEditionYear`/`mangaExternalWorkId` (props) pour la recherche par contexte (passés par `MangaDetailPage`, T13). Heuristique d'affichage : toujours proposer caméra + QR + saisie ; sur desktop sans caméra, `IsbnScanner` montre « indisponible » et l'utilisateur utilise le QR. Réutiliser `coverUrl()` pour l'aperçu.

**Tests**

- `IsbnScanner.spec` : avec `useBarcodeScanner` mocké (`isSupported=true`), déclencher `onDetected` → l'événement `detected` est émis avec l'ISBN ; `isSupported=false` → message d'indisponibilité rendu.
- `ScanViaPhone.spec` : `useScanSession` mocké → un QR est rendu (présence du `<canvas>`/`<img>`), un message `onIsbn` émet `isbn`.

**Verify**

```bash
cd front && npm run test && npm run type-check && npm run lint:check
```

---

#### Task 11 : Couche API frontend + types

Exposer les nouveaux endpoints et champs au frontend.

**Skills and docs to load:**
- `/vue-best-practices` — typage TS strict de la couche API.
- `.claude/CLAUDE.md` — couche API front (`api/*.ts`), endpoints.

**Files:**
- Modify `front/src/api/manga.ts` — `discoverEditions(title, lang)` → `GET /api/manga/editions` ; étendre `searchVolumeExternal(..., isbn?)` ; `updateVolume` payload `+ isbn?` ; `startScanSession()` → `POST /api/manga/scan-session` ; `postScannedIsbn(sessionId, isbn)` → `POST /api/manga/scan-session/{id}/isbn`. Ajouter type `DiscoveredEdition` et `ScanSessionStartResponse`.
- Modify `front/src/api/collection.ts` — n/a (inchangé) sauf si types partagés.
- Modify `front/src/types/index.ts` — `Manga` : `publisher: string | null`, `editionYear: number | null`, `externalWorkId: string | null`. `importManga` payload (dans api/manga.ts) `+ publisher?, editionYear?, externalWorkId?`.

**Implementation**

`DiscoveredEdition = { publisher:string; editionLabel:string|null; year:number|null; language:string; coverUrl:string|null; volumeCount:number|null; sampleIsbn:string|null; source:string }`. `ScanSessionStartResponse = { sessionId; mercureUrl; subscriberToken; topic }` (réutiliser la forme de `CoverBatchStartResponse`).

**Verify**

```bash
cd front && npm run type-check
```

---

#### Task 12 : Écran « choisir l'édition » dans le flux d'ajout

Insérer l'étape de découverte d'éditions entre la recherche d'œuvre et le formulaire.

**Skills and docs to load:**
- `/vue-best-practices` — `<script setup>`, état d'étapes, vue-query (`useQuery`).
- `/vue-testing-best-practices` — montage + mock API.
- `.claude/CLAUDE.md` — seules les pages appellent `useQuery`/`useMutation`.

**Files:**
- Create `front/src/components/molecules/EditionCard.vue` — affiche logo éditeur (via `FRENCH_EDITIONS`), `publisher`, `year`, cover tome 1, `volumeCount` ; `@select`.
- Create `front/src/components/organisms/EditionPicker.vue` — sélecteur de langue (FR par défaut) + grille d'`EditionCard` depuis `discoverEditions` ; état vide → bouton « Saisir manuellement ».
- Modify `front/src/pages/AddMangaPage.vue` — nouvelle étape 2 « Choisir l'édition » : après `applyResult(work)`, appeler `discoverEditions(work.title, lang)` ; sélection d'une édition → pré-remplir `form` (`publisher`, `editionYear`, `edition` label, `coverUrl`, `totalVolumes`, `externalWorkId`) ; renuméroter les étapes (1 recherche / 2 édition / 3 infos / 4 destination) ; le formulaire (ex-étape 2) gagne les champs `publisher`/`editionYear`.
- Modify `front/src/i18n/fr.json` + `en.json` — `add.chooseEdition`, `add.editionsFor`, `add.noEditionsFound`, `manga.publisher`, `manga.editionYear`.
- Create `front/src/components/organisms/__tests__/EditionPicker.spec.ts`.

**Implementation**

`discoverEditions` via `useQuery` côté page (clé `['editions', title, lang]`, `enabled` quand on entre à l'étape 2). `applyResult` ne saute plus directement au formulaire mais à l'étape « édition ». `importManga` (mutation) envoie désormais `publisher`/`editionYear`/`externalWorkId`. Conserver le repli « Saisir manuellement » qui passe à l'étape formulaire avec un form vide.

**Tests**

- `EditionPicker.spec` : `discoverEditions` mocké renvoyant 2 éditions → 2 `EditionCard` ; clic → événement `select` avec l'édition ; réponse vide → bouton manuel visible.

**Verify**

```bash
cd front && npm run test -- EditionPicker && npm run type-check && npm run lint:check
```

---

#### Task 13 : Page série — affichage éditeur/année + contexte d'édition

Afficher l'édition structurée et alimenter la recherche de couverture par le contexte d'édition.

**Skills and docs to load:**
- `/vue-best-practices` — `<script setup>`, édition inline, props typées.
- `.claude/CLAUDE.md` — atomic design, pages appellent les mutations.

**Files:**
- Modify `front/src/pages/MangaDetailPage.vue` — en-tête : afficher `publisher` (logo via `FRENCH_EDITIONS`) + `editionYear` + label `edition` ; édition inline pour `publisher`/`editionYear` (étendre `updateMangaMutation` payload `{publisher?, editionYear?}`) ; passer `mangaPublisher`/`mangaEditionYear`/`mangaExternalWorkId` à `<EnrichVolumeModal>`.
- Modify `front/src/api/manga.ts` — `updateManga` payload `+ publisher?, editionYear?`.
- Modify `front/src/i18n/fr.json` + `en.json` — libellés `manga.publisher`/`manga.editionYear` (réutilisés de T12).

**Implementation**

L'affichage compose `publisher` + (`edition` ? « — »+label) + (`editionYear` ? « ("+year+") »). `BaseEditionSelector` reste pour éditer le `publisher` (la liste `FRENCH_EDITIONS` correspond à des éditeurs) ; ajouter un petit champ année. `EnrichVolumeModal` (T10) consomme les nouveaux props pour `searchVolumeExternal`.

**Tests**

Pas de test ajouté (page d'intégration ; la logique testable — recherche par contexte/scan — est couverte en T8–T12). Vérification manuelle + type-check.

**Verify**

```bash
cd front && npm run type-check && npm run lint:check && npm run build
```

---

#### Task 14 : Final lint, test, and review loop.

Once finished, run lint and test for the affected sub-directories, dump the git diff in a tmp file with `git diff HEAD > /tmp/last-review.diff`, then spawn a `file-reviewer` subagent with the prompt: *"Review the diff at /tmp/last-review.diff. Invoke the `code-review` skill, follow its 'Load thematic rules by file type' step using the diff's file paths, and write structured findings."*
Fix every finding, then redo all three steps. Repeat until lint passes, tests pass, and the subagent returns no findings (max 5 iterations).
