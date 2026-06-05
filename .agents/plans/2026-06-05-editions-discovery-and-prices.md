# Découverte multi-éditions & prix marchands (Ziggytheque)

> Plan généré le 2026-06-05. Branche de travail : `claude/friendly-noether-MfZze`.
> Plan-only : ce document décrit l'intention, il n'implémente rien.

### TL;DR

> [!NOTE]
> Aujourd'hui une œuvre comme *Berserk* existe dans Ziggythèque sous forme de fiches **indépendantes et non reliées** : la version classique Glénat, l'édition prestige (Maximum), la Deluxe US… sont des entrées séparées qu'on doit retrouver à la main. Ce plan ajoute deux super-pouvoirs **gratuits et légaux**.
>
> **1) Découverte d'éditions.** À partir d'un simple titre, on interroge en direct des catalogues bibliographiques gratuits — la **BnF** (qui, grâce au dépôt légal, liste *chaque* édition française avec son éditeur, sa collection et son ISBN) et **Open Library** (qui couvre le mieux les éditions US/anglaises : Deluxe reliées « cuir », omnibus, 3-en-1), enrichis par **Google Books**. On regroupe tout par « ligne d'édition » (éditeur + langue + format) et on l'affiche groupé par pays, avec drapeau, format, nombre de tomes et une couverture. L'utilisateur voit *toutes* les éditions existantes d'une œuvre et importe celle qu'il veut en un clic — dans le flux d'ajout **et** dans un nouvel onglet « Éditions » de la fiche.
>
> **2) Prix & où l'acheter.** Un nouvel onglet « Prix » sur la fiche affiche, pour chaque tome (via son ISBN), des offres marchandes : logo du marchand + prix + bouton « Voir l'offre » qui redirige (lien affilié). La source principale est l'**API eBay Browse** (gratuite, recherche par ISBN, marchés FR *et* US, lien affilié inclus), complétée par un prix de référence (« prix éditeur ») issu de Google Books. Comme les ISBN découverts à l'étape 1 alimentent à la fois le **système de couvertures existant** et la recherche de prix, retrouver couvertures *et* prix devient nettement plus facile. Tout est conçu pour rester sous les quotas (mise en cache) et **sans scraping fragile/illégal** (FNAC, Cultura, Amazon sont protégés par WAF — explicitement écartés au profit de leurs flux d'affiliation, prévus plus tard).

---

### Implementation

#### État actuel

```
Manga (table mangas)                         Volume (table volumes)
 ├─ id, title                                 ├─ id, manga_id, number
 ├─ edition  (string libre : "Glénat")  ◀──   ├─ coverUrl, price (float, saisi main)
 ├─ language (fr/en…)                          ├─ releaseDate
 ├─ coverUrl, genre, author, summary           ├─ isbn  (VO Isbn, type Doctrine 'isbn')
 └─ volumes (1—N)                              └─ spineUrl

UNE fiche Manga = UNE édition. Les éditions d'une même œuvre ne sont PAS reliées.

Système multi-providers DÉJÀ en place (le patron à imiter) :
  MangaCoverProviderInterface ──┐
  MultiSourceCoverProviderInt. ─┤
        CompositeMangaCoverApiClient  (!tagged_iterator app.manga_cover_provider)
          ├─ MangaDex (100)  ├─ BnF (80)  ├─ OpenLibrary covers (70)
          ├─ GoogleBooks dynamic-links (60)  └─ Hardcover (55)
  + service-locator par provider (SearchExternal/SearchVolumeExternal)
  + Mercure pour la progression temps réel (batch covers, scan)
  + VO Isbn (valide & normalise ISBN-10 → 13)
```

#### État cible

Deux nouveaux **composites multi-providers** strictement calqués sur celui des couvertures, plus deux onglets sur la fiche `/collection/:id` (`MangaDetailPage.vue`) et un mode « Éditions » dans `/add`.

```
DÉCOUVERTE D'ÉDITIONS                          PRIX MARCHANDS
 EditionProviderInterface                       VolumePriceProviderInterface
   CompositeEditionProvider                       CompositePriceProvider
   (!tagged_iterator                              (!tagged_iterator
    app.manga_edition_provider)                    app.volume_price_provider)
     ├─ BnfEditionProvider     (FR, SRU)            ├─ EbayBrowsePriceProvider (FR+US, gtin)
     ├─ OpenLibraryEditionProv (US/EN, editions)    ├─ GoogleBooksPriceProvider (saleInfo)
     └─ GoogleBooksEditionProv (enrichissement)     └─ (opt.) MangaNewsReferencePriceProvider
   → EditionGrouper (domain svc : dédup par           (désactivé par défaut)
     éditeur+langue+format)                        + PriceOfferCacheInterface (cache pool, TTL 24h)
   → DiscoverEditionsHandler                       → GetVolumePricesHandler

Endpoints :  GET /api/manga/editions?q=&author=&language=
             GET /api/manga/{id}/editions
             GET /api/manga/{id}/volumes/{volumeId}/prices?marketplace=EBAY_FR
```

Onglets de `MangaDetailPage.vue` (DaisyUI `tabs`, état via query-param `?tab=`) :

```
┌ Tomes (existant) ┬ Éditions (neuf) ┬ Prix / Où l'acheter (neuf) ┐
│ grille de tomes  │ 🇫🇷 Glénat — classique (18 t.)   [Importer]   │
│                  │ 🇫🇷 Glénat — éd. Maximum (12 t.) [Importer]   │
│                  │ 🇺🇸 Dark Horse — Deluxe relié    [Importer]   │
│                  │ ────────────────────────────────────────────│
│                  │ Tome 1 · 9782723…                            │
│                  │  [logo eBay] à partir de 7,50 € [Voir l'offre]│
│                  │  [logo Play] Prix éditeur 6,99 €  (indicatif) │
└──────────────────┴──────────────────┴───────────────────────────┘
```

#### Providers d'éditions — conclusions de recherche (à intégrer tel quel)

| Source | Gratuit / auth | Modèle « éditions d'une œuvre » | FR | US/EN | Requête de référence |
|---|---|---|---|---|---|
| **BnF SRU** (`catalogue.bnf.fr/api/SRU`) | gratuit, **sans auth** ; `BNF_BASE_URL` **déjà câblé** | **Oui, autoritatif** (dépôt légal : 1 notice par édition, avec éditeur + collection + ISBN + date) | **Meilleur** | faible | `?version=1.2&operation=searchRetrieve&query=bib.title all "berserk"&recordSchema=dublincore&maximumRecords=50` → XML Dublin Core (`dc:title`,`dc:publisher`,`dc:date`,`dc:identifier`=ISBN,`dc:language`) |
| **Open Library editions.json** (`openlibrary.org`) | gratuit, sans clé ; **UA descriptif requis** (3 req/s) | **Oui** (Work `OL…W` → N Editions) | inégal | **Meilleur** (Deluxe reliée, omnibus, 3-in-1) | `GET /search.json?q={titre}&fields=key,title,author_name,edition_count` → `GET /works/{key}/editions.json?limit=500` → `entries[]` (`publishers[]`,`languages[{key:"/languages/fre"}]`,`isbn_13[]`,`isbn_10[]`,`physical_format`,`publish_date`,`covers[]`) |
| **Google Books** (`googleapis.com/books/v1`) | gratuit, **clé requise** ~1000/j ; `GoogleBooksMangaApiClient` déjà câblé | partiel (1 ISBN ≈ 1 volume, pas d'arbre work) | correct | correct | `GET /volumes?q=intitle:{t}+inauthor:{a}&langRestrict=fr&maxResults=40&key=` (opérateurs `isbn:`,`inpublisher:`) |
| AniList / Jikan / MangaUpdates | gratuit | **Non** (niveau série) | — | — | *Hors périmètre découverte d'éditions* — n'apportent que l'identité canonique du titre |

**Pipeline retenu** : titre → **BnF SRU** (toutes éditions FR) **+** **Open Library** (éditions US/EN) → **Google Books** (enrichit ISBN/couvertures manquants) → `EditionGrouper` dédoublonne par `(éditeur + langue + format normalisé)`.

#### Providers de prix — stratégie en tiers (conclusions de recherche)

| Tier | Source | Gratuit / auth | Prix par ISBN | Lien marchand + logo | FR | US | Type |
|---|---|---|---|---|---|---|---|
| **1 (principal)** | **eBay Browse API** | gratuit, **OAuth app-token** (client-credentials), 5000/j | **Oui** : `item_summary/search?gtin={isbn13}` → `price.value`/`price.currency` | **Oui** : `itemAffiliateWebUrl` (via EPN `affiliateCampaignId`), `image.imageUrl` | `EBAY_FR` | `EBAY_US` | `merchant_live` (« à partir de » : annonces neuves/occasion) |
| **2 (référence)** | **Google Books `saleInfo`** | gratuit, clé | partiel : `saleInfo.listPrice/retailPrice` + `buyLink` (Google Play) — **`&country=FR/US` obligatoire** sinon 403 depuis le cloud, **e-book seulement** | Google Play (pas d'affiliation) | oui | oui | `publisher_reference` (indicatif) |
| **2b (opt., désactivé)** | manga-news / nautiljon (prix éditeur) | gratuit mais **403 aux fetchers**, CGU restrictives | EAN → prix éditeur EUR | non | oui | non | `publisher_reference` |
| 3 (plus tard) | Awin (FNAC) / Affilae (Cultura) | gratuit, **approbation par marchand** | feed EAN | oui + deeplink affilié | oui | non | `merchant_live` |
| ❌ À ÉVITER | FNAC/Cultura/Amazon scraping, PA-API, isbnsearch/bookfinder | — | — | — | — | — | WAF DataDome / CGU / API coupée mai 2026 |

**v1 livre Tier 1 + Tier 2.** Le Tier 2b est implémenté comme provider **désactivé par défaut** (no-op sans flag) : l'architecture l'accepte, mais v1 n'embarque aucun scraper fragile. Tier 3/4 = évolutions documentées, hors de ce plan.

#### Flux de données

```
Découverte d'éditions (Add ou onglet Éditions)
  Front useEditions(title) ─GET /api/manga/editions─▶ DiscoverEditionsHandler
    ├─ CompositeEditionProvider.findEditions(title,author,lang)
    │    ├─ BnF SRU (FR)  ├─ OpenLibrary (US/EN)  └─ GoogleBooks (enrich)
    └─ EditionGrouper.group(dtos) → lignes d'édition dédupliquées
  ◀── ExternalEdition[] (groupé par pays)  ──▶ import via flux ImportManga existant

Prix (onglet Prix / EnrichVolumeModal)
  Front useVolumePrices(mangaId,volumeId) ─GET …/prices?marketplace─▶ GetVolumePricesHandler
    ├─ résout Volume.isbn (404 si manga/volume inconnu ; [] si pas d'ISBN)
    ├─ PriceOfferCache.get(isbn,marketplace)  ── frais ? ──▶ renvoie
    └─ sinon CompositePriceProvider.findOffers(isbn,marketplace)
         ├─ eBay Browse (merchant_live)  └─ Google Books saleInfo (reference)
       → cache.put(…, TTL 24h)
  ◀── PriceOffer[] (merchant_live triés avant reference)
```

#### Nouveaux objets de domaine (synthèse)

- **Éditions** : `EditionProviderInterface` (port), `ExternalEditionDto`, `EditionFormatEnum` (Broche/Relie/Coffret/Deluxe/Omnibus/Unknown), `CompositeEditionProvider`, `EditionGrouper` (domain service), `NullEditionProvider`.
- **Prix** : `VolumePriceProviderInterface` (port), `PriceOfferDto`, `PriceKindEnum` (MerchantLive/PublisherReference), `Marketplace` (enum : FR/US → `ebayId()`,`currencyCode()`,`fromLanguage()`), `CompositePriceProvider`, `PriceOfferCacheInterface` (+ adaptateur cache pool), `NullPriceProvider`.
- **Doubles de test** (dans `back/tests/Doubles/Manga/`, alias `when@test`) : `StubEditionProvider`, `StubPriceProvider` (calqués sur `StubMangaCoverProvider`).

#### Variables d'environnement (nouvelles)

| Clé | Valeur défaut (back/.env) | Secret ? | Env-sync |
|---|---|---|---|
| `OPEN_LIBRARY_BASE_URL` | `https://openlibrary.org` | non | ajouter à `IGNORE_KEYS` |
| `OPENLIBRARY_USER_AGENT` | `Ziggytheque/1.0 (contact@ziggytheque.fr)` | non | ajouter à `IGNORE_KEYS` |
| `EBAY_BASE_URL` | `https://api.ebay.com` | non | ajouter à `IGNORE_KEYS` |
| `EBAY_OAUTH_URL` | `https://api.ebay.com/identity/v1/oauth2/token` | non | ajouter à `IGNORE_KEYS` |
| `EBAY_CLIENT_ID` | *(vide)* | **oui** | sentinelle `CHANGEME` (Railway) |
| `EBAY_CLIENT_SECRET` | *(vide)* | **oui** | sentinelle `CHANGEME` |
| `EBAY_CAMPAIGN_ID` | *(vide)* | **oui** (EPN) | sentinelle `CHANGEME` |

`GOOGLE_BOOKS_API_KEY` et `BNF_BASE_URL` existent déjà. Le provider eBay est **no-op tant que `EBAY_CLIENT_ID` est vide** (comme Hardcover aujourd'hui) — l'app reste fonctionnelle sans clé, exactement comme le pattern existant.

#### Décisions de persistance

- **Pas de nouvelle entité `Work`.** La découverte est **live + cache**, l'import réutilise `ImportMangaCommand`. Relier les éditions en base serait un sur-investissement : le besoin est de *découvrir* et *importer*, pas de maintenir un graphe d'œuvres.
- **Prix = cache pool, pas de table.** `PriceOfferCacheInterface` est implémenté sur un pool Symfony (`cache.app`, clé `price_offers.{isbn}.{marketplace}`, `expiresAfter(24h)`). Pas de migration, Domaine pur, TTL natif. En test : adaptateur array (déterministe).
- **`Volume.price` est conservé** (prix d'achat saisi par l'utilisateur → valeur de collection). Les offres live sont **séparées** et ne l'écrasent jamais.
- **`Volume.isbn`** reste le pivot : la synergie couvertures/prix consiste à le **remplir** depuis les providers d'éditions (Phase E), ce qui profite immédiatement au batch de couvertures *existant*.

#### Code retiré

**Aucun.** Fonctionnalité purement additive : aucun endpoint, entité, handler ou composant existant n'est supprimé. Les nouveaux providers s'ajoutent via `!tagged_iterator` sans toucher la cascade de couvertures.

#### Découpage en PRs (discipline « un commit par PR »)

5 phases **indépendamment livrables**, une PR (un commit) chacune. Recommandation : les livrer dans l'ordre A→E (B dépend de A ; D dépend de C ; E dépend de A).

- **Phase A** (tâches 1-5) — back : découverte d'éditions.
- **Phase B** (tâches 6-8) — front : UI éditions (Add + onglet).
- **Phase C** (tâches 9-12) — back : prix.
- **Phase D** (tâches 13-14) — front : UI prix.
- **Phase E** (tâche 15) — synergie ISBN → couvertures + prix.

#### Risques & parades

| Risque | Parade |
|---|---|
| Quota Google Books (~1000/j) & eBay (5000/j) | cache 24h par ISBN+marché ; Google Books en *enrichissement* uniquement ; providers no-op sans clé |
| Open Library throttle (3 req/s) | `OPENLIBRARY_USER_AGENT` obligatoire ; 1 appel work + 1 appel editions par recherche |
| `saleInfo` Google absent (physique) → pas de prix | c'est un *reference* opportuniste, jamais l'unique source ; eBay reste le Tier 1 |
| eBay `country`/marché incohérent | `Marketplace::fromLanguage()` mappe fr→EBAY_FR/EUR, en→EBAY_US/USD |
| Couverture ISBN incomplète → onglet Prix vide | message « Aucune offre — ajoute un ISBN » ; bouton « Synchroniser les ISBN » (Phase E) |
| Scraping FR (manga-news) fragile/illégal | Tier 2b **désactivé par défaut**, documenté ; jamais sur le chemin critique de v1 |

---

### Tasks

- **Phase A — back / éditions**
  - Task 1 : Port + DTO + enum + composite + `EditionGrouper` + `NullEditionProvider` + câblage squelette
  - Task 2 : `BnfEditionProvider` (SRU Dublin Core)
  - Task 3 : `OpenLibraryEditionProvider` (search → editions.json)
  - Task 4 : `GoogleBooksEditionProvider` (enrichissement)
  - Task 5 : `DiscoverEditions` query/handler + endpoints + env/câblage/doc + tests fonctionnels
- **Phase B — front / éditions**
  - Task 6 : couche API + composable `useEditions`
  - Task 7 : `EditionCard` + `BaseCountryFlag` + mode « Éditions » du flux `/add` + i18n
  - Task 8 : onglets `MangaDetailPage` + onglet « Éditions »
- **Phase C — back / prix**
  - Task 9 : ports + DTO + enums + `Marketplace` + composite + cache iface + `NullPriceProvider` + câblage
  - Task 10 : `EbayBrowsePriceProvider` + cache du token OAuth
  - Task 11 : `GoogleBooksPriceProvider` + (opt.) `MangaNewsReferencePriceProvider` désactivé
  - Task 12 : `GetVolumePrices` query/handler + adaptateur cache + endpoint + env/doc + tests fonctionnels
- **Phase D — front / prix**
  - Task 13 : couche API + composable `useVolumePrices`
  - Task 14 : `BaseMerchantLogo` + `PriceOfferCard` + onglet « Prix » + section prix dans `EnrichVolumeModal` + i18n
- **Phase E — synergie**
  - Task 15 : batch « synchroniser les ISBN » (éditions → `Volume.isbn`) → couvertures + prix
- Task 16 : boucle finale lint / test / file-reviewer

---

#### Task 1 : Port d'éditions, DTO, enum, composite, grouper, Null + câblage squelette

Poser le socle multi-providers des éditions, calqué **à l'identique** sur `CompositeMangaCoverApiClient` (tagged_iterator + cascade tolérante aux pannes). La logique de regroupement vit dans un **domain service** (R3), pas dans le composite ni le handler.

**Skills and docs to load:**
- `/project-quality-setup` — hexagonal/DDD/CQRS, `final readonly`, conventions de nommage, gates QA back
- `.claude/backend.md` — R1 (events), R3 (handler/domain service), R4 (port Domain ↔ adaptateur Infra), R10 (noms de variables)
- `.claude/CLAUDE.md` — section « Testing » (unit tests obligatoires), FQCN interdits pour classes natives

**Files:**
- Create `back/src/Manga/Domain/EditionProviderInterface.php`
- Create `back/src/Manga/Domain/ExternalEditionDto.php`
- Create `back/src/Manga/Domain/EditionFormatEnum.php`
- Create `back/src/Manga/Domain/Service/EditionGrouper.php`
- Create `back/src/Manga/Infrastructure/ExternalApi/CompositeEditionProvider.php`
- Create `back/src/Manga/Infrastructure/ExternalApi/NullEditionProvider.php`
- Modify `back/config/services.yaml` (alias + tag `app.manga_edition_provider`, override `when@test`)
- Test `back/tests/Unit/Manga/Domain/ExternalEditionDtoTest.php`
- Test `back/tests/Unit/Manga/Domain/EditionFormatEnumTest.php`
- Test `back/tests/Unit/Manga/Domain/Service/EditionGrouperTest.php`
- Test `back/tests/Unit/Manga/Infrastructure/ExternalApi/CompositeEditionProviderTest.php`

**Implementation**

`EditionProviderInterface` :
```php
interface EditionProviderInterface
{
    /** @return list<ExternalEditionDto> */
    public function findEditions(string $workTitle, ?string $author, ?string $language): array;
}
```

`ExternalEditionDto` (`final readonly`, avec `toArray()`) :
```php
public function __construct(
    public string $workTitle,
    public string $editionLabel,      // "Glénat — édition Maximum", "Dark Horse Deluxe"
    public ?string $publisher,
    public string $language,          // ISO 639-1 : fr, en, ja
    public ?string $country,          // ISO 3166-1 : FR, US, JP (déduit langue/éditeur)
    public EditionFormatEnum $format,
    public ?int $volumeCount,         // approximatif
    public ?string $isbnSample,       // un ISBN représentatif (pivot couvertures/prix)
    public ?string $coverUrl,
    public string $source,            // 'bnf', 'open_library', 'google_books'
    public ?string $externalId = null,
) {}
```

`EditionFormatEnum: string` — cases `Broche='broche'`, `Relie='relie'`, `Coffret='coffret'`, `Deluxe='deluxe'`, `Omnibus='omnibus'`, `Unknown='unknown'`, avec `public static function fromRawLabel(?string $raw): self` (mappe « Hardcover/relié/cartonné »→Relie, « Deluxe/édition prestige/Maximum »→Deluxe, « Coffret/box »→Coffret, « Omnibus/3-in-1/intégrale »→Omnibus, défaut→Broche, null→Unknown).

`EditionGrouper::group(array $dtos): array` — domain service pur : dédoublonne par clé `strtolower(publisher) . '|' . language . '|' . format->value`, fusionne (garde le `coverUrl`/`isbnSample` non-null, `volumeCount` = max), renvoie une `list<ExternalEditionDto>` triée par `country` puis `editionLabel`. **Aucune I/O.**

`CompositeEditionProvider implements EditionProviderInterface` — copie structurelle de `CompositeMangaCoverApiClient::findAllByContext` : `iterable $providers` (tagged), boucle `try/catch` par provider (log `error` + skip en cas d'exception, comme l'existant), concatène les `list<ExternalEditionDto>`. **Ne déduplique pas** (c'est le rôle du grouper, appelé par le handler).

`NullEditionProvider` — `findEditions(): []`.

`services.yaml` : alias `App\Manga\Domain\EditionProviderInterface: '@…\CompositeEditionProvider'` ; `CompositeEditionProvider` avec `$providers: !tagged_iterator { tag: app.manga_edition_provider }` ; sous `when@test` alias → `NullEditionProvider`. (Les providers concrets sont taggés dans les tâches 2-4.)

**Tests (TDD pour le grouper et l'enum)**
- `EditionFormatEnumTest` : `fromRawLabel` pour chaque famille de libellés (Hardcover→Relie, « édition Maximum »→Deluxe, « 3-in-1 »→Omnibus, null→Unknown).
- `ExternalEditionDtoTest` : `toArray()` expose toutes les clés (dont `format` sérialisé en `->value`).
- `EditionGrouperTest` : 2 DTO même (éditeur,langue,format) → 1 ligne fusionnée (cover non-null gagnant, volumeCount = max) ; 2 DTO formats différents → 2 lignes ; tri par pays.
- `CompositeEditionProviderTest` : 2 providers stub (un renvoyant 2 DTO, l'autre levant une exception) → résultat = 2 DTO + pas de propagation d'exception (log seulement).

**Verify**
- `make test-php` (ou `docker compose exec back vendor/bin/phpunit --filter 'Edition'`) → vert.
- `make phpstan && make phpcs && make deptrac` → 0 erreur (Deptrac : `EditionGrouper` ne référence que du Domaine ; le composite est en Infrastructure).

---

#### Task 2 : `BnfEditionProvider` (BnF SRU, éditions FR autoritatives)

Adaptateur Infrastructure interrogeant l'API SRU de la BnF (sans auth) et parsant le Dublin Core en `ExternalEditionDto` FR.

**Skills and docs to load:**
- `/project-quality-setup` — conventions adaptateur Infra, `final readonly`
- `.claude/backend.md` — R4 (Infra implémente le port Domain)
- `.claude/CLAUDE.md` — règle « jamais de FQCN pour classes natives » (`SimpleXMLElement`, `Throwable` → `use`)

**Files:**
- Create `back/src/Manga/Infrastructure/ExternalApi/BnfEditionProvider.php`
- Modify `back/config/services.yaml` (tag `app.manga_edition_provider`, priorité 80, `$baseUrl: '%env(BNF_BASE_URL)%'`)
- Test `back/tests/Unit/Manga/Infrastructure/ExternalApi/BnfEditionProviderTest.php`
- Create `back/tests/Fixtures/Bnf/berserk-sru-dublincore.xml` (réponse SRU capturée/forgée)

**Implementation**

Constructeur identique au pattern existant : `HttpClientInterface $httpClient, string $baseUrl, LoggerInterface $logger`. `findEditions()` ne s'active que pour `language` ∈ {null, 'fr'} (sinon `return []`).

Requête (cf. tableau « Providers d'éditions ») :
```
GET {baseUrl}/api/SRU?version=1.2&operation=searchRetrieve
   &query=bib.title all "{title}"   (+ ' and bib.author all "{author}"' si fourni)
   &recordSchema=dublincore&maximumRecords=50&startRecord=1
```
Parser via `SimpleXMLElement` (importé) : enregistrer les namespaces `srw`, `dc` (`http://purl.org/dc/elements/1.1/`), itérer les `srw:record/srw:recordData/*` ; par notice extraire `dc:title`, `dc:publisher` (→ `publisher`), `dc:date` (année), `dc:identifier` filtré sur ISBN (regex `97[89][0-9]{10}` ou ISBN-10 → `Isbn::tryFrom`), `dc:language`. `editionLabel` = collection si présente dans le titre, sinon `"{publisher}"`. `format` = `EditionFormatEnum::fromRawLabel()` sur le titre/notes. `country='FR'`, `language='fr'`, `source='bnf'`. `findByIsbn`/contexte : N/A. Tolérant : tout `Throwable` → log + `return []` (jamais d'exception propagée, comme `OpenLibraryCoversApiClient`).

Câblage `services.yaml` :
```yaml
App\Manga\Infrastructure\ExternalApi\BnfEditionProvider:
    arguments: { $baseUrl: '%env(BNF_BASE_URL)%' }
    tags: [{ name: app.manga_edition_provider, priority: 80 }]
```

**Tests (unit, HTTP mocké)**
Injecter un `MockHttpClient` (Symfony) renvoyant `berserk-sru-dublincore.xml`. Asserter : ≥1 `ExternalEditionDto` ; `publisher` contient « Glénat » ; `language='fr'`, `country='FR'`, `source='bnf'` ; `isbnSample` est un ISBN-13 normalisé ; réponse vide/malformée → `[]` sans exception ; `findEditions(_, _, 'en')` → `[]`.

**Verify**
- `docker compose exec back vendor/bin/phpunit --filter BnfEditionProvider` → vert.
- `make phpstan && make phpcs` → 0 erreur.

---

#### Task 3 : `OpenLibraryEditionProvider` (Work → editions.json, éditions US/EN)

Adaptateur résolvant un titre en Work Open Library puis listant toutes ses éditions (la seule source gratuite avec un vrai arbre Work→Editions ; meilleure couverture des Deluxe reliées « cuir », omnibus, 3-in-1).

**Skills and docs to load:**
- `/project-quality-setup` — conventions adaptateur Infra
- `.claude/backend.md` — R4
- `.claude/CLAUDE.md` — FQCN interdits (`Throwable` → `use`) ; ajout d'env var (`OPEN_LIBRARY_BASE_URL`, `OPENLIBRARY_USER_AGENT`) — voir aussi Task 5 pour `IGNORE_KEYS`

**Files:**
- Create `back/src/Manga/Infrastructure/ExternalApi/OpenLibraryEditionProvider.php`
- Modify `back/config/services.yaml` (tag priorité 70, `$baseUrl`, `$userAgent`)
- Modify `back/.env` (+ `OPEN_LIBRARY_BASE_URL`, `OPENLIBRARY_USER_AGENT`)
- Test `back/tests/Unit/Manga/Infrastructure/ExternalApi/OpenLibraryEditionProviderTest.php`
- Create `back/tests/Fixtures/OpenLibrary/berserk-search.json`, `back/tests/Fixtures/OpenLibrary/berserk-editions.json`

**Implementation**

Constructeur `HttpClientInterface $httpClient, string $baseUrl, string $userAgent, LoggerInterface $logger`. **Tous** les appels passent l'en-tête `User-Agent: {userAgent}` (sans quoi Open Library throttle/bloque). Deux étapes :
1. `GET {baseUrl}/search.json?q={title}&fields=key,title,author_name,edition_count&limit=5` → prendre `docs[0].key` (`/works/OL…W`). Si `author` fourni, filtrer `docs` dont `author_name` matche (insensible casse).
2. `GET {baseUrl}{workKey}/editions.json?limit=500` → pour chaque `entries[]` construire un `ExternalEditionDto` : `publisher`=`publishers[0]`, `language`=mappe `languages[0].key` (`/languages/fre`→`fr`, `/languages/eng`→`en`, `/languages/jpn`→`ja`), `country` déduit (`fr`→FR, `en`→US, sinon null), `format`=`fromRawLabel(physical_format)`, `isbnSample`=`isbn_13[0] ?? isbn_10[0]` (→ `Isbn::tryFrom`), `coverUrl`= `https://covers.openlibrary.org/b/id/{covers[0]}-L.jpg` si présent, `source='open_library'`, `externalId`=clé édition `OL…M`. `volumeCount`=null (rempli par le grouper). Filtre `language` honoré si fourni.

Tolérant : work introuvable / JSON invalide / `Throwable` → log + `[]`.

`services.yaml` :
```yaml
App\Manga\Infrastructure\ExternalApi\OpenLibraryEditionProvider:
    arguments:
        $baseUrl: '%env(OPEN_LIBRARY_BASE_URL)%'
        $userAgent: '%env(OPENLIBRARY_USER_AGENT)%'
    tags: [{ name: app.manga_edition_provider, priority: 70 }]
```
`.env` : `OPEN_LIBRARY_BASE_URL=https://openlibrary.org` et `OPENLIBRARY_USER_AGENT="Ziggytheque/1.0 (contact@ziggytheque.fr)"`. (Attention : distinct de `OPEN_LIBRARY_COVERS_BASE_URL=https://covers.openlibrary.org` déjà présent.)

**Tests (unit, HTTP mocké séquentiel)**
`MockHttpClient` avec 2 réponses successives (`berserk-search.json` puis `berserk-editions.json`). Asserter : éditions EN ET FR mappées ; une entrée `physical_format:"Hardcover"` → `format=Relie` ; `coverUrl` construite depuis l'ID ; `language='en'`→`country='US'` ; en-tête `User-Agent` bien envoyé (vérifier via `MockResponse` callback) ; work absent (`docs:[]`) → `[]`.

**Verify**
- `docker compose exec back vendor/bin/phpunit --filter OpenLibraryEditionProvider` → vert.
- `docker compose exec back php bin/console debug:container OpenLibraryEditionProvider` → service résolu (env vars lues).

---

#### Task 4 : `GoogleBooksEditionProvider` (enrichissement ISBN/couvertures/éditions FR)

Provider d'enrichissement réutilisant la clé Google Books déjà configurée — comble les ISBN/couvertures que BnF/Open Library laissent vides.

**Skills and docs to load:**
- `/project-quality-setup` — conventions Infra
- `.claude/backend.md` — R4

**Files:**
- Create `back/src/Manga/Infrastructure/ExternalApi/GoogleBooksEditionProvider.php`
- Modify `back/config/services.yaml` (tag priorité 50, `$apiKey: '%env(GOOGLE_BOOKS_API_KEY)%'`)
- Test `back/tests/Unit/Manga/Infrastructure/ExternalApi/GoogleBooksEditionProviderTest.php`
- Create `back/tests/Fixtures/GoogleBooks/berserk-volumes.json`

**Implementation**

Constructeur `HttpClientInterface $httpClient, string $apiKey, LoggerInterface $logger`, base `https://www.googleapis.com/books/v1` (constante, comme `GoogleBooksMangaApiClient`). **No-op si `apiKey===''`** (`return []`). Requête :
```
GET /volumes?q=intitle:{title}(+inauthor:{author})&langRestrict={language|fr}&maxResults=40&key={apiKey}
```
Pour chaque `items[].volumeInfo` : `publisher`, `language`, ISBN = `industryIdentifiers[type=ISBN_13]`, `coverUrl`=`imageLinks.thumbnail`, `format`=`fromRawLabel(printType/subtitle)`, `source='google_books'`, `externalId`=`items[].id`. Tolérant (429/exception → log + `[]`). Priorité 50 = passe après BnF/OpenLibrary, donc dans `EditionGrouper` ses entrées **complètent** (cover/isbn non-null) sans écraser une ligne déjà créée par une source autoritative.

**Tests (unit, HTTP mocké)** : fixture → DTO avec ISBN-13 + cover ; `apiKey=''` → `[]` sans requête (asserter 0 appel via `MockHttpClient`).

**Verify** : `docker compose exec back vendor/bin/phpunit --filter GoogleBooksEditionProvider` → vert ; `make phpstan` → 0 erreur.

---

#### Task 5 : `DiscoverEditions` query/handler + endpoints + env/câblage/doc + tests fonctionnels

Exposer la découverte d'éditions en HTTP (recherche libre **et** « autres éditions de cette œuvre » depuis une fiche), orchestration pure (R3).

**Skills and docs to load:**
- `/project-quality-setup` — CQRS, controller mince, `#[MapRequestPayload]` si body
- `.claude/backend.md` — R3 (handler orchestrateur), R6 (pas d'import cross-module hors Shared)
- `.claude/CLAUDE.md` — **tests fonctionnels obligatoires (succès + tous les codes d'erreur)**, `NullEditionProvider`/`when@test`
- `docs/railway-env-sync.md` — ajout des nouvelles clés à `IGNORE_KEYS`

**Files:**
- Create `back/src/Manga/Application/DiscoverEditions/DiscoverEditionsQuery.php`
- Create `back/src/Manga/Application/DiscoverEditions/DiscoverEditionsHandler.php`
- Modify `back/src/Manga/Infrastructure/Http/MangaController.php` (2 routes, **avant** `/{id}`)
- Modify `back/src/Manga/Domain/MangaRepositoryInterface.php` *(si besoin)* — déjà `findById()` ; rien à ajouter
- Modify `scripts/railway-sync-env-keys.sh` (ou son `IGNORE_KEYS`) — `OPEN_LIBRARY_BASE_URL`, `OPENLIBRARY_USER_AGENT`, `EBAY_BASE_URL`, `EBAY_OAUTH_URL` (les 3 `EBAY_*` secrets restent gérés par sentinelle)
- Create `docs/editions-prices.md` (doc d'architecture des 2 features + tiers + env)
- Test `back/tests/Functional/Manga/DiscoverEditionsTest.php`

**Implementation**

`DiscoverEditionsQuery(public string $query, public ?string $author = null, public ?string $language = null)`.

`DiscoverEditionsHandler` (`#[AsMessageHandler(bus: 'query.bus')]`) injecte `EditionProviderInterface $provider` + `EditionGrouper $grouper` :
```php
public function __invoke(DiscoverEditionsQuery $query): array {
    $editions = $this->provider->findEditions($query->query, $query->author, $query->language);
    return array_map(
        static fn (ExternalEditionDto $edition) => $edition->toArray(),
        $this->grouper->group($editions),
    );
}
```
(Pas de pagination : une œuvre a au plus quelques dizaines de lignes d'édition → liste simple, donc **pas** `AbstractPaginatedQuery` ici, R11 ne s'applique pas.)

`MangaController`, dans le bloc des routes spécifiques **avant** `#[Route('/{id}')]` :
```php
#[Route('/editions', methods: ['GET'])]
public function editions(Request $request): JsonResponse {
    return new JsonResponse($this->queryBus->ask(new DiscoverEditionsQuery(
        query:    $request->query->get('q', ''),
        author:   $request->query->get('author'),
        language: $request->query->get('language'),
    )));
}

#[Route('/{id}/editions', methods: ['GET'])]
public function mangaEditions(string $id): JsonResponse {
    $manga = $this->queryBus->ask(new GetMangaQuery($id)); // 404 via NotFoundException si absent
    return new JsonResponse($this->queryBus->ask(new DiscoverEditionsQuery(
        query:    $manga['title'],
        author:   $manga['author'] ?? null,
        language: null, // toutes langues pour « autres éditions »
    )));
}
```
Doc `docs/editions-prices.md` : pipeline, providers, tiers prix, env vars, comment activer Tier 2b/3, comment ajouter un provider (tag).

**Tests (functional, kernel + PostgreSQL réels, `NullEditionProvider` via `when@test`)**
- `GET /api/manga/editions?q=berserk` sans JWT → **401**.
- avec JWT → **200**, body = `[]` (Null provider).
- `GET /api/manga/{idInexistant}/editions` + JWT → **404**.
- `GET /api/manga/{idExistant}/editions` + JWT → **200**, `[]`.
- *(option)* alias de test pointant vers un `StubEditionProvider` renvoyant 1 DTO → asserter la forme `toArray()` et le dédoublonnage.

**Verify**
- `make test-php` → vert (nouveaux tests fonctionnels inclus).
- `make php-qa` → style + stan + deptrac + tests verts.
- `docker compose exec back php bin/console debug:router | grep editions` → 2 routes listées.

---

#### Task 6 : Couche API front + composable `useEditions`

Brancher le front sur `/api/manga/editions` via un composable réutilisable (entrées « maybe-reactive »).

**Skills and docs to load:**
- `/vue-best-practices` — `<script setup>` + TS, Composition API
- `/create-adaptable-composable` — entrées `MaybeRefOrGetter`, normalisation `toValue()`
- `/vue-testing-best-practices` — Vitest + mock axios
- `/project-quality-setup` — conventions TS/Vue, gate `vue-qa`

**Files:**
- Modify `front/src/api/manga.ts` (+ `discoverEditions`, + types `ExternalEdition`, `EditionFormat`)
- Create `front/src/composables/useEditions.ts`
- Test `front/src/composables/__tests__/useEditions.spec.ts`

**Implementation**

`api/manga.ts` :
```ts
export type EditionFormat = 'broche' | 'relie' | 'coffret' | 'deluxe' | 'omnibus' | 'unknown'
export interface ExternalEdition {
  workTitle: string; editionLabel: string; publisher: string | null
  language: string; country: string | null; format: EditionFormat
  volumeCount: number | null; isbnSample: string | null
  coverUrl: string | null; source: string; externalId: string | null
}
export function discoverEditions(q: string, author?: string | null, language?: string | null): Promise<ExternalEdition[]>
export function mangaEditions(id: string): Promise<ExternalEdition[]>
```

`useEditions(title, author?, language?)` — calqué sur `useExternalSearch` mais en lecture seule : accepte `MaybeRefOrGetter<string>`, expose `editions`, `isLoading`, `error`, `search()`, `clear()`. Normalise les entrées avec `toValue()`. Groupe l'affichage par `country` (helper `groupByCountry`).

**Tests** : mock `discoverEditions` → `search('berserk')` peuple `editions`, gère l'erreur (axios reject → `error` non-null, `isLoading=false`).

**Verify** : `make test-vue` (ou `docker compose exec app npm run test`) → vert ; `make type-check` → 0 erreur.

---

#### Task 7 : `EditionCard` + `BaseCountryFlag` + mode « Éditions » du flux `/add` + i18n

Permettre, dans le flux d'ajout, de chercher une œuvre puis de voir/importer **toutes** ses éditions groupées par pays.

**Skills and docs to load:**
- `/vue-best-practices` — composants `<script setup>`, props typées
- `/vue-testing-best-practices` — `@vue/test-utils`
- `/project-quality-setup` — Atomic Design (atom/molecule/organism)

**Files:**
- Create `front/src/components/atoms/BaseCountryFlag.vue` (props `country: string`, emoji/SVG drapeau)
- Create `front/src/components/organisms/EditionCard.vue` (props `edition: ExternalEdition`, emit `import`)
- Modify `front/src/pages/AddMangaPage.vue` (nouveau mode « Par édition » : `useEditions` → grille `EditionCard` → préremplit le formulaire d'import existant avec `editionLabel`/`publisher`/`language`/`volumeCount`/`coverUrl`)
- Modify `front/src/i18n/fr.json`, `front/src/i18n/en.json` (namespace `editions`)
- Test `front/src/components/organisms/__tests__/EditionCard.spec.ts`

**Implementation**

`EditionCard` : cover (via `coverUrl()` util existant + `BaseLazyImage`), `BaseCountryFlag`, `editionLabel`, `publisher`, badge `format` (i18n `editions.format.*`), `volumeCount` (« {n} tomes »), bouton `editions.import`. Émet `import` avec l'`ExternalEdition`.

`AddMangaPage` : ajouter un onglet/segment « Par titre » (existant) / « Par édition » (neuf). En mode édition : champ de recherche → `useEditions` → sections par pays (titre = `BaseCountryFlag` + nom pays) → `EditionCard`. Au clic `import`, préremplir le `form` d'import existant (mapping : `editionLabel`→`edition`, `publisher`→`author`? non → laisser `author` ; `language`, `coverUrl`, `volumeCount`→`totalVolumes`) puis réutiliser la mutation `importManga` déjà en place.

i18n `editions` : `title`, `byEdition`, `byTitle`, `searchPlaceholder`, `import`, `volumes` (« {count} tomes »), `noResults`, `format.broche|relie|coffret|deluxe|omnibus|unknown`, `country.FR|US|JP|other`.

**Tests** : monter `EditionCard` avec une `edition` deluxe US → drapeau US rendu, libellé format « Deluxe », clic bouton → event `import` émis avec la payload.

**Verify** : `make vue-qa` (lint + type-check + test) → vert ; vérifier visuellement le mode « Par édition » dans `/add` (cf. skill `/run` hors plan).

---

#### Task 8 : Onglets `MangaDetailPage` + onglet « Éditions »

Introduire un système d'onglets DaisyUI sur la fiche et y placer « Tomes » (existant) + « Éditions » (autres éditions de l'œuvre, live).

**Skills and docs to load:**
- `/vue-best-practices` — refactor de page volumineuse, `<script setup>`
- `/vue-router-best-practices` — synchroniser l'onglet actif avec un query-param `?tab=`
- `/vue-testing-best-practices`
- `/project-quality-setup`

**Files:**
- Modify `front/src/pages/MangaDetailPage.vue` (scaffold onglets `tabs tabs-bordered`, état `activeTab` ↔ `route.query.tab` ; contenu actuel = onglet « Tomes » ; nouvel onglet « Éditions » via `useEditions(entry.manga.title, entry.manga.author)`, grille `EditionCard`, badge « déjà dans ta collection » si une fiche du même `editionLabel` existe)
- Modify `front/src/i18n/fr.json`, `en.json` (`manga.tabs.volumes|editions|prices`)
- Test `front/src/pages/__tests__/MangaDetailPage.tabs.spec.ts` *(ciblé onglets — éviter de monter toute la page si trop lourd : extraire un sous-composant `MangaTabs.vue` testable si nécessaire)*

**Implementation**

Pour limiter le risque sur un fichier ~1400 LOC : envelopper le contenu volumes existant dans `<template v-if="activeTab==='volumes'">` sans le modifier, ajouter `<template v-else-if="activeTab==='editions'">`. `activeTab` initialisé depuis `route.query.tab` (défaut `volumes`), `watch` → `router.replace({ query: { ...route.query, tab } })`. L'onglet « Prix » (Task 14) réserve sa valeur `'prices'` dès maintenant (placeholder caché tant que Phase D non livrée, ou onglet ajouté seulement en Task 14 — préférer **ajouter `prices` en Task 14** pour garder les PRs étanches).

**Tests** : si extraction `MangaTabs.vue`, tester le changement d'onglet + sync query-param. Sinon, test composable déjà couvert (Task 6) ; documenter le choix « no heavy page test ».

**Verify** : `make vue-qa` → vert ; navigation `/collection/:id?tab=editions` affiche les éditions.

---

#### Task 9 : Ports prix, DTO, enums, `Marketplace`, composite, cache iface, Null + câblage

Socle multi-providers des prix + abstraction de cache, calqués sur le composite couvertures.

**Skills and docs to load:**
- `/project-quality-setup` — DDD/CQRS, `final readonly`, enums
- `.claude/backend.md` — R3 (logique en domain service), R4 (port/adaptateur)
- `.claude/CLAUDE.md` — unit tests obligatoires (DTO/enum/VO)

**Files:**
- Create `back/src/Manga/Domain/VolumePriceProviderInterface.php`
- Create `back/src/Manga/Domain/PriceOfferDto.php`
- Create `back/src/Manga/Domain/PriceKindEnum.php`
- Create `back/src/Manga/Domain/Marketplace.php`
- Create `back/src/Manga/Domain/PriceOfferCacheInterface.php`
- Create `back/src/Manga/Domain/Service/PriceOfferSorter.php`
- Create `back/src/Manga/Infrastructure/ExternalApi/CompositePriceProvider.php`
- Create `back/src/Manga/Infrastructure/ExternalApi/NullPriceProvider.php`
- Modify `back/config/services.yaml` (alias + tag `app.volume_price_provider`, `when@test`)
- Test `back/tests/Unit/Manga/Domain/PriceOfferDtoTest.php`, `…/MarketplaceTest.php`, `…/PriceKindEnumTest.php`
- Test `back/tests/Unit/Manga/Domain/Service/PriceOfferSorterTest.php`
- Test `back/tests/Unit/Manga/Infrastructure/ExternalApi/CompositePriceProviderTest.php`

**Implementation**

```php
interface VolumePriceProviderInterface {
    /** @return list<PriceOfferDto> */
    public function findOffers(Isbn $isbn, Marketplace $marketplace): array;
}
```
`PriceOfferDto` (`final readonly`, `toArray()`) : `PriceKindEnum $kind, string $merchant, string $merchantLogo /* clé logo front */, float $amount, string $currency, ?string $url, ?string $imageUrl, string $source`.
`PriceKindEnum: string` : `MerchantLive='merchant_live'`, `PublisherReference='publisher_reference'`.
`Marketplace: string` : `Fr='EBAY_FR'`, `Us='EBAY_US'` ; méthodes `ebayId(): string` (= `->value`), `currencyCode(): string` (FR→`EUR`, US→`USD`), `static fromLanguage(?string $lang): self` (`en`→Us, défaut→Fr), `static fromValue(?string $raw): self` (parse query-param, défaut Fr).
`PriceOfferCacheInterface` : `get(Isbn $isbn, Marketplace $m): ?array` (offres ou null si absent/expiré), `put(Isbn $isbn, Marketplace $m, array $offers): void`.
`CompositePriceProvider` : `iterable $providers` tagged, **concatène seulement** les offres (try/catch + log par provider, comme le composite couvertures) — **aucun tri** (parité avec `CompositeMangaCoverApiClient`).
`PriceOfferSorter` (domain service pur, R3) : `sort(array $offers): array` → `MerchantLive` avant `PublisherReference`, puis `amount` croissant. C'est le **seul** point de tri, appelé par le handler (Task 12) — jamais dans le composite (pas de double tri).
`NullPriceProvider` : `[]`.

`services.yaml` : alias interface → `CompositePriceProvider` (`$providers: !tagged_iterator { tag: app.volume_price_provider }`) ; `when@test` → `NullPriceProvider` + `StubPriceProvider` public (Task 12).

**Tests** : `Marketplace::fromLanguage('en')===Us`, `currencyCode()` ; `PriceOfferDto::toArray()` (kind en `->value`) ; `PriceOfferSorterTest` : offres mélangées → MerchantLive d'abord puis `amount` croissant ; `CompositePriceProvider` avec 2 stubs (offres + exception) → concat (ordre des providers préservé, **non trié**) + pas de propagation.

**Verify** : `docker compose exec back vendor/bin/phpunit --filter 'Price|Marketplace'` → vert ; `make phpstan && make deptrac` → 0 erreur.

---

#### Task 10 : `EbayBrowsePriceProvider` + cache du token OAuth (Tier 1)

Provider principal : token OAuth client-credentials (mis en cache) puis recherche par `gtin={isbn13}` sur le bon marché, avec lien affilié EPN.

**Skills and docs to load:**
- `/project-quality-setup` — adaptateur Infra, secrets via env
- `.claude/backend.md` — R4
- `.claude/CLAUDE.md` — FQCN interdits ; ajout env `EBAY_*`

**Files:**
- Create `back/src/Manga/Infrastructure/ExternalApi/Ebay/EbayOAuthTokenProvider.php`
- Create `back/src/Manga/Infrastructure/ExternalApi/EbayBrowsePriceProvider.php`
- Modify `back/config/services.yaml` (tag `app.volume_price_provider` priorité 100 ; args env + `CacheItemPoolInterface`)
- Modify `back/.env` (+ `EBAY_BASE_URL`, `EBAY_OAUTH_URL`, `EBAY_CLIENT_ID`, `EBAY_CLIENT_SECRET`, `EBAY_CAMPAIGN_ID`)
- Test `back/tests/Unit/Manga/Infrastructure/ExternalApi/EbayBrowsePriceProviderTest.php`
- Create `back/tests/Fixtures/Ebay/oauth-token.json`, `back/tests/Fixtures/Ebay/browse-search.json`

**Implementation**

`EbayOAuthTokenProvider(HttpClientInterface, string $oauthUrl, string $clientId, string $clientSecret, CacheItemPoolInterface $cache, LoggerInterface)` : `getToken(): ?string` → cache clé `ebay.oauth.token` ; sur miss, `POST {oauthUrl}` `grant_type=client_credentials&scope=https://api.ebay.com/oauth/api_scope`, en-tête `Authorization: Basic base64(clientId:clientSecret)` ; stocke `access_token` avec `expiresAfter(expires_in - 60)`. **`null` si `clientId===''`** (no-op).

`EbayBrowsePriceProvider(HttpClientInterface, EbayOAuthTokenProvider $tokenProvider, string $baseUrl, string $campaignId, LoggerInterface)` :
```
GET {baseUrl}/buy/browse/v1/item_summary/search?gtin={isbn13}&limit=3
Headers: Authorization: Bearer {token}
         X-EBAY-C-MARKETPLACE-ID: {marketplace->ebayId()}
         X-EBAY-C-ENDUSERCTX: affiliateCampaignId={campaignId}   (si campaignId !== '')
```
Map `itemSummaries[]` → `PriceOfferDto(kind: MerchantLive, merchant:'eBay', merchantLogo:'ebay', amount:(float)price.value, currency:price.currency, url: itemAffiliateWebUrl ?? itemWebUrl, imageUrl: image.imageUrl, source:'ebay')`. **No-op si token null** (`return []`). Tolérant (401/429/exception → log + `[]`).

`.env` defaults : `EBAY_BASE_URL=https://api.ebay.com`, `EBAY_OAUTH_URL=https://api.ebay.com/identity/v1/oauth2/token`, `EBAY_CLIENT_ID=`, `EBAY_CLIENT_SECRET=`, `EBAY_CAMPAIGN_ID=`.

**Tests (unit, HTTP mocké séquentiel + cache array)** : `MockHttpClient` (token puis search) + `ArrayAdapter` ; asserter 1 `PriceOfferDto` (`amount` float, `currency`, `url`=affiliate, `kind=MerchantLive`) ; `clientId=''` → `[]` sans aucun appel ; 2ᵉ appel rapproché → token **non** re-demandé (cache hit, asserter 1 seul POST OAuth).

**Verify** : `docker compose exec back vendor/bin/phpunit --filter EbayBrowsePriceProvider` → vert ; `make phpstan` → 0 erreur.

---

#### Task 11 : `GoogleBooksPriceProvider` (référence) + `MangaNewsReferencePriceProvider` (opt., désactivé)

Prix de référence « indicatif » légal (Google Books `saleInfo`) + squelette scraping prix-éditeur désactivé par défaut.

**Skills and docs to load:**
- `/project-quality-setup` — Infra
- `.claude/backend.md` — R4
- `docs/editions-prices.md` (créé Task 5) — documenter l'activation du Tier 2b

**Files:**
- Create `back/src/Manga/Infrastructure/ExternalApi/GoogleBooksPriceProvider.php`
- Create `back/src/Manga/Infrastructure/ExternalApi/MangaNewsReferencePriceProvider.php`
- Modify `back/config/services.yaml` (GoogleBooksPrice tag priorité 60 ; MangaNews tag priorité 10 **commenté/désactivé** + note)
- Test `…/GoogleBooksPriceProviderTest.php`, `…/MangaNewsReferencePriceProviderTest.php`
- Create `back/tests/Fixtures/GoogleBooks/saleinfo-fr.json`

**Implementation**

`GoogleBooksPriceProvider(HttpClientInterface, string $apiKey, LoggerInterface)` : no-op si `apiKey===''`. `GET /books/v1/volumes?q=isbn:{isbn}&country={FR|US selon marketplace}&key={apiKey}` → si `items[0].saleInfo.saleability==='FOR_SALE'` et `retailPrice`/`listPrice` présents → 1 `PriceOfferDto(kind: PublisherReference, merchant:'Google Play', merchantLogo:'google_play', amount, currency, url: saleInfo.buyLink, imageUrl:null, source:'google_books')`. Sinon `[]`. **`&country=` obligatoire** (sinon 403 / pas de saleInfo depuis le cloud).

`MangaNewsReferencePriceProvider(HttpClientInterface, string $userAgent, bool $enabled, LoggerInterface)` : **`if (!$enabled) return [];`** en première ligne (no-op par défaut). Documenter (dans le code + `docs/editions-prices.md`) : nécessite UA navigateur, cache agressif, respect robots.txt/CGU, attribution ; renvoie `PriceKindEnum::PublisherReference`, `url`=page manga-news (pas d'affiliation). Implémentation de parsing laissée minimale/`[]` tant que `$enabled=false` (activable ultérieurement).

`services.yaml` : `GoogleBooksPriceProvider` taggé priorité 60 ; `MangaNewsReferencePriceProvider` avec `$enabled: false` et tag `app.volume_price_provider` **commenté** (activation = décommenter + passer `$enabled` true). Note explicite dans le YAML.

**Tests** : `GoogleBooksPriceProvider` fixture FOR_SALE → 1 offre reference EUR avec `buyLink` ; `NOT_FOR_SALE` → `[]` ; `apiKey=''` → `[]`. `MangaNewsReferencePriceProvider` `$enabled=false` → `[]` sans requête.

**Verify** : `docker compose exec back vendor/bin/phpunit --filter 'GoogleBooksPriceProvider|MangaNews'` → vert.

---

#### Task 12 : `GetVolumePrices` query/handler + adaptateur cache + endpoint + tests fonctionnels

Exposer les offres d'un tome en HTTP, avec cache 24h et résolution du marché par langue.

**Skills and docs to load:**
- `/project-quality-setup` — CQRS, controller mince
- `.claude/backend.md` — R3 (orchestration), R4
- `.claude/CLAUDE.md` — **tests fonctionnels (succès + 404 + ISBN absent)**, `NullPriceProvider`/`StubPriceProvider` en `when@test`
- `docs/editions-prices.md`

**Files:**
- Create `back/src/Manga/Application/GetVolumePrices/GetVolumePricesQuery.php`
- Create `back/src/Manga/Application/GetVolumePrices/GetVolumePricesHandler.php`
- Create `back/src/Manga/Infrastructure/Cache/PsrPriceOfferCache.php` (implémente `PriceOfferCacheInterface`)
- Modify `back/src/Manga/Infrastructure/Http/MangaController.php` (route prix)
- Modify `back/config/services.yaml` (alias cache + `$pool: '@cache.app'` + TTL ; `StubPriceProvider` en `when@test`)
- Create `back/tests/Doubles/Manga/StubPriceProvider.php`
- Test `back/tests/Functional/Manga/GetVolumePricesTest.php`

**Implementation**

`GetVolumePricesQuery(public string $mangaId, public string $volumeId, public ?string $marketplace = null)`.

`GetVolumePricesHandler` (`query.bus`) injecte `MangaRepositoryInterface`, `VolumePriceProviderInterface`, `PriceOfferCacheInterface`, `PriceOfferSorter` :
```php
$manga = $repo->findById($mangaId) ?? throw new NotFoundException('Manga', $mangaId);
$volume = /* first volume id===volumeId */ ?? throw new NotFoundException('Volume', $volumeId);
if ($volume->isbn === null) { return ['offers' => [], 'hasIsbn' => false]; }
$market = Marketplace::fromValue($query->marketplace) /* ou fromLanguage($manga->language) si null */;
$cached = $cache->get($volume->isbn, $market);
$offers = $cached ?? $sorter->sort($provider->findOffers($volume->isbn, $market));
if ($cached === null) { $cache->put($volume->isbn, $market, $offers); }
return ['offers' => $offers, 'hasIsbn' => true, 'marketplace' => $market->value];
```
(La résolution marché par défaut = `Marketplace::fromLanguage($manga->language)` ; override par query-param via `fromValue`.)

`PsrPriceOfferCache(CacheItemPoolInterface $pool, int $ttlSeconds=86400)` : clé `sprintf('price_offers.%s.%s', $isbn->value, $marketplace->value)` ; `get` renvoie le tableau d'offres (déjà `toArray()`) si hit, sinon null ; `put` `expiresAfter($ttl)`.

`MangaController` :
```php
#[Route('/{id}/volumes/{volumeId}/prices', methods: ['GET'])]
public function volumePrices(string $id, string $volumeId, Request $request): JsonResponse {
    return new JsonResponse($this->queryBus->ask(new GetVolumePricesQuery(
        mangaId: $id, volumeId: $volumeId, marketplace: $request->query->get('marketplace'),
    )));
}
```
`StubPriceProvider` (test double) renvoie 1 `PriceOfferDto` MerchantLive pour valider la forme.

**Tests (functional)**
- sans JWT → **401**.
- manga inconnu → **404** ; volume inconnu → **404**.
- volume **sans ISBN** + JWT → **200** `{offers:[], hasIsbn:false}`.
- volume **avec ISBN** (alias `when@test` → `StubPriceProvider`) → **200**, `offers[0]` a `merchant`,`amount`,`currency`,`url`,`kind:'merchant_live'` ; `marketplace` déduit de la langue.
- 2ᵉ appel → même résultat (cache ; pas d'assert d'appels en fonctionnel, couvert en unit Task 10).

**Verify** : `make test-php` → vert ; `make php-qa` → tout vert ; `docker compose exec back php bin/console debug:router | grep prices` → route listée.

---

#### Task 13 : Couche API front + composable `useVolumePrices`

**Skills and docs to load:**
- `/vue-best-practices`, `/create-adaptable-composable`, `/vue-testing-best-practices`, `/project-quality-setup`

**Files:**
- Modify `front/src/api/manga.ts` (+ `getVolumePrices`, + types `PriceOffer`, `PriceKind`, `VolumePricesResponse`)
- Create `front/src/composables/useVolumePrices.ts`
- Test `front/src/composables/__tests__/useVolumePrices.spec.ts`

**Implementation**

```ts
export type PriceKind = 'merchant_live' | 'publisher_reference'
export interface PriceOffer { kind: PriceKind; merchant: string; merchantLogo: string
  amount: number; currency: string; url: string | null; imageUrl: string | null; source: string }
export interface VolumePricesResponse { offers: PriceOffer[]; hasIsbn: boolean; marketplace?: string }
export function getVolumePrices(mangaId: string, volumeId: string, marketplace?: string): Promise<VolumePricesResponse>
```
`useVolumePrices(mangaId, volumeId, marketplace?)` : entrées `MaybeRefOrGetter`, **lazy** (`enabled`/`fetch()` explicite pour ne pas brûler le quota au montage), expose `offers`, `hasIsbn`, `isLoading`, `error`, `fetch()`. Helper `formatPrice(amount, currency)` (Intl.NumberFormat).

**Tests** : mock `getVolumePrices` → `fetch()` peuple `offers`/`hasIsbn` ; erreur gérée ; `formatPrice(7.5,'EUR')` → « 7,50 € ».

**Verify** : `make test-vue` → vert ; `make type-check` → 0 erreur.

---

#### Task 14 : `BaseMerchantLogo` + `PriceOfferCard` + onglet « Prix » + section prix `EnrichVolumeModal` + i18n

Afficher les offres (logo marchand + prix + lien de redirection) dans un onglet « Prix » de la fiche **et** dans l'éditeur de tome existant.

**Skills and docs to load:**
- `/vue-best-practices`, `/vue-testing-best-practices`, `/project-quality-setup` (Atomic Design)

**Files:**
- Create `front/src/components/atoms/BaseMerchantLogo.vue` (props `merchant: string` — SVG par clé : `ebay`, `google_play`, `fnac`, `manga_news`, fallback générique) — **calqué sur `BaseCoverProviderLogo.vue`**
- Create `front/src/components/molecules/PriceOfferCard.vue` (props `offer: PriceOffer`)
- Modify `front/src/pages/MangaDetailPage.vue` (onglet `prices` : sélecteur de tome ou liste par tome → `useVolumePrices` lazy → `PriceOfferCard` ; message si `!hasIsbn`)
- Modify `front/src/components/organisms/EnrichVolumeModal.vue` (section « Prix & où acheter » : `useVolumePrices(mangaId, volume.id)` au clic d'un bouton « Chercher les prix »)
- Modify `front/src/i18n/fr.json`, `en.json` (namespace `prices`)
- Test `front/src/components/molecules/__tests__/PriceOfferCard.spec.ts`, `front/src/components/atoms/__tests__/BaseMerchantLogo.spec.ts`

**Implementation**

`PriceOfferCard` : `BaseMerchantLogo`, libellé marchand, prix via `formatPrice` (préfixe « à partir de » si `kind==='merchant_live'`, badge « Prix éditeur » + « indicatif » si `publisher_reference`), bouton/lien `prices.viewOffer` `<a :href="offer.url" target="_blank" rel="noopener noreferrer">` (masqué si `url===null`). `offer.imageUrl` en vignette optionnelle.

`MangaDetailPage` onglet `prices` : ajoute la valeur `'prices'` au système d'onglets (Task 8) ; pour chaque tome possédant un ISBN, bloc dépliable → `useVolumePrices` lazy au déploiement → cartes. Si aucun tome n'a d'ISBN → encart « Ajoute des ISBN (ou synchronise-les) pour voir les prix » (renvoie vers Phase E).

i18n `prices` : `title`, `viewOffer`, `from` (« à partir de »), `publisherPrice`, `indicative`, `noOffers`, `noIsbn`, `searchPrices`, `marketplace`.

**Tests** : `PriceOfferCard` merchant_live → « à partir de 7,50 € » + lien `href` correct + `target=_blank` ; publisher_reference → badge « Prix éditeur », pas de « à partir de » ; `url=null` → pas de lien. `BaseMerchantLogo` `merchant='ebay'` → SVG eBay ; clé inconnue → fallback.

**Verify** : `make vue-qa` → vert ; onglet « Prix » affiche une offre stub en dev.

---

#### Task 15 : Batch « synchroniser les ISBN » (éditions → `Volume.isbn`) → couvertures + prix

Remplir les `Volume.isbn` manquants depuis les providers d'éditions, ce qui débloque **à la fois** le batch de couvertures existant et l'onglet Prix. Réutilise le pattern async + Mercure de `AutoCovers`.

**Skills and docs to load:**
- `/project-quality-setup` — CQRS, Messenger
- `.claude/backend.md` — R1/R5 (events + listeners), R3
- `.claude/CLAUDE.md` — tests obligatoires
- `docs/mercure.md` — réutilisation du canal de progression (si progression temps réel souhaitée ; sinon batch synchrone simple)

**Files:**
- Create `back/src/Manga/Application/SyncIsbns/SyncIsbnsCommand.php`
- Create `back/src/Manga/Application/SyncIsbns/SyncIsbnsHandler.php`
- Create `back/src/Manga/Domain/Service/VolumeIsbnMatcher.php` (domain service : associe une `ExternalEditionDto`/notice à un `number` de tome)
- Modify `back/src/Manga/Infrastructure/Http/MangaController.php` (route `POST /{id}/sync-isbns`)
- Modify `back/src/Manga/Infrastructure/Http/` (+ `SyncIsbnsRequest` si body)
- Test `back/tests/Unit/Manga/Domain/Service/VolumeIsbnMatcherTest.php`
- Test `back/tests/Functional/Manga/SyncIsbnsTest.php`

**Implementation**

`POST /api/manga/{id}/sync-isbns` → `SyncIsbnsCommand(mangaId)`. `SyncIsbnsHandler` (`command.bus`) : charge le manga, interroge `EditionProviderInterface->findEditions(title, author, language)` (réutilise Phase A) en mode « par volume » — pour BnF/OpenLibrary, récupérer les notices par tome (numéro dans le titre) → `VolumeIsbnMatcher::match($notices, $volumeNumber): ?Isbn` (domain service pur, testable) → écrit `Volume.isbn` quand vide, **sans** écraser un ISBN existant. Publie `SyncIsbnsSucceededEvent` (les 3 listeners ActivityLog génériques le tracent — R7). Optionnel : déclencher ensuite `StartCoverBatchCommand` existant pour rafraîchir les couvertures des tomes nouvellement ISBN-és (synergie couvertures). La portée « par tome » peut, en v1, se limiter à OpenLibrary `editions.json` (1 entrée ≈ 1 ISBN) + BnF (1 notice/tome) ; Google Books en complément.

`VolumeIsbnMatcher` : logique pure d'extraction du numéro de tome depuis un titre de notice (« Berserk, tome 3 » / « Berserk Deluxe Volume 3 ») et d'association au `Volume.number`.

**Tests**
- Unit `VolumeIsbnMatcherTest` : titres variés (« tome 3 », « vol. 3 », « Volume 3 », « T03 ») → 3 ; pas de numéro → null ; choisit l'ISBN-13.
- Functional `SyncIsbnsTest` : `POST …/sync-isbns` sans JWT → 401 ; manga inconnu → 404 ; manga connu (Null provider en test → aucun ISBN écrit) → 200/202 et réponse cohérente (0 mis à jour).

**Verify** : `make php-qa` → vert ; `docker compose exec back php bin/console debug:router | grep sync-isbns` → route listée.

---

**Task 16: Final lint, test, and review loop.**
Once finished, run lint and test for the affected sub-directories, dump the git diff in a tmp file with `git diff HEAD > /tmp/last-review.diff`, then spawn a `file-reviewer` subagent with the prompt: *"Review the diff at /tmp/last-review.diff. Invoke the `code-review` skill, follow its 'Load thematic rules by file type' step using the diff's file paths, and write structured findings."*
Fix every finding, then redo all three steps. Repeat until lint passes, tests pass, and the subagent returns no findings (max 5 iterations).
