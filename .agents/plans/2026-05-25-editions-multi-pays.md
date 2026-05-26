# Plan — Refonte éditions + ISBN-first (v3)

## TL;DR

> [!NOTE]
> Aujourd'hui, un manga = une seule édition implicite : `Manga.edition` est un simple varchar libre, ce qui empêche d'avoir Berserk *Prestige* et Berserk *Maximum* côte à côte dans la collection. La recherche externe (Google Books, `langRestrict=fr` codé en dur) ramène 1 carte par titre et oblige à des recopies manuelles pour distinguer les éditions.
>
> On introduit une nouvelle entité **`Edition`** entre `Manga` (la série) et `Volume` (le tome). Une édition porte le couple `(publisher, label)` (Glénat / Maximum, Pika / Standard, Glénat / Prestige…). `CollectionEntry` et `WishlistItem` se rattachent désormais à une `Edition`, pas à un `Manga` direct. Une migration auto-créée garantit que chaque `Manga.edition` (string) existant devient une `Edition` rattachée, sans perte de données.
>
> Deux flows complémentaires pour ajouter à la collection : (1) **recherche série** par titre → liste d'éditions agrégées (Google Books + nouveau client **BNF SRU**) → import en un clic, et (2) **lookup ISBN direct** (saisie clavier ou scan code-barres caméra mobile via `@zxing/browser`, exposé uniquement sur le flow « j'ai en main ») → résout `Manga + Edition + Volume` en une requête `GET /api/lookup/isbn/{isbn}`. La jaquette HD vient de **Amazon** par URL déterministe `images/P/{isbn10}.jpg`. Plus de copiés-collés d'URLs, plus d'éditions silencieuses confondues.

---

## Implementation

### Contexte projet rappelé

- Backend Symfony 8 / PHP 8.4 + FrankenPHP + PostgreSQL 17, hexagonal + CQRS via Messenger.
- Doctrine : `make migration` génère les noms FK/index — interdiction de les écrire à la main, sinon `doctrine:schema:validate` casse.
- Tests : règle non négociable (CLAUDE.md) — tout endpoint ajouté/modifié reçoit un test fonctionnel, tout VO/entité reçoit un test unitaire.
- Front Vue 3 + TS (`<script setup>`), Pinia, DaisyUI, vue-i18n (FR/EN).

### État actuel (audit fait)

```
                            CollectionEntry
                            unique(owner_id, manga_id)
                                 │
                                 │ manga_id (FK direct)
                                 ▼
   Manga(id, title, edition?VARCHAR(100), language, author,
         summary, coverUrl, genre, externalId)
                                 │
                                 │ 1..*
                                 ▼
                     Volume(id, manga_id, number, coverUrl, price,
                            releaseDate, isbn IsbnType, spineUrl)

   WishlistItem(id, owner_id, manga_id)   ← lié directement à Manga
```

**À conserver tel quel (déjà fonctionnel) :**

| Élément | Chemin | Notes |
|---|---|---|
| `Isbn` VO complet (validation ISBN-10/13, conversion canonique) | `back/src/Manga/Domain/Isbn.php` | Réutilisé pour tous les nouveaux lookups |
| `IsbnType` Doctrine custom type | `back/src/Manga/Infrastructure/Doctrine/Type/IsbnType.php` | Déjà mappé sur `Volume.isbn` |
| `Volume.isbn` colonne | `volumes` table | Existe, à propager dans les imports |
| `ExternalApiClientInterface` + DTOs `ExternalMangaDto` / `ExternalVolumeDto` | `back/src/Manga/Domain/` | Interface étendue (pas remplacée) |
| `GoogleBooksMangaApiClient` (search + cover + `findByIsbn`) | `back/src/Manga/Infrastructure/ExternalApi/` | Étendu (extraction publisher + label) |
| `MangaCoverProviderInterface` + clients (Google Books / MangaDex / OpenLibrary) | `back/src/Manga/Domain/` & `Infrastructure/ExternalApi/` | Réutilisés ; on ajoute juste Amazon |
| Endpoint `GET /api/manga/volume-search?provider=composite` | `back/src/Manga/Infrastructure/Http/MangaController.php` | Resté tel quel, sert toujours aux jaquettes par tome |
| Composable `useExternalSearch` | `front/src/composables/useExternalSearch.ts` | Sera adapté en `useEditionSearch` (renommage + signature) |
| Page `AddMangaPage.vue` (3 étapes) | `front/src/pages/AddMangaPage.vue` | Refonte en 2 étapes |

**Clients externes actuels :**

