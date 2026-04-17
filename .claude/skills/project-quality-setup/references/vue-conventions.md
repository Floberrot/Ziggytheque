# Vue 3 / TypeScript — Conventions

## DaisyUI — required for all Vue 3 projects

Every Vue 3 project uses **Tailwind CSS + DaisyUI**. Always install both. Always include a theme switch.

### Installation

```bash
npm install -D tailwindcss @tailwindcss/vite daisyui@latest
```

**vite.config.ts** — use the Tailwind Vite plugin (no PostCSS config needed):
```ts
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [vue(), tailwindcss()],
})
```

**src/assets/main.css** (or `app.css`):
```css
@import "tailwindcss";
@plugin "daisyui";
```

**No `tailwind.config.js` needed** when using the Vite plugin — config lives in CSS via `@theme` if overrides are needed.

### Theme switch — mandatory in every project

Every project ships a `BaseThemeSwitch` atom and a `useThemeStore`. This is non-negotiable.

```ts
// src/stores/useThemeStore.ts
import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useThemeStore = defineStore('theme', () => {
  const theme = ref<string>(localStorage.getItem('theme') ?? 'light')

  const setTheme = (newTheme: string): void => {
    theme.value = newTheme
    localStorage.setItem('theme', newTheme)
    document.documentElement.setAttribute('data-theme', newTheme)
  }

  // apply saved theme on init
  setTheme(theme.value)

  return { theme, setTheme }
})
```

```vue
<!-- src/components/atoms/BaseThemeSwitch.vue -->
<script setup lang="ts">
import { useThemeStore } from '@/stores/useThemeStore'

const themeStore = useThemeStore()

const toggle = (): void => {
  themeStore.setTheme(themeStore.theme === 'dark' ? 'light' : 'dark')
}
</script>

<template>
  <label class="swap swap-rotate">
    <input type="checkbox" :checked="themeStore.theme === 'dark'" @change="toggle" />
    <!-- sun icon (shown in dark mode → click to go light) -->
    <svg class="swap-on h-6 w-6 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
      <path d="M5.64,17l-.71.71a1,1,0,0,0,0,1.41,1,1,0,0,0,1.41,0l.71-.71A1,1,0,0,0,5.64,17ZM5,12a1,1,0,0,0-1-1H3a1,1,0,0,0,0,2H4A1,1,0,0,0,5,12Zm7-7a1,1,0,0,0,1-1V3a1,1,0,0,0-2,0V4A1,1,0,0,0,12,5ZM5.64,7,4.93,6.34A1,1,0,1,0,3.51,7.76L4.22,8.46A1,1,0,0,0,5.64,7Zm12,.71.71-.71A1,1,0,1,0,16.93,5.64l-.71.71A1,1,0,0,0,17.66,7.66ZM19,11H18a1,1,0,0,0,0,2h1a1,1,0,0,0,0-2Zm-2,6a1,1,0,0,0-1.41,0l-.71.71a1,1,0,0,0,1.41,1.41l.71-.71A1,1,0,0,0,17,17ZM7,19a1,1,0,0,0,.71-.29l.71-.71a1,1,0,0,0-1.41-1.41L6.29,17.29A1,1,0,0,0,7,19ZM12,6a6,6,0,1,0,6,6A6,6,0,0,0,12,6Zm0,10a4,4,0,1,1,4-4A4,4,0,0,1,12,10Z"/>
    </svg>
    <!-- moon icon (shown in light mode → click to go dark) -->
    <svg class="swap-off h-6 w-6 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
      <path d="M21.64,13a1,1,0,0,0-1.05-.14,8.05,8.05,0,0,1-3.37.73A8.15,8.15,0,0,1,9.08,5.49a8.59,8.59,0,0,1,.25-2A1,1,0,0,0,8,2.36,10.14,10.14,0,1,0,22,14.05,1,1,0,0,0,21.64,13Zm-9.5,6.69A8.14,8.14,0,0,1,7.08,5.22v.27A10.15,10.15,0,0,0,17.22,15.63a9.79,9.79,0,0,0,2.1-.22A8.11,8.11,0,0,1,12.14,19.73Z"/>
    </svg>
  </label>
</template>
```

