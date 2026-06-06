# PWA iPhone — Ziggytheque installable en application native

## TL;DR

> [!NOTE]
> L'application Ziggytheque va devenir installable sur iPhone en tant que **Progressive Web App (PWA)**, sans nouveau projet, sans Swift. L'approche est entièrement frontale : on ajoute `vite-plugin-pwa` pour câbler un Web App Manifest et un service worker Workbox, on enrichit `index.html` avec les meta tags spécifiques à iOS Safari, et on génère les icônes et écrans de démarrage aux dimensions exactes requises par chaque iPhone.
>
> La stratégie de cache du service worker est volontairement conservatrice : les assets statiques (JS/CSS hachés, icônes) sont mis en cache de manière agressive, mais **tous les appels `/api/`** passent en mode `NetworkOnly` — le JWT est dans `sessionStorage` et les données seraient trompeuses si elles étaient servies depuis un cache. L'expérience hors-ligne se résume donc à : l'app shell se charge, puis un bandeau "hors ligne" s'affiche. Simple, honnête, sans fausse promesse.
>
> Les **push notifications Web** (iOS 16.4+) sont explicitement hors périmètre de ce plan — elles nécessitent des clés VAPID, un endpoint Symfony de stockage des souscriptions, et un listener d'événements domaine ; c'est un PR complet à part entière. Ce plan se concentre sur l'installation et l'expérience standalone, qui constituent la valeur principale pour un usage personnel.

---

## Implementation

### État actuel

```
front/
├── public/
│   ├── icon-dark.png     (18 KB — icône thème sombre)
│   ├── icon-light.png    (15 KB — icône thème clair, source pour les assets PWA)
│   ├── logo-dark.png     (110 KB)
│   ├── logo-light.png    (104 KB)
│   └── icons.svg
├── index.html            ← minimal : pas de meta tags PWA, pas de manifest
└── vite.config.ts        ← pas de plugin PWA
```

Résultat actuel sur iPhone : "Ajouter à l'écran d'accueil" produit un signet basique, sans plein écran, sans icône personnalisée, sans splash screen.

### État cible

```
front/
├── public/
│   ├── icon-light.png                     (source inchangée)
│   ├── apple-touch-icon-180x180.png       ← nouvelle (iOS home screen icon)
│   ├── pwa-64x64.png                      ← nouvelle (favicon)
│   ├── pwa-192x192.png                    ← nouvelle (manifest icon)
│   ├── pwa-512x512.png                    ← nouvelle (manifest icon)
│   ├── maskable-icon-512x512.png          ← nouvelle (Android adaptive icon)
│   └── splash/
│       ├── splash-750x1334.png            ← iPhone SE / 8
│       ├── splash-1125x2436.png           ← iPhone X/XS/11 Pro / 12-13 mini
│       ├── splash-828x1792.png            ← iPhone XR / 11
│       ├── splash-1170x2532.png           ← iPhone 12 / 13 / 14
│       ├── splash-1179x2556.png           ← iPhone 14 Pro / 15 Pro / 16
│       ├── splash-1290x2796.png           ← iPhone 14 Plus / 15 / 15 Pro Max / 16 Pro Max
│       └── splash-1080x2340.png           ← iPhone 12 mini / 13 mini
├── scripts/
│   └── generate-splash-screens.mjs       ← nouveau script Node
├── pwa-assets.config.ts                  ← config @vite-pwa/assets-generator
├── index.html                            ← enrichi : meta iOS + liens splash
└── vite.config.ts                        ← plugin VitePWA ajouté
```

### Stratégie de cache Workbox

| Pattern d'URL | Stratégie | Raison |
|---|---|---|
| `/api/**` | `NetworkOnly` | Auth-gated, données périmées trompeuses |
| `/proxy/**` | `NetworkOnly` | Proxy d'images externes, non sûr à cacher |
| `/.well-known/mercure` | `NetworkOnly` | SSE — non cacheable |
| `*.js`, `*.css` (hachés) | `CacheFirst` (30 j) | Noms immutables, cache agressif idéal |
| `*.png`, `*.svg`, `*.ico` | `CacheFirst` (7 j) | Rarement modifié |
| navigation (HTML) | `NetworkFirst` | Garantit que le dernier SW s'active |