| Client | Interface | Rôle aujourd'hui | Évolution v3 |
|---|---|---|---|
| `JikanMangaApiClient` | `ExternalApiClientInterface` | Recherche série, alias prod par défaut | Conservé tel quel (pas modifié dans ce plan, restera utilisable mais n'est plus utilisé dans le composite éditions) |
| `GoogleBooksMangaApiClient` | `ExternalApiClientInterface` + `MangaCoverProviderInterface` | Recherche + jaquettes | Étendu : extraction publisher + label, `searchByIsbn` exposé via interface |
| `MangaDexMangaApiClient` | `MangaCoverProviderInterface` | Jaquettes par tome | Inchangé |
| `OpenLibraryCoversApiClient` | `MangaCoverProviderInterface` | Cover par ISBN | Inchangé |
| `NullMangaApiClient` | `ExternalApiClientInterface` | Stub `when@test` | Étendu pour les nouvelles méthodes |

### Cible

```
                         CollectionEntry
                         unique(owner_id, edition_id)
                              │
                              │ edition_id (FK, NEW)
                              ▼
   Manga(id, title, author, summary, genre, originalLanguage?)
        ← retire edition VARCHAR, retire externalId, retire language
                              │
                              │ 1..*
                              ▼
   Edition(id, manga_id, publisher_name?, label, language='fr',
           total_volumes?, cover_url?, created_at)
           unique(manga_id, publisher_name, label)
                              │
                              │ 1..*
                              ▼
   Volume(id, edition_id, number, coverUrl, price,
          releaseDate, isbn IsbnType, spineUrl)
          unique(edition_id, number)

   WishlistItem(id, owner_id, edition_id)   ← bascule sur edition_id
```

**Nouveaux concepts Domain :**

| Élément | Type | Rôle |
|---|---|---|
| `Edition` | Entity | Variante éditoriale d'un `Manga` (Maximum chez Pika, Prestige chez Glénat…) |
| `PublisherName` | VO `final readonly` | String normalisée (`Glénat`, `Pika Édition`, `Ki-oon`…) ; trim + collapse whitespace + canonical form |
| `EditionLabel` | VO `final readonly` | String normalisée (`Standard`, `Maximum`, `Prestige`, `Perfect`, `Deluxe`, `Wideban`, `Pocket`, `Collector`, `Édition Originale`, `Big`, `Ultimate`) + fallback `Standard` |
| `EditionLabelExtractor` | Domain service `final readonly` | Regex appliquée sur `title + subtitle` Google Books pour détecter le label ; retombe sur `Standard` si rien |
| `EditionAggregator` | Domain service `final readonly` | Reçoit les `EditionSearchResultDto` venant des clients externes, regroupe par `(publisher_name, label)`, dédup, fusionne |
| `EditionSearchResultDto` | DTO `final readonly` | Une ligne de la recherche éditions : `source`, `externalEditionId`, `mangaTitle`, `publisherName?`, `label`, `language`, `totalVolumes?`, `coverUrl?`, `author?`, `summary?`, `genre?` |

### Choix techniques majeurs

1. **`Edition` est une entité dédiée**, pas un champ enrichi sur `Manga`.
   - Sémantique DDD claire : « Berserk » (série) ≠ « Berserk Maximum chez Pika » (édition).
   - Permet la requête native « toutes mes éditions de Berserk ».
   - Trade-off : refacto `CollectionEntry`/`WishlistItem`/`Stats`. Acceptable car l'app est jeune et seul `florian.berrot92@gmail.com` y a des données réelles → migration auto SQL transparente.

2. **Sources d'agrégation : Google Books (existant, étendu) + BNF SRU (nouveau).**
   - Google Books : extraction améliorée `publisher` + `label` (regex sur `title + subtitle`).
   - BNF : API SRU 1.2 officielle, gratuite, exhaustive sur le dépôt légal FR. URL : `http://catalogue.bnf.fr/api/SRU`. Parse XML UNIMARC.
   - Pas de Nautiljon dans cette phase : décision utilisateur (rationale ToS + scraping fragile, peut venir en v4).
   - Multi-pays / multi-langue hors scope : `language` reste `'fr'` partout dans le code et la migration.

3. **Couvertures HD via Amazon par ISBN déterministe.**
   - URL stable depuis 15+ ans : `https://images-na.ssl-images-amazon.com/images/P/{isbn10}.jpg`.
   - Conversion ISBN-13 → ISBN-10 via méthode dérivée du VO `Isbn` (à exposer).
   - Pas de scraping, pas d'anti-bot ; fallback OpenLibrary si Amazon répond 404 (déjà câblé via composite).

4. **Lookup ISBN direct = endpoint dédié transparent.**
   - `GET /api/lookup/isbn/{isbn}` :
     1. Si `Volume.isbn = isbn` existe en base → retourne `{manga, edition, volume}` directement.
     2. Sinon, interroge Google Books (`searchByIsbn`) puis BNF SRU (`searchByIsbn`) ; fusionne le résultat.
     3. Auto-import : crée `Manga` + `Edition` + `Volume` en transaction, retourne les 3 entités.
   - Endpoint utilisé à la fois par la saisie clavier (web/desktop/tablette) et par le scan caméra mobile.

5. **Scan caméra ISBN exposé uniquement sur le flow « j'ai en main ».**
   - Lib : `@zxing/browser` (déjà éprouvée, MIT).
   - Décodage EAN-13 (les ISBN-13 sont stockés sous code-barres EAN-13).
   - Permission caméra demandée à l'ouverture du modal ; si refusée → fallback saisie clavier dans le même modal.
   - Exposition UI : bouton `📷 Scanner l'ISBN` dans la page détail d'un `CollectionEntry` (panneau d'ajout d'un volume possédé). Pas exposé dans la recherche générale.
   - Détecté côté front : `navigator.mediaDevices.getUserMedia` ; le bouton est désactivé si l'API n'est pas dispo (desktop sans webcam).

6. **Migration data — backfill SQL dans la migration générée par `make migration`.**
   - Pour chaque `(manga_id, edition_string)` distinct dans `mangas`, créer 1 ligne `editions`.
   - Backfill `volumes.edition_id`, `collection_entries.edition_id`, `wishlist_items.edition_id` via JOIN.
   - DROP `mangas.edition` à la fin du `up()`.
   - `down()` documenté comme destructif (perd les éditions ajoutées après migration).

7. **`UX picker édition` : intégré au flow d'ajout collection.**
   - Sur `AddMangaPage` (refondu 3→2 étapes) : étape 1 = recherche par titre ; étape 2 = sélecteur des éditions trouvées via API agrégées + fallback `<details>` saisie manuelle.
   - Sur `CollectionDetailPage` : un picker secondaire permet de switcher d'édition pour la même collection (rare mais utile si l'utilisateur s'est trompé).

### Scope

**Inclus dans ce plan :**
- Nouvelle entité `Edition` + VOs `PublisherName`, `EditionLabel` + service `EditionLabelExtractor`.
- Migration Doctrine + backfill SQL transparent.
- Refacto `CollectionEntry`, `VolumeEntry`, `WishlistItem`, `Stats` pour pointer sur `Edition`.
- Nouveau client `BnfSruApiClient` + parser `BnfUnimarcParser`.
- Nouveau provider `AmazonCoverProvider` (cover par ISBN, URL déterministe).
- Extension `GoogleBooksMangaApiClient` : `searchByIsbn` exposé sur l'interface, extraction publisher + label.
- Service Domain `EditionAggregator` + handlers `SearchEditionsHandler` / `ImportEditionHandler` / `LookupIsbnHandler`.
- Endpoints HTTP : `GET /api/editions/search`, `POST /api/editions/import`, `GET /api/editions/{id}`, `GET /api/lookup/isbn/{isbn}`, `GET /api/manga/{id}/editions`.
- Adaptation `POST /api/collection` (body `{editionId}` au lieu de `{mangaId}`) + idem `POST /api/wishlist`.
- Frontend : `useEditionSearch` (renommé), `useIsbnLookup`, `useBarcodeScanner`, `BarcodeScannerModal`, `EditionPicker`, refonte `AddMangaPage` (2 étapes), adaptation `CollectionPage` / `CollectionDetailPage` / `WishlistPage`.
- Tests : functional sur chaque endpoint + unit sur chaque nouveau Domain/VO/service.
- Mise à jour `.claude/CLAUDE.md` (Bounded Contexts + API Endpoints + External API).

**Hors scope (plan ultérieur) :**
- Nautiljon comme source d'agrégation (v4 si besoin).
- Multi-pays / multi-langue (`country` paramètre, éditions JP/EN/IT).
- Détection automatique des éditions exotiques par OCR de la 4e de couverture.
- Synchronisation périodique des nouvelles parutions.
- Vue de regroupement « toutes les éditions de la série X » (fiche série dédiée).

### Risques et mitigations

| Risque | Mitigation |
|---|---|
| `doctrine:schema:validate` casse à cause d'un mismatch enum length / default / FK name | Toujours `make migration` ; jamais de nom FK à la main ; `options: ['default' => ...]` quand DEFAULT DB |
| BNF en retard de quelques semaines sur les nouveautés FR | Acceptable car Google Books complète, et l'import manuel via `<details>` reste possible |
| Permission caméra refusée sur mobile | Fallback automatique : le `BarcodeScannerModal` bascule sur un input texte avec validation `Isbn::tryFrom` |
| Amazon répond 404 ou bannit l'IP | Fallback OpenLibrary covers (déjà câblé dans le composite) ; pas critique car cover ne bloque pas l'import |
| BNF SRU lent (XML SOAP-like, timeout ~3s) | `HttpClientInterface::stream()` pour paralléliser Google Books + BNF ; timeout 5s puis fallback Google Books seul |
| Backfill échoue si plusieurs `Manga.edition` distincts pointent vers la même série | Acceptable : chacun devient une `Edition` distincte (1 par valeur unique), c'est le comportement souhaité |
| Régression collection : l'utilisateur perd l'accès à son Berserk après migration | La migration garantit *exactement* une `Edition` par `(manga_id, edition_string)` existant ; test fonctionnel sur scénario de bascule |
| zxing-js bundle size impact | Importé en `defineAsyncComponent` côté Vue pour code-splitting ; n'impacte que la page qui ouvre le modal |
| Doublons mal dédupliqués entre Google Books et BNF | Clé canonique `lowercase(strip_diacritics(publisher_name)) + '|' + lowercase(label)` ; test unitaire avec cas réels (Berserk Maximum vu sur GBooks + BNF) |

### Code retiré

- **Champs Doctrine** : `Manga.edition` (varchar), `Manga.externalId`, `Manga.language`. Les FK `manga_id` sur `volumes`, `collection_entries`, `wishlist_items` sont remplacées par `edition_id`.
- **Endpoints retirés** : aucun endpoint complet n'est supprimé (on garde `/api/manga/external` en alias rétrocompat vers `/api/editions/search` dans Task 14, puis suppression définitive dans une PR ultérieure). `POST /api/collection` change de signature (body : `mangaId` → `editionId`) ; idem `POST /api/wishlist`.
- **Méthode interface** : `ExternalApiClientInterface::searchByTitle()` étendu avec `searchByIsbn(Isbn $isbn): array<ExternalMangaDto>` ; `getMangaById()` conservé tel quel.
- **Fichiers à supprimer** : aucun fichier complet. Le helper `BaseEditionSelector.vue` est conservé mais relégué au fallback manuel (`<details>`).

---

### Tasks

- Task 1 : Domain — entité `Edition`, VOs `PublisherName` + `EditionLabel`, enum `EditionLabel` valeurs canoniques.
- Task 2 : Domain service `EditionLabelExtractor` (regex sur title+subtitle).
- Task 3 : Extension `ExternalApiClientInterface` + DTO `EditionSearchResultDto` + adapter `NullMangaApiClient`.
- Task 4 : Extension `GoogleBooksMangaApiClient` : `searchByIsbn` exposé + extraction publisher + label via `EditionLabelExtractor`.
- Task 5 : Nouveau client `BnfSruApiClient` + parser `BnfUnimarcParser`.
- Task 6 : Nouveau provider `AmazonCoverProvider` (cover par ISBN, conversion ISBN-13→10 via `Isbn`).
- Task 7 : Service Domain `EditionAggregator` + handlers `SearchEditionsHandler` / `ImportEditionHandler` / `LookupIsbnHandler`.
- Task 8 : Refacto `CollectionEntry` / `VolumeEntry` / `WishlistItem` / `Stats` pour pointer sur `Edition`.
- Task 9 : Migration Doctrine + backfill SQL (Manga.edition → Edition rows).
- Task 10 : Endpoints HTTP — `EditionController`, `LookupController`, adaptation `CollectionController` / `WishlistController` / `MangaController`.
- Task 11 : Tests fonctionnels nouveaux endpoints + mise à jour tests existants.
- Task 12 : Front — API layer (`editions.ts`, `lookup.ts`) + types (`Edition`, `EditionSearchResult`).
- Task 13 : Front — composables `useEditionSearch` (refactor) + `useIsbnLookup` + `useBarcodeScanner`.
- Task 14 : Front — composants `EditionPicker.vue`, `BarcodeScannerModal.vue`, `IsbnInput.vue`.
- Task 15 : Front — refonte `AddMangaPage.vue` (3→2 étapes, picker édition intégré).
- Task 16 : Front — adaptation `CollectionPage.vue` / `CollectionDetailPage.vue` / `WishlistPage.vue` (affichage édition + bouton scan ISBN contextuel).
- Task 17 : Mise à jour `.claude/CLAUDE.md` (Bounded Contexts + API Endpoints + External API).
- Task 18 : Final lint, test, and review loop.

---

#### Task 1 : Domain — entité `Edition`, VOs `PublisherName` + `EditionLabel`

Introduire l'entité `Edition` et les deux VOs qui composent son identité naturelle.

**Skills and docs to load:**
- `/project-quality-setup` — hexagonal, `final readonly`, Deptrac.
- `.claude/backend.md` — R10 (naming), section Doctrine.
- `.claude/CLAUDE.md` — règle « never FQCN built-ins », section Doctrine Mapping Rules.

**Files:**
- Create `back/src/Manga/Domain/Edition.php`
- Create `back/src/Manga/Domain/PublisherName.php`
- Create `back/src/Manga/Domain/EditionLabel.php`
- Create `back/src/Manga/Domain/Exception/InvalidPublisherNameException.php`
- Create `back/src/Manga/Domain/Exception/InvalidEditionLabelException.php`

**Implementation**

`PublisherName` (`final readonly`) :
- Constructeur privé, factory statique `fromString(string $raw): self`.
- Normalisation : trim + collapse whitespaces + reject empty + max 100 chars.
- Méthodes : `value` (public string), `equals(self): bool`, `__toString(): string`.
- Lève `InvalidPublisherNameException` si vide ou > 100 chars.

`EditionLabel` (`final readonly`) :
- Constantes : `STANDARD = 'Standard'`, `MAXIMUM = 'Maximum'`, `PRESTIGE = 'Prestige'`, `PERFECT = 'Perfect'`, `DELUXE = 'Deluxe'`, `WIDEBAN = 'Wideban'`, `POCKET = 'Pocket'`, `COLLECTOR = 'Collector'`, `ORIGINALE = 'Édition Originale'`, `BIG = 'Big'`, `ULTIMATE = 'Ultimate'`.
- Constructeur privé, factory `fromString(string $raw): self` qui normalise contre la liste connue (case-insensitive, accents tolérés) ; sinon `STANDARD` par défaut.
- Méthode `isCanonical(): bool` pour distinguer un label canonique d'une chaîne libre.
- Méthodes : `value`, `equals(self)`, `__toString()`.

`Edition` (entité Doctrine) :
```php
#[ORM\Entity]
#[ORM\Table(name: 'editions')]
#[ORM\UniqueConstraint(columns: ['manga_id', 'publisher_name', 'label'])]
final class Edition
{
    /** @var Collection<int, Volume> */
    #[ORM\OneToMany(
        targetEntity: Volume::class,
        mappedBy: 'edition',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    #[ORM\OrderBy(['number' => 'ASC'])]
    public Collection $volumes;

    #[ORM\Column]
    public DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public readonly string $id,
        #[ORM\ManyToOne(targetEntity: Manga::class, inversedBy: 'editions')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public Manga $manga,
        #[ORM\Column(length: 100, nullable: true)]
        public ?string $publisherName,
        #[ORM\Column(length: 50)]
        public string $label,
        #[ORM\Column(length: 5, options: ['default' => 'fr'])]
        public string $language = 'fr',
        #[ORM\Column(nullable: true)]
        public ?int $totalVolumes = null,
        #[ORM\Column(nullable: true)]
        public ?string $coverUrl = null,
    ) {
        $this->volumes = new ArrayCollection();
        $this->createdAt = new DateTimeImmutable();
    }

    public function addVolume(Volume $volume): void { /* idempotent contains check */ }
    public function toArray(): array { /* id, mangaId, publisherName, label, language, totalVolumes, coverUrl, volumeCount */ }
    public function toDetailArray(): array { /* toArray + volumes[] triés par number ASC */ }
}
```

`Manga` est modifié pour ajouter la collection inverse `editions` (`OneToMany Edition::class`, mappedBy: 'manga'). Les champs `edition`, `language`, `externalId` de `Manga` sont retirés à la Task 9 lors de la migration (en Task 1 on ne touche pas encore aux propriétés existantes pour ne pas casser le build).

**Tests**

- `back/tests/Unit/Manga/Domain/EditionTest.php` :
  - Construction avec tous les champs.
  - `addVolume()` idempotent.
  - `toArray()` expose tous les champs attendus.
  - `toDetailArray()` trie les volumes par `number ASC`.
- `back/tests/Unit/Manga/Domain/PublisherNameTest.php` :
  - `fromString('Glénat')` OK, `value === 'Glénat'`.
  - `fromString('  Glénat   ')` normalisé.
  - `fromString('')` → `InvalidPublisherNameException`.
  - `fromString(str_repeat('x', 101))` → exception.
  - `equals()` true sur même valeur, false sinon.
- `back/tests/Unit/Manga/Domain/EditionLabelTest.php` :
  - `fromString('Maximum')` → `EditionLabel::MAXIMUM`.
  - `fromString('maximum')` (case-insensitive) → `EditionLabel::MAXIMUM`.
  - `fromString('édition prestige')` → `EditionLabel::PRESTIGE`.
  - `fromString('inconnu')` → fallback `EditionLabel::STANDARD`, `isCanonical() === false`.
  - `fromString('')` → fallback `EditionLabel::STANDARD`.

**Verify**

```bash
make test-php
docker compose exec back ./vendor/bin/phpunit --filter "EditionTest|PublisherNameTest|EditionLabelTest" --testdox
```

Attendu : tous les tests Edition / PublisherName / EditionLabel verts.

---

#### Task 2 : Domain service `EditionLabelExtractor`

Service qui extrait le label canonique d'une édition à partir des métadonnées textuelles d'un résultat externe (typiquement `volumeInfo.title + volumeInfo.subtitle` Google Books).

**Skills and docs to load:**
- `.claude/backend.md` — R3 (handlers/services purs), R10 (naming).

**Files:**
- Create `back/src/Manga/Domain/EditionLabelExtractor.php`

**Implementation**

```php
final readonly class EditionLabelExtractor
{
    public function extract(string $title, ?string $subtitle = null): EditionLabel
    {
        $haystack = strtolower(($title ?? '') . ' ' . ($subtitle ?? ''));
        $haystack = $this->stripDiacritics($haystack);

        return match (true) {
            str_contains($haystack, 'maximum')                 => EditionLabel::fromString(EditionLabel::MAXIMUM),
            str_contains($haystack, 'prestige')                => EditionLabel::fromString(EditionLabel::PRESTIGE),
            str_contains($haystack, 'perfect')                 => EditionLabel::fromString(EditionLabel::PERFECT),
            str_contains($haystack, 'deluxe')                  => EditionLabel::fromString(EditionLabel::DELUXE),
            str_contains($haystack, 'wideban')                 => EditionLabel::fromString(EditionLabel::WIDEBAN),
            str_contains($haystack, 'pocket')                  => EditionLabel::fromString(EditionLabel::POCKET),
            str_contains($haystack, 'collector')               => EditionLabel::fromString(EditionLabel::COLLECTOR),
            str_contains($haystack, 'edition originale')
                || str_contains($haystack, 'edition orig.'),   => EditionLabel::fromString(EditionLabel::ORIGINALE),
            str_contains($haystack, 'big')                     => EditionLabel::fromString(EditionLabel::BIG),
            str_contains($haystack, 'ultimate')                => EditionLabel::fromString(EditionLabel::ULTIMATE),
            default                                            => EditionLabel::fromString(EditionLabel::STANDARD),
        };
    }

    private function stripDiacritics(string $input): string
    {
        return iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $input) ?: $input;
    }
}
```

Le service est `final readonly`, sans dépendances. Auto-wire par défaut.

**Tests**

- `back/tests/Unit/Manga/Domain/EditionLabelExtractorTest.php` :
  - 10+ cas réels avec fixtures (`'Berserk Maximum - Tome 1'`, `'Berserk - Édition Prestige'`, `'Berserk (Édition Originale)'`, `'Berserk Deluxe Edition'`, etc.).
  - Cas vide → `STANDARD`.
  - Subtitle non null contribue au match (`title='Berserk Tome 5', subtitle='Édition Maximum'` → `MAXIMUM`).
  - Case-insensitive (`'MAXIMUM'`, `'maximum'`, `'Maximum'`).
  - Accents normalisés (`'édition prestige'` → `PRESTIGE`).

**Verify**

```bash
docker compose exec back ./vendor/bin/phpunit --filter EditionLabelExtractor --testdox
```

---

#### Task 3 : Extension `ExternalApiClientInterface` + `EditionSearchResultDto`

Étendre l'interface Domain et ajouter le DTO consommé par l'agrégateur.

**Skills and docs to load:**
- `.claude/backend.md` — R4 (Domain interfaces).

**Files:**
- Modify `back/src/Manga/Domain/ExternalApiClientInterface.php` — ajouter `searchByIsbn(Isbn $isbn): array` (retourne `ExternalMangaDto[]`).
- Create `back/src/Manga/Domain/EditionSearchResultDto.php`
- Modify `back/src/Manga/Infrastructure/ExternalApi/NullMangaApiClient.php` — implémente `searchByIsbn()` → `[]`.

**Implementation**

`EditionSearchResultDto` (`final readonly`) — porte tout ce qu'il faut pour afficher une carte et déclencher l'import sans second appel intermédiaire :

```php
final readonly class EditionSearchResultDto
{
    /** @param ExternalVolumeDto[] $volumes */
    public function __construct(
        public string $source,              // 'google_books' | 'bnf'
        public string $externalEditionId,   // ID unique côté source (à utiliser pour l'import)
        public string $mangaTitle,
        public ?string $publisherName,
        public string $label,               // EditionLabel::value
        public string $language,
        public ?int $totalVolumes,
        public ?string $coverUrl,
        public ?string $author,
        public ?string $summary,
        public ?string $genre,
        public array $volumes = [],         // pré-rempli si la source ramène déjà les tomes
    ) {}
}
```

`ExternalApiClientInterface` après modification :
```php
interface ExternalApiClientInterface
{
    /** @return ExternalMangaDto[] */
    public function searchByTitle(string $query, string $type = 'manga', int $page = 1): array;

    /** @return ExternalMangaDto[] */
    public function searchByIsbn(Isbn $isbn): array;

    public function getMangaById(string $externalId): ?ExternalMangaDto;
}
```

`NullMangaApiClient::searchByIsbn(Isbn)` retourne `[]`.

**Tests**

- `back/tests/Unit/Manga/Domain/EditionSearchResultDtoTest.php` — construction + accès à tous les champs (1 test simple, structure de données).
- Modifier `back/tests/Unit/Manga/Infrastructure/NullMangaApiClientTest.php` (créer si absent) — `searchByIsbn()` retourne `[]`.

**Verify**

```bash
docker compose exec back ./vendor/bin/phpunit --filter "EditionSearchResultDto|NullMangaApi" --testdox
```

---

#### Task 4 : Extension `GoogleBooksMangaApiClient`

Exposer `searchByIsbn()` sur l'interface (le client le supportait déjà en privé via `findByIsbn`), et améliorer le `mapToDto()` pour produire des `ExternalMangaDto` enrichis avec `publisher` ET `label` détecté.

**Skills and docs to load:**
- `.claude/backend.md` — R4.

**Files:**
- Modify `back/src/Manga/Infrastructure/ExternalApi/GoogleBooksMangaApiClient.php`
- Modify `back/src/Manga/Domain/ExternalMangaDto.php` — ajouter `?string $publisher` et `?string $editionLabel` (déjà avait `edition` string vague, on conserve pour rétrocompat mais on ajoute les deux champs structurés ; `edition` sera retiré en Task 9 par migration).

**Implementation**

- Ajouter dépendance `EditionLabelExtractor` au constructeur de `GoogleBooksMangaApiClient`.
- Dans `mapToDto()` :
  - `publisher = $info['publisher'] ?? null` (déjà extrait, juste à exposer dans le nouveau champ `publisher` du DTO).
  - `editionLabel = $this->editionLabelExtractor->extract($info['title'] ?? '', $info['subtitle'] ?? null)->value`.
- Implémenter `searchByIsbn(Isbn $isbn): array` :
  ```php
  $response = $this->httpClient->request('GET', self::BASE_URL . '/volumes', [
      'query' => ['q' => 'isbn:' . $isbn->value, 'key' => $this->apiKey],
  ]);
  $data = $response->toArray();
  return array_values(array_filter(array_map(
      fn (array $item) => $this->mapToDto($item),
      $data['items'] ?? [],
  )));
  ```
- Conserver le `langRestrict=fr` codé en dur (multi-pays hors scope).

**Tests**

- Modifier `back/tests/Unit/Manga/Infrastructure/GoogleBooksMangaApiClientTest.php` :
  - `testSearchByIsbnHitsCorrectEndpoint` : mock response → vérifie que la query string est `q=isbn:9782845993029&key=...`.
  - `testSearchByIsbnReturnsEmptyOnNoResults` : mock empty response → `[]`.
  - `testMapToDtoExtractsPublisherAndEditionLabel` : fixture Google Books `volumeInfo` avec `publisher='Glénat'` et `title='Berserk Maximum T1'` → DTO avec `publisher='Glénat'`, `editionLabel='Maximum'`.
  - `testMapToDtoFallsBackToStandardLabel` : `title='Berserk T5'` sans mot-clé → `editionLabel='Standard'`.

**Verify**

```bash
docker compose exec back ./vendor/bin/phpunit --filter GoogleBooksMangaApiClient --testdox
```

---

#### Task 5 : Nouveau client `BnfSruApiClient` + parser `BnfUnimarcParser`

Client BNF SRU 1.2 qui parse l'XML UNIMARC et expose les mêmes méthodes que `GoogleBooksMangaApiClient`.

**Skills and docs to load:**
- `.claude/backend.md` — R4.

**Files:**
- Create `back/src/Manga/Infrastructure/ExternalApi/BnfSruApiClient.php`
- Create `back/src/Manga/Infrastructure/ExternalApi/BnfUnimarcParser.php`
- Create `back/tests/Fixtures/Bnf/berserk_search.xml` — fixture XML SRU contenant 3 notices Berserk (Glénat Standard, Glénat Maximum, Pika Maximum) capturées depuis l'API réelle, anonymisées.
- Create `back/tests/Fixtures/Bnf/isbn_9782845993029.xml` — fixture XML pour une lookup ISBN.
- Modify `back/config/services.yaml` — déclarer `BnfSruApiClient` avec injection HttpClient + LoggerInterface. Tagger `manga.edition_search_client`.

**Implementation**

`BnfSruApiClient` (`final readonly`) :
- URL base : `http://catalogue.bnf.fr/api/SRU`.
- Query SRU :
  - Title search : `version=1.2&operation=searchRetrieve&query=bib.title%20all%20%22{q}%22%20and%20bib.subject%20any%20%22manga%22&maximumRecords=20&recordSchema=unimarcxchange`.
  - ISBN search : `version=1.2&operation=searchRetrieve&query=bib.isbn%20all%20%22{isbn}%22&maximumRecords=5&recordSchema=unimarcxchange`.
- Timeout HTTP : 5s, 1 retry exponentiel (1s puis 2s).
- Parse via `BnfUnimarcParser::parse(string $xml): array<ExternalMangaDto>`.

`BnfUnimarcParser` (`final readonly`) :
- Utilise `SimpleXMLElement` (use import explicite, jamais `\SimpleXMLElement`).
- Extrait pour chaque `<record>` :
  - `title` : champ UNIMARC 200$a
  - `author` : champ 700$a
  - `publisher` : champ 210$c
  - `isbn` : champ 010$a (peut nécessiter `Isbn::tryFrom`)
  - `releaseDate` : champ 210$d (parse l'année)
  - `summary` : champ 330$a (optionnel)
  - `language` : champ 101$a (déduit `'fr'` si `'fre'`)
- Retourne un `ExternalMangaDto` par notice, source `'bnf'`. Pas de cover (BNF n'en fournit pas) — le cover sera complété par Amazon/OpenLibrary à l'import via le composite.

`BnfSruApiClient::searchByTitle()` retourne `ExternalMangaDto[]` ; `searchByIsbn()` idem ; `getMangaById($id)` retourne 1 DTO (SRU recordIdentifier sert d'ID externe).

**Tests**

- `back/tests/Unit/Manga/Infrastructure/BnfSruApiClientTest.php` :
  - `testSearchByTitleParsesFixture` : mock HttpClient renvoyant `berserk_search.xml` → 3 `ExternalMangaDto` avec `publisher` et `isbn`.
  - `testSearchByIsbnReturnsSingleResult` : fixture `isbn_9782845993029.xml` → 1 DTO.
  - `testHandlesEmptySruResponse` : XML vide (`<numberOfRecords>0</numberOfRecords>`) → `[]`.
  - `testHandlesMalformedXmlGracefully` : XML invalide → `[]` + log error.
  - `testRetriesOnNetworkFailure` : 1ʳᵉ requête échoue, 2ᵉ réussit → DTOs retournés (`MockHttpClient` avec 2 responses).
- `back/tests/Unit/Manga/Infrastructure/BnfUnimarcParserTest.php` :
  - `testParsesAllUnimarcFields` : fixture XML → tous les champs (title, author, publisher, isbn, releaseDate, language).
  - `testHandlesMissingOptionalFields` : notice sans summary → DTO avec `summary = null`.

**Verify**

```bash
docker compose exec back ./vendor/bin/phpunit --filter "BnfSruApiClient|BnfUnimarcParser" --testdox
```

---

#### Task 6 : Nouveau provider `AmazonCoverProvider`

Cover par ISBN via URL Amazon déterministe. Conversion ISBN-13 → ISBN-10 nécessaire.

**Skills and docs to load:**
- `.claude/backend.md` — R4.

**Files:**
- Create `back/src/Manga/Infrastructure/ExternalApi/AmazonCoverProvider.php`
- Modify `back/src/Manga/Domain/Isbn.php` — exposer `toIsbn10(): ?string` (conversion publique, méthode actuelle `convertIsbn10ToIsbn13` reste privée).
- Modify `back/config/services.yaml` — déclarer `AmazonCoverProvider` taggé `manga.cover_provider` (rejoint Google Books / MangaDex / OpenLibrary dans le composite existant).

**Implementation**

`Isbn::toIsbn10()` :
- Si `$this->value` commence par `978` : extraire les 9 chiffres `[3..12]`, calculer la check digit ISBN-10 modulo 11, retourner les 10 caractères.
- Sinon (`979…`) : retourner `null` (pas de mapping ISBN-13→10 pour le préfixe 979).

`AmazonCoverProvider` (`final readonly`, implements `MangaCoverProviderInterface`) :
- `findByIsbn(Isbn $isbn): ?MangaVolumeCoverDto` :
  - `$isbn10 = $isbn->toIsbn10()` ; si null → retour null.
  - URL : `https://images-na.ssl-images-amazon.com/images/P/{$isbn10}.jpg`.
  - HEAD request (timeout 2s) ; si 200 + content-length > 1000 → retourner `MangaVolumeCoverDto` avec `source='amazon'`.
  - Si 404 ou content-length ≤ 1000 (placeholder Amazon) → null.
- `findByContext(...)` : retourne null (Amazon n'a pas de recherche par contexte).

**Tests**

- `back/tests/Unit/Manga/Infrastructure/AmazonCoverProviderTest.php` :
  - `testFindByIsbnReturnsCoverWhenHeadIs200` : mock HttpClient HEAD 200 + content-length 5000 → DTO.
  - `testFindByIsbnReturnsNullOn404` : HEAD 404 → null.
  - `testFindByIsbnReturnsNullForIsbn13StartingWith979` : ISBN-13 `979…` → null (pas de mapping).
  - `testFindByContextAlwaysReturnsNull`.
- `back/tests/Unit/Manga/Domain/IsbnTest.php` (modifié) :
  - `testToIsbn10ConvertsValidIsbn13` : `978-2-8459-9302-9` → `'2845993021'`.
  - `testToIsbn10ReturnsNullForPrefix979` : `9791234567896` → null.

**Verify**

```bash
docker compose exec back ./vendor/bin/phpunit --filter "AmazonCoverProvider|Isbn" --testdox
```

---

#### Task 7 : `EditionAggregator` + handlers Application

Service Domain qui agrège Google Books + BNF, déduplique sur `(publisher_name, label)`. Handlers Application pour les 3 use cases (search, import, lookup).

**Skills and docs to load:**
- `.claude/backend.md` — R3 (orchestrators), R5 (events), R11 (pagination).
- `.claude/CLAUDE.md` — `AbstractPaginatedQuery` + `PaginatedResult`.

**Files:**
- Create `back/src/Manga/Domain/EditionAggregator.php`
- Create `back/src/Manga/Application/SearchEditions/SearchEditionsQuery.php`
- Create `back/src/Manga/Application/SearchEditions/SearchEditionsHandler.php`
- Create `back/src/Manga/Application/SearchEditions/EditionSearchPaginatedResult.php`
- Create `back/src/Manga/Application/ImportEdition/ImportEditionCommand.php`
- Create `back/src/Manga/Application/ImportEdition/ImportEditionHandler.php`
- Create `back/src/Manga/Application/LookupIsbn/LookupIsbnQuery.php`
- Create `back/src/Manga/Application/LookupIsbn/LookupIsbnHandler.php`
- Create `back/src/Manga/Application/LookupIsbn/IsbnLookupResultDto.php`
- Create `back/src/Manga/Domain/Event/EditionImportedEvent.php`
- Create `back/src/Manga/Domain/Repository/EditionRepositoryInterface.php`
- Create `back/src/Manga/Infrastructure/Doctrine/Repository/DoctrineEditionRepository.php`
- Modify `back/config/services.yaml` — tagger `manga.edition_search_client` sur `GoogleBooksMangaApiClient` + `BnfSruApiClient` ; injection `!tagged_iterator manga.edition_search_client` dans `EditionAggregator`.

**Implementation**

`EditionAggregator::aggregate(string $query, int $page = 1): array<EditionSearchResultDto>` :
1. Pour chaque client taggé `manga.edition_search_client`, appelle `searchByTitle($query, 'manga', $page)`.
2. Mappe les `ExternalMangaDto` en `EditionSearchResultDto` (extrait `publisher_name`, `label`).
3. Déduplique sur clé canonique :
   ```php
   $key = strtolower(trim($result->mangaTitle))
        . '|' . strtolower(trim($result->publisherName ?? ''))
        . '|' . strtolower(trim($result->label));
   ```
4. Pour les doublons : fusionne en gardant le DTO le plus complet (priorité au plus grand nombre de champs non nuls ; ex-aequo → préfère `source = 'bnf'` pour les métadonnées textuelles, `source = 'google_books'` pour la cover).
5. Tri final : `totalVolumes DESC NULLS LAST`, puis `mangaTitle ASC`.

`SearchEditionsQuery` (extend `AbstractPaginatedQuery`) :
```php
final readonly class SearchEditionsQuery extends AbstractPaginatedQuery
{
    public function __construct(
        public string $query,
        int $page = 1,
        int $limit = 20,
    ) {
        parent::__construct($page, $limit);
    }
}
```

`EditionSearchPaginatedResult` (extend `PaginatedResult<EditionSearchResultDto>`) :
```php
/** @extends PaginatedResult<EditionSearchResultDto> */
final class EditionSearchPaginatedResult extends PaginatedResult
{
    protected function serializeItems(): array
    {
        return array_map(
            static fn (EditionSearchResultDto $result) => [
                'source'            => $result->source,
                'externalEditionId' => $result->externalEditionId,
                'mangaTitle'        => $result->mangaTitle,
                'publisherName'     => $result->publisherName,
                'label'             => $result->label,
                'language'          => $result->language,
                'totalVolumes'      => $result->totalVolumes,
                'coverUrl'          => $result->coverUrl,
                'author'            => $result->author,
                'summary'           => $result->summary,
                'genre'             => $result->genre,
            ],
            $this->items,
        );
    }
}
```

`SearchEditionsHandler` (Application, `final readonly`) :
- Injecte `EditionAggregator`.
- `__invoke(SearchEditionsQuery): array` → délègue à l'agrégateur, enveloppe dans `EditionSearchPaginatedResult`, `toArray()`.

`ImportEditionCommand` :
```php
final readonly class ImportEditionCommand
{
    public function __construct(
        public string $source,              // 'google_books' | 'bnf'
        public string $externalEditionId,
        public string $mangaTitle,
        public ?string $publisherName,
        public string $label,
        public string $language,
        public ?int $totalVolumes,
        public ?string $coverUrl,
        public ?string $author,
        public ?string $summary,
        public ?string $genre,
    ) {}
}
```

`ImportEditionHandler` (orchestrator, R3) :
1. Lookup `Manga` par `title + author` (normalisé) → crée si absent.
2. Lookup `Edition` par `(manga, publisher_name, label)` → si déjà présent, retourne son ID (idempotent).
3. Sinon, crée `Edition` avec les champs du command.
4. Si `totalVolumes > 0`, crée N `Volume` skeleton (sans ISBN ni cover individuelle ; le composite `MangaCoverProviderInterface` existant via `/api/manga/volume-search` peut être déclenché ensuite par la page de détail).
5. Persiste en transaction.
6. Dispatch `EditionImportedEvent(editionId)` sur l'event bus (R5).
7. Retourne l'`Edition` créée.

`LookupIsbnQuery(Isbn $isbn)` :
- Pas paginé (résolution unique).

`LookupIsbnHandler` :
1. Cherche en base : `Volume.isbn = $isbn` → si trouvé, retourne `IsbnLookupResultDto(manga, edition, volume)`.
2. Sinon, pour chaque client `manga.edition_search_client` : `searchByIsbn($isbn)`. Premier hit non vide gagne (Google Books a priorité).
3. Si trouvé externe : mappe en `EditionSearchResultDto`, dispatche `ImportEditionCommand` via command.bus, récupère `Edition`. Crée le `Volume` correspondant à l'ISBN (numéro inféré depuis le DTO si présent, sinon `1`). Persiste.
4. Cover du volume : composite `MangaCoverProviderInterface::findByIsbn()` (déjà câblé, complété par `AmazonCoverProvider`).
5. Retourne `IsbnLookupResultDto`.
6. Si rien trouvé nulle part : lève `IsbnNotFoundException` (handled par l'ExceptionListener → 404).

`IsbnLookupResultDto` (`final readonly`) : `manga: array, edition: array, volume: array` (toArray des entités).

`EditionImportedEvent` (`final readonly`) : `editionId: string`, `source: string`.

`DoctrineEditionRepository` : méthodes `findById`, `findByMangaAndPublisherAndLabel`, `save`.

**Tests**

- `back/tests/Unit/Manga/Domain/EditionAggregatorTest.php` :
  - 2 clients fakes, l'un renvoie `Berserk Maximum (Pika)`, l'autre `Berserk Maximum (Pika)` (même `publisher+label`) → 1 seul DTO dans le résultat, champs fusionnés.
  - Tri : DTO avec 21 tomes avant DTO sans `totalVolumes`.
  - Fusion : cover prise depuis `google_books`, summary depuis `bnf`.
- `back/tests/Unit/Manga/Application/SearchEditions/SearchEditionsHandlerTest.php` — handler enveloppe dans `EditionSearchPaginatedResult`, `toArray()` contient `items`, `total`, `page`, `limit`.
- `back/tests/Unit/Manga/Application/ImportEdition/ImportEditionHandlerTest.php` :
  - Command avec `totalVolumes = 5` → 1 `Manga` (si absent), 1 `Edition`, 5 `Volume` skeleton.
  - Idempotence : 2ᵉ import avec même `(manga, publisher, label)` → retourne l'ID existant, ne crée pas de doublon.
  - Dispatch `EditionImportedEvent` une seule fois.
- `back/tests/Unit/Manga/Application/LookupIsbn/LookupIsbnHandlerTest.php` :
  - ISBN déjà en base → retourne `IsbnLookupResultDto` sans appel externe.
  - ISBN inconnu, Google Books renvoie DTO → import déclenché, DTO retourné.
  - ISBN inconnu, aucune source ne répond → `IsbnNotFoundException`.

**Verify**

```bash
docker compose exec back ./vendor/bin/phpunit --filter "EditionAggregator|SearchEditionsHandler|ImportEditionHandler|LookupIsbnHandler" --testdox
```

---

#### Task 8 : Refacto `CollectionEntry` / `VolumeEntry` / `WishlistItem` / `Stats`

Bascule des FK `manga_id` vers `edition_id`. Les entités gardent `manga` accessible via `edition->manga` (pas de duplication).

**Skills and docs to load:**
- `.claude/backend.md` — R3 (handlers purs), R6 (Shared layer), section Doctrine.
- `.claude/CLAUDE.md` — Doctrine Mapping Rules (defaults, enum length).

**Files:**
- Modify `back/src/Collection/Domain/CollectionEntry.php` — `ManyToOne Edition $edition` (au lieu de `Manga`) ; unique `(owner_id, edition_id)` ; `toArray()` expose `edition` (via `$this->edition->toArray()`) + `manga` (via `$this->edition->manga->toArray()`).
- Modify `back/src/Collection/Domain/VolumeEntry.php` — relation inchangée (lié à `Volume`), mais `Volume.edition` accessible via le chemin.
- Modify `back/src/Wishlist/Domain/WishlistItem.php` — idem `CollectionEntry`.
- Modify `back/src/Manga/Domain/Volume.php` — remplacer `ManyToOne Manga $manga` par `ManyToOne Edition $edition` ; unique `(edition_id, number)`.
- Modify `back/src/Manga/Domain/Manga.php` — retirer `edition`, `language`, `externalId` (champs orphelins après migration) ; ajouter `OneToMany Edition $editions` inverse.
- Modify `back/src/Collection/Application/AddCollectionEntry/AddCollectionEntryCommand.php` — `editionId` au lieu de `mangaId`.
- Modify `back/src/Collection/Application/AddCollectionEntry/AddCollectionEntryHandler.php` — charge `Edition` par ID.
- Modify `back/src/Wishlist/Application/*` (équivalent) — `editionId`.
- Modify `back/src/Stats/Application/GetStatsHandler.php` :
  - `collectionValue` : `SUM(v.price)` joint via `Volume → Edition → Manga`.
  - `genreBreakdown` : `GROUP BY m.genre` via `JOIN editions e ON ce.edition_id = e.id JOIN mangas m ON e.manga_id = m.id`.
- Modify `back/src/Collection/Shared/CollectionReaderInterface.php` (si présent) — DTO retourné expose `edition` + sa `manga`.

**Implementation**

`CollectionEntry::toArray()` :
- Continue d'exposer une clé `manga` racine (champs `title`, `author`, `summary`, `genre` lus depuis `$this->edition->manga`) **PLUS** une clé `edition` (`publisherName`, `label`, `language`, `totalVolumes`, `coverUrl`).
- `totalVolumes` (anciennement `$this->manga->volumes->count()`) → `$this->edition->volumes->count()`.
- Cela évite de casser le front avant la Task 16.

`WishlistItem` : structure symétrique.

**Tests**

- Modifier `back/tests/Unit/Collection/Domain/CollectionEntryTest.php` :
  - Construit avec une `Edition` (qui contient un `Manga`).
  - `toArray()['edition']['label']` OK.
  - `toArray()['manga']['title']` OK (rétrocompat front).
  - `ownedValue` calculé sur les volumes de l'édition.
- Modifier `back/tests/Unit/Wishlist/Domain/WishlistItemTest.php` — idem.
- Modifier `back/tests/Unit/Manga/Domain/MangaTest.php` — `toArray()` ne contient plus `edition`, `language`, `externalId`.
- Modifier `back/tests/Unit/Manga/Domain/VolumeTest.php` — `volume.edition` au lieu de `volume.manga`.

**Verify**

```bash
make test-php
```

Tous les tests unit verts après le refacto.

---

#### Task 9 : Migration Doctrine + backfill

Générer la migration via `make migration`, l'enrichir avec le `addSql()` de backfill SQL, et le DROP final de `mangas.edition`.

**Skills and docs to load:**
- `.claude/CLAUDE.md` — section « Doctrine Mapping Rules », « FK / index names — always use `make migration` ».
- `.claude/backend.md` — R10.

**Files:**
- Create `back/migrations/VersionYYYYMMDDHHMMSS.php` (généré, puis édité).

**Implementation**

1. Vérifier que les Tasks 1, 7, 8 sont en place localement (entités modifiées). `doctrine:schema:validate` devra être vert APRÈS la migration.
2. Lancer `make migration` — Doctrine génère :
   - `CREATE TABLE editions (id VARCHAR(36) NOT NULL, manga_id VARCHAR(36) NOT NULL, publisher_name VARCHAR(100) DEFAULT NULL, label VARCHAR(50) NOT NULL, language VARCHAR(5) DEFAULT 'fr' NOT NULL, total_volumes INT DEFAULT NULL, cover_url VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))`.
   - Index unique `(manga_id, publisher_name, label)`.
   - FK `editions.manga_id → mangas.id ON DELETE CASCADE`.
   - `ALTER TABLE volumes ADD edition_id VARCHAR(36)` ; FK ; index unique `(edition_id, number)` ; DROP unique `(manga_id, number)` ; DROP FK + colonne `manga_id`.
   - Idem pour `collection_entries` et `wishlist_items`.
   - `ALTER TABLE mangas DROP edition`, `DROP external_id`, `DROP language`.
3. Insérer manuellement, dans `up()` AVANT les `DROP COLUMN` mais APRÈS les `ADD COLUMN edition_id` (nullable) et `CREATE TABLE editions`, le SQL suivant :

```sql
-- A : 1 Edition par tuple (manga_id, edition_string) existant
INSERT INTO editions (id, manga_id, publisher_name, label, language, total_volumes, cover_url, created_at)
SELECT
    gen_random_uuid()::text,
    m.id,
    NULL,
    COALESCE(NULLIF(TRIM(m.edition), ''), 'Standard'),
    COALESCE(m.language, 'fr'),
    (SELECT COUNT(*) FROM volumes WHERE manga_id = m.id),
    m.cover_url,
    m.created_at
FROM mangas m;

-- B : volumes -> editions
UPDATE volumes v
SET edition_id = (SELECT e.id FROM editions e WHERE e.manga_id = v.manga_id LIMIT 1);

-- C : collection_entries -> editions
UPDATE collection_entries ce
SET edition_id = (SELECT e.id FROM editions e WHERE e.manga_id = ce.manga_id LIMIT 1);

-- D : wishlist_items -> editions
UPDATE wishlist_items wi
SET edition_id = (SELECT e.id FROM editions e WHERE e.manga_id = wi.manga_id LIMIT 1);
```

4. Ajouter `ALTER TABLE volumes ALTER COLUMN edition_id SET NOT NULL` (idem pour collection_entries et wishlist_items) APRÈS les UPDATE.
5. `down()` : symétrique, avec commentaire `// WARNING: drops all editions beyond the first per manga, destructive in dev only`.

**Verify**

```bash
make migrate
docker compose exec back php bin/console doctrine:schema:validate
# Attendu : « [OK] The mapping files are correct. » + « [OK] The database schema is in sync with the mapping files. »

docker compose exec db psql -U app -d app -c "SELECT COUNT(*) FROM volumes WHERE edition_id IS NULL;"
# Attendu : 0

docker compose exec db psql -U app -d app -c "SELECT COUNT(*) FROM collection_entries WHERE edition_id IS NULL;"
# Attendu : 0
```

---

#### Task 10 : Endpoints HTTP — `EditionController`, `LookupController`, adaptation existants

Exposer les nouveaux endpoints et adapter les controllers existants pour la nouvelle signature `editionId`.

**Skills and docs to load:**
- `.claude/CLAUDE.md` — `#[MapRequestPayload]`, pas de try/catch dans les controllers.
- `.claude/backend.md` — R11 (pagination).

**Files:**
- Create `back/src/Manga/Infrastructure/Http/EditionController.php` — routes :
  - `GET /api/editions/search?q=&page=1` → `SearchEditionsQuery`.
  - `POST /api/editions/import` (payload `ImportEditionRequest`) → `ImportEditionCommand`.
  - `GET /api/editions/{id}` → `GetEditionQuery`.
- Create `back/src/Manga/Infrastructure/Http/ImportEditionRequest.php` (`final readonly`, `#[MapRequestPayload]`) :
  - Validation : `source` requis (`Choice` parmi `google_books|bnf`), `externalEditionId` requis, `mangaTitle` requis (min 1, max 255), `label` requis, `language` requis (min 2, max 5).
- Create `back/src/Manga/Application/GetEdition/GetEditionQuery.php` + Handler.
- Create `back/src/Manga/Infrastructure/Http/LookupController.php` — route `GET /api/lookup/isbn/{isbn}` → `LookupIsbnQuery`.
- Modify `back/src/Manga/Infrastructure/Http/MangaController.php` — ajouter `GET /api/manga/{id}/editions` → query qui renvoie la liste des `Edition` rattachées au `Manga`.
- Modify `back/src/Collection/Infrastructure/Http/CollectionController.php` :
  - `POST /api/collection` (payload `{editionId}` au lieu de `{mangaId}`).
  - `GET /api/collection` retourne `edition` enrichie dans chaque entrée.
- Modify `back/src/Wishlist/Infrastructure/Http/WishlistController.php` — idem.
- Modify `back/src/Collection/Infrastructure/Http/AddCollectionEntryRequest.php` — `editionId` au lieu de `mangaId`, validation `Uuid` (déjà appliquée).

**Implementation**

`EditionController::search()` :
- Lit `q` (string, requis, non vide ; sinon 400) et `page` (int, default 1 ; min 1).
- Dispatch `SearchEditionsQuery($q, $page)` sur le query.bus.
- Retourne le résultat tel quel (`EditionSearchPaginatedResult::toArray()`).

`EditionController::import()` :
- Reçoit `ImportEditionRequest` via `#[MapRequestPayload]`.
- Mappe en `ImportEditionCommand`, dispatch sur le command.bus.
- Récupère l'`Edition` créée via `HandledStamp` (le handler retourne l'entité).
- Retourne `201 Created` + `Edition::toDetailArray()`.

`LookupController::lookupByIsbn(string $isbn)` :
- Valide l'ISBN via `Isbn::tryFrom($isbn)` → 400 si invalide.
- Dispatch `LookupIsbnQuery($isbn)` sur le query.bus.
- Retourne `IsbnLookupResultDto` sérialisé `{manga: {...}, edition: {...}, volume: {...}}`.
- Si non trouvé : `IsbnNotFoundException` → 404 (gérée par `ExceptionListener`).

`MangaController::listEditionsForManga(string $mangaId)` :
- Query Doctrine via `EditionRepositoryInterface::findByMangaId($mangaId)`.
- Retourne `[Edition::toArray(), ...]`.

`AddCollectionEntryHandler` (modifié) :
- Reçoit `editionId`, charge l'`Edition`, crée `CollectionEntry` en passant `$edition` + `$owner`.
- Crée les `VolumeEntry` pour chaque `Volume` de l'`Edition` (comportement actuel, juste rebranché sur la nouvelle FK).

**Tests**

Couverts dans Task 11.

**Verify**

```bash
docker compose exec back php bin/console debug:router | grep -E 'editions|lookup'
# Attendu : les nouvelles routes apparaissent.
make php-qa
```

---

#### Task 11 : Tests fonctionnels

**Skills and docs to load:**
- `.claude/CLAUDE.md` — testing mandatory, DAMA savepoints, `NullMangaApiClient` en `when@test`.

**Files:**
- Create `back/tests/Functional/Manga/EditionControllerTest.php` :
  - `testSearchEditionsRequiresJwt` — sans Bearer → 401.
  - `testSearchEditionsRejectsEmptyQuery` — `q=` → 400.
  - `testSearchEditionsReturnsEmptyInTestEnvWithNullClient` — `GET /api/editions/search?q=berserk` → 200 + `{items: [], total: 0}`.
  - `testSearchEditionsReturnsAggregatedResultsWithFakeClient` — en service `when@test` taggé `manga.edition_search_client`, injecter un `FakeEditionSearchClient` qui renvoie 2 DTOs (Glénat Maximum + Pika Maximum) → 200 + 2 items.
  - `testImportEditionRequiresJwt` → 401.
  - `testImportEditionRejectsInvalidSource` — `source=xxx` → 400.
  - `testImportEditionRejectsMissingFields` — payload incomplet → 400.
  - `testImportEditionCreatesMangaEditionAndVolumesInTransaction` — payload complet (Berserk, Pika, Maximum, 21 tomes) → 201 + `Edition::toDetailArray()` ; vérifier en DB : 1 Manga, 1 Edition, 21 Volume.
  - `testImportEditionIsIdempotent` — 2ᵉ POST identique → 200 (ou 201 avec même `editionId` ; à arbitrer dans le handler, comportement documenté).
  - `testGetEditionByIdReturns404IfMissing`.
  - `testGetEditionByIdReturnsDetailWithVolumes`.
  - `testListEditionsForMangaReturnsArray` — `GET /api/manga/{id}/editions` → 200 + `[]` ou liste.

- Create `back/tests/Functional/Manga/LookupControllerTest.php` :
  - `testLookupRequiresJwt` → 401.
  - `testLookupRejectsInvalidIsbn` — `/api/lookup/isbn/abc` → 400.
  - `testLookupReturnsExistingVolumeWhenInDb` — fixture Volume.isbn = 9782845993029 → 200 + manga/edition/volume.
  - `testLookupReturns404WhenUnknownIsbnAndNoExternalHit` — NullClient, ISBN inconnu → 404.
  - `testLookupAutoImportsWhenExternalHit` — `when@test` `FakeEditionSearchClient::searchByIsbn` renvoie un DTO → 200 + entités auto-créées en DB.

- Modify `back/tests/Functional/Collection/CollectionControllerTest.php` :
  - `testAddCollectionEntryNowAcceptsEditionId` — `POST /api/collection {editionId: ...}` → 201.
  - `testAddCollectionEntryRejectsLegacyMangaIdPayload` — `POST /api/collection {mangaId: ...}` → 400.
  - `testGetCollectionExposesEditionInResults` — chaque entry a `edition.publisherName`, `edition.label`, `edition.totalVolumes`.
- Modify `back/tests/Functional/Wishlist/WishlistControllerTest.php` — symétrique.
- Modify `back/tests/Functional/Stats/StatsControllerTest.php` — `collectionValue` et `genreBreakdown` restent corrects après bascule (joindre via Edition).
- Modify `back/tests/Functional/Manga/MangaControllerTest.php` — retirer les références à `Manga.edition` (string) dans les assertions ; vérifier que `GET /api/manga` ne plante pas après suppression du champ.

**Implementation**

- Créer un client de test `back/tests/Stub/FakeEditionSearchClient.php` (`final readonly`, implements `ExternalApiClientInterface`), enregistré en `when@test` taggé `manga.edition_search_client`. Il expose `pushResults(array $editionSearchResultDtos)` et `pushIsbnResults(array $externalMangaDtos)` pour piloter les retours depuis les tests.
- Étendre le helper `importManga()` (s'il existe) pour produire des `Edition` paramétrables, ou créer un nouveau helper `importEdition(array $overrides)` dans `back/tests/Functional/AbstractFunctionalTestCase.php` (ou équivalent).

**Verify**

```bash
make test-php
docker compose exec back ./vendor/bin/phpunit --filter "Edition|Lookup|Collection|Wishlist|Stats" --testdox
```

---

#### Task 12 : Front — API layer + types

API client et types TS partagés.

**Skills and docs to load:**
- `/vue-best-practices` — TS strict, types nommés.

**Files:**
- Create `front/src/api/editions.ts` — `searchEditions(q, page)`, `importEdition(payload)`, `getEdition(id)`, `listEditionsForManga(mangaId)`.
- Create `front/src/api/lookup.ts` — `lookupIsbn(isbn)`.
- Modify `front/src/api/collection.ts` — `addToCollection({editionId})` au lieu de `{mangaId}` ; le type retourné inclut désormais `edition`.
- Modify `front/src/api/wishlist.ts` — symétrique.
- Create `front/src/types/edition.ts` — types `Edition`, `EditionSearchResult`, `IsbnLookupResult`, `EditionLabel` (union string littérale des 11 valeurs canoniques).
- Modify `front/src/types/index.ts` — interface `CollectionEntry` enrichie avec `edition: Edition`.

**Implementation**

```ts
// front/src/types/edition.ts
export type EditionLabel =
  | 'Standard' | 'Maximum' | 'Prestige' | 'Perfect' | 'Deluxe'
  | 'Wideban' | 'Pocket' | 'Collector' | 'Édition Originale' | 'Big' | 'Ultimate'

export interface Edition {
  id: string
  mangaId: string
  publisherName: string | null
  label: EditionLabel
  language: string
  totalVolumes: number | null
  coverUrl: string | null
  volumeCount: number
}

export interface EditionSearchResult {
  source: 'google_books' | 'bnf'
  externalEditionId: string
  mangaTitle: string
  publisherName: string | null
  label: EditionLabel
  language: string
  totalVolumes: number | null
  coverUrl: string | null
  author: string | null
  summary: string | null
  genre: string | null
}

export interface IsbnLookupResult {
  manga: { id: string; title: string; author: string | null; genre: string | null }
  edition: Edition
  volume: { id: string; number: number; isbn: string; coverUrl: string | null }
}
```

**Verify**

```bash
make vue-qa
docker compose exec app npx vue-tsc --noEmit
```

---

#### Task 13 : Front — composables `useEditionSearch`, `useIsbnLookup`, `useBarcodeScanner`

Trois composables consommés par les composants de Task 14 et 15.

**Skills and docs to load:**
- `/vue-best-practices` — Composition API + `<script setup>` + TS.
- `/create-adaptable-composable` — accepter `MaybeRefOrGetter` pour les inputs réactifs.
- `/vue-testing-best-practices` — Vitest + Vue Test Utils + mocks.

**Files:**
- Modify `front/src/composables/useExternalSearch.ts` → **renommer en `useEditionSearch.ts`**.
  - Signature : `useEditionSearch(): { query, results, isLoading, isLoadingMore, hasMore, error, search(q), loadMore(), clear() }`.
  - L'appel HTTP devient `searchEditions(q, page)` depuis `front/src/api/editions.ts`.
  - Type des résultats : `EditionSearchResult[]`.
- Create `front/src/composables/useIsbnLookup.ts` :
  - Signature : `useIsbnLookup(): { lookup(isbn: string): Promise<IsbnLookupResult>; isLoading; error }`.
  - Appelle `lookupIsbn(isbn)` ; convertit les erreurs 404 en `error.value = 'isbn_not_found'`.
- Create `front/src/composables/useBarcodeScanner.ts` :
  - Wrap `@zxing/browser` (`new BrowserMultiFormatReader()`).
  - Expose `{ start(videoElementRef: Ref<HTMLVideoElement | null>, onDecode: (isbn: string) => void): Promise<void>; stop(): void; hasCamera: ComputedRef<boolean>; permissionState: Ref<'unknown'|'granted'|'denied'> }`.
  - Détection caméra : `navigator.mediaDevices?.enumerateDevices()` filtré sur `kind === 'videoinput'`.
  - Permission : demande via `getUserMedia({video: true})` ; si `NotAllowedError` → `permissionState = 'denied'`.
  - Décodage : sur succès, normalise le code-barres (ISBN-13 EAN-13 : 13 chiffres, parfois préfixe `978` ou `979`) ; appelle `onDecode(code)`.
- Modify `front/package.json` — ajouter `"@zxing/browser": "^0.1.5"`.

**Implementation**

`useEditionSearch` reset complet sur changement de `query` ; debounce 400ms (réutiliser le pattern existant de `useExternalSearch`).

`useBarcodeScanner` est importé en lazy/async dans le modal pour code-splitting :
```ts
// dans le composant BarcodeScannerModal
const { useBarcodeScanner } = await import('@/composables/useBarcodeScanner')
```

**Tests** (seulement si Vitest est configuré côté front — vérifier d'abord `make test-vue`)

- `front/src/composables/__tests__/useEditionSearch.test.ts` :
  - Appel `search('berserk')` → query string `q=berserk`, results populés.
  - `loadMore()` incrémente `page`.
  - Changement de `query` reset les résultats.
- `front/src/composables/__tests__/useIsbnLookup.test.ts` :
  - ISBN valide → `result` populé.
  - 404 → `error = 'isbn_not_found'`.
- `front/src/composables/__tests__/useBarcodeScanner.test.ts` :
  - Mock `@zxing/browser` ; `start()` appelle `decodeFromVideoDevice` ; `onDecode` reçoit l'ISBN.
  - Mock `navigator.mediaDevices.getUserMedia` rejette avec `NotAllowedError` → `permissionState = 'denied'`.

**Verify**

```bash
docker compose exec app npm install --save @zxing/browser
make vue-qa
```

---

#### Task 14 : Front — composants `EditionPicker.vue`, `BarcodeScannerModal.vue`, `IsbnInput.vue`

Composants consommés par `AddMangaPage` (Task 15) et `CollectionDetailPage` (Task 16).

**Skills and docs to load:**
- `/vue-best-practices` — Composition API, props/emits typés, slots.
- `/vue-testing-best-practices` — tests de composants.

**Files:**
- Create `front/src/components/molecules/EditionPicker.vue` :
  - Props : `mangaTitle: string`, `searchResults: EditionSearchResult[]`, `isLoading: boolean`.
  - Emits : `(e: 'select', edition: EditionSearchResult): void`, `(e: 'manual'): void` (bascule sur saisie manuelle).
  - UI : liste DaisyUI de cartes ; chaque carte affiche jaquette (`coverUrl` ou placeholder), `publisherName · label · totalVolumes` ; bouton `Choisir cette édition`. Tout en bas, `<details>` `Saisir manuellement` → émet `manual`.
- Create `front/src/components/organisms/BarcodeScannerModal.vue` :
  - Props : `open: boolean`.
  - Emits : `(e: 'close'): void`, `(e: 'scanned', isbn: string): void`.
  - UI : modal DaisyUI mobile-first. Section principale : `<video ref="videoRef">` 320×240. Section secondaire (toujours visible) : `IsbnInput` pour fallback clavier.
  - Au mount : si `useBarcodeScanner().hasCamera`, démarre le scanner ; sinon affiche `IsbnInput` agrandi.
  - Si `permissionState = 'denied'` après tentative : message « Permission caméra refusée, saisir l'ISBN ci-dessous ».
- Create `front/src/components/molecules/IsbnInput.vue` :
  - Props : `modelValue: string`, `autofocus?: boolean`.
  - Emits : `(e: 'update:modelValue', v: string)`, `(e: 'submit', isbn: string)`.
  - UI : input texte DaisyUI, validation client-side : normalise (strip `-` et espaces), vérifie longueur 10 ou 13, vérifie checksum ISBN-13 (logique en JS). En cas d'erreur, affichage en rouge.

**Implementation**

`EditionPicker` :
```vue
<script setup lang="ts">
import type { EditionSearchResult } from '@/types/edition'

defineProps<{
  mangaTitle: string
  searchResults: EditionSearchResult[]
  isLoading: boolean
}>()

defineEmits<{
  select: [edition: EditionSearchResult]
  manual: []
}>()
</script>
```

`BarcodeScannerModal` charge `useBarcodeScanner` en async (code-splitting) ; appelle `scanner.start(videoRef, (isbn) => emit('scanned', isbn))`.

`IsbnInput` valide ISBN-13 côté front avec la même checksum modulo 10 que `Isbn::validateIsbn13Checksum` côté back. Émet `submit` uniquement si ISBN valide.

**Tests** (si Vitest configuré)

- `front/src/components/molecules/__tests__/EditionPicker.test.ts` :
  - Render avec 3 résultats → 3 cartes.
  - Clic carte → emit `select` avec le bon DTO.
  - Clic `Saisir manuellement` → emit `manual`.
- `front/src/components/molecules/__tests__/IsbnInput.test.ts` :
  - Input `9782845993029` (valide) + Enter → emit `submit` avec ISBN normalisé.
  - Input `1234567890123` (checksum invalide) + Enter → pas d'emit, message d'erreur visible.

**Verify**

```bash
make vue-qa
```

---

#### Task 15 : Front — refonte `AddMangaPage.vue` (3→2 étapes)

Remplacer le flow actuel (recherche série → formulaire → destination) par 2 étapes : (1) recherche d'éditions, (2) confirmation + import.

**Skills and docs to load:**
- `/vue-best-practices` — Composition API.
- `/vue-router-best-practices` — redirection après confirmation.

**Files:**
- Modify `front/src/pages/AddMangaPage.vue`

**Implementation**

```
┌────────── Step 1 : Recherche ───────────────┐
│  [berserk                               🔍] │
│  ──────────────────────────────────────── │
│  EditionPicker affiché avec results        │
│  - Berserk · Maximum · Pika · 21 tomes     │
│  - Berserk · Standard · Glénat · 41 tomes  │
│  - Berserk · Prestige · Glénat · 14 tomes  │
│  [+ Saisir manuellement (collapse)]        │
└─────────────────────────────────────────────┘
                 │ clic « Choisir »
                 ▼
┌────────── Step 2 : Confirmation ────────────┐
│  Berserk · Édition Prestige                │
│  Glénat · 14 tomes                          │
│  Destination : ( ) Collection  ( ) Wishlist│
│                       [Confirmer l'import] │
└─────────────────────────────────────────────┘
                 │
                 ▼
        redirect /collection/:editionId
```

Logique :
- `currentStep: 1 | 2`.
- Step 1 utilise `useEditionSearch` ; affiche `EditionPicker` avec les résultats.
- Sur `select(edition)` : `selectedEdition.value = edition` + `currentStep.value = 2`.
- Sur `manual` : ouvre un sous-formulaire avec `BaseEditionSelector` (existant, conservé) + champs publisher/label libres → permet la saisie d'une édition non trouvée.
- Step 2 affiche les détails de la sélection + radio Collection/Wishlist + bouton `Confirmer`.
- Au confirm :
  1. `importEdition({source, externalEditionId, mangaTitle, publisherName, label, language, totalVolumes, coverUrl, author, summary, genre})` → récupère l'`editionId`.
  2. `addToCollection({editionId})` ou `addToWishlist({editionId})` selon destination.
  3. Redirige vers `/collection/{editionId}` ou `/wishlist`.

**Verify**

```bash
make vue-qa
make dev
# Test manuel http://localhost:5173/add :
#  1. Taper « berserk » → au moins 1 carte (Standard/Maximum/Prestige selon Google Books)
#  2. Choisir une édition → step 2 affiche les détails
#  3. Confirmer → redirect /collection/{editionId}
#  4. Vérifier que la collection affiche publisherName + label
```

---

#### Task 16 : Front — adaptation `CollectionPage` / `CollectionDetailPage` / `WishlistPage`

Affichage de l'édition (publisher + label) sur les listes et la fiche détail. Bouton `Scanner l'ISBN` contextuel sur `CollectionDetailPage`.

**Skills and docs to load:**
- `/vue-best-practices` — props typées.
- `/vue-pinia-best-practices` — consommation potentielle de stores.

**Files:**
- Modify `front/src/pages/CollectionPage.vue` — pastille `{publisherName} · {label}` sur chaque carte.
- Modify `front/src/pages/CollectionDetailPage.vue` :
  - Sous le titre : `{publisherName} · {label}`.
  - Nouveau bouton `📷 Scanner l'ISBN` qui ouvre `BarcodeScannerModal`. À la réception de l'event `scanned`, appelle `useIsbnLookup().lookup(isbn)` et — si l'édition retournée matche l'édition courante — toggle le `VolumeEntry` correspondant en `isOwned = true`. Sinon, affiche un toast d'erreur (« cet ISBN appartient à une autre édition »).
  - Bouton désactivé si `navigator.mediaDevices` indisponible (desktop sans webcam) ; tooltip explicatif.
- Modify `front/src/pages/WishlistPage.vue` — pastille édition.
- Modify `front/src/components/organisms/CollectionList.vue` (ou nom équivalent) — colonne édition.

**Implementation**

`CollectionDetailPage` — gestion du scan :
```ts
async function onScanned(isbn: string) {
  const result = await useIsbnLookup().lookup(isbn)
  if (result.edition.id !== currentEditionId.value) {
    pushToast({ kind: 'error', message: t('scan.wrongEdition') })
    return
  }
  await toggleVolume(currentCollectionId.value, result.volume.id, 'isOwned')
  pushToast({ kind: 'success', message: t('scan.volumeMarkedOwned', { num: result.volume.number }) })
}
```

`CollectionPage` filtres : conservés tels quels, mais les filtres `edition` actuels (qui filtraient sur la string libre) deviennent un filtre `publisherName` + `label`.

**Verify**

```bash
make vue-qa
make dev
# Test manuel :
#  1. Importer Berserk Prestige → /collection/{editionId} affiche publisherName + label
#  2. Cliquer « Scanner l'ISBN » sur mobile (Chrome) → modal s'ouvre, demande permission caméra
#  3. Scanner le code-barres d'un tome (ou saisir l'ISBN clavier) → le volume devient « possédé »
#  4. Scanner un ISBN d'une autre édition → toast d'erreur, rien n'est modifié
```

---

#### Task 17 : Mise à jour `.claude/CLAUDE.md`

**Skills and docs to load:**
- `/update-coding-rules` — format CLAUDE.md.

**Files:**
- Modify `/home/user/Ziggytheque/.claude/CLAUDE.md`

**Implementation**

- Section « Bounded Contexts » : ajouter `Edition` sous `Manga/` :
  ```
  - `Manga/` — Manga + Edition + Volume entities, ExternalApiClientInterface, EditionAggregator
  ```
- Section « API Endpoints » : ajouter :
  ```
  - GET    /api/editions/search?q=&page=
  - POST   /api/editions/import
  - GET    /api/editions/:id
  - GET    /api/manga/:id/editions
  - GET    /api/lookup/isbn/:isbn
  ```
- Adapter `POST /api/collection` et `POST /api/wishlist` pour mentionner `{editionId}`.
- Section « External API » : remplacer le bloc « Google Books » par un bloc multi-sources :
  ```
  ## External API (Google Books + BNF)
  - Interface: App\Manga\Domain\ExternalApiClientInterface (searchByTitle / searchByIsbn / getMangaById)
  - DTOs: ExternalMangaDto (publisher, editionLabel), EditionSearchResultDto (agrégateur)
  - Implementations: GoogleBooksMangaApiClient, BnfSruApiClient, NullMangaApiClient (test stub)
  - Cover providers: GoogleBooks, MangaDex, OpenLibrary, Amazon (par ISBN, URL déterministe)
  - Requires: GOOGLE_BOOKS_API_KEY env var
  - Aggregator: EditionAggregator (Domain service) déduplique sur (publisherName, label)
  - Endpoints: GET /api/editions/search?q= → EditionSearchResult[], POST /api/editions/import, GET /api/lookup/isbn/:isbn
  ```
- Nouvelle sous-section « Editions Rule » :
  ```
  ## Editions Rule

  - Une `Manga` (série) a 1..N `Edition`.
  - Une `Edition` a 1..N `Volume`.
  - `CollectionEntry` et `WishlistItem` pointent sur `Edition`, jamais sur `Manga` directement.
  - La recherche externe renvoie des éditions (pas des séries), agrégées et dédupliquées côté serveur sur `(publisherName, label)`.
  - Le lookup ISBN (`/api/lookup/isbn/:isbn`) résout en une requête : Manga + Edition + Volume (auto-import si nouveau).
  - Le scan code-barres mobile est exposé UNIQUEMENT sur le flow « volume possédé » (CollectionDetailPage), pas dans la recherche.
  ```

**Verify**

```bash
git diff .claude/CLAUDE.md
# Inspection visuelle : les 4 modifications listées sont présentes.
```

---

#### Task 18 : Final lint, test, and review loop.

Once finished, run lint and test for the affected sub-directories, dump the git diff in a tmp file with `git diff HEAD > /tmp/last-review.diff`, then spawn a `file-reviewer` subagent with the prompt: *"Review the diff at /tmp/last-review.diff. Invoke the `code-review` skill, follow its 'Load thematic rules by file type' step using the diff's file paths, and write structured findings."*
Fix every finding, then redo all three steps. Repeat until lint passes, tests pass, and the subagent returns no findings (max 5 iterations).