Mount `BaseThemeSwitch` in the main layout/navbar. Add `data-theme` to `<html>` in `index.html` as a fallback:
```html
<html lang="en" data-theme="light">
```

### DaisyUI component usage rules

- Always use DaisyUI semantic classes: `btn`, `btn-primary`, `card`, `input`, `modal`, etc.
- Never write custom button/form/card CSS when DaisyUI has a component for it
- Extend via Tailwind utility classes only when DaisyUI doesn't cover the case

### BaseButton with DaisyUI

```vue
<!-- atoms/BaseButton.vue -->
<script setup lang="ts">
defineProps<{
  label: string
  variant?: 'primary' | 'secondary' | 'accent' | 'ghost' | 'error' | 'warning' | 'success'
  size?: 'xs' | 'sm' | 'md' | 'lg'
  disabled?: boolean
  loading?: boolean
}>()
defineEmits<{ click: [event: MouseEvent] }>()
</script>

<template>
  <button
    :class="['btn', variant ? `btn-${variant}` : 'btn-primary', size ? `btn-${size}` : '']"
    :disabled="disabled || loading"
    @click="$emit('click', $event)"
  >
    <span v-if="loading" class="loading loading-spinner" />
    <span v-else>{{ label }}</span>
  </button>
</template>
```

---

## Core rules (always)

- `<script setup lang="ts">` — never Options API, never `export default {}`
- `defineProps<{}>()` with TypeScript generic syntax
- `defineEmits<{}>()` with typed event map
- `defineModel` for two-way binding
- Extract to a component whenever a piece of UI is reusable OR logically distinct — even one button

---

## Atomic Design — component levels

```
components/
├── atoms/       Base* — smallest indivisible elements
├── molecules/   Functional groups of atoms
├── organisms/   Self-contained sections, may read from store
├── templates/   Page skeleton / layout slots only
└── pages/       Route-bound, owns data fetching
```

### When to extract a new component

Extract whenever:
- The UI piece appears (or could appear) in more than one place
- The template block has its own clear responsibility
- The block has more than ~15 lines of template
- The block manages its own local state

Do not wait until something is actually reused — extract proactively if it's logically
distinct. A single icon with a tooltip is worth a component. A form field with label +
input + error is always a component.

### Atoms — `Base*` prefix

```vue
<!-- atoms/BaseButton.vue -->
<script setup lang="ts">
defineProps<{
  label: string
  variant?: 'primary' | 'secondary' | 'ghost' | 'danger'
  disabled?: boolean
  loading?: boolean
}>()
defineEmits<{ click: [event: MouseEvent] }>()
</script>

<template>
  <button
    :class="['btn', `btn--${variant ?? 'primary'}`]"
    :disabled="disabled || loading"
    @click="$emit('click', $event)"
  >
    <BaseSpinner v-if="loading" size="sm" />
    <span v-else>{{ label }}</span>
  </button>
</template>
```

Rules for atoms:
- Name: `Base*` prefix, PascalCase
- Props: primitive types only (`string`, `number`, `boolean`)
- Emits: generic events (`click`, `change`, `focus`)
- Zero domain knowledge, zero store access, zero API calls

### Molecules — functional group of atoms

```vue
<!-- molecules/SearchField.vue -->
<script setup lang="ts">
import BaseInput from '@/components/atoms/BaseInput.vue'
import BaseButton from '@/components/atoms/BaseButton.vue'

const model = defineModel<string>()
defineEmits<{ search: [query: string] }>()
</script>
<template>
  <div class="search-field">
    <BaseInput v-model="model" placeholder="Search…" />
    <BaseButton label="Search" @click="$emit('search', model ?? '')" />
  </div>
</template>
```

### Organisms — domain-aware, may read from store

```vue
<!-- organisms/ProductCard.vue -->
<script setup lang="ts">
import type { Product } from '@/types/product'
defineProps<{ product: Product }>()
defineEmits<{ addToCart: [productId: string] }>()
</script>
```

