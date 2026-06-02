# Recherche de covers via ISBN (saisie, scan caméra, hand-off téléphone)

### TL;DR

> [!NOTE]
> On ajoute un **mode « ISBN »** dans la fenêtre de choix de couverture, à côté des modes existants
> (coller une URL / chercher via les API). Ce mode propose **quatre façons** d'obtenir une couverture à
> partir du code-barres unique d'un tome :
>
> 1. **ISBN déjà connu** — si le tome a déjà un ISBN enregistré, ouvrir l'onglet déclenche
>    automatiquement la recherche de couverture (et l'applique si le tome n'en a pas encore).
> 2. **Saisie manuelle** — un champ texte où l'on tape un ISBN, puis on récupère la couverture.
> 3. **Scan caméra** — un bouton « Scanner » qui ouvre la caméra de l'appareil et lit le code-barres
>    EAN-13 du livre ; utile directement depuis un téléphone ou une tablette.
> 4. **Hand-off PC → téléphone** — sur ordinateur (souvent sans caméra), un bouton génère un **QR code**
>    et un lien à usage unique. On l'ouvre sur son téléphone, on scanne le code-barres, et la couverture
>    **apparaît et se met à jour toute seule sur l'écran de l'ordinateur**, en temps réel, sans rien
>    recharger.
>
> Le socle technique est déjà en place : la base sait stocker un ISBN par tome, les fournisseurs de
> couvertures (Google Books, Open Library, MangaDex) savent déjà chercher par ISBN, et le canal temps réel
> (Mercure) est déjà utilisé ailleurs dans l'app. Le travail consiste surtout à **assembler ces briques**,
> ajouter une route publique minimale pour le téléphone, et brancher deux librairies front (génération de
> QR code + lecture de code-barres). Aucune migration de base n'est nécessaire.
>
> Limite connue à anticiper : l'accès caméra du navigateur n'est autorisé qu'en **HTTPS ou sur localhost**.
> Le scan téléphone fonctionne donc en production (site en HTTPS) ; en dev local sur IP LAN il faut un
> tunnel HTTPS. La saisie manuelle reste un repli universel.

---

### Implementation

#### État actuel

La modale `EnrichVolumeModal.vue` permet déjà de choisir une couverture de tome de deux manières :
coller une URL, ou lancer une recherche d'image via des **providers** (`composite`, `mangadex`,
`openlibrary`, `googlebooks`) sur `GET /api/manga/volume-search`. Le choix est appliqué via
`PATCH /api/manga/{id}/volumes/{volumeId}` (commande `UpdateVolumeCommand`).

Côté back, tout l'outillage ISBN existe **déjà** :

```
Volume.isbn : ?Isbn            (colonne `isbn` VARCHAR(20), type Doctrine `IsbnType`, déjà migrée)
Isbn (VO)                       fromString()/tryFrom(), valide ISBN-10/13, forme canonique 13 chiffres
MangaCoverProviderInterface     findByIsbn(Isbn): ?MangaVolumeCoverDto   ← cherche une cover par ISBN
                                findByContext(title, edition, volumeNumber): ?MangaVolumeCoverDto
  ├─ GoogleBooksMangaApiClient  (q=isbn:...)
  ├─ OpenLibraryCoversApiClient (covers.openlibrary.org/b/isbn/{isbn}-L.jpg)
  ├─ MangaDexMangaApiClient
  └─ CompositeMangaCoverApiClient (cascade)
MangaVolumeCoverDto { coverUrl, spineUrl, isbn, source }
```

Mais `findByIsbn` n'est **exposé par aucune route HTTP** : `volume-search` ne sait chercher que par
titre (`findByContext`). Il manque donc une route « résoudre une cover à partir d'un ISBN ».

Le canal temps réel **Mercure** est déjà opérationnel et sert l'auto-remplissage de couvertures :

