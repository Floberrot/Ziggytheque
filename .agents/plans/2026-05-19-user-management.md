# Plan — Gestion d'utilisateurs (multi-tenant + admin panel)

> Branche : `claude/add-user-management-blzhh`
> Date : 2026-05-19

> [!IMPORTANT]
> **Livraison en deux PRs séquentielles** — les migrations Doctrine s'exécutent automatiquement au deploy Railway (`doctrine:migrations:migrate` joué par le worker au boot). Verrouiller `owner_id` en NOT NULL dès la première PR planterait le deploy, parce que la commande `app:bootstrap-admin` doit tourner manuellement **entre** les deux migrations pour backfill l'admin.
>
> | PR | Scope | Schéma | Action manuelle requise après deploy |
> |---|---|---|---|
> | **PR A — celle-ci (#97)** | Tout le user management (login, register, admin panel, multi-tenancy, emails, etc.) | Migration v1 — `owner_id` **nullable** | Oui : `railway run --service back php bin/console app:bootstrap-admin <email> <password>` |
> | **PR B — follow-up** | Verrouillage du schéma + tightening types PHP | Migration v2 — `owner_id` **NOT NULL** | Aucune |
>
> PR A est entièrement fonctionnelle en prod après son deploy + la commande. PR B n'apporte pas de feature — uniquement la garantie d'intégrité côté DB.

---

### TL;DR

> [!NOTE]
> L'application passe d'un système mono-utilisateur (un seul `GATE_PASSWORD` partagé) à un vrai modèle multi-utilisateurs avec rôles. Tout le monde se connecte avec email + mot de passe via un formulaire d'inscription/connexion classique. Un nouvel utilisateur passe par deux portes successives avant de pouvoir utiliser l'app : vérification d'email (token envoyé automatiquement) puis approbation manuelle par l'admin. Le `GATE_PASSWORD` n'est PAS supprimé : il devient une seconde authentification que SEUL l'admin doit fournir pour ouvrir les zones sensibles (journal d'activité + écran de gestion des utilisateurs), et nulle part ailleurs.
>
> Toutes les données existantes (collections, listes de souhaits, notifications, journal, etc.) sont rattachées rétroactivement à un compte admin créé par une commande console à exécuter une fois après la migration. À partir de là, chaque utilisateur a ses propres collections, ses propres séries suivies, ses propres préférences. Le catalogue manga/volumes reste lui partagé entre tous (un seul Naruto pour tout le monde, mais chacun a sa propre lecture).
>
> Les notifications par utilisateur acceptent désormais deux canaux au choix : email (avec un template HTML stylé reprenant la direction artistique DaisyUI dark du site) ou webhook Discord. Côté admin, une nouvelle page paginée liste tous les utilisateurs, permet de modifier leur statut, leur rôle, leur supprimer le compte, et de générer un lien de réinitialisation de mot de passe (qui est aussi envoyé automatiquement par email à l'utilisateur, mais l'admin peut le copier-coller en plus). Aucun endpoint existant ne disparaît : ils sont étendus pour filtrer par utilisateur authentifié.

---

### Implementation

#### État actuel (résumé)

```
┌──────────────────────────────────────────────────────────────────────┐
│  Auth = 1 seul password partagé (GATE_PASSWORD)                      │
│  ↓                                                                   │
│  POST /api/auth/gate { password }  →  JWT (identifier=gate)          │
│                                                                      │
│  TOUTES les entités utilisateur ignorent l'identité :                │
│    CollectionEntry, VolumeEntry, Notification, Article, ActivityLog  │
│  → un seul "utilisateur logique" possède tout                        │
│                                                                      │
│  Manga + Volume = catalogue (déjà global, OK)                        │
└──────────────────────────────────────────────────────────────────────┘
```

#### État cible

```
┌──────────────────────────────────────────────────────────────────────┐
│  Auth = User entity (email + bcrypt) + JWT                           │
│                                                                      │
│  POST /api/auth/register  → user(status=pending_email_verification)  │
│         ↓ (email envoyé avec token)                                  │
│  POST /api/auth/verify-email  → status=pending_admin_approval        │
│         ↓ (admin valide)                                             │
│  POST /api/auth/login        → JWT (sub=userId, roles=[ROLE_USER])   │
│  POST /api/auth/request-reset → email avec token reset               │
│  POST /api/auth/reset-password{token,newPassword}                    │
│                                                                      │
│  Le GATE_PASSWORD survit en "second factor admin" :                  │
│  POST /api/auth/gate { password } → ré-émet un JWT avec scope        │
│       supplémentaire "admin_unlocked" (claim booléen)                │
│  → Requis seulement pour /api/admin/** et /api/activity-logs         │
│                                                                      │
│  Entités user-scoped : ajout d'une FK user_id NOT NULL               │
│    CollectionEntry, VolumeEntry (par cascade), Notification,         │
│    Article, ActivityLog                                              │
│  Catalogue : Manga, Volume (inchangé, partagé)                       │
│                                                                      │
│  Notifications : nouvelles colonnes per-user                         │
│    notificationChannel = email | discord                             │
│    notificationEmail = string|null                                   │
│    discordWebhookUrl = string|null                                   │
└──────────────────────────────────────────────────────────────────────┘
```

#### Flux d'authentification

```
                         ┌────────────────────┐
visiteur ──/register──▶ │ user pending_email │ ──email─▶ user clique lien
                         └────────────────────┘                 │
                                                                 ▼
                         ┌────────────────────┐
                         │ user pending_admin │ ◀── verify-email
                         └────────────────────┘
                                    │
                       admin approuve depuis /admin/users
                                    ▼
                         ┌────────────────────┐
                         │   user active      │ ──login──▶ JWT
                         └────────────────────┘

admin ──/login──▶ JWT user ──/gate──▶ JWT admin_unlocked
                                          │
                                          ▼
                            /admin/users + /journal
```

#### Bounded contexts touchés

| Module | Changement |
|---|---|
| `Auth/` | Refonte majeure : ajout `User` entity, `UserRepository`, `RegisterUser`/`LoginUser`/`VerifyEmail`/`RequestPasswordReset`/`ConsumePasswordReset`/`ApproveUser` (commands), `JwtAuthenticator` adapté. `GateUser` reste pour le second factor admin. |
| `Admin/` (nouveau) | Module dédié : `ListUsers`/`UpdateUser`/`DeleteUser`/`GenerateResetLink` (queries+commands), `AdminUserController`. |
| `Collection/`, `Wishlist/`, `Notification/`, `Stats/` | Filtrage par `userId` dans repositories + handlers. Schéma : ajout FK `user_id` nullable puis NOT NULL après backfill. |
| `Manga/` | Catalogue partagé entre tous les users (un seul Naruto en base, réutilisé par tout le monde). **Écriture restreinte à l'admin** : `POST /api/manga`, `PATCH /api/manga/:id`, `POST /api/manga/:id/volumes`, `PATCH /api/manga/:id/volumes/:volumeId`, `POST /api/manga/:id/auto-covers` exigent désormais `ROLE_ADMIN` (pas besoin de `adminUnlocked` pour ça — un admin connecté suffit). Lecture (GET, recherche externe, recherche volumes) reste ouverte à tous les users authentifiés. |
| `Notification/` | Préférences per-user : channel, email, webhook. Templates email Twig nouveaux. |
| `Shared/` | Ajout d'un `CurrentUserProvider` injecté partout pour récupérer l'ID utilisateur authentifié depuis le token. |

#### Choix techniques majeurs

1. **Module `Admin/` séparé** plutôt que de polluer `Auth/`. `Auth` reste responsable de l'identité ; `Admin` parle de la gestion des autres comptes.
2. **Migration en deux PRs séquentielles** (contrainte deploy Railway auto-migrate) :
   - **PR A** (celle-ci) : migration v1 ajoute les colonnes `owner_id` **nullable**, sans backfill auto. La commande console `app:bootstrap-admin email password` est exécutée **manuellement après le deploy** pour créer l'admin et backfill toutes les lignes orphelines. Les entités Doctrine côté PHP utilisent `?User $owner` (nullable) pour rester cohérentes avec le schéma — les repositories filtrent par `WHERE owner_id = :userId`, donc les lignes NULL (legacy avant backfill) sont invisibles tant que la commande n'a pas tourné.
   - **PR B** (follow-up, après merge + deploy + bootstrap-admin de PR A) : passe les entités à `User $owner` (non-nullable PHP) et embarque la migration v2 `ALTER COLUMN owner_id SET NOT NULL` + index. Doctrine schema validate est vert dans les deux PRs (parce que les types PHP et DB matchent à chaque étape).
   - Cela permet de ne PAS embarquer de logique métier dans Doctrine Migrations (R1) et de respecter le deploy auto-migrate sans risque.
3. **`CurrentUserProvider` côté `Shared`** : interface dans `Shared/Domain/`, adapter dans `Shared/Infrastructure/Security/` qui lit depuis `Security $security`. Les handlers reçoivent l'utilisateur via cette interface, jamais via `Security` directement.
4. **Second factor admin (`gate`)** : on garde l'endpoint existant mais il devient conditionnel à `ROLE_ADMIN`. Le JWT initial du login contient `roles=[ROLE_USER, ROLE_ADMIN]`. Après `/api/auth/gate`, un **nouveau** JWT est émis avec un claim `adminUnlocked: true`. Les firewalls Symfony exigent ce claim pour `/api/admin/**` et `/api/journal`. Côté front, ce JWT remplace l'ancien dans `sessionStorage`. Cela évite de gérer deux tokens.
5. **Tokens email** : table `auth_tokens` avec colonnes `id, user_id, type (email_verification | password_reset), token_hash, expires_at, consumed_at`. Tokens stockés hashés (SHA-256), comparés en constant-time. Expirent à 24h pour reset, 7j pour vérification email.
6. **Mailer DA** : layout Twig `back/templates/emails/layout.html.twig` reprenant les couleurs DaisyUI dark (#1d232a fond, #66cc8a primary, #ffffff texte). Templates filles : `email_verification.html.twig`, `password_reset.html.twig`, `account_approved.html.twig`.
7. **Notifications per-user** : on **garde** la variable d'env `NOTIFICATION_EMAIL` comme fallback pour les jobs scheduler côté admin global, mais chaque utilisateur a désormais son propre canal. Les listeners (`DiscordNewArticlesListener`, futur `EmailNewArticlesListener`) lisent les préférences via `User`.
8. **Pas de suppression d'endpoint** : tous les endpoints existants survivent. Ils ajoutent juste un filtre WHERE userId (entités user-scoped) ou un check de rôle (Manga write). Garantit zéro régression côté frontend pendant la transition.
9. **Scheduler reste global, fan-out per-user automatique** : le Symfony Scheduler tourne en un seul cron pour toute l'app (pas un cron par user). Le fan-out actuel itère déjà sur les `CollectionEntry` avec `notificationsEnabled=true`. Après ajout de `owner_id`, chaque job RSS/Jikan résout l'owner via `$entry->owner` et délègue au `NotificationDispatcher` qui choisit email/discord selon les préférences du user. Le summary de fin de cycle (`SendSchedulerDiscordSummaryHandler`) doit être refactoré pour grouper par owner et émettre **un summary par user** dans son canal préféré. Les CollectionEntries sans owner (cas legacy pré-`bootstrap-admin`) sont skippés silencieusement (`WHERE owner_id IS NOT NULL`).

10. **SMTP gratuit recommandé : Brevo** (ex-Sendinblue). 300 emails/jour gratuits, **pas de carte bancaire requise**, SMTP relay standard donc compatible direct avec Symfony Mailer via `MAILER_DSN=smtp://...`. Alternatives possibles documentées en annexe : Resend (100/j, dev-friendly mais demande domaine vérifié), MailerSend (3000/mois). Le choix est isolé dans une seule variable d'env — switchable sans toucher au code.

#### Code being removed

Aucune suppression nette. Tout est ajout ou refonte interne. Le `GateUser` minimal reste mais perd son rôle de "user principal" (devient un marqueur de second factor). Pas d'autre code obsolète identifié à ce stade.

#### Risques et points d'attention

- **Backfill obligatoire en prod** : tant que `app:bootstrap-admin` n'est pas exécuté, les colonnes `user_id` sont NULL et les WHERE clauses ne renverront rien. La commande DOIT être documentée dans `README.md` et exécutée avant la seconde migration NOT NULL.
- **Tests fonctionnels existants** : ils s'authentifient via `/api/auth/gate`. La signature change : il faut une nouvelle `AbstractApiTestCase::fetchAuthToken()` qui crée un user de test + login. Cela touche environ 30 tests fonctionnels. À faire en une seule passe.
- **Frontend `sessionStorage`** : refresh page = perte du token. Comportement actuel conservé (out of scope refresh token).
- **Suppression d'utilisateur** : en SET NULL ou en CASCADE ? Décision : CASCADE pour `CollectionEntry`, `Notification`, `Article`, `WishlistItem`. SET NULL pour `ActivityLog` (on garde l'historique pour audit).
- **Doctrine schema validate** : strict respect des règles dans `CLAUDE.md` (length enums, options['default'], FK names via `make migration`).

---

### Tasks

**PR A — celle-ci (#97). Tasks 1-3 et 5-24. Entités avec `?User $owner`. Entièrement fonctionnelle en prod après deploy + `app:bootstrap-admin` manuel.**

**PR B — follow-up. Task 4 uniquement. Verrouillage NOT NULL + tightening types PHP.**

- task 1 : Backend — créer l'entité `User`, ses enums, ses interfaces et le repository (sans toucher au schéma DB).
- task 2 : Backend — migration Doctrine v1 : table `users`, table `auth_tokens`, ajout `owner_id` **nullable** sur toutes les tables user-scoped. Entités restent `?User $owner`.
- task 3 : Backend — commande console `app:bootstrap-admin` qui crée l'admin et backfill `owner_id`.
- task 4 : **[PR B, follow-up]** Backend — migration Doctrine v2 : passage `owner_id` en NOT NULL + index/FK + tightening des types PHP `?User` → `User`.
- task 5 : Backend — refonte Security : `JwtAuthenticator` adapté, `UserProvider` DB, firewalls, claim `adminUnlocked`.
- task 6 : Backend — endpoints d'authentification publique : register, verify-email, login, request-reset, reset-password.
- task 7 : Backend — refonte `GateController` pour devenir le second factor admin (re-émet le JWT avec `adminUnlocked`).
- task 8 : Backend — module `Admin/` : CRUD users paginé + génération de lien reset.
- task 9 : Backend — `CurrentUserProvider` dans `Shared` + injection dans tous les handlers user-scoped.
- task 10 : Backend — refonte des repositories user-scoped pour filtrer par `userId` (Collection, Wishlist, Notification, Article, ActivityLog, Stats).
- task 11 : Backend — préférences notifications per-user : channel, email, webhookUrl + adaptation des listeners.
- task 12 : Backend — templates email Twig (layout DaisyUI dark + vérification + reset + approval).
- task 13 : Backend — tests unitaires nouveaux (User, tokens, handlers).
- task 14 : Backend — refonte de `AbstractApiTestCase` + adaptation des tests fonctionnels existants + nouveaux tests fonctionnels.
- task 15 : Frontend — refonte `useAuthStore`, ajout `useAdminGateStore`, api `auth.ts` + `users.ts`.
- task 16 : Frontend — pages publiques : `LoginPage`, `RegisterPage`, `VerifyEmailPage`, `RequestResetPage`, `ResetPasswordPage`, `AccountPendingPage`.
- task 17 : Frontend — page admin : `AdminUsersPage` (DataTable paginé, modal édition, génération lien reset).
- task 18 : Frontend — page préférences notifications utilisateur.
- task 19 : Frontend — router : guards `requiresAuth`, `requiresAdmin`, `requiresAdminUnlocked` + redirections.
- task 20 : Frontend — i18n fr.json + en.json (toutes nouvelles clés).
- task 21 : Backend — restreindre l'édition du catalogue Manga aux admins (ROLE_ADMIN sur write endpoints).
- task 22 : Backend + Docs — configurer Brevo comme provider SMTP (Mailer DSN) + écrire le guide Railway dédié.
- task 23 : Documentation `README.md` (étapes de mise à jour : 2 migrations + 1 commande + SMTP).
- task 24 : Final lint, test, et boucle de revue.

---

#### Task 1 : Entité `User`, enums, interfaces et repository

Créer le domaine User et son port repository, sans aucun changement DB pour l'instant. Les valeurs par défaut, statuts et rôles vivent dans des enums.

**Skills and docs to load :**
- `/project-quality-setup` — conventions PHP/Symfony/DDD, naming, hexagonal layout
- `.claude/CLAUDE.md` — Doctrine mapping rules (length enum, options default)
- `.claude/backend.md` — R2 (Shared SDK), R4 (Domain interface / Infra adapter)

**Files :**
- Create `back/src/Auth/Domain/User.php`
- Create `back/src/Auth/Domain/UserRoleEnum.php`
- Create `back/src/Auth/Domain/UserStatusEnum.php`
- Create `back/src/Auth/Domain/NotificationChannelEnum.php`
- Create `back/src/Auth/Domain/UserRepositoryInterface.php`
- Create `back/src/Auth/Domain/Exception/UserNotFoundException.php`
- Create `back/src/Auth/Domain/Exception/EmailAlreadyTakenException.php`
- Create `back/src/Auth/Domain/Exception/InvalidCredentialsException.php`
- Create `back/src/Auth/Domain/Exception/AccountNotActivatedException.php`
- Create `back/src/Auth/Shared/UserReaderInterface.php`
- Create `back/src/Auth/Shared/Dto/UserDto.php`
- Create `back/src/Auth/Infrastructure/Doctrine/DoctrineUserRepository.php`
- Create `back/src/Auth/Infrastructure/Doctrine/DoctrineUserReader.php`
- Modify `back/config/services.yaml` — alias `UserRepositoryInterface` → `DoctrineUserRepository`, `UserReaderInterface` → `DoctrineUserReader`

**Implementation**

`User` est une entité Doctrine `final` (pas `final readonly` car mutable : status, password, etc.) avec :
- `id: Ulid` (uuid v7 ou ulid, voir convention projet — utilise `Symfony\Component\Uid\Ulid`)
- `email: string` (unique, citext-style stocké lowercase)
- `passwordHash: string`
- `displayName: string`
- `role: UserRoleEnum` (`#[ORM\Column(enumType: UserRoleEnum::class)]`, pas de `length` → VARCHAR(255) par défaut, doit matcher migration)
- `status: UserStatusEnum`
- `notificationChannel: NotificationChannelEnum` (default `email`)
- `notificationEmail: ?string`
- `discordWebhookUrl: ?string`
- `createdAt: DateTimeImmutable`
- `lastLoginAt: ?DateTimeImmutable`

Méthodes domaine : `approve()`, `markEmailVerified()`, `changePassword(string $newHash)`, `updateProfile(...)`, `updateNotificationPreferences(...)`. Aucune logique infrastructure.

`UserRoleEnum` : cases `User`, `Admin` (valeurs strings `'ROLE_USER'`, `'ROLE_ADMIN'` pour matcher Symfony Security).

`UserStatusEnum` : cases `PendingEmailVerification`, `PendingAdminApproval`, `Active`, `Disabled`.

`NotificationChannelEnum` : cases `Email`, `Discord`.

`UserRepositoryInterface` : `findById(string $id): ?User`, `findByEmail(string $email): ?User`, `save(User $user): void`, `delete(User $user): void`, `findPaginated(AdminListUsersQuery $query): array` (renvoie `['items' => User[], 'total' => int]`).

`UserReaderInterface` (dans `Shared/`) : façade légère exposée aux autres modules, ne renvoie que des `UserDto` immuables (pas d'entité Doctrine).

`UserDto` (readonly) : `id, email, displayName, role, status, notificationChannel, notificationEmail, discordWebhookUrl`.

**Tests**

Unit tests dans `back/tests/Unit/Auth/Domain/` :
- `UserTest.php` : construction, transitions de statut (`approve()` n'est valide que depuis `PendingAdminApproval` ; sinon lance `DomainException`), changement de mdp, update préférences.
- `UserRoleEnumTest.php`, `UserStatusEnumTest.php`, `NotificationChannelEnumTest.php` : valeurs et `from()`.

**Verify**

```bash
docker compose exec back vendor/bin/phpunit --testsuite=Unit --filter Auth
docker compose exec back vendor/bin/phpstan analyse src/Auth
```

Tous verts.

---

#### Task 2 : Migration Doctrine v1 — tables `users`, `auth_tokens`, FK nullable partout

Première moitié du changement de schéma. Toutes les nouvelles tables sont créées et toutes les FK `user_id` sont ajoutées en **nullable** sur les tables user-scoped. Aucune contrainte NOT NULL ici — c'est ce qui permet le backfill par la commande task 3.

**Skills and docs to load :**
- `.claude/CLAUDE.md` — Doctrine mapping rules (Enum length, FK/index names via `make migration`)
- `.claude/backend.md` — R2 (Shared SDK)

**Files :**
- Create `back/migrations/Version20260519000000.php` (généré via `make migration` après ajout entité User + ajout `?User $owner` nullable sur entités user-scoped)
- Modify `back/src/Collection/Domain/CollectionEntry.php` — ajout `public ?User $owner` (ManyToOne, nullable: true)
- Modify `back/src/Notification/Domain/Notification.php` — ajout `public ?User $owner`
- Modify `back/src/Notification/Domain/Article.php` — ajout `public ?User $owner`
- Modify `back/src/Notification/Domain/ActivityLog.php` — ajout `public ?User $owner`
- Create `back/src/Auth/Domain/AuthToken.php`
- Create `back/src/Auth/Domain/AuthTokenTypeEnum.php` (cases `EmailVerification`, `PasswordReset`)
- Create `back/src/Auth/Domain/AuthTokenRepositoryInterface.php`
- Create `back/src/Auth/Infrastructure/Doctrine/DoctrineAuthTokenRepository.php`
- Modify `back/config/services.yaml`

**Implementation**

Étape 1 : ajouter la propriété `?User $owner` sur toutes les entités user-scoped (nullable). VolumeEntry hérite par cascade via `CollectionEntry` — pas de FK directe.

Étape 2 : ajouter l'entité `AuthToken` :
- `id: Ulid`
- `user: User` (ManyToOne not null, cascade nothing)
- `type: AuthTokenTypeEnum`
- `tokenHash: string` (SHA-256 hex, 64 chars)
- `expiresAt: DateTimeImmutable`
- `consumedAt: ?DateTimeImmutable`
- `createdAt: DateTimeImmutable`

Étape 3 : ajouter Doctrine type custom pour `Ulid` si pas déjà présent (vérifier `dbal.types` dans `config/packages/doctrine.yaml`).

Étape 4 : `make migration` → Doctrine génère `Version20260519000000.php` avec :
- `CREATE TABLE users (...)`
- `CREATE TABLE auth_tokens (...)` avec FK `user_id` NOT NULL + index
- `ALTER TABLE collection_entries ADD owner_id ULID NULL`
- `ALTER TABLE notifications ADD owner_id ULID NULL`
- `ALTER TABLE articles ADD owner_id ULID NULL`
- `ALTER TABLE activity_logs ADD owner_id ULID NULL`
- Toutes les FK avec ON DELETE rules : CASCADE pour Collection/Notification/Article ; SET NULL pour ActivityLog

⚠️ Ne JAMAIS écrire les noms de FK à la main — utiliser `make migration` (cf. `CLAUDE.md`).

**Verify**

```bash
docker compose exec back php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec back php bin/console doctrine:schema:validate
```

Schema validate : « The mapping information is in sync with the database schema. »

---

#### Task 3 : Commande console `app:bootstrap-admin`

Commande one-shot qui crée le compte admin et backfill `owner_id` sur toutes les lignes existantes.

**Skills and docs to load :**
- `.claude/backend.md` — R3 (handler = pure orchestrator, logique dans Domain service)
- `/project-quality-setup` — Symfony Console + DDD

**Files :**
- Create `back/src/Auth/Infrastructure/Console/BootstrapAdminCommand.php`
- Create `back/src/Auth/Application/BootstrapAdmin/BootstrapAdminCommand.php` (Application layer command, à ne pas confondre)
- Create `back/src/Auth/Application/BootstrapAdmin/BootstrapAdminHandler.php`
- Create `back/src/Auth/Domain/Service/AdminBackfillService.php`
- Create `back/src/Auth/Domain/Service/AdminBackfillServiceInterface.php`
- Create `back/src/Auth/Infrastructure/Doctrine/DoctrineAdminBackfillService.php`
- Modify `back/config/services.yaml`

**Implementation**

`BootstrapAdminCommand` (Symfony Console) :
- Signature : `app:bootstrap-admin {email} {password} {--display-name=Admin}`
- Vérifie qu'aucun admin n'existe déjà (refuse si oui, sauf `--force`).
- Dispatch `BootstrapAdminCommand` (Application) via `CommandBus`.

`BootstrapAdminHandler` (Application, R3 = pure orchestrator) :
1. Crée `User` admin via `User::createAdmin(email, hash, displayName)` (factory statique en Domain).
2. Persiste via `UserRepositoryInterface::save()`.
3. Appelle `AdminBackfillServiceInterface::assignAllOrphans($adminId)`.

`AdminBackfillServiceInterface` (Domain) :
```php
public function assignAllOrphans(string $adminId): BackfillReport;
```

`BackfillReport` : VO `final readonly` avec compteurs par table.

`DoctrineAdminBackfillService` (Infrastructure) :
- Exécute des `UPDATE table SET owner_id = :adminId WHERE owner_id IS NULL` en SQL natif pour chaque table user-scoped, dans une transaction.
- Renvoie le `BackfillReport`.

Le hash de password utilise `UserPasswordHasherInterface` de Symfony (injecté dans le handler).

**Tests**

Unit test `back/tests/Unit/Auth/Application/BootstrapAdminHandlerTest.php` :
- Avec UserRepository en mémoire et AdminBackfillService mocké, vérifier que :
  - Un User admin est persisté avec rôle `Admin` et status `Active`.
  - `assignAllOrphans` est appelé une fois avec l'ID admin.
  - Lever exception si admin existe déjà.

**Verify**

```bash
docker compose exec back php bin/console app:bootstrap-admin admin@ziggy.local "MotDePasseLong123!"
# attendu : ✅ Admin created. Backfill: 12 collection_entries, 0 notifications, 0 articles, 0 activity_logs
docker compose exec back vendor/bin/phpunit --filter BootstrapAdminHandlerTest
```

---

#### Task 4 : **[PR B — follow-up]** Migration Doctrine v2 — `owner_id` NOT NULL + index + tightening types

> [!WARNING]
> **Cette task ne fait PAS partie de la PR #97**. Elle vit dans une PR séparée (`claude/lock-user-owner-not-null` ou équivalent), ouverte **uniquement après** :
> 1. La PR A (#97) a été mergée et déployée en prod.
> 2. La commande `railway run --service back php bin/console app:bootstrap-admin <email> <password>` a été exécutée avec succès sur l'environnement Railway production.
> 3. Vérification manuelle : aucune ligne `owner_id IS NULL` ne reste dans `collection_entries`, `notifications`, `articles`.
>
> Sinon le deploy auto-migrate plantera (ALTER NOT NULL sur une colonne contenant des NULL).

**Skills and docs to load :**
- `.claude/CLAUDE.md` — Doctrine mapping rules

**Files :**
- Create `back/migrations/Version20260520000000.php` (généré via `make migration` après passage des `?User` en `User` non-nullable)
- Modify `back/src/Collection/Domain/CollectionEntry.php` — `?User $owner` → `User $owner` (suppression du `?`)
- Modify `back/src/Notification/Domain/Notification.php` — idem
- Modify `back/src/Notification/Domain/Article.php` — idem
- Modify `back/src/Notification/Domain/ActivityLog.php` — reste `?User` (ON DELETE SET NULL pour audit)
- Modify tous les handlers / repos / services qui défensaient le cas `null` sur `$entry->owner` — peuvent enlever leurs checks

**Implementation**

Passer le type Doctrine de `?User` à `User` (sauf ActivityLog) puis `make migration`. La migration générée contiendra `ALTER TABLE ... ALTER COLUMN owner_id SET NOT NULL` et l'ajout d'index sur `owner_id` (pour accélérer les `WHERE owner_id = :userId` qui sont sur le chemin chaud).

Ajouter dans la docblock de la migration un `getDescription(): string` : « Lock owner_id NOT NULL after admin backfill — REQUIRES app:bootstrap-admin to have been run in prod first. »

Une garde supplémentaire optionnelle dans la migration (mode prudent) : un `SELECT COUNT(*) FROM collection_entries WHERE owner_id IS NULL` qui throw si non-zero, AVANT de lancer le ALTER. Cela transforme un échec deploy bruyant en message clair (« Bootstrap admin n'a pas été exécuté »).

**Verify (avant merge de PR B)**

```bash
docker compose exec back php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec back php bin/console doctrine:schema:validate
docker compose exec back vendor/bin/phpunit
docker compose exec back vendor/bin/phpstan analyse
```

Tout doit être vert. Schema validate confirme que entités et DB sont alignés sur NOT NULL. La PR B se termine par sa propre boucle lint/test/review (équivalent task 24), pas couverte ici.

**À ne PAS faire dans cette task** : tout le reste du plan vit dans PR A. Cette task est isolée pour pouvoir être copiée-collée dans un mini-plan PR B au moment voulu.

---

#### Task 5 : Refonte Security — `UserProvider` DB + `JwtAuthenticator` + claim `adminUnlocked`

Faire pointer le firewall API sur le nouveau `User` au lieu de `GateUser`, garder le firewall messenger inchangé, et ajouter la gestion du claim admin.

**Skills and docs to load :**
- `.claude/CLAUDE.md` — Auth section
- `.claude/backend.md` — R4 (Domain interface / Infra adapter)

**Files :**
- Create `back/src/Auth/Infrastructure/Security/DoctrineUserProvider.php`
- Create `back/src/Auth/Infrastructure/Security/JwtUserExtractor.php`
- Modify `back/config/packages/security.yaml`
- Modify `back/config/packages/lexik_jwt_authentication.yaml` (si présent, sinon `services.yaml`)
- Modify `back/config/services.yaml`
- Modify `back/src/Auth/Infrastructure/Http/GateUserProvider.php` — ne gère plus que `MonitorUser`, renommer en `MonitorUserProvider`
- Delete `back/src/Auth/Domain/GateUser.php` (devenu inutile : remplacé par claim sur JWT)

**Implementation**

`DoctrineUserProvider implements UserProviderInterface` :
- `loadUserByIdentifier(string $identifier): UserInterface` → cherche par email puis renvoie un wrapper `AuthenticatedUser` (objet adaptateur qui implémente `UserInterface` + `PasswordAuthenticatedUserInterface`, garde l'ID et les rôles).
- `refreshUser`, `supportsClass`.

`AuthenticatedUser` (`Infrastructure/Security/AuthenticatedUser.php`) : VO `final readonly` avec `id, email, passwordHash, role: UserRoleEnum, adminUnlocked: bool`. Méthode `getRoles()` retourne `['ROLE_USER']` + `['ROLE_ADMIN']` si role admin + `['ROLE_ADMIN_UNLOCKED']` si claim présent.

`security.yaml` :
```yaml
providers:
    app_user_provider:
        id: App\Auth\Infrastructure\Security\DoctrineUserProvider
    monitor_provider:
        id: App\Auth\Infrastructure\Http\MonitorUserProvider

firewalls:
    messenger_monitor: # inchangé
    api:
        pattern: ^/api
        stateless: true
        provider: app_user_provider
        jwt: ~

access_control:
    - { path: ^/api/auth/(login|register|verify-email|request-reset|reset-password), roles: PUBLIC_ACCESS }
    - { path: ^/api/auth/gate, roles: ROLE_USER }
    - { path: ^/api/admin, roles: ROLE_ADMIN_UNLOCKED }
    - { path: ^/api/activity-logs, roles: ROLE_ADMIN_UNLOCKED }
    - { path: ^/api, roles: ROLE_USER }
```

`JwtUserExtractor` : event listener sur `JWTAuthenticatedEvent` qui lit les claims `sub` (userId), `roles`, `adminUnlocked` et construit `AuthenticatedUser`.

**Tests**

Unit `back/tests/Unit/Auth/Infrastructure/Security/AuthenticatedUserTest.php` :
- `getRoles()` renvoie `[ROLE_USER]` si user simple
- `getRoles()` renvoie `[ROLE_USER, ROLE_ADMIN]` si admin sans unlock
- `getRoles()` renvoie `[ROLE_USER, ROLE_ADMIN, ROLE_ADMIN_UNLOCKED]` si admin unlocked

**Verify**

```bash
docker compose exec back php bin/console debug:config security
docker compose exec back vendor/bin/phpunit --filter AuthenticatedUserTest
```

Le firewall `api` doit pointer sur `app_user_provider`.

---

#### Task 6 : Endpoints d'authentification publique

Tous les endpoints qui n'exigent pas encore un utilisateur connecté : inscription, vérification email, login, demande de reset, consommation de reset.

**Skills and docs to load :**
- `.claude/backend.md` — R1 (events for all side effects), R3 (pure orchestrator), R11 (pagination if needed)
- `.claude/CLAUDE.md` — `#[MapRequestPayload]`, `final readonly`, no try/catch in controllers

**Files :**
- Create `back/src/Auth/Application/Register/RegisterUserCommand.php`
- Create `back/src/Auth/Application/Register/RegisterUserHandler.php`
- Create `back/src/Auth/Application/VerifyEmail/VerifyEmailCommand.php`
- Create `back/src/Auth/Application/VerifyEmail/VerifyEmailHandler.php`
- Create `back/src/Auth/Application/Login/LoginCommand.php`
- Create `back/src/Auth/Application/Login/LoginHandler.php`
- Create `back/src/Auth/Application/RequestPasswordReset/RequestPasswordResetCommand.php`
- Create `back/src/Auth/Application/RequestPasswordReset/RequestPasswordResetHandler.php`
- Create `back/src/Auth/Application/ResetPassword/ResetPasswordCommand.php`
- Create `back/src/Auth/Application/ResetPassword/ResetPasswordHandler.php`
- Create `back/src/Auth/Infrastructure/Http/AuthController.php`
- Create `back/src/Auth/Infrastructure/Http/Request/RegisterRequest.php`
- Create `back/src/Auth/Infrastructure/Http/Request/VerifyEmailRequest.php`
- Create `back/src/Auth/Infrastructure/Http/Request/LoginRequest.php`
- Create `back/src/Auth/Infrastructure/Http/Request/RequestResetRequest.php`
- Create `back/src/Auth/Infrastructure/Http/Request/ResetPasswordRequest.php`
- Create `back/src/Auth/Shared/Event/UserRegisteredEvent.php`
- Create `back/src/Auth/Shared/Event/UserEmailVerifiedEvent.php`
- Create `back/src/Auth/Shared/Event/PasswordResetRequestedEvent.php`
- Create `back/src/Auth/Shared/Event/UserApprovedEvent.php`
- Create `back/src/Auth/Domain/Service/TokenGeneratorInterface.php`
- Create `back/src/Auth/Infrastructure/Token/SecureTokenGenerator.php`

**Implementation**

`AuthController` (routes : `/api/auth/register`, `/api/auth/verify-email`, `/api/auth/login`, `/api/auth/request-reset`, `/api/auth/reset-password`) — pure dispatch via `CommandBus`. Jamais de logique métier ni de try/catch (R3 + `ExceptionListener` global).

Handlers :
- `RegisterUserHandler` : vérifie unicité email, hash le mdp, crée User en status `PendingEmailVerification`, génère un `AuthToken` type `EmailVerification` (token clair envoyé par email, hash en DB), dispatch `UserRegisteredEvent`. Un listener Infrastructure envoie l'email (task 12).
- `VerifyEmailHandler` : trouve le token par hash, vérifie expiration et non-consommation, marque consommé, fait passer le user en `PendingAdminApproval`, dispatch `UserEmailVerifiedEvent`.
- `LoginHandler` : trouve user par email, vérifie hash via `UserPasswordHasherInterface`, exige status `Active`, génère JWT (sans claim `adminUnlocked`), met à jour `lastLoginAt`. Renvoie `['token' => ...]`. Sinon lance `InvalidCredentialsException` ou `AccountNotActivatedException` (codes HTTP 401/403 via `ExceptionListener`).
- `RequestPasswordResetHandler` : trouve user par email (ne RIEN faire si introuvable — pas d'oracle), génère un AuthToken `PasswordReset` (expire 24h), dispatch `PasswordResetRequestedEvent`. Listener envoie l'email reset.
- `ResetPasswordHandler` : trouve token, vérifie validité, update password, marque token consommé.

`TokenGeneratorInterface` : `generate(): string` retourne un token URL-safe (32 bytes random, base64url). L'implémentation `SecureTokenGenerator` utilise `random_bytes(32)`.

`UserApprovedEvent` est dispatché plus tard par le handler admin (task 8), pas ici.

Tous les events implémentent `StartedEventInterface`/`SucceededEventInterface`/`FailedEventInterface` quand pertinent pour bénéficier des `ActivityLog*Listener` génériques (R7).

**Tests**

Unit `back/tests/Unit/Auth/Application/` :
- `RegisterUserHandlerTest` — email déjà pris → exception ; sinon user créé + event dispatché + token créé.
- `VerifyEmailHandlerTest` — token expiré, déjà consommé, inconnu, valide.
- `LoginHandlerTest` — credentials invalides, user pending, user disabled, user actif (succès).
- `RequestPasswordResetHandlerTest` — email inconnu (no-op silencieux), email connu (token + event).
- `ResetPasswordHandlerTest` — token invalide, expiré, valide (password changé).

Functional `back/tests/Functional/Auth/AuthControllerTest.php` :
- POST register → 201, email envoyé (intercepter via Mailer profiler).
- POST register avec email pris → 409.
- POST login avec bons credentials → 200 + JWT.
- POST login user pending → 403.
- POST reset flow complet : request → consume → relogin avec nouveau mdp.

**Verify**

```bash
docker compose exec back vendor/bin/phpunit --testsuite=Unit --filter Auth
docker compose exec back vendor/bin/phpunit --testsuite=Functional --filter AuthControllerTest
```

Tous verts.

---

#### Task 7 : Refonte `GateController` en second factor admin

L'endpoint `/api/auth/gate` reste mais change de sémantique : il exige désormais un JWT user déjà valide, vérifie le `GATE_PASSWORD`, et ré-émet un JWT avec le claim `adminUnlocked: true`.

**Skills and docs to load :**
- `.claude/backend.md` — R3 (pure orchestrator)
- `.claude/CLAUDE.md` — Auth section

**Files :**
- Modify `back/src/Auth/Infrastructure/Http/GateController.php`
- Modify `back/src/Auth/Application/Gate/GateHandler.php`
- Modify `back/src/Auth/Application/Gate/GateCommand.php` (ajout `string $userId`)
- Update `back/tests/Functional/Auth/GateControllerTest.php` (modification du contrat)

**Implementation**

`GateController::__invoke` :
- Récupère `Security $security->getUser()` (déjà un `AuthenticatedUser` via firewall API).
- Vérifie que `user->role === Admin` sinon 403 (« Gate reserved to admins »).
- Dispatch `GateCommand($userId, $password)`.

`GateHandler` :
- Vérifie le `GATE_PASSWORD` en constant-time (`hash_equals`).
- Si succès : émet un nouveau JWT contenant les mêmes claims + `adminUnlocked: true`, TTL court (1h par défaut, configurable).
- Si échec : lance `InvalidGatePasswordException` (déjà existant) — 401.

Le frontend remplacera son token courant par celui-ci pour avoir accès au journal et à `/admin/**`.

**Tests**

- Functional : un user non-admin reçoit 403 ; un admin avec mauvais mdp → 401 ; bon mdp → 200 + JWT contenant claim `adminUnlocked`. Le JWT renvoyé permet ensuite d'accéder à `/api/admin/users`.

**Verify**

```bash
docker compose exec back vendor/bin/phpunit --filter GateControllerTest
```

---

#### Task 8 : Module `Admin/` — gestion des utilisateurs

Nouveau bounded context `Admin/` qui expose le CRUD utilisateurs pour l'administration.

**Skills and docs to load :**
- `.claude/backend.md` — R3 (orchestrator), R6 (deptrac : Admin → Auth_Shared seulement), R11 (pagination)
- `.claude/CLAUDE.md` — pagination Shared classes

**Files :**
- Create `back/src/Admin/Application/ListUsers/AdminListUsersQuery.php` (extends `AbstractPaginatedQuery`)
- Create `back/src/Admin/Application/ListUsers/AdminListUsersHandler.php`
- Create `back/src/Admin/Application/ListUsers/AdminUsersPaginatedResult.php`
- Create `back/src/Admin/Application/GetUser/GetUserQuery.php`
- Create `back/src/Admin/Application/GetUser/GetUserHandler.php`
- Create `back/src/Admin/Application/UpdateUser/UpdateUserCommand.php`
- Create `back/src/Admin/Application/UpdateUser/UpdateUserHandler.php`
- Create `back/src/Admin/Application/ApproveUser/ApproveUserCommand.php`
- Create `back/src/Admin/Application/ApproveUser/ApproveUserHandler.php`
- Create `back/src/Admin/Application/DeleteUser/DeleteUserCommand.php`
- Create `back/src/Admin/Application/DeleteUser/DeleteUserHandler.php`
- Create `back/src/Admin/Application/GenerateResetLink/GenerateResetLinkCommand.php`
- Create `back/src/Admin/Application/GenerateResetLink/GenerateResetLinkHandler.php`
- Create `back/src/Admin/Application/GenerateResetLink/GenerateResetLinkResult.php`
- Create `back/src/Admin/Infrastructure/Http/AdminUserController.php`
- Create `back/src/Admin/Infrastructure/Http/Request/UpdateUserRequest.php`
- Create `back/src/Admin/Infrastructure/Http/Request/ListUsersRequest.php`
- Modify `back/config/services.yaml`
- Modify `back/deptrac.yaml` (si présent) — ajout layer Admin

**Implementation**

Routes (prefix `/api/admin`, déjà filtrées par `ROLE_ADMIN_UNLOCKED` dans security.yaml) :

| Méthode | Route | Action |
|---|---|---|
| GET | `/api/admin/users?page=&limit=&search=&status=` | Liste paginée |
| GET | `/api/admin/users/{id}` | Détail |
| PATCH | `/api/admin/users/{id}` | Update (displayName, status, role, notif prefs) |
| POST | `/api/admin/users/{id}/approve` | Raccourci pour passer status → Active |
| DELETE | `/api/admin/users/{id}` | Suppression (CASCADE collections etc., SET NULL activity logs) |
| POST | `/api/admin/users/{id}/reset-link` | Génère un token reset + déclenche email + renvoie le lien complet |

`AdminListUsersQuery` extends `AbstractPaginatedQuery` avec champs additionnels `?string $search, ?UserStatusEnum $status`.

`AdminUsersPaginatedResult` extends `PaginatedResult<User>` avec `serializeItems()` qui mappe chaque `User` vers un tableau `{ id, email, displayName, role, status, createdAt, lastLoginAt, notificationChannel }`.

`GenerateResetLinkHandler` :
- Crée un `AuthToken` type `PasswordReset` (réutilise la logique de task 6).
- Dispatch `PasswordResetRequestedEvent` (l'email part automatiquement via listener).
- Retourne `GenerateResetLinkResult { url: string, expiresAt: DateTimeImmutable }` où `url = "{FRONT_URL}/reset-password?token={token}"`.
- L'admin peut donc copier le lien depuis l'UI ET l'utilisateur le reçoit par email (réponse choisie : option 2).

`FRONT_URL` est un nouveau paramètre `services.yaml` / `.env` (`APP_FRONT_URL=http://localhost:5173`).

**Tests**

Unit `back/tests/Unit/Admin/Application/` :
- `AdminListUsersHandlerTest` — pagination, filtres search/status.
- `UpdateUserHandlerTest` — modification autorisée des champs, blocage si user inconnu.
- `ApproveUserHandlerTest` — transitions valides/invalides, event dispatché.
- `DeleteUserHandlerTest` — suppression + (vérifier que les cascades sont laissées à la DB, pas faites en PHP).
- `GenerateResetLinkHandlerTest` — création token + event + résultat url/expiresAt.

Functional `back/tests/Functional/Admin/AdminUserControllerTest.php` :
- Sans JWT admin_unlocked : 403 sur tous les endpoints.
- Avec JWT admin_unlocked : GET/PATCH/DELETE OK, pagination respectée, search fonctionne.

**Verify**

```bash
docker compose exec back vendor/bin/phpunit --filter Admin
docker compose exec back vendor/bin/deptrac analyse
```

Deptrac vert (Admin n'importe que Auth_Shared, Shared).

---

#### Task 9 : `CurrentUserProvider` dans Shared + injection partout

Centraliser la lecture de l'utilisateur authentifié pour tous les modules.

**Skills and docs to load :**
- `.claude/backend.md` — R4 (interface in Domain, adapter in Infrastructure)

**Files :**
- Create `back/src/Shared/Domain/Security/CurrentUserProviderInterface.php`
- Create `back/src/Shared/Infrastructure/Security/SymfonyCurrentUserProvider.php`
- Modify `back/config/services.yaml`

**Implementation**

```php
interface CurrentUserProviderInterface
{
    public function currentUserId(): string; // throws DomainException if not authenticated
    public function currentUserIdOrNull(): ?string;
    public function isAdmin(): bool;
    public function isAdminUnlocked(): bool;
}
```

`SymfonyCurrentUserProvider` injecte `Security $security` et lit `AuthenticatedUser`.

Cette interface sera injectée dans tous les handlers user-scoped à la task 10.

**Tests**

Unit avec un fake Security : null user → exception, user lambda → id + flags adéquats.

**Verify**

```bash
docker compose exec back vendor/bin/phpstan analyse src/Shared
```

---

#### Task 10 : Filtrage par user dans tous les repositories user-scoped

Adapter les requêtes pour ne renvoyer QUE les données de l'utilisateur authentifié. Le contrat HTTP ne change pas (mêmes routes, mêmes payloads).

**Skills and docs to load :**
- `.claude/backend.md` — R3 (handler orchestrateur), R11 (pagination)
- `.claude/CLAUDE.md` — naming

**Files :**
- Modify `back/src/Collection/Domain/CollectionRepositoryInterface.php` (signatures avec `string $ownerId`)
- Modify `back/src/Collection/Infrastructure/Doctrine/DoctrineCollectionRepository.php`
- Modify `back/src/Collection/Application/**Handler.php` (tous les handlers : ajout `CurrentUserProviderInterface` + passage de l'ID au repo)
- Modify `back/src/Wishlist/**` (idem, basé sur `VolumeEntry.isWished` mais filtré via owner du CollectionEntry parent)
- Modify `back/src/Notification/Domain/NotificationRepositoryInterface.php` + handler
- Modify `back/src/Notification/Domain/ArticleRepositoryInterface.php` + handler
- Modify `back/src/Notification/Domain/ActivityLogRepositoryInterface.php` — décision : tous les ActivityLog filtrés par owner SAUF si l'appelant est admin_unlocked (vue admin globale)
- Modify `back/src/Stats/Application/GetStatsHandler.php` — filtre par user
- Modify `back/src/Manga/Application/**` — pour les imports utilisateur : le `Manga` reste partagé mais le `CollectionEntry` créé en parallèle doit être owné

**Implementation**

Pattern dans chaque handler :
```php
public function __construct(
    private CurrentUserProviderInterface $currentUserProvider,
    private CollectionRepositoryInterface $repository,
) {}

public function __invoke(GetCollectionQuery $query): array
{
    $ownerId = $this->currentUserProvider->currentUserId();
    $result  = $this->repository->findFiltered($query, $ownerId);
    return (new CollectionPaginatedResult(...))->toArray();
}
```

Pour `ActivityLogRepository`, deux méthodes :
- `findGlobalPaginated($query)` — utilisée par `/api/admin/activity-logs` (admin_unlocked)
- `findOwnedPaginated($query, $ownerId)` — utilisée par `/api/notifications/activity-logs` (user normal voit seulement ses logs)

Décision : le journal d'app est admin-only. Donc côté user, on retire l'endpoint d'activity-logs (ou on renvoie 403). Confirmer dans le frontend (`JournalPage` devient `AdminJournalPage` accessible seulement après gate unlocked).

**Tests**

Functional `back/tests/Functional/` pour chaque module : créer 2 users, vérifier que user A ne voit pas les données de user B sur tous les endpoints. C'est le test critique de cette task.

Unit : si la logique de filtrage est dans le repo Doctrine, pas testable en Unit ; les handlers sont testés via Functional.

**Verify**

```bash
docker compose exec back vendor/bin/phpunit --testsuite=Functional
docker compose exec back vendor/bin/phpstan analyse src
```

Tous les tests existants passent (après adaptation task 14), zéro test ne révèle de fuite de données entre users.

---

#### Task 11 : Préférences notifications per-user + scheduler fan-out per-user

Adapter le système de notifications + le scheduler de crawl pour qu'ils utilisent les préférences de chaque utilisateur au lieu des variables d'env globales. Le scheduler reste un seul cron global ; le fan-out par user est implicite via `CollectionEntry.owner`.

**Skills and docs to load :**
- `.claude/backend.md` — R1 (events for side effects), R5 (one event, many listeners), R7 (3 generic ActivityLog listeners)
- `.claude/CLAUDE.md` — services config, naming

**Files :**
- Modify `back/src/Notification/Infrastructure/Listener/DiscordNewArticlesListener.php` — lire le webhook depuis le user owner de la collection, pas depuis env ; skip si null
- Create `back/src/Notification/Infrastructure/Listener/EmailNewArticlesListener.php` — listener symétrique pour les users en mode email
- Modify `back/src/Notification/Infrastructure/Discord/DiscordNotifier.php` — `send(string $webhookUrl, DiscordPayload $payload)` (webhookUrl en argument par appel, plus en constructor)
- Create `back/src/Notification/Domain/NotificationDispatcherInterface.php` — abstrait le choix du canal
- Create `back/src/Notification/Infrastructure/NotificationDispatcher.php` — selon `User->notificationChannel`, route vers Discord ou Mailer
- Modify `back/src/Notification/Application/SendFollowingNotificationHandler.php` — utiliser le dispatcher
- Modify `back/src/Notification/Application/SendSchedulerDiscordSummaryHandler.php` — renommer en `SendSchedulerSummaryHandler` ; GROUP BY owner ; un summary par user via NotificationDispatcher
- Modify `back/src/Notification/Application/DispatchFollowingCrawlTask.php` — itérer sur les entries `WHERE owner_id IS NOT NULL AND notificationsEnabled=true` ; skip orphans
- Modify `back/src/Notification/Infrastructure/Listener/ActivityLogSchedulerFiredListener.php` — le scheduler fire reste un log global (admin-only), pas attaché à un user
- Create `back/src/Auth/Application/UpdateNotificationPreferences/UpdateNotificationPreferencesCommand.php`
- Create `back/src/Auth/Application/UpdateNotificationPreferences/UpdateNotificationPreferencesHandler.php`
- Create `back/src/Auth/Infrastructure/Http/ProfileController.php` (endpoint `PATCH /api/me/notifications`)

**Implementation**

`NotificationDispatcher::dispatch(User $user, NotificationPayload $payload)` :
- `match ($user->notificationChannel)` → email via Mailer, Discord via DiscordNotifier.
- Si email : utilise `$user->notificationEmail` ou fallback `$user->email`.
- Si discord : utilise `$user->discordWebhookUrl` ; si null → log warning et skip.

Listeners RSS/Jikan (`EmailNewArticlesListener`, `DiscordNewArticlesListener`) deviennent symétriques :
- Tous deux écoutent `RssFetchSucceededEvent` et `JikanFetchSucceededEvent`.
- Chacun vérifie en premier : `if ($entry->owner === null) return;` (legacy guard).
- Puis : `if ($entry->owner->notificationChannel !== <leur canal>) return;` (un seul listener s'active par user).
- Puis appellent leur émetteur respectif.

**Scheduler — résumé per-user.** Le handler actuel `SendSchedulerDiscordSummaryHandler` agrège tout dans un seul message Discord global. Refactor :
```php
final readonly class SendSchedulerSummaryHandler
{
    public function __invoke(SendSchedulerSummaryMessage $message): void
    {
        $byOwner = $this->crawlRunRepository->groupNewArticlesByOwner($message->crawlRunId);
        // ['userId1' => ['count' => 5, 'mangaTitles' => [...]], ...]

        foreach ($byOwner as $ownerId => $summary) {
            $user = $this->userRepository->findById($ownerId);
            if ($user === null) {
                continue; // legacy orphan
            }
            $this->notificationDispatcher->dispatch(
                $user,
                new SchedulerSummaryPayload($summary['count'], $summary['mangaTitles']),
            );
        }
    }
}
```

**Scheduler — fan-out skip orphans.** `DispatchFollowingCrawlTask` filtre désormais explicitement `WHERE owner_id IS NOT NULL AND notifications_enabled = true`. Tant que `bootstrap-admin` n'a pas tourné, le scheduler ne traite aucune entry (comportement attendu : pas de crash, pas de notification).

**ActivityLog scheduler events.** Le `SchedulerFiredEvent` reste un log **global** (pas de `owner_id` — c'est un événement système, visible uniquement dans le journal admin). Les `RssFetch*Event` héritent du `owner_id` de leur `CollectionEntry` parent (via le mécanisme générique R7).

`ProfileController` : un seul endpoint `PATCH /api/me/notifications { channel, notificationEmail, discordWebhookUrl }`. Validation : si `channel=email`, `notificationEmail` non vide ; si `channel=discord`, `discordWebhookUrl` matche regex Discord (`^https://discord(app)?\.com/api/webhooks/\d+/[\w-]+$`).

**Tests**

Unit :
- `UpdateNotificationPreferencesHandlerTest` — validation channel ↔ champ associé.
- `SendSchedulerSummaryHandlerTest` — 3 users, 2 en email + 1 en discord, 5 new articles répartis : assert que NotificationDispatcher est appelé 3 fois avec les bons payloads ; un 4ème entry sans owner est skippé.

Functional :
- `ProfileControllerTest` : PATCH avec channel=email sans email → 422 ; channel=discord sans webhook → 422 ; PATCH valide → 200.
- `EmailVsDiscordListenerTest` : dispatch `RssFetchSucceededEvent` avec un entry dont l'owner est en mode discord → mock DiscordNotifier appelé, mock Mailer JAMAIS appelé ; même test avec un user en mode email → inverse. Avec un entry `owner=null` → ni l'un ni l'autre.
- `SchedulerFanOutTest` : seed 2 users (1 email, 1 discord) chacun avec 1 followed manga, lancer un cycle complet (mock fetcher RSS qui renvoie 3 articles par feed) ; assert chaque user reçoit son summary dans son canal, et le journal d'activité contient les bons logs `owner_id`.

**Verify**

```bash
docker compose exec back vendor/bin/phpunit --filter Notification
docker compose exec back vendor/bin/phpunit --filter ProfileController
docker compose exec back vendor/bin/phpunit --filter Scheduler
```

---

#### Task 12 : Templates email Twig (DaisyUI dark)

Créer les templates HTML pour les emails transactionnels avec une DA qui matche celle du site.

**Skills and docs to load :**
- `/project-quality-setup` — conventions Twig

**Files :**
- Create `back/templates/emails/layout.html.twig`
- Create `back/templates/emails/email_verification.html.twig`
- Create `back/templates/emails/password_reset.html.twig`
- Create `back/templates/emails/account_approved.html.twig`
- Create `back/templates/emails/welcome.html.twig` (envoyé après approbation admin)
- Create `back/src/Notification/Infrastructure/Listener/SendUserRegisteredEmailListener.php`
- Create `back/src/Notification/Infrastructure/Listener/SendPasswordResetEmailListener.php`
- Create `back/src/Notification/Infrastructure/Listener/SendUserApprovedEmailListener.php`
- Modify `back/config/services.yaml`

**Implementation**

`layout.html.twig` — un seul fichier de base avec :
- inline CSS only (clients mail ne lisent pas les `<style>` externes)
- couleurs DaisyUI dark : `--b1: #1d232a` (bg), `--b2: #191e24` (cards), `--p: #66cc8a` (primary green), `--bc: #a6adbb` (texte secondaire), `--n: #2a323c`
- police système (`-apple-system, BlinkMacSystemFont, "Segoe UI"`)
- en-tête centré « Ziggythèque » avec petit logo SVG inline (ou Unicode 📚)
- footer minimaliste avec mention « Si vous n'avez pas demandé cet email, ignorez-le. »
- block Twig `{% block body %}{% endblock %}` pour les templates filles

Templates filles : bouton CTA central (`<a>` stylé en bouton DaisyUI primary, couleur #66cc8a, padding, border-radius), texte explicatif, fallback URL en clair en dessous.

Listeners :
- `SendUserRegisteredEmailListener` écoute `UserRegisteredEvent`, lit le token clair (à conserver dans l'event ou via un `lastToken` mutable du handler ; choix : l'event porte `tokenPlain: string` éphémère).
- Idem pour reset et approved.

L'URL générée pointe vers `APP_FRONT_URL/verify-email?token=...` et `APP_FRONT_URL/reset-password?token=...`.

**Tests**

Functional `back/tests/Functional/Notification/EmailListenerTest.php` :
- Dispatch `UserRegisteredEvent` → 1 email présent dans le profiler Mailer Symfony, sujet correct, contient le lien.
- Idem pour reset et approved.

**Verify**

```bash
docker compose exec back vendor/bin/phpunit --filter EmailListenerTest
# Visuel : envoyer un mail de test et l'ouvrir sur Mailpit http://localhost:8025
docker compose exec back php bin/console app:send-test-email admin@ziggy.local
```

(Optionnel — créer `app:send-test-email` n'est PAS dans le scope, juste pour vérif manuelle.)

---

#### Task 13 : Tests unitaires backend (User, tokens, handlers)

Cf. couverts dans tasks 1, 3, 5, 6, 8, 9, 11 — section listée ici uniquement pour rappel. Aucun fichier supplémentaire à créer dans cette task ; vérification que tous les tests unitaires `back/tests/Unit/Auth/` et `back/tests/Unit/Admin/` existent et passent.

**Verify**

```bash
docker compose exec back vendor/bin/phpunit --testsuite=Unit
```

100% des tests unitaires verts. Si une assertion manque pour un cas, l'ajouter ici.

---

#### Task 14 : Refonte `AbstractApiTestCase` + adaptation tests fonctionnels existants

Le helper d'authentification des tests change radicalement. C'est le point de friction principal de la migration.

**Skills and docs to load :**
- `.claude/CLAUDE.md` — testing rules, DAMA, `when@test:`

**Files :**
- Modify `back/tests/Functional/AbstractApiTestCase.php`
- Modify `back/tests/Functional/Collection/CollectionControllerTest.php` (toutes les méthodes existantes)
- Modify `back/tests/Functional/Notification/NotificationControllerTest.php`
- Modify `back/tests/Functional/Notification/ArticleControllerTest.php`
- Modify `back/tests/Functional/Manga/MangaControllerTest.php`
- Modify `back/tests/Functional/Wishlist/WishlistControllerTest.php`
- Modify `back/tests/Functional/Stats/StatsControllerTest.php`
- Modify `back/tests/Functional/Auth/GateControllerTest.php`
- Create `back/tests/Functional/Support/UserFixtureFactory.php`

**Implementation**

`UserFixtureFactory` : helper qui crée un user en DB avec status `Active` et renvoie ses credentials.

`AbstractApiTestCase` :
```php
protected function authenticatedClient(UserRoleEnum $role = UserRoleEnum::User, bool $adminUnlocked = false): KernelBrowser
{
    $user = $this->userFactory->create(role: $role, status: UserStatusEnum::Active);
    $loginToken = $this->fetchLoginToken($user);
    if ($adminUnlocked) {
        $loginToken = $this->fetchAdminUnlockedToken($loginToken);
    }
    $this->client->setServerParameter('HTTP_AUTHORIZATION', "Bearer {$loginToken}");
    return $this->client;
}
```

Tous les tests existants passent de `fetchAuthToken()` à `authenticatedClient()`. Pour les tests qui touchent à des données partagées (Manga), pas de changement. Pour les tests Collection/Notification, ils doivent créer leur user dans le setup et insérer leurs fixtures avec le bon owner.

**Verify**

```bash
docker compose exec back vendor/bin/phpunit --testsuite=Functional
```

Tous les tests fonctionnels (anciens et nouveaux) passent. C'est le gate critique.

---

#### Task 15 : Frontend — refonte `useAuthStore`, ajout API auth + users

Côté Vue, étendre le store auth pour porter l'utilisateur courant + rôles + flag adminUnlocked, et créer les modules API correspondants.

**Skills and docs to load :**
- `/vue-best-practices` — Composition API + TS
- `/vue-pinia-best-practices` — design du store

**Files :**
- Modify `front/src/stores/useAuthStore.ts`
- Create `front/src/api/auth.ts` (élargi : login, register, verifyEmail, requestReset, resetPassword, gate)
- Create `front/src/api/users.ts` (admin CRUD)
- Create `front/src/api/profile.ts` (PATCH /api/me/notifications)
- Modify `front/src/api/client.ts` — interceptor 403 (redirige selon route)
- Create `front/src/types/user.ts`

**Implementation**

`useAuthStore` :
```ts
interface AuthState {
  token: string | null;          // JWT actif (peut être le admin_unlocked)
  user: AuthUser | null;         // claims décodés
}
interface AuthUser {
  id: string;
  email: string;
  displayName: string;
  role: 'ROLE_USER' | 'ROLE_ADMIN';
  adminUnlocked: boolean;
}
```

Méthodes :
- `loginWithEmail(email, password)` → POST `/api/auth/login` → setToken + décoder claims
- `register(payload)` → POST `/api/auth/register`
- `verifyEmail(token)` → POST `/api/auth/verify-email`
- `requestReset(email)` → POST `/api/auth/request-reset`
- `resetPassword(token, newPassword)` → POST `/api/auth/reset-password`
- `unlockAdmin(gatePassword)` → POST `/api/auth/gate` → remplace token actuel par admin_unlocked
- `logout()` → clear

Décodage JWT côté front : `jwt-decode` (déjà dans le projet ? sinon ajout dans `package.json` — `jwt-decode@4`).

`api/users.ts` : `listUsers`, `getUser`, `updateUser`, `approveUser`, `deleteUser`, `generateResetLink`. Toutes typées avec les DTOs du backend.

**Tests**

Vitest `front/tests/stores/useAuthStore.test.ts` : login → state + sessionStorage écrits ; logout → tout effacé ; unlockAdmin → token remplacé, user.adminUnlocked passe à true.

**Verify**

```bash
cd front && npx vue-tsc --noEmit
cd front && npx vitest run
```

---

#### Task 16 : Pages publiques d'authentification

Toutes les pages user-facing pour gérer le cycle : inscription, vérification, login, reset, écran d'attente.

**Skills and docs to load :**
- `/vue-best-practices` — Composition API, `<script setup>`, TS
- `/ux-icons` — icônes Lucide pour les formulaires

**Files :**
- Create `front/src/pages/LoginPage.vue`
- Create `front/src/pages/RegisterPage.vue`
- Create `front/src/pages/VerifyEmailPage.vue` (lit `?token=...` dans la query)
- Create `front/src/pages/RequestResetPage.vue`
- Create `front/src/pages/ResetPasswordPage.vue` (lit `?token=...`)
- Create `front/src/pages/AccountPendingPage.vue` (affiché si login OK mais status != Active)
- Modify `front/src/pages/GatePage.vue` — devient écran de SECOND factor admin (titre, message changent)

**Implementation**

Toutes les pages utilisent DaisyUI :
- card centrée `card bg-base-200 w-full max-w-md`
- inputs `input input-bordered`
- bouton `btn btn-primary`
- error `alert alert-error` (via `useUiStore` toast aussi)

`LoginPage` :
- 2 champs (email, password) + bouton login + lien « S'inscrire » + lien « Mot de passe oublié »
- Sur succès : `useAuthStore.loginWithEmail()` → router push `/dashboard` si status Active, sinon `/account-pending`
- Sur 403 (`AccountNotActivated`) → push `/account-pending` avec le sous-statut affiché

`RegisterPage` : email, displayName, password, confirmPassword. Validation côté client (zod ou validation manuelle). Sur succès : message « Email envoyé, vérifie ta boîte ».

`VerifyEmailPage` : auto-call de `verifyEmail(token)` au mount, affiche succès/erreur, redirige vers `/account-pending` après 2s.

`RequestResetPage` : un champ email. Sur succès : message générique « Si un compte existe avec cet email, un lien a été envoyé. »

`ResetPasswordPage` : 2 champs (nouveau mdp + confirm). Sur succès : redirect `/login`.

`AccountPendingPage` : affiche un message selon le statut (`PendingEmailVerification` → « Clique sur le lien dans ton email » ; `PendingAdminApproval` → « En attente d'approbation par l'admin » ; `Disabled` → « Compte désactivé »).

`GatePage` (modifiée) : titre devient « Accès admin », sous-texte « Entre le password admin pour accéder au journal et à la gestion des utilisateurs. ». Sur succès : `unlockAdmin()` puis `router.back()` ou push `/admin/users`.

**Verify**

Visuellement via `cd front && npm run dev` puis manuellement :
- Test E2E manuel du flow complet (register → verif → admin approve → login → use).

---

#### Task 17 : Page admin `AdminUsersPage` (DataTable paginée)

Liste des utilisateurs avec pagination, tri, recherche, et actions (édition, approval, suppression, génération de lien reset).

**Skills and docs to load :**
- `/vue-best-practices`
- `/vue-pinia-best-practices` — store dédié si pagination complexe
- `/ux-icons` — icônes (edit, trash, key, check)

**Files :**
- Create `front/src/pages/AdminUsersPage.vue`
- Create `front/src/components/organisms/AdminUsersTable.vue`
- Create `front/src/components/organisms/AdminUserEditModal.vue`
- Create `front/src/components/organisms/AdminResetLinkModal.vue`
- Create `front/src/stores/useAdminUsersStore.ts` (optionnel : sinon useQuery directement dans la page)

**Implementation**

`AdminUsersPage` :
- Sidebar gauche (déjà fournie par MainLayout)
- En-tête : titre + bouton recherche (input debounce 300ms)
- Filtre statut (select DaisyUI avec All / Pending / Active / Disabled)
- `AdminUsersTable` : colonnes Email, Display Name, Rôle (badge), Status (badge coloré), Créé le, Dernière connexion, Actions
- Actions par ligne (boutons icône Lucide) : Edit, Approve (visible seulement si pending), Reset link (key icon), Delete (trash icon, double-confirm)
- Pagination en bas : `daisyui-pagination` (prev/pages/next)

`AdminUserEditModal` : DaisyUI modal, formulaire avec displayName, role select, status select, channel select + email/webhook. Sauvegarde via `PATCH /api/admin/users/:id`.

`AdminResetLinkModal` : affiche le lien retourné par `POST /api/admin/users/:id/reset-link`, avec bouton « Copier dans le presse-papier » (`navigator.clipboard.writeText`). Mention « Un email a aussi été envoyé à l'utilisateur. »

Routes ajoutées dans task 19, route `/admin/users`.

Toutes les requêtes utilisent `useQuery`/`useMutation` TanStack (pattern existant).

**Verify**

`cd front && npx vue-tsc --noEmit` + test manuel :
- Liste affichée, pagination cliquable, recherche filtre, modal édition modifie, delete supprime, reset link affiche un URL copiable.

---

#### Task 18 : Page préférences notifications utilisateur

Permettre à chaque utilisateur de configurer son canal et ses coordonnées.

**Skills and docs to load :**
- `/vue-best-practices`

**Files :**
- Create `front/src/pages/AccountNotificationsPage.vue`

**Implementation**

Page accessible via la sidebar (route `/account/notifications`) :
- Radio group DaisyUI : Email / Discord
- Si Email sélectionné : input `notificationEmail` (par défaut l'email du compte, modifiable)
- Si Discord sélectionné : input `discordWebhookUrl` + helper text « Format attendu : https://discord.com/api/webhooks/... »
- Bouton « Enregistrer » → PATCH `/api/me/notifications`
- Toast succès / erreur via `useUiStore`

**Verify**

`cd front && npx vue-tsc --noEmit` + manuel.

---

#### Task 19 : Router — guards et nouvelles routes

Configurer les permissions de chaque route et les redirections.

**Skills and docs to load :**
- `/vue-router-best-practices` — `beforeEach`, meta, redirects

**Files :**
- Modify `front/src/router/index.ts`

**Implementation**

Routes ajoutées :
- `/login` (public, meta `{ public: true, redirectIfAuthenticated: true }`)
- `/register` (public, idem)
- `/verify-email` (public)
- `/forgot-password` (public)
- `/reset-password` (public)
- `/account-pending` (login OK mais non Active : meta `{ requiresAuth: true, requiresActive: false }`)
- `/account/notifications` (meta `{ requiresAuth: true, requiresActive: true }`)
- `/admin/users` (meta `{ requiresAuth: true, requiresActive: true, requiresAdmin: true, requiresAdminUnlocked: true }`)
- `/journal` (idem `/admin/users` — passe en admin-only)
- `/gate` reste (second factor)

Guard `beforeEach` :
1. Si `requiresAuth` et pas de token → push `/login` avec `?redirect=...`
2. Si `requiresActive` et `user.status !== 'Active'` → push `/account-pending`
3. Si `requiresAdmin` et user.role !== Admin → push `/dashboard` + toast 403
4. Si `requiresAdminUnlocked` et `!user.adminUnlocked` → push `/gate?redirect=...`
5. Si `redirectIfAuthenticated` et token présent → push `/dashboard`

**Verify**

Manuel : tenter chaque route en non-connecté, en user normal, en admin pas unlocked, en admin unlocked. Aucune fuite d'accès.

---

#### Task 20 : i18n fr.json + en.json

Ajouter toutes les clés de traduction pour les nouvelles pages et messages.

**Files :**
- Modify `front/src/i18n/fr.json`
- Modify `front/src/i18n/en.json`

**Implementation**

Ajouter sections :
- `auth.login.*`, `auth.register.*`, `auth.verify.*`, `auth.reset.*`, `auth.pending.*`, `auth.gate.*`
- `admin.users.*` (titres, colonnes, actions, confirmations)
- `account.notifications.*`
- `errors.*` (mapping des `DomainException` côté front : `invalidCredentials`, `accountNotActivated`, `emailAlreadyTaken`, etc.)

FR par défaut, EN miroir exact (clés identiques).

**Verify**

```bash
cd front && node -e "const fr=require('./src/i18n/fr.json'); const en=require('./src/i18n/en.json'); function keys(o,p=''){return Object.entries(o).flatMap(([k,v])=>typeof v==='object'?keys(v,p+k+'.'):[p+k])}; const f=keys(fr),e=keys(en); console.log('Missing in en:', f.filter(k=>!e.includes(k))); console.log('Missing in fr:', e.filter(k=>!f.includes(k)));"
```

Output : `Missing in en: []` et `Missing in fr: []`.

---

#### Task 21 : Restreindre l'édition du catalogue Manga aux admins

Les fiches manga sont partagées entre tous les users, mais SEUL l'admin peut les créer/éditer pour éviter le vandalisme et garantir la qualité du catalogue. La recherche externe et l'ajout d'un manga existant à sa collection restent ouverts à tous.

**Skills and docs to load :**
- `.claude/CLAUDE.md` — Auth section, endpoint conventions
- `.claude/backend.md` — R3 (no business logic in controllers)

**Files :**
- Modify `back/config/packages/security.yaml` — ajouter des règles `access_control` granulaires pour les méthodes write de `/api/manga`
- Modify `back/src/Manga/Infrastructure/Http/MangaController.php` — ajouter `#[IsGranted('ROLE_ADMIN')]` sur les routes write
- Modify `back/tests/Functional/Manga/MangaControllerTest.php` — adapter les tests existants + nouveaux cas 403
- Modify `front/src/pages/AddMangaPage.vue` — la page reste accessible mais le bouton « Importer » n'est visible qu'aux admins ; un user normal voit la liste de recherche en lecture seule (« Demande à l'admin d'ajouter ce manga »)
- Modify `front/src/pages/MangaDetailPage.vue` — les contrôles d'édition (titre, résumé, ajout volume) cachés pour non-admin

**Implementation**

Côté backend, deux options pour la restriction :
- **Option A (retenue)** : attribut PHP `#[IsGranted('ROLE_ADMIN')]` directement sur les méthodes du controller. Plus lisible que les regex `access_control` pour des routes au cas par cas (certaines méthodes du même controller sont publiques, d'autres restreintes).
- Option B : `access_control` global — rejetée parce que les routes GET et POST partagent le même préfixe `/api/manga`.

Routes restreintes (`ROLE_ADMIN`) :
- `POST   /api/manga` (import manuel)
- `PATCH  /api/manga/:id` (édition fiche)
- `POST   /api/manga/:id/volumes` (ajout volume)
- `PATCH  /api/manga/:id/volumes/:volumeId` (édition volume)
- `POST   /api/manga/:id/auto-covers` (régénération covers)

Routes restant publiques (`ROLE_USER`) :
- `GET    /api/manga` (liste/recherche)
- `GET    /api/manga/:id` (détail)
- `GET    /api/manga/external` (recherche Google Books)
- `GET    /api/manga/volume-search` (recherche volumes externes)

Note importante : l'ajout d'un manga à sa collection (`POST /api/collection`) reste public — c'est un acte user-scoped, pas une modification du catalogue.

Côté front, encapsuler la visibilité des contrôles dans un composable :
```ts
// front/src/composables/useIsAdmin.ts
export function useIsAdmin() {
  const auth = useAuthStore();
  return computed(() => auth.user?.role === 'ROLE_ADMIN');
}
```

Utilisé dans `MangaDetailPage.vue` et `AddMangaPage.vue` pour `v-if`.

**Tests**

Functional `back/tests/Functional/Manga/MangaControllerTest.php` :
- Adapter les tests POST/PATCH existants pour utiliser un client admin.
- Ajouter `testPostMangaReturns403ForRegularUser`, `testPatchMangaReturns403ForRegularUser`, `testAddVolumeReturns403ForRegularUser`.
- Vérifier que GET et external search marchent toujours pour un user normal.

**Verify**

```bash
docker compose exec back vendor/bin/phpunit --filter MangaControllerTest
```

Tous verts, y compris les nouveaux 403.

---

#### Task 22 : Configuration SMTP Brevo + guide Railway

Mettre en place un provider SMTP gratuit (Brevo, 300 emails/jour) et documenter sa configuration sur Railway. Aucun code applicatif ne change : tout passe par `MAILER_DSN`.

**Skills and docs to load :**
- `.claude/CLAUDE.md` — Docker gotchas, Railway notes
- `/use-railway` — configuration Railway, variables d'env, services

**Files :**
- Modify `back/.env` — commentaires sur les providers supportés
- Modify `back/.env.test` — garder Mailpit/null
- Create `docs/smtp-setup.md` — guide pas à pas Brevo + alternatives
- Create `docs/railway-deploy.md` — guide complet de déploiement Railway incluant SMTP, DB, env vars critiques
- Modify `README.md` — section « Production : SMTP & Railway » qui pointe vers `docs/smtp-setup.md` et `docs/railway-deploy.md`

**Implementation**

Aucun changement de code applicatif. Le projet utilise déjà Symfony Mailer (cf. `back/config/packages/mailer.yaml` lit `%env(MAILER_DSN)%`). Il suffit de changer la variable d'env selon l'environnement.

`docs/smtp-setup.md` couvre :

1. **Création du compte Brevo**
   - URL : https://www.brevo.com/ (signup gratuit, aucune carte bancaire)
   - Aller dans **Senders, Domains & Dedicated IPs** → ajouter un sender email `noreply@<ton-domaine>` ou utiliser l'adresse de signup
   - Aller dans **SMTP & API** → onglet **SMTP** → copier le **SMTP server** (`smtp-relay.brevo.com:587`), le **login** (email du compte) et la **master password** (à générer)

2. **Construction du DSN Symfony**
   ```
   MAILER_DSN=smtp://USER:PASSWORD@smtp-relay.brevo.com:587?encryption=tls&auth_mode=login
   ```
   - `USER` = l'email de login Brevo (URL-encodé : `%40` au lieu de `@`)
   - `PASSWORD` = la master password générée
   - Encoder tout caractère spécial dans password avec `urlencode`

3. **From address** : configurer dans `back/config/packages/mailer.yaml` un `envelope.sender` global pointant sur le sender vérifié Brevo, sinon les emails partent en spam. Ou utiliser une variable `MAILER_FROM` lue dans les listeners.

4. **Alternatives documentées** :
   - **Resend** : `MAILER_DSN=resend+smtp://API_KEY@default` (paquet `symfony/resend-mailer` à installer), 100 emails/jour, plus moderne mais demande la vérification d'un domaine via DNS.
   - **MailerSend** : `MAILER_DSN=mailersend+smtp://API_KEY@default`, 3000/mois.
   - **Mailpit (dev uniquement)** : `MAILER_DSN=smtp://mailer:1025` — déjà dans `docker-compose.yml`, ne pas utiliser en prod.

5. **Test rapide**
   ```bash
   docker compose exec back php bin/console messenger:setup-transports
   docker compose exec back php -r "require 'vendor/autoload.php'; /* envoyer un email via Mailer */"
   ```
   Ou plus simple : déclencher un `app:bootstrap-admin` et vérifier qu'un email part bien (le welcome admin sera envoyé en task 12).

`docs/railway-deploy.md` couvre :

1. **Pré-requis** : compte Railway, CLI installé (`railway login`), repo connecté.

2. **Création du projet Railway**
   - `railway init` à la racine du repo, ou via UI Railway.
   - Ajouter 3 services : `back` (Dockerfile `back/Dockerfile`), `front` (Dockerfile `front/Dockerfile`), `worker` (Dockerfile `worker/Dockerfile`).
   - Ajouter 1 plugin Postgres officiel.

3. **Variables d'environnement critiques par service**

   Pour `back` :
   | Variable | Valeur | Notes |
   |---|---|---|
   | `APP_ENV` | `prod` | |
   | `APP_SECRET` | `<random 32 hex>` | `openssl rand -hex 32` |
   | `DATABASE_URL` | `${{Postgres.DATABASE_URL}}` | Référence dynamique Railway |
   | `JWT_SECRET_KEY` | `%kernel.project_dir%/config/jwt/private.pem` | Généré au build |
   | `JWT_PUBLIC_KEY` | `%kernel.project_dir%/config/jwt/public.pem` | Idem |
   | `JWT_PASSPHRASE` | `<random>` | Secret |
   | `GATE_PASSWORD` | `<password fort admin>` | Pour le second factor admin |
   | `MAILER_DSN` | `smtp://USER:PASS@smtp-relay.brevo.com:587?encryption=tls&auth_mode=login` | Brevo |
   | `APP_FRONT_URL` | `https://<ton-domaine-front>` | Pour les liens dans les emails |
   | `MONITOR_USER` / `MONITOR_PASSWORD` | `<random>` | Messenger monitor |
   | `MESSENGER_TRANSPORT_DSN` | `doctrine://default` | Réutilise la DB |
   | `PORT` | (auto Railway) | Lu par le Caddyfile |
   | `SERVER_NAME` | **NE PAS SET** en prod | Cf. CLAUDE.md docker gotcha 1 |

   Pour `worker` :
   - Toutes les mêmes que `back` (le worker partage le kernel).

   Pour `front` :
   | Variable | Valeur |
   |---|---|
   | `BACKEND_URL` | `https://<url-railway-back>` |
   | `VITE_API_URL` | `https://<url-railway-back>/api` |

4. **Domaines custom**
   - Sur le service `back`, dans **Settings → Networking → Custom Domain**, attacher `api.ziggytheque.com` (ou subdomain Railway par défaut).
   - Idem pour le `front`.

5. **Première mise en service après déploiement** (la séquence DOIT être respectée)
   ```bash
   # 1. Migrations v1 (tables users + FK nullable)
   railway run --service back php bin/console doctrine:migrations:migrate --no-interaction

   # 2. Backfill admin
   railway run --service back php bin/console app:bootstrap-admin admin@toi.com "MotDePasseAdminFort!"

   # 3. Migrations v2 (NOT NULL)
   railway run --service back php bin/console doctrine:migrations:migrate --no-interaction

   # 4. Validation
   railway run --service back php bin/console doctrine:schema:validate
   ```

6. **Pièges Railway connus**
   - **FrankenPHP HTTPS** : ne PAS définir `SERVER_NAME` en prod (cf. CLAUDE.md). Le Caddyfile bind `:{$PORT:80}`.
   - **JWT keys** : générer au build via une étape Dockerfile ou via un init container ; sinon `lexik:jwt:generate-keypair` au premier déploiement.
   - **Migrations** : ne sont PAS automatiques. Toujours run manuellement (ou ajouter un init container `worker` qui les exécute avant de démarrer).
   - **PORT** : Railway injecte la variable, ne pas la hardcoder.

**Verify**

Pas de test automatique pour cette task. Verif manuelle :

```bash
# En local avec Brevo configuré dans back/.env.local :
docker compose exec back php bin/console app:bootstrap-admin admin@brevo-test.local "test1234!"
# Vérifier dans la dashboard Brevo (onglet "Statistics → Transactional") qu'un email a bien été émis.

# Sur Railway :
railway run --service back php bin/console doctrine:schema:validate
# → "The mapping information is in sync with the database schema."
```

---

#### Task 23 : Documentation `README.md`

Documenter la nouvelle procédure de mise en service en local et pointer vers les guides SMTP et Railway.

**Files :**
- Modify `README.md`
- Modify `.claude/CLAUDE.md` — sections Auth, Endpoints, Bounded Contexts (ajout Admin/, modifications)

**Implementation**

Dans `README.md`, ajouter une section **« Mise à jour vers user management — deux étapes »** :

**Étape 1 — Déploiement de PR A (#97)**
1. Merge de PR A → Railway redeploy automatique → migration v1 (nullable) joue toute seule.
2. Manuel : `railway run --service back php bin/console app:bootstrap-admin <email> <password>`. Crée le compte admin et assigne toutes les lignes orphelines (`owner_id IS NULL`) à cet admin.
3. Vérif : `railway run --service back php bin/console doctrine:schema:validate` → vert.
4. Vérif manuelle : se logger côté front avec le mail/password admin, vérifier que la collection préexistante est bien visible.

**Étape 2 — Déploiement de PR B (follow-up)**
1. Ouvrir la PR B (`claude/lock-user-owner-not-null` ou nom équivalent) qui contient uniquement Task 4 du plan.
2. Merge → Railway redeploy → migration v2 (NOT NULL) joue automatiquement.
3. Vérif : `railway run --service back php bin/console doctrine:schema:validate` → vert.

**Nouveaux env communs aux deux étapes** : `APP_FRONT_URL`, `MAILER_DSN` (Brevo en prod, Mailpit en dev — voir `docs/smtp-setup.md`).

Ajouter une section **« Déploiement Railway »** courte qui pointe vers `docs/railway-deploy.md`.

Dans `CLAUDE.md`, mettre à jour :
- Section **Auth** : login email/mdp + second factor admin via gate password (au lieu de single password).
- Section **Bounded Contexts** : ajout du module `Admin/`, modifications dans `Auth/`, `Notification/`.
- Section **API Endpoints** : nouveaux endpoints `/api/auth/login|register|verify-email|request-reset|reset-password`, `/api/admin/users`, `/api/me/notifications`. Marquer `POST/PATCH /api/manga*` comme admin-only.
- Section **Stack** : ajouter Brevo comme provider SMTP recommandé.

**Verify**

Lecture humaine. Pas de test automatique pour cette task.

---

#### Task 24 : Final lint, test, et boucle de revue **(scope PR A uniquement)**.

PR B aura sa propre boucle finale au moment de son ouverture (Task 4 du plan + lint/test/review équivalent).

Once finished, run lint and test for the affected sub-directories, dump the git diff in a tmp file with `git diff HEAD > /tmp/last-review.diff`, then spawn a `file-reviewer` subagent with the prompt: *"Review the diff at /tmp/last-review.diff. Invoke the `code-review` skill, follow its 'Load thematic rules by file type' step using the diff's file paths, and write structured findings."*
Fix every finding, then redo all three steps. Repeat until lint passes, tests pass, and the subagent returns no findings (max 5 iterations).