### Contraintes iOS Safari — récapitulatif

| Contrainte | Valeur requise |
|---|---|
| Meta `apple-mobile-web-app-capable` | `yes` |
| Meta `apple-mobile-web-app-status-bar-style` | `black-translucent` (barre d'état transparente, overlay) |
| `viewport-fit` | `cover` (obligatoire pour `env(safe-area-inset-*)`) |
| `apple-touch-icon` | PNG 180×180, pas d'alpha (fond blanc sinon) |
| Splash screens | `<link rel="apple-touch-startup-image">` par taille de device |
| Safe area (encoche / home indicator) | `env(safe-area-inset-top/bottom)` sur le shell |

**Note sur `black-translucent`** : la barre de statut iOS devient transparente et se superpose au contenu. Sans `padding-top: env(safe-area-inset-top)` sur le header, le contenu passe *sous* la barre de statut. La Task 4 traite ce point.

**Note sur `sessionStorage` et le cycle de vie de la PWA** : en mode standalone iOS, chaque "session" de la PWA (fermeture complète → relancement) vide la `sessionStorage`. L'utilisateur devra ressaisir le mot de passe gate à chaque cold start. C'est le comportement attendu par design de l'auth.

### Arborescence des changements (vue d'ensemble)

```
Task 1  front/package.json                 → +3 devDeps
        front/pwa-assets.config.ts         → créé
        front/scripts/generate-splash-screens.mjs → créé
        front/public/*                     → icônes + splash générés

Task 2  front/vite.config.ts               → plugin VitePWA
        (manifeste + Workbox inline)

Task 3  front/index.html                   → meta tags iOS + splash links

Task 4  front/src/components/organisms/MainLayout.vue → safe-area header + content
        front/src/assets/main.css          → utilitaire safe-area

Task 5  front/nginx.conf.template          → MIME type webmanifest + no-cache SW
```

---

### Tasks

- Task 1 : Dépendances + génération des assets PWA (icônes et splash screens)
- Task 2 : Configuration de `vite-plugin-pwa` (manifest + Workbox)
- Task 3 : Enrichissement de `index.html` (meta tags iOS + liens splash)
- Task 4 : Safe-area dans `MainLayout.vue` (encoche + home indicator)
- Task 5 : nginx — MIME type `webmanifest` + headers no-cache pour SW
- Task 6 : Boucle finale lint, test et review

---

#### Task 1 : Dépendances + génération des assets PWA

Installer les outils de génération, créer les configs, et produire tous les assets statiques (icônes et splash screens) dans `front/public/`.

**Skills and docs to load:**
- `.claude/CLAUDE.md` — conventions projet (scripts npm, nommage)

**Files:**
- Modify `front/package.json`
- Create `front/pwa-assets.config.ts`
- Create `front/scripts/generate-splash-screens.mjs`
- Generate (assets produits, non commités manuellement) : `front/public/apple-touch-icon-180x180.png`, `front/public/pwa-{64,192,512}x{64,192,512}.png`, `front/public/maskable-icon-512x512.png`, `front/public/splash/splash-*.png`

**Implementation**

Ajouter trois devDependencies dans `front/package.json` :
```json
"@vite-pwa/assets-generator": "^0.2.6",
"vite-plugin-pwa": "^0.21.0",
"sharp": "^0.34.0"
```

Ajouter deux scripts :
```json
"pwa:icons": "pwa-assets-generator --config pwa-assets.config.ts",
"pwa:splash": "node scripts/generate-splash-screens.mjs"
```

Créer `front/pwa-assets.config.ts` :
```ts
import { defineConfig, minimal2023Preset } from '@vite-pwa/assets-generator/config'

export default defineConfig({
  headLinkOptions: { preset: '2023' },
  preset: {
    ...minimal2023Preset,
    apple: { sizes: [180] },
  },
  images: ['public/icon-light.png'],
})
```

Cela génère via `npm run pwa:icons` (depuis `front/`) :
- `public/apple-touch-icon-180x180.png`
- `public/pwa-64x64.png`, `pwa-192x192.png`, `pwa-512x512.png`
- `public/maskable-icon-512x512.png`

Créer `front/scripts/generate-splash-screens.mjs` — script Node.js utilisant `sharp` pour générer les splash screens iOS. Chaque splash est un fond uni `#1e1b1a` (couleur `ziggy-dark` base-100) avec l'icône `icon-light.png` centrée et redimensionnée à 20 % de la largeur.

```js
// front/scripts/generate-splash-screens.mjs
import sharp from 'sharp'
import { mkdirSync } from 'node:fs'
import { join, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'

const directory = dirname(fileURLToPath(import.meta.url))
const projectRoot = join(directory, '..')
const splashDirectory = join(projectRoot, 'public', 'splash')
const iconPath = join(projectRoot, 'public', 'icon-light.png')

mkdirSync(splashDirectory, { recursive: true })

const BACKGROUND_COLOR = { r: 30, g: 27, b: 26, alpha: 1 }

const SIZES = [
  { width: 750,  height: 1334 },   // iPhone SE / 8
  { width: 828,  height: 1792 },   // iPhone XR / 11
  { width: 1080, height: 2340 },   // iPhone 12-13 mini
  { width: 1125, height: 2436 },   // iPhone X / XS / 11 Pro
  { width: 1170, height: 2532 },   // iPhone 12 / 13 / 14
  { width: 1179, height: 2556 },   // iPhone 14 Pro / 15 Pro / 16
  { width: 1290, height: 2796 },   // iPhone 14 Plus / 15 / 15 Pro Max / 16 Pro Max
]

for (const { width, height } of SIZES) {
  const iconSize = Math.round(width * 0.22)
  const iconLeft = Math.round((width - iconSize) / 2)
  const iconTop = Math.round((height - iconSize) / 2)

  const resizedIcon = await sharp(iconPath)
    .resize(iconSize, iconSize, { fit: 'contain', background: BACKGROUND_COLOR })
    .toBuffer()

  await sharp({
    create: { width, height, channels: 4, background: BACKGROUND_COLOR },
  })
    .composite([{ input: resizedIcon, left: iconLeft, top: iconTop }])
    .png()
    .toFile(join(splashDirectory, `splash-${width}x${height}.png`))

  console.log(`Generated splash-${width}x${height}.png`)
}
```

Committer les assets générés dans `public/` afin qu'ils soient inclus dans le build Docker (le `npm run build` ne régénère pas les splash screens — ils sont statiques).

**Verify**

```bash
cd front
npm install
npm run pwa:icons   # génère les icônes dans public/
npm run pwa:splash  # génère public/splash/*.png
ls public/apple-touch-icon-180x180.png public/pwa-192x192.png public/pwa-512x512.png
ls public/splash/splash-1170x2532.png  # vérifie une taille clé
```

Passe si tous les fichiers existent et ont une taille > 10 KB (les splash screens sont de l'ordre de 50–200 KB selon la résolution).

---

#### Task 2 : Configuration de `vite-plugin-pwa`

Câbler le plugin PWA dans Vite pour générer le manifest `manifest.webmanifest` et le service worker Workbox lors du build de production.

**Skills and docs to load:**
- `.claude/CLAUDE.md` — conventions projet

**Files:**
- Modify `front/vite.config.ts`

**Implementation**

Importer `VitePWA` et l'ajouter au tableau `plugins`. Le plugin **ne s'active pas en dev** (`devOptions.enabled: false`) pour éviter les conflits avec le proxy Vite.

```ts
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { VitePWA } from 'vite-plugin-pwa'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig({
  plugins: [
    vue(),
    tailwindcss(),
    VitePWA({
      registerType: 'autoUpdate',
      injectRegister: 'auto',
      includeAssets: [
        'apple-touch-icon-180x180.png',
        'icons.svg',
        'splash/**/*.png',
      ],
      manifest: {
        name: 'Ziggytheque',
        short_name: 'Ziggytheque',
        description: 'Ma collection de mangas',
        theme_color: '#1e1b1a',
        background_color: '#1e1b1a',
        display: 'standalone',
        orientation: 'portrait',
        scope: '/',
        start_url: '/',
        lang: 'fr',
        icons: [
          {
            src: '/pwa-64x64.png',
            sizes: '64x64',
            type: 'image/png',
          },
          {
            src: '/pwa-192x192.png',
            sizes: '192x192',
            type: 'image/png',
          },
          {
            src: '/pwa-512x512.png',
            sizes: '512x512',
            type: 'image/png',
          },
          {
            src: '/maskable-icon-512x512.png',
            sizes: '512x512',
            type: 'image/png',
            purpose: 'maskable',
          },
        ],
      },
      workbox: {
        globPatterns: ['**/*.{js,css,html,ico,png,svg,woff,woff2}'],
        navigateFallback: '/index.html',
        cleanupOutdatedCaches: true,
        runtimeCaching: [
          {
            // Tous les appels API : jamais en cache (auth-gated)
            urlPattern: ({ url }) => url.pathname.startsWith('/api/'),
            handler: 'NetworkOnly',
          },
          {
            // Proxy covers (images externes)
            urlPattern: ({ url }) => url.pathname.startsWith('/proxy/'),
            handler: 'NetworkOnly',
          },
          {
            // SSE Mercure
            urlPattern: ({ url }) => url.pathname.startsWith('/.well-known/mercure'),
            handler: 'NetworkOnly',
          },
        ],
      },
      devOptions: {
        enabled: false,
      },
    }),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: process.env.BACKEND_URL ?? 'http://localhost:8000',
        changeOrigin: true,
      },
      '/proxy': {
        target: process.env.BACKEND_URL ?? 'http://localhost:8000',
        changeOrigin: true,
      },
      '/.well-known/mercure': {
        target: process.env.BACKEND_URL ?? 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
})
```

**Verify**

```bash
cd front
npm run build
ls dist/sw.js dist/manifest.webmanifest
# sw.js et manifest.webmanifest doivent être présents dans dist/
```

---

#### Task 3 : Enrichissement de `index.html`

Ajouter les meta tags iOS obligatoires et les liens vers les splash screens.

**Skills and docs to load:**
- `.claude/CLAUDE.md` — conventions projet

**Files:**
- Modify `front/index.html`

**Implementation**

Remplacer le contenu actuel de `<head>` par la version enrichie :

```html
<!doctype html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />

    <!-- ── Viewport ──────────────────────────────────────────────────── -->
    <!-- viewport-fit=cover est obligatoire pour env(safe-area-inset-*) -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />

    <!-- ── PWA / iOS Safari ──────────────────────────────────────────── -->
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <!-- black-translucent : barre de statut transparente en overlay -->
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
    <meta name="apple-mobile-web-app-title" content="Ziggytheque" />
    <meta name="mobile-web-app-capable" content="yes" />

    <!-- ── Icônes ────────────────────────────────────────────────────── -->
    <link rel="icon" type="image/png" href="/icon-dark.png" media="(prefers-color-scheme: dark)" />
    <link rel="icon" type="image/png" href="/icon-light.png" media="(prefers-color-scheme: light)" />
    <link rel="apple-touch-icon" href="/apple-touch-icon-180x180.png" />

    <!-- ── Splash screens iOS (portrait uniquement) ───────────────────
         Générés par : npm run pwa:splash (scripts/generate-splash-screens.mjs)
         Taille = pixels physiques de l'écran.
    ──────────────────────────────────────────────────────────────────── -->
    <!-- iPhone SE (2020/2022) / iPhone 8 -->
    <link
      rel="apple-touch-startup-image"
      media="(device-width: 375px) and (device-height: 667px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)"
      href="/splash/splash-750x1334.png"
    />
    <!-- iPhone XR / 11 -->
    <link
      rel="apple-touch-startup-image"
      media="(device-width: 414px) and (device-height: 896px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)"
      href="/splash/splash-828x1792.png"
    />
    <!-- iPhone 12 mini / 13 mini -->
    <link
      rel="apple-touch-startup-image"
      media="(device-width: 360px) and (device-height: 780px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)"
      href="/splash/splash-1080x2340.png"
    />
    <!-- iPhone X / XS / 11 Pro -->
    <link
      rel="apple-touch-startup-image"
      media="(device-width: 375px) and (device-height: 812px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)"
      href="/splash/splash-1125x2436.png"
    />
    <!-- iPhone 12 / 13 / 14 -->
    <link
      rel="apple-touch-startup-image"
      media="(device-width: 390px) and (device-height: 844px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)"
      href="/splash/splash-1170x2532.png"
    />
    <!-- iPhone 14 Pro / 15 Pro / 16 -->
    <link
      rel="apple-touch-startup-image"
      media="(device-width: 393px) and (device-height: 852px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)"
      href="/splash/splash-1179x2556.png"
    />
    <!-- iPhone 14 Plus / 15 / 15 Plus / 15 Pro Max / 16 Plus / 16 Pro Max -->
    <link
      rel="apple-touch-startup-image"
      media="(device-width: 430px) and (device-height: 932px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)"
      href="/splash/splash-1290x2796.png"
    />

    <title>Ziggytheque</title>
  </head>
  <body>
    <div id="app"></div>
    <script type="module" src="/src/main.ts"></script>
  </body>
</html>
```

Note : `vite-plugin-pwa` injecte automatiquement `<link rel="manifest">` et le script d'enregistrement du SW lors du build. Ne pas les ajouter manuellement.

---

#### Task 4 : Safe-area dans `MainLayout.vue`

Adapter le header mobile et le contenu principal pour respecter l'encoche (Dynamic Island / notch) en haut et le home indicator en bas, uniquement en mode standalone.

**Skills and docs to load:**
- `vue-best-practices` — conventions SFC, pas de logique dans le template
- `.claude/CLAUDE.md` — conventions projet

**Files:**
- Modify `front/src/assets/main.css`
- Modify `front/src/components/organisms/MainLayout.vue`

**Implementation**

**1. Utilitaires CSS dans `main.css`**

Ajouter à la fin de `front/src/assets/main.css` :

```css
/* ── PWA safe-area (iOS standalone mode) ──────────────────────────────── */
/* Actif uniquement quand l'app est lancée depuis l'écran d'accueil. */
/* viewport-fit=cover dans index.html est requis pour que ces valeurs     */
/* soient non nulles en mode standalone.                                   */

@media (display-mode: standalone) {
  .pwa-safe-top {
    padding-top: env(safe-area-inset-top, 0px);
  }

  .pwa-safe-bottom {
    padding-bottom: env(safe-area-inset-bottom, 0px);
  }

  /* Le header mobile est fixed h-14 (3.5rem).                            */
  /* En standalone avec safe-area, il doit s'étendre vers le haut.        */
  /* Le contenu (pt-14) doit compenser la hauteur totale du header.       */
  .pwa-header-height {
    height: calc(3.5rem + env(safe-area-inset-top, 0px));
  }

  .pwa-content-top {
    padding-top: calc(3.5rem + env(safe-area-inset-top, 0px));
  }
}
```

**2. Modifications dans `MainLayout.vue`**

La structure actuelle du `<header>` mobile (ligne 65) :
```html
<header class="lg:hidden fixed top-0 inset-x-0 z-30 h-14 bg-base-100/80 ... flex items-end px-4 gap-3">
```

Changements :
- Remplacer `h-14` par `pwa-header-height` (classe CSS custom) — en mode non-standalone, `pwa-header-height` n'a aucun effet (la règle `@media (display-mode: standalone)` n'est pas active), mais `h-14` disparaît. Pour conserver `h-14` hors standalone, garder les deux : `h-14 pwa-header-height`.
- Ajouter `items-end` au lieu de `items-center` pour que les boutons s'alignent en bas du header étendu (au-dessus de la safe area).
- Ajouter la classe `pwa-safe-top` pour le padding.

```html
<header class="lg:hidden fixed top-0 inset-x-0 z-30 h-14 pwa-header-height bg-base-100/80 backdrop-blur-md border-b border-base-200 flex items-end pb-2 px-4 gap-3 pwa-safe-top">
```

La `<main>` (ligne 91) :
```html
<main class="flex-1 bg-base-200 min-h-screen pt-14 lg:pt-0">
```
Becomes :
```html
<main class="flex-1 bg-base-200 min-h-screen pt-14 pwa-content-top lg:pt-0 lg:pwa-content-top-none">
```

Wait — Tailwind ne peut pas annuler une classe custom avec `lg:`. La solution propre est d'utiliser une classe conditionnelle uniquement sur mobile via CSS :

```css
/* Dans la règle @media (display-mode: standalone) déjà définie */
.pwa-content-top {
  padding-top: calc(3.5rem + env(safe-area-inset-top, 0px));
}

/* Sur desktop, le drawer est toujours ouvert, pas besoin du padding */
@media (display-mode: standalone) and (min-width: 1024px) {
  .pwa-content-top {
    padding-top: 0;
  }
}
```

Pour la `<main>` : ajouter `pwa-content-top` à côté de `pt-14`. La règle `pt-14` s'applique toujours ; `pwa-content-top` la remplace en mode standalone (car `pwa-content-top` est définie dans `@media (display-mode: standalone)` qui a spécificité supérieure à la règle Tailwind de base).

```html
<main class="flex-1 bg-base-200 min-h-screen pt-14 pwa-content-top lg:pt-0">
```

Pour le bas d'écran (home indicator) : le `.drawer-content` ou l'`<aside>` sidebar peut recevoir `pwa-safe-bottom` si du contenu se trouve proche du bord. À évaluer visuellement : si la dernière entrée de nav est coupée par le home indicator, ajouter `pwa-safe-bottom` au `<nav>` ou à l'`<aside>`.

---

#### Task 5 : nginx — MIME type `webmanifest` + headers no-cache pour SW

Assurer que nginx sert le manifest avec le bon Content-Type et que le service worker n'est jamais mis en cache côté client (sinon les mises à jour ne sont pas détectées).

**Skills and docs to load:**
- `.claude/CLAUDE.md` — conventions infra Railway (nginx.conf.template)
- `use-railway` — contexte déploiement nginx Railway

**Files:**
- Modify `front/nginx.conf.template`

**Implementation**

Ajouter dans le bloc `server {}` de `front/nginx.conf.template`, avant le `location /` existant :

```nginx
# ── PWA : MIME type pour le manifest ─────────────────────────────────────
# nginx:alpine n'inclut pas application/manifest+json par défaut.
types {
    application/manifest+json  webmanifest;
}

# ── PWA : pas de cache navigateur pour le SW et le manifest ──────────────
# Si ces fichiers étaient mis en cache, les mises à jour seraient bloquées.
location = /sw.js {
    add_header Cache-Control "no-cache, no-store, must-revalidate";
    add_header Pragma "no-cache";
    expires 0;
    try_files $uri =404;
}

location = /manifest.webmanifest {
    add_header Cache-Control "no-cache, no-store, must-revalidate";
    expires 0;
    try_files $uri =404;
}
```

Le bloc `types {}` local dans nginx override le type MIME global uniquement pour ce `server {}` block — il ne casse pas les autres MIME types déjà configurés par nginx:alpine.

**Verify**

Après déploiement (ou build Docker local) :
```bash
# En local via Docker (si disponible)
docker build -t ziggy-front front/
docker run -e BACKEND_URL=http://localhost:8000 -p 8080:80 ziggy-front
curl -I http://localhost:8080/manifest.webmanifest | grep -i content-type
# Attendu : Content-Type: application/manifest+json

curl -I http://localhost:8080/sw.js | grep -i cache-control
# Attendu : Cache-Control: no-cache, no-store, must-revalidate
```

---

#### Task 6 : Boucle finale lint, test et review

**Task 6: Final lint, test, and review loop.**
Once finished, run lint and test for the affected sub-directories, dump the git diff in a tmp file with `git diff HEAD > /tmp/last-review.diff`, then spawn a `file-reviewer` subagent with the prompt: *"Review the diff at /tmp/last-review.diff. Invoke the `code-review` skill, follow its 'Load thematic rules by file type' step using the diff's file paths, and write structured findings."*
Fix every finding, then redo all three steps. Repeat until lint passes, tests pass, and the subagent returns no findings (max 5 iterations).

Commandes spécifiques à ce PR :
```bash
cd front && npm run qa        # type-check + lint + vitest
npm run build                 # vérifie que le build prod ne casse pas
ls dist/sw.js dist/manifest.webmanifest   # assets PWA présents
```