```
StartCoverBatchHandler ──> CoverBatchSubscriberAuthorizerInterface.issueToken(id, ttl)/topicFor(id)/publicHubUrl()
                      └──> renvoie { batchId, mercureUrl, subscriberToken, topic }   (StartCoverBatchResult)
Front: useCoverBatchProgress.start({mercureUrl, topic, subscriberToken}) ──> EventSource(`${mercureUrl}?topic=&authorization=`)
Back publie via CoverBatchProgressPublisherInterface ──> MercureCoverBatchProgressPublisher (POST hub, JWT publisher, topic `https://ziggytheque.app/cover-batch/{id}`, private=1)
Doubles de test: InMemoryCoverBatchProgressPublisher, StubCoverBatchSubscriberAuthorizer
```

Auth : firewall `^/api` = JWT obligatoire (`ROLE_USER`). Quelques routes sont publiques via
`access_control` + `PUBLIC_ACCESS` (ex. `^/api/auth/login`). Les `DomainException` sont transformées en
réponse JSON `{ error }` + code HTTP par `ExceptionListener` (`InvalidIsbnException` → **422**,
`NotFoundException` → **404**).

Front : SPA Vue 3 (`<script setup>` + TS), routeur avec routes `meta: { public: true }` (mais le guard
**redirige un utilisateur connecté hors d'une route publique**, sauf `verify-email`/`reset-password`),
client axios `client` (baseURL `/api`, Bearer auto si token présent), stores Pinia (`useUiStore.addToast`).
Aucune librairie de QR code ni de lecture de code-barres n'est installée. Infra de test front présente
(`vitest`, pattern `MockEventSource` dans `useCoverBatchProgress.spec.ts`).

#### État cible

Nouvelles routes HTTP :

| Méthode | Route | Auth | Rôle |
|---|---|---|---|
| GET | `/api/manga/cover-by-isbn?isbn=…` | JWT (`ROLE_USER`) | Résout une cover depuis un ISBN → `{coverUrl, spineUrl, isbn, source}` ou `null` |
| PATCH | `/api/manga/{id}/volumes/{volumeId}` | JWT | **étendu** : accepte désormais `isbn` (persiste l'ISBN sur le tome) |
| POST | `/api/scan/sessions` | JWT | Crée une session de scan → `{sessionId, scanToken, mercureUrl, subscriberToken, topic}` |
| POST | `/api/scan/submit` | **PUBLIC** | Le téléphone envoie `{scanToken, isbn}` → publie l'ISBN sur le topic de la session |

Séquence du hand-off PC → téléphone (exigence 4) :

```
 PC (modale, onglet ISBN)                Backend                         Téléphone (/scan/:token)
 ───────────────────────                 ───────                         ────────────────────────
 1. clic « Scanner avec mon tél. »
    POST /api/scan/sessions {mangaId,volumeId}
        ───────────────────────────────────────►
                                  génère sessionId (uuid)
                                  scanToken = ScanTokenIssuer.issue(sessionId, 600s)   [JWT sid+exp]
                                  subscriberToken/topic/mercureUrl via CoverBatchSubscriberAuthorizer
        ◄───────────────────────────────────────
        {sessionId, scanToken, mercureUrl, subscriberToken, topic}
 2. affiche <BaseQrCode value=`${origin}/scan/${scanToken}`>
 3. useScanSession.start({mercureUrl,topic,subscriberToken})
    EventSource(`${mercureUrl}?topic=&authorization=`)  (s'abonne)
                                                                        4. ouvre l'URL du QR
                                                                           caméra lit l'EAN-13 → isbn
                                                                        5. POST /api/scan/submit {scanToken,isbn}
                                                        ◄──────────────────────────────────────────────
                                          ScanTokenIssuer.verify(scanToken) → sessionId  (sinon 410)
                                          Isbn::fromString(isbn)                          (sinon 422)
                                          ScanResultPublisher.publish(sessionId, isbn)
                                          ── POST hub Mercure topic cover-batch/{sessionId} data={isbn} ──►
        ◄═══════════ SSE {isbn} ═══════════════════════════════════════════════════════
 6. onResult(isbn): coverByIsbn(isbn) → updateVolume(mangaId,volumeId,{coverUrl,spineUrl,isbn})
    → invalide ['collection', collectionEntryId] → la page se recharge toute seule + toast
```

Choix structurants :

- **Le endpoint public ne fait qu'un relais.** `/api/scan/submit` ne fait **aucune écriture en base** :
  il vérifie le `scanToken` (jeton de capacité court, signé) et publie l'ISBN sur le topic de la session.
  Toute la résolution de couverture + la persistance se font **côté PC authentifié** (qui écoute Mercure).
  Surface publique minimale, aucune mutation derrière une route non authentifiée.
- **Jeton de capacité `scanToken`** = JWT signé HS256 (lib `lcobucci/jwt`, déjà présente) avec un secret
  dédié `SCAN_TOKEN_SECRET` (≥32 car., même schéma que les clés Mercure), claims `sid` + `exp` (10 min).
  Stateless : pas de table `ScanSession`, pas de migration.
- **Réutilisation Mercure.** Le PC s'abonne via le `CoverBatchSubscriberAuthorizerInterface` **existant**
  (en passant le `sessionId` comme identifiant). Le topic reste `https://ziggytheque.app/cover-batch/{id}`
  (espace de noms générique, isolé par l'UUID de session). On ajoute seulement un petit publisher dédié
  `ScanResultPublisherInterface` (calque de `MercureCoverBatchProgressPublisher`) qui publie `{isbn}`.
- **Mode ISBN dans la modale** = nouveau sous-onglet, et non un 5ᵉ provider (la sémantique diffère : on
  part d'un code unique, pas d'une recherche par titre). Il réutilise la mutation d'application de cover
  existante, étendue pour transmettre `spineUrl` + `isbn`.
- **Une seule lib de scan** (`@zxing/browser`, lecture EAN-13) partagée par le bouton « Scanner » de la
  modale et la page téléphone, via un composable unique.

#### Code supprimé / nettoyé

Fonctionnalité **purement additive** — aucun code obsolété à retirer. Seul nettoyage : lors de l'édition
de `UpdateVolumeHandler`, renommer la variable de closure `$v` → `$volume` (R10, `backend.md`).

#### Risques / contraintes

- **Caméra = HTTPS ou localhost obligatoire** (`getUserMedia`). Scan téléphone OK en prod ; en dev LAN
  prévoir un tunnel HTTPS. La saisie manuelle (exigence 2) est le repli universel et doit toujours marcher.
- **Ordre des routes Symfony** : `/api/manga/cover-by-isbn` doit être déclaré **avant** `/{id}` (sinon
  capté comme un id), comme le sont déjà `/external` et `/volume-search`.
- **Guard routeur** : la route téléphone `/scan/:token` est publique mais doit être ajoutée à la liste
  d'exceptions du guard, sinon un téléphone déjà connecté est redirigé vers le dashboard.
- **CORS Mercure** déjà configuré pour `localhost:5173/8000` (c'est le PC qui s'abonne, pas le téléphone).

---

### Tasks

- Task 1 : route `GET /api/manga/cover-by-isbn` (résolution d'une cover par ISBN) + double de test `StubMangaCoverProvider`.
- Task 2 : persister l'ISBN du tome (extension de `UpdateVolume` : Command/Handler/Request + couche API front).
- Task 3 : infrastructure scan back (jeton de capacité + publisher Mercure dédié + secret env + wiring + doubles + tests unitaires).
- Task 4 : endpoints scan HTTP (`ScanController` public/privé + commandes/handlers + route publique sécurité + tests fonctionnels).
- Task 5 : couche API front (`api/manga.ts`) + dépendances `qrcode` / `@zxing/browser`.
- Task 6 : composables front (`useIsbnCoverSearch`, `useScanSession`, `useBarcodeScanner`) + specs Vitest.
- Task 7 : UI mode ISBN dans `EnrichVolumeModal.vue` + atome `BaseQrCode.vue` + i18n + test composant.
- Task 8 : page publique téléphone `/scan/:token` (`ScanPage.vue`) + route + exception de guard + i18n + test.
- Task 9 : boucle finale lint / test / revue.

---

#### Task 1 : Route `GET /api/manga/cover-by-isbn`

Exposer `MangaCoverProviderInterface::findByIsbn` derrière une route JWT qui résout une couverture à partir
d'un ISBN (alimente les exigences 1, 2, 3 et le côté PC de l'exigence 4).

**Skills and docs to load :**
- `/project-quality-setup` — conventions hexagonal/CQRS, PHPStan/PHPCS/Deptrac, nommage.
- `.claude/backend.md` — R3 (handler orchestrateur sans logique), R4 (port Domain injecté), R10 (nommage).
- `.claude/CLAUDE.md` — règles de test obligatoires, style (pas de FQCN built-in, `final readonly`).

**Files :**
- Create `back/src/Manga/Application/FindCoverByIsbn/FindCoverByIsbnQuery.php`
- Create `back/src/Manga/Application/FindCoverByIsbn/FindCoverByIsbnHandler.php`
- Modify `back/src/Manga/Infrastructure/Http/MangaController.php` — ajouter la route **avant** `get(string $id)`
- Create `back/tests/Doubles/Manga/StubMangaCoverProvider.php`
- Modify `back/config/services.yaml` — bloc `when@test` : remplacer l'alias `MangaCoverProviderInterface` (`NullMangaCoverApiClient` → `StubMangaCoverProvider`) + déclarer le double `public: true`
- Create `back/tests/Functional/Manga/CoverByIsbnControllerTest.php`
- Create `back/tests/Unit/Manga/Application/FindCoverByIsbn/FindCoverByIsbnHandlerTest.php`

**Implementation**

- `FindCoverByIsbnQuery` : `final readonly`, `public string $isbn`.
- `FindCoverByIsbnHandler` : `#[AsMessageHandler(bus: 'query.bus')]`, injecte `MangaCoverProviderInterface`.
  `__invoke(FindCoverByIsbnQuery): ?array` — construit `Isbn::fromString($query->isbn)` (laisse remonter
  `InvalidIsbnException` → 422), appelle `findByIsbn`, retourne `null` si pas de cover sinon
  `['coverUrl' => $dto->coverUrl, 'spineUrl' => $dto->spineUrl, 'isbn' => $dto->isbn?->value, 'source' => $dto->source]`
  (calque exact de `SearchVolumeExternalHandler::mapDtoToArray`).
- `MangaController` : `#[Route('/cover-by-isbn', methods: ['GET'])]` placé **avant** `#[Route('/{id}', …)]`,
  lit `$request->query->get('isbn', '')`, renvoie `new JsonResponse($this->queryBus->ask(new FindCoverByIsbnQuery($isbn)))`.
- `StubMangaCoverProvider implements MangaCoverProviderInterface` : `findByIsbn`/`findByContext` renvoient
  `null` **par défaut** (préserve les tests existants qui attendent `[]` / 0 résolu), plus une méthode
  `registerIsbn(string $isbnValue, MangaVolumeCoverDto $dto): void` stockant dans un tableau interne indexé
  par la forme canonique (`Isbn::fromString($isbnValue)->value`) ; `findByIsbn(Isbn $isbn)` retourne l'entrée
  enregistrée pour `$isbn->value` ou `null`.

**Tests**

- Unit (`FindCoverByIsbnHandlerTest`, étend `PHPUnit\Framework\TestCase`) avec un provider en mémoire
  anonyme : (a) ISBN connu → tableau attendu ; (b) ISBN inconnu → `null` ; (c) ISBN invalide
  (`'abc'`) → `expectException(InvalidIsbnException::class)`.
- Functional (`CoverByIsbnControllerTest`, étend `AbstractApiTestCase`) : récupérer
  `StubMangaCoverProvider` via `static::getContainer()->get(...)`, `registerIsbn('9782811645632', new MangaVolumeCoverDto('https://img/x.jpg', null, Isbn::fromString('9782811645632'), 'googlebooks'))`.
  Scénarios : `200` + corps cover pour cet ISBN ; `200` + corps `null` pour un ISBN non enregistré valide
  (`9782723492607`) ; `422` pour `?isbn=not-an-isbn` ; `401` sans JWT (`auth: false`).

**Verify**

- `cd back && composer phpstan && composer test -- --filter 'CoverByIsbn|FindCoverByIsbn'` → vert.
- Pas de migration (la colonne `isbn` existe déjà) ; ne pas lancer `make migration`.

---

#### Task 2 : Persister l'ISBN saisi/scanné sur le tome

Étendre `UpdateVolume` pour accepter un `isbn` optionnel, afin qu'une saisie manuelle ou un scan enregistre
l'ISBN sur le tome en plus d'appliquer la couverture.

**Skills and docs to load :**
- `/project-quality-setup` — CQRS, validation des Request, qualité.
- `.claude/backend.md` — R10 (renommer `$v`→`$volume`), pattern command/handler.
- `.claude/CLAUDE.md` — `#[MapRequestPayload]`, tests obligatoires sur endpoint modifié.

**Files :**
- Modify `back/src/Manga/Application/UpdateVolume/UpdateVolumeCommand.php` — ajouter `public ?string $isbn = null`
- Modify `back/src/Manga/Infrastructure/Http/UpdateVolumeRequest.php` — ajouter `public ?string $isbn = null`
- Modify `back/src/Manga/Application/UpdateVolume/UpdateVolumeHandler.php` — appliquer l'ISBN + nettoyage R10
- Modify `back/src/Manga/Infrastructure/Http/MangaController.php` — passer `isbn: $request->isbn` au `UpdateVolumeCommand`
- Modify `back/tests/Functional/Manga/MangaControllerTest.php` — étendre `testUpdateVolume` + cas ISBN invalide

**Implementation**

- `UpdateVolumeHandler` : après les autres champs, `if ($command->isbn !== null) { $volume->isbn = Isbn::fromString($command->isbn); }`
  (laisse remonter `InvalidIsbnException` → 422). Ajouter `use App\Manga\Domain\Isbn;`. Renommer la closure
  `fn (Volume $v) => $v->id === …` en `fn (Volume $volume) => $volume->id === …` (R10).
- Pas de contrainte `Assert` sur le champ `isbn` de la Request (la validation métier est dans le VO `Isbn`).

**Tests**

- `MangaControllerTest::testUpdateVolume` : ajouter `'isbn' => '9782811645632'` au PATCH, puis
  `GET /api/manga/{id}` et asserter que le tome porte l'ISBN canonique attendu (vérifier la clé `isbn`
  exposée dans la sérialisation du volume ; sinon asserter via le tome retourné).
- Nouveau `testUpdateVolumeRejectsInvalidIsbn` : PATCH avec `'isbn' => 'xxx'` → `422`.

**Verify**

- `cd back && composer test -- --filter MangaControllerTest` → vert.
- `cd back && composer phpstan` → 0 erreur.

---

#### Task 3 : Infrastructure scan back (jeton de capacité + publisher Mercure)

Briques back réutilisables pour le hand-off : un émetteur/vérificateur de **jeton de capacité** signé, et un
**publisher Mercure** qui pousse l'ISBN scanné vers le topic d'une session.

**Skills and docs to load :**
- `/project-quality-setup` — hexagonal (port Domain + adaptateur Infra), qualité.
- `/frankenphp` — contexte du hub Mercure intégré (le hub existe déjà ; ici on ne fait que publier dessus).
- `/railway-devsecops` — ajout d'une variable d'env propagée à docker-compose + Railway.
- `.claude/backend.md` — R4 (port/adaptateur), R10. `.claude/CLAUDE.md` — `final readonly`, pas de FQCN built-in, doubles de test + `when@test`.

**Files :**
- Create `back/src/Manga/Domain/ScanTokenIssuerInterface.php`
- Create `back/src/Manga/Domain/ScanResultPublisherInterface.php`
- Create `back/src/Manga/Domain/Exception/InvalidScanTokenException.php`
- Create `back/src/Manga/Infrastructure/Scan/JwtScanTokenIssuer.php`
- Create `back/src/Manga/Infrastructure/Mercure/MercureScanResultPublisher.php`
- Create `back/tests/Doubles/Manga/InMemoryScanResultPublisher.php`
- Create `back/tests/Unit/Manga/Infrastructure/Scan/JwtScanTokenIssuerTest.php`
- Modify `back/config/services.yaml` — wiring prod (issuer + publisher) et `when@test` (alias publisher → double)
- Modify `back/.env` — `SCAN_TOKEN_SECRET=!ChangeThisScanTokenSecret32chars!!`
- Modify `back/.env.test` — `SCAN_TOKEN_SECRET=test-scan-token-secret-32-characters`
- Modify `docker-compose.yml` — service `back` : ajouter `SCAN_TOKEN_SECRET` dans `environment`

**Implementation**

- `ScanTokenIssuerInterface` : `issue(string $sessionId, int $ttlSeconds): string` ;
  `verify(string $token): string` (retourne le `sessionId`, lève `InvalidScanTokenException`).
- `ScanResultPublisherInterface` : `publish(string $sessionId, string $isbn): void`.
- `InvalidScanTokenException extends DomainException` : `getHttpStatusCode(): int { return 410; }`
  (410 Gone — évite la collision avec l'intercepteur 401 du front ; couvre jeton malformé **et** expiré).
- `JwtScanTokenIssuer` (calque de `MercureCoverBatchSubscriberAuthorizer` pour la création) :
  `Configuration::forSymmetricSigner(new Sha256(), InMemory::plainText($secret))`, garde `< 32` car. →
  `RuntimeException`. `issue` : builder `issuedAt`/`expiresAt(+ttl)`/`withClaim('sid', $sessionId)`.
  `verify` : `parser()->parse($token)` puis `validator()->validate($token, new SignedWith($signer,$key), new StrictValidAt(SystemClock::fromUTC()))` ; en cas d'échec ou claim `sid` absent → `InvalidScanTokenException`.
  Importer `Lcobucci\JWT\Validation\Constraint\SignedWith`, `…\StrictValidAt`, `Lcobucci\Clock\SystemClock`.
- `MercureScanResultPublisher implements ScanResultPublisherInterface` (calque de
  `MercureCoverBatchProgressPublisher`) : ctor `HttpClientInterface $httpClient, string $hubUrl, string $publisherJwtKey, LoggerInterface $logger`.
  `publish` : `$topic = sprintf('https://ziggytheque.app/cover-batch/%s', $sessionId);` (même convention que
  le subscriber authorizer), POST form-urlencoded `topic`/`data=json_encode(['isbn'=>$isbn])`/`private='1'`,
  `Authorization: Bearer <mintPublisherJwt()>` (réutiliser la même logique de mint que le publisher existant) ;
  `try/catch (Throwable)` → `logger->warning(...)`.
- `InMemoryScanResultPublisher` : `public array $published = [];`, `publish` empile `['sessionId'=>…, 'isbn'=>…]`.
- `services.yaml` (hors `when@test`, à la suite du bloc Mercure existant) :
  ```yaml
  App\Manga\Domain\ScanTokenIssuerInterface:
      alias: App\Manga\Infrastructure\Scan\JwtScanTokenIssuer
  App\Manga\Infrastructure\Scan\JwtScanTokenIssuer:
      arguments:
          $secret: '%env(SCAN_TOKEN_SECRET)%'
  App\Manga\Domain\ScanResultPublisherInterface:
      alias: App\Manga\Infrastructure\Mercure\MercureScanResultPublisher
  App\Manga\Infrastructure\Mercure\MercureScanResultPublisher:
      arguments:
          $hubUrl: '%env(MERCURE_URL)%'
          $publisherJwtKey: '%env(MERCURE_PUBLISHER_JWT_KEY)%'
          $logger: '@logger'
  ```
  `when@test` : `App\Manga\Domain\ScanResultPublisherInterface: { alias: App\Tests\Doubles\Manga\InMemoryScanResultPublisher }`
  et `App\Tests\Doubles\Manga\InMemoryScanResultPublisher: { public: true }`. Le `JwtScanTokenIssuer` reste
  l'implémentation réelle en test (crypto pure, sans I/O) — d'où `SCAN_TOKEN_SECRET` dans `.env.test`.
- **Prod (Railway)** : documenter dans le commit que `SCAN_TOKEN_SECRET` (≥32 car.) doit être ajouté aux
  variables du service backend ; le worker n'en a pas besoin.

**Tests**

- `JwtScanTokenIssuerTest` (unit) : instancier avec un secret de test ≥32 car. (a) `issue` puis `verify`
  retourne le même `sessionId` ; (b) jeton signé avec un **autre** secret → `InvalidScanTokenException` ;
  (c) jeton expiré (`ttl` négatif, ou forger un `expiresAt` passé) → `InvalidScanTokenException` ;
  (d) chaîne arbitraire `'garbage'` → `InvalidScanTokenException`.

**Verify**

- `cd back && composer test -- --filter JwtScanTokenIssuer` → vert.
- `cd back && composer phpstan` → 0 erreur ; `composer deptrac` → aucune violation de couche.

---

#### Task 4 : Endpoints scan HTTP (`ScanController`)

Exposer la création de session (JWT) et la soumission depuis le téléphone (publique).

**Skills and docs to load :**
- `/project-quality-setup` — CQRS, sécurité, qualité.
- `/railway-devsecops` — règle `access_control` + CORS sur le périmètre `/api`.
- `.claude/backend.md` — R3 (handler orchestrateur), modèle `StartCoverBatchHandler`.
- `.claude/CLAUDE.md` — `#[MapRequestPayload]`, tests fonctionnels couvrant chaque code HTTP.

**Files :**
- Create `back/src/Manga/Application/Scan/CreateScanSessionCommand.php`
- Create `back/src/Manga/Application/Scan/CreateScanSessionHandler.php`
- Create `back/src/Manga/Application/Scan/ScanSessionResult.php`
- Create `back/src/Manga/Application/Scan/SubmitScanCommand.php`
- Create `back/src/Manga/Application/Scan/SubmitScanHandler.php`
- Create `back/src/Manga/Infrastructure/Http/ScanController.php`
- Create `back/src/Manga/Infrastructure/Http/CreateScanSessionRequest.php`
- Create `back/src/Manga/Infrastructure/Http/SubmitScanRequest.php`
- Modify `back/config/packages/security.yaml` — `access_control` : route publique de soumission
- Create `back/tests/Functional/Manga/ScanControllerTest.php`

**Implementation**

- `CreateScanSessionRequest` : `public string $mangaId`, `public string $volumeId` (`#[Assert\NotBlank]`).
- `CreateScanSessionCommand` (command.bus, **retourne** `ScanSessionResult`) — calque de `StartCoverBatchHandler` :
  charge le manga via `MangaRepositoryInterface` → `NotFoundException('Manga', …)` si absent ; vérifie que le
  tome existe (`$manga->volumes->filter(fn (Volume $volume) => $volume->id === $command->volumeId)->first()`)
  → `NotFoundException('Volume', …)` si absent ; `sessionId = Uuid::v4()->toRfc4122()` ;
  `subscriberToken = $subscriberAuthorizer->issueToken($sessionId, 600)`, `topic = …->topicFor($sessionId)`,
  `mercureUrl = …->publicHubUrl()` ; `scanToken = $scanTokenIssuer->issue($sessionId, 600)` ;
  retourne `new ScanSessionResult(sessionId, scanToken, mercureUrl, subscriberToken, topic)`.
  Injecte `MangaRepositoryInterface`, `CoverBatchSubscriberAuthorizerInterface`, `ScanTokenIssuerInterface`.
- `ScanSessionResult` (calque de `StartCoverBatchResult`) : `toArray()` → `{sessionId, scanToken, mercureUrl, subscriberToken, topic}`.
- `SubmitScanRequest` : `public string $scanToken`, `public string $isbn` (`#[Assert\NotBlank]`).
- `SubmitScanHandler` (command.bus, `void`) : `$sessionId = $this->scanTokenIssuer->verify($command->scanToken);`
  (→ 410 si invalide) ; `$isbn = Isbn::fromString($command->isbn);` (→ 422 si invalide) ;
  `$this->scanResultPublisher->publish($sessionId, $isbn->value);`. Injecte `ScanTokenIssuerInterface`,
  `ScanResultPublisherInterface`.
- `ScanController` `#[Route('/api/scan')]` :
  - `#[Route('/sessions', methods: ['POST'])] createSession(#[MapRequestPayload] CreateScanSessionRequest $request)`
    → `$result = $this->commandBus->dispatch(new CreateScanSessionCommand(...))` → `JsonResponse($result->toArray(), 201)`.
  - `#[Route('/submit', methods: ['POST'])] submit(#[MapRequestPayload] SubmitScanRequest $request)`
    → dispatch `SubmitScanCommand` → `JsonResponse(null, 204)`.
- `security.yaml` : ajouter **avant** la règle `{ path: ^/api, roles: ROLE_USER }` :
  `- { path: ^/api/scan/submit$, roles: PUBLIC_ACCESS }`. (`/api/scan/sessions` reste couvert par `^/api`.)

**Tests**

- `ScanControllerTest` (étend `AbstractApiTestCase`, helper `importManga` + ajout de volume comme dans
  `MangaControllerTest::testUpdateVolume`) :
  - `testCreateSessionReturnsTokens` : POST `/api/scan/sessions {mangaId, volumeId}` → `201` ; corps contient
    `sessionId, scanToken, mercureUrl, subscriberToken, topic` ; `topic` contient `sessionId`.
  - `testCreateSessionRequiresAuth` : `auth: false` → `401`.
  - `testCreateSessionMangaNotFound` : `mangaId` bidon → `404`.
  - `testSubmitPublishesIsbn` : créer une session (récupérer `scanToken`), POST **public**
    (`auth: false`) `/api/scan/submit {scanToken, isbn:'9782811645632'}` → `204` ; récupérer
    `InMemoryScanResultPublisher` via le container et asserter `published` = 1 entrée avec l'ISBN canonique
    et le `sessionId` de la session.
  - `testSubmitInvalidTokenReturns410` : `scanToken:'garbage'` → `410`.
  - `testSubmitInvalidIsbnReturns422` : `scanToken` valide + `isbn:'xxx'` → `422`.

**Verify**

- `cd back && composer test -- --filter ScanController` → vert.
- `cd back && composer qa` (phpcbf, phpcs, phpstan, deptrac, test) → tout vert.

---

#### Task 5 : Couche API front + dépendances

Ajouter les fonctions client et les librairies QR / lecture de code-barres.

**Skills and docs to load :**
- `/vue-best-practices` — TS strict, structure `api/`.
- `.claude/CLAUDE.md` — couche API (`api/client.ts` axios, baseURL `/api`).

**Files :**
- Modify `front/src/api/manga.ts` — `coverByIsbn`, `ScanSessionResponse`, `createScanSession`, `submitScan`, extension de `updateVolume`
- Modify `front/package.json` — deps `qrcode`, `@zxing/browser`, `@zxing/library` ; dev `@types/qrcode`

**Implementation**

- `coverByIsbn(isbn: string): Promise<{ coverUrl: string; spineUrl: string | null; isbn: string | null; source: string } | null>`
  → `client.get('/manga/cover-by-isbn', { params: { isbn } })` → `res.data`.
- `export interface ScanSessionResponse { sessionId: string; scanToken: string; mercureUrl: string; subscriberToken: string; topic: string }`.
- `createScanSession(payload: { mangaId: string; volumeId: string }): Promise<ScanSessionResponse>` → `client.post('/scan/sessions', payload)`.
- `submitScan(payload: { scanToken: string; isbn: string }): Promise<void>` → `client.post('/scan/submit', payload)`
  (le client n'ajoute le Bearer que si un token existe ; sur le téléphone sans session, aucun header — OK ;
  les erreurs sont 410/422, jamais 401, donc l'intercepteur de logout n'est pas déclenché).
- `updateVolume` : étendre le type `payload` avec `isbn?: string`.
- `package.json` : `qrcode` (^1.5.4), `@zxing/browser` (^0.1.5), `@zxing/library` (^0.21.3), devDep `@types/qrcode` (^1.5.5). Lancer `npm install` dans `front/`.

**Verify**

- `cd front && npm install && npm run type-check` → 0 erreur TS.

---

#### Task 6 : Composables front (ISBN, session de scan, lecteur de code-barres)

Trois composables réutilisables, un par responsabilité.

**Skills and docs to load :**
- `/create-adaptable-composable` — entrées `MaybeRefOrGetter`, normalisation `toValue()`/`toRef()`.
- `/vue-best-practices` — `<script setup>`/composables, `onScopeDispose`.
- `/vue-testing-best-practices` — Vitest, mocks.
- Fichier `front/src/composables/useCoverBatchProgress.ts` — calque exact pour `useScanSession`.
- Fichier `front/src/composables/__tests__/useCoverBatchProgress.spec.ts` — calque pour les specs EventSource (`MockEventSource`, `effectScope`).

**Files :**
- Create `front/src/composables/useScanSession.ts`
- Create `front/src/composables/useIsbnCoverSearch.ts`
- Create `front/src/composables/useBarcodeScanner.ts`
- Create `front/src/composables/__tests__/useScanSession.spec.ts`
- Create `front/src/composables/__tests__/useIsbnCoverSearch.spec.ts`
- Create `front/src/composables/__tests__/useBarcodeScanner.spec.ts`

**Implementation**

- `useScanSession()` — copie de `useCoverBatchProgress` : `start(payload: ScanSessionResponse, { onResult }: { onResult: (isbn: string) => void })`.
  Ouvre `new EventSource(\`${payload.mercureUrl}?topic=${encodeURIComponent(payload.topic)}&authorization=${payload.subscriberToken}\`)`
  (utiliser `URL` + `searchParams.append` comme l'original) ; `onmessage` : `JSON.parse(msg.data)` → `{ isbn }`
  → `onResult(isbn)` ; expose `close()` ; `onScopeDispose(close)`.
- `useIsbnCoverSearch(isbn: MaybeRefOrGetter<string>, options?: { immediate?: boolean })` — composable adaptable :
  `const isbnRef = toRef(isbn)`, état `cover`/`isLoading`/`error` (refs) ; `search()` (lit `toValue(isbn)`,
  ignore si vide, met `isLoading`, appelle `coverByIsbn`, remplit `cover`/`error`) ; si `options.immediate`,
  `watch(isbnRef, search, { immediate: true })`. Retourne `{ cover, isLoading, error, search }`.
- `useBarcodeScanner()` — enveloppe `@zxing/browser` : `start(video: HTMLVideoElement, onDecode: (isbn: string) => void)`
  crée un `BrowserMultiFormatReader`, `decodeFromVideoDevice(undefined, video, (result) => { if (result) onDecode(result.getText()) })` ;
  `stop()` libère le reader (`reader.reset()`/`BrowserCodeReader.releaseAllStreams` selon l'API de la version) ;
  expose un ref `isScanning` et `errorMessage` (capture le rejet `getUserMedia`/NotAllowedError) ; `onScopeDispose(stop)`.

**Tests**

- `useScanSession.spec.ts` — calque de `useCoverBatchProgress.spec.ts` avec `MockEventSource` : un message
  `{ isbn: '9782811645632' }` déclenche `onResult` avec cette valeur ; l'URL contient `topic=` et
  `authorization=` ; `close()` appelé à la fermeture du scope et sur `onerror`.
- `useIsbnCoverSearch.spec.ts` — `vi.mock('@/api/manga')` : `search()` appelle `coverByIsbn` et remplit
  `cover` ; ISBN vide → pas d'appel ; rejet API → `error` rempli, `isLoading` repasse à `false`.
- `useBarcodeScanner.spec.ts` — `vi.mock('@zxing/browser')` : `start` câble le callback de décodage (simuler
  un `result.getText()` → `onDecode` reçoit l'ISBN) ; `stop` appelle la libération ; un rejet
  `NotAllowedError` remplit `errorMessage`.

**Verify**

- `cd front && npm run test -- src/composables/__tests__` → tous verts.

---

#### Task 7 : UI mode ISBN dans la modale + atome QR code

Ajouter le sous-onglet « ISBN » à `EnrichVolumeModal.vue` (les 4 exigences) et un atome de rendu de QR code.

**Skills and docs to load :**
- `/vue-best-practices` — `<script setup>`, props/emits typés, organisation atomic design.
- `/vue-pinia-best-practices` — `useUiStore.addToast` pour les retours utilisateur.
- `/vue-testing-best-practices` — test de composant (mock api + vue-query).
- `/create-adaptable-composable` — usage de `useIsbnCoverSearch`.
- Fichier `front/src/components/organisms/EnrichVolumeModal.vue` — structure existante (boutons providers ~305-314, champ URL ~383-406, `enrichMutation` ~146-154, props ~11-20).

**Files :**
- Create `front/src/components/atoms/BaseQrCode.vue`
- Modify `front/src/components/organisms/EnrichVolumeModal.vue` — sous-onglet ISBN
- Modify `front/src/i18n/fr.json` et `front/src/i18n/en.json` — clés du mode ISBN
- Create `front/src/components/organisms/__tests__/EnrichVolumeModal.isbn.spec.ts`

**Implementation**

- `BaseQrCode.vue` (atome) : prop `value: string` (+ `size?: number`), `import QRCode from 'qrcode'`, génère un
  data URL via `QRCode.toDataURL(value, { width: size ?? 220 })` dans un `watchEffect`, rend `<img :src=…>`.
- `EnrichVolumeModal.vue` : introduire un commutateur de **mode** haut niveau (`'search' | 'isbn'`) au-dessus
  des boutons providers existants (réutiliser le style de boutons des providers). Le panneau `isbn` contient :
  - **Auto (exigence 1)** : à l'activation de l'onglet, si `props.volume?.isbn` est défini, pré-remplir le ref
    `isbnInput` et lancer la recherche (`useIsbnCoverSearch(isbnInput, { immediate: true })`). Comportement
    d'application : si le tome n'a **pas** encore de cover (`!props.volume?.coverUrl`), appliquer
    automatiquement le résultat ; sinon afficher l'aperçu + bouton « Appliquer » (ne pas écraser en silence).
  - **Saisie (exigence 2)** : `<input v-model="isbnInput">` + bouton « Rechercher » → `search()`. Aperçu du
    résultat (`cover.coverUrl`) + « Appliquer ».
  - **Scan caméra (exigence 3)** : bouton « Scanner » qui affiche un `<video ref>` et appelle
    `useBarcodeScanner().start(video, (isbn) => { isbnInput.value = isbn; search() })`. Afficher
    `errorMessage` du composable (permission caméra refusée / contexte non sécurisé).
  - **Hand-off téléphone (exigence 4)** : bouton « Scanner avec mon téléphone » → `createScanSession({ mangaId: props.mangaId, volumeId: props.volume!.volumeId })`,
    puis `<BaseQrCode :value="\`${window.location.origin}/scan/${resp.scanToken}\`"/>` + lien cliquable, et
    `useScanSession().start(resp, { onResult: applyIsbn })`. `applyIsbn(isbn)` = `coverByIsbn(isbn)` →
    `enrichMutation.mutate({ coverUrl, spineUrl, isbn })` → toast « Couverture reçue du téléphone ».
  - **Application** : étendre la mutation existante (`enrichMutation`) pour passer aussi `spineUrl` et `isbn`
    à `updateVolume`, et conserver l'invalidation `['collection', props.collectionEntryId]` (auto-reload).
  - Utiliser `useI18n()` `t()` pour tous les libellés du nouveau panneau.
- i18n (mêmes clés dans `fr.json` / `en.json`), ex. sous `enrich` : `tabSearch`, `tabIsbn`, `isbnLabel`,
  `isbnPlaceholder`, `searchIsbn`, `scanCamera`, `scanPhone`, `coverFound`, `noCoverForIsbn`,
  `coverFromPhone`, `cameraError`.

**Tests**

- `EnrichVolumeModal.isbn.spec.ts` (Vue Test Utils, `vi.mock('@/api/manga')`, vue-query en mode test) :
  - saisir un ISBN + clic « Rechercher » → `coverByIsbn` appelé avec l'ISBN ; l'aperçu s'affiche.
  - clic « Appliquer » → `updateVolume` appelé avec `{ coverUrl, spineUrl, isbn }`.
  - ouverture de l'onglet avec `props.volume.isbn` défini et sans cover → `coverByIsbn` appelé et
    `updateVolume` déclenché automatiquement (auto-apply).
  (Mocker `useBarcodeScanner` et `useScanSession` pour isoler le test des API navigateur.)

**Verify**

- `cd front && npm run type-check && npm run test -- src/components/organisms/__tests__/EnrichVolumeModal.isbn.spec.ts` → vert.

---

#### Task 8 : Page publique téléphone `/scan/:token`

La page que le téléphone ouvre depuis le QR : caméra → lecture EAN-13 → soumission.

**Skills and docs to load :**
- `/vue-router-best-practices` — route publique + exception de guard.
- `/vue-best-practices` — page `<script setup>`.
- `/vue-testing-best-practices` — test de page (mock scanner + api).
- Fichier `front/src/router/index.ts` — guard `beforeEach` (condition de redirection des routes publiques).

**Files :**
- Create `front/src/pages/ScanPage.vue`
- Modify `front/src/router/index.ts` — route `/scan/:token` + exception de guard
- Modify `front/src/i18n/fr.json` et `front/src/i18n/en.json` — clés de la page scan
- Create `front/src/pages/__tests__/ScanPage.spec.ts`

**Implementation**

- Route : `{ path: '/scan/:token', name: 'scan', component: () => import('@/pages/ScanPage.vue'), meta: { public: true, title: 'Scanner un ISBN' } }`.
- Guard : dans la condition existante
  `if (to.meta.public && auth.isAuthenticated && to.name !== 'verify-email' && to.name !== 'reset-password')`,
  ajouter `&& to.name !== 'scan'` (sinon un téléphone déjà connecté serait redirigé vers le dashboard).
- `ScanPage.vue` : lit `route.params.token`. Affiche un `<video ref>` et démarre `useBarcodeScanner().start(video, onScan)`.
  `onScan(isbn)` = `submitScan({ scanToken: token, isbn })` → état succès « Envoyé ✓ {isbn} » + possibilité de
  scanner un autre tome. Gérer les erreurs : 410 → « Session expirée, régénérez le QR sur l'ordinateur » ;
  422 → « Code-barres non reconnu » ; rejet caméra (`errorMessage` du composable) → message + rappel HTTPS.
  Page autonome (pas de `MainLayout`, pas de store auth requis). Libellés via `t()`.

**Tests**

- `ScanPage.spec.ts` : `vi.mock('@/api/manga')` + mock `useBarcodeScanner` (exposer un moyen de déclencher
  `onScan`). Simuler un décodage `'9782811645632'` → `submitScan` appelé avec `{ scanToken: <param>, isbn }` ;
  l'état succès s'affiche. Simuler un rejet `submitScan` 410 → message « session expirée ».

**Verify**

- `cd front && npm run test -- src/pages/__tests__/ScanPage.spec.ts` → vert.
- Vérif manuelle (prod/HTTPS ou localhost) : ouvrir la modale d'un tome, « Scanner avec mon téléphone »,
  ouvrir le QR sur un téléphone, scanner un ISBN réel → la couverture apparaît sur le PC sans rechargement.

---

#### Task 9 : Final lint, test, and review loop.
Once finished, run lint and test for the affected sub-directories, dump the git diff in a tmp file with `git diff HEAD > /tmp/last-review.diff`, then spawn a `file-reviewer` subagent with the prompt: *"Review the diff at /tmp/last-review.diff. Invoke the `code-review` skill, follow its 'Load thematic rules by file type' step using the diff's file paths, and write structured findings."*
Fix every finding, then redo all three steps. Repeat until lint passes, tests pass, and the subagent returns no findings (max 5 iterations).