### Pages — own data fetching, compose templates + organisms

```vue
<!-- pages/ProductListPage.vue -->
<script setup lang="ts">
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import { fetchProducts, addToCart } from '@/api/product'

const queryClient = useQueryClient()
const { data: products, isPending } = useQuery({ queryKey: ['products'], queryFn: fetchProducts })
const { mutate } = useMutation({
  mutationFn: addToCart,
  onSuccess: () => queryClient.invalidateQueries({ queryKey: ['cart'] }),
})
</script>
```

---

## Naming conventions

| Type | Convention | Example |
|------|-----------|---------|
| Atom component | `Base*` + PascalCase | `BaseButton.vue`, `BaseInput.vue` |
| Molecule / Organism | PascalCase, descriptive | `SearchField.vue`, `ProductCard.vue` |
| Page | `*Page` suffix | `ProductListPage.vue` |
| Template | `*Template` suffix | `DashboardTemplate.vue` |
| Composable | `use*` prefix | `useCart.ts`, `useAuth.ts` |
| Store | `use*Store` | `useCartStore.ts` |
| API file | domain noun | `product.ts`, `order.ts` |

- All file/component usage: PascalCase in templates (`<ProductCard />`)
- No single-word component names
- Props: `camelCase` in script (Vue auto-converts to `kebab-case` in template)
- Events: `camelCase` in `defineEmits` → `@kebab-case` in template

---

## Data fetching — TanStack Query

Server state belongs to **TanStack Query**, never duplicated in Pinia.

```ts
// assets/api/order.ts — one file per domain
export const fetchOrder = async (id: string): Promise<OrderView> => {
  const res = await fetch(`/api/orders/${id}`)
  if (!res.ok) throw new Error('Failed to fetch order')
  return res.json()
}

export const placeOrder = async (payload: PlaceOrderDto): Promise<void> => {
  const res = await fetch('/api/orders', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  })
  if (!res.ok) throw new Error('Failed to place order')
}
```

Rules:
- `useQuery` for reads — never in `onMounted`
- `useMutation` for writes — always invalidate related queries on success
- Only Pages call `useQuery`/`useMutation` — organisms receive data via props
- `queryKey` is an array: `['orders']`, `['order', id]`

---

## State — Pinia (UI state only)

Pinia = UI state only (sidebar open, theme, auth token, cart count).
Server data = TanStack Query.

```ts
// stores/useUiStore.ts
export const useUiStore = defineStore('ui', () => {
  const sidebarOpen = ref(false)
  const toggle = () => { sidebarOpen.value = !sidebarOpen.value }
  return { sidebarOpen, toggle }
})
```

- One store per concern
- Only pages and organisms access stores
- Atoms and molecules never touch the store

---

## TypeScript strictness

```ts
// ✅ explicit types everywhere
const fetchOrder = async (id: string): Promise<OrderView> => { ... }

// ❌ no implicit any, no missing return types
const fetchOrder = async (id) => { ... }

// ✅ typed props
defineProps<{ product: Product; loading?: boolean }>()

// ❌ no untyped props
defineProps(['product', 'loading'])
```

ESLint rule `@typescript-eslint/no-explicit-any` is `error` — fix the type, never use `any`.

---

## Component extraction examples

```vue
<!-- ❌ Too much in one template — extract -->
<template>
  <div class="user-profile">
    <div class="avatar">
      <img :src="user.avatar" :alt="user.name" />
      <span v-if="user.isOnline" class="dot" />
    </div>
    <div class="info">
      <h2>{{ user.name }}</h2>
      <p>{{ user.bio }}</p>
    </div>
    <div class="actions">
      <button @click="follow">Follow</button>
      <button @click="message">Message</button>
    </div>
  </div>
</template>

<!-- ✅ Extracted — each piece is a component -->
<template>
  <div class="user-profile">
    <UserAvatar :user="user" />
    <UserInfo :user="user" />
    <UserActions @follow="follow" @message="message" />
  </div>
</template>
```
