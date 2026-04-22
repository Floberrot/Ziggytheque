# Design Tokens Reference

All design values are stored in `front/src/design/tokens.css` and DaisyUI theme definitions. Use CSS variables in components; never hardcode hex/rgb/px values.

## Color Variables

### Semantic (DaisyUI)

```css
/* Light & dark themes inherit these */
var(--color-primary)          /* Vermilion red: oklch(0.72 0.18 12) */
var(--color-primary-content)  /* Text on primary */
var(--color-secondary)        /* Aged gold: oklch(0.78 0.14 75) */
var(--color-accent)           /* Indigo-cyan: oklch(0.72 0.15 200) */
var(--color-success)          /* Jade: oklch(0.74 0.15 160) */
var(--color-warning)          /* Warm: oklch(0.80 0.14 75) */
var(--color-error)            /* Red: oklch(0.66 0.21 22) */
var(--color-info)             /* Blue: oklch(0.72 0.13 240) */

/* Backgrounds */
var(--color-base-100)         /* Page bg (deep ink in dark, light in light) */
var(--color-base-200)         /* Panel bg */
var(--color-base-300)         /* Border color */
var(--color-base-content)     /* Text color */
```

### Usage

```vue
<div :style="{ color: 'var(--color-primary)' }">Primary text</div>
<div class="bg-[color:var(--color-base-200)]">Panel</div>

<!-- Tailwind @apply -->
<style scoped>
.badge-custom {
  @apply px-2 py-1 rounded-full;
  background-color: var(--color-primary);
  color: var(--color-primary-content);
}
</style>
```

## Spacing & Radii

```css
var(--radius-xs)    /* 4px */
var(--radius-sm)    /* 8px */
var(--radius-md)    /* 12px — default for inputs */
var(--radius-lg)    /* 16px — buttons, cards */
var(--radius-xl)    /* 22px — large modals */
var(--radius-2xl)   /* 28px */
var(--radius-full)  /* 9999px — pills, circles */
```

### Usage in Tailwind

DaisyUI applies these automatically. In custom CSS:

```css
.my-card {
  border-radius: var(--radius-lg);
  padding: 1.5rem; /* Use Tailwind's spacing scale */
}
```

## Shadows

```css
var(--shadow-xs)            /* 1px blur */
var(--shadow-sm)            /* 2px blur */
var(--shadow-md)            /* 8px blur — cards, inputs */
var(--shadow-lg)            /* 20px blur — modals, dropdowns */
var(--shadow-xl)            /* 32px blur — hero sections */

var(--shadow-glow-primary)  /* Neon primary glow for completion */
var(--shadow-glow-success)  /* Neon success glow for achievement */
```

### Usage

```vue
<div class="shadow-md">Card content</div>

<style scoped>
.milestone-ring {
  box-shadow: var(--shadow-glow-primary);
  animation: glow-pulse 2s var(--motion-ease-out) infinite;
}
</style>
```

## Motion

All transitions must use motion tokens to respect `prefers-reduced-motion`.

```css
var(--motion-fast)        /* 150ms — quick feedback (button hover) */
var(--motion-base)        /* 220ms — standard (page transition, modal enter) */
var(--motion-slow)        /* 360ms — sustained (stagger, reveal) */

var(--motion-ease-out)    /* cubic-bezier(0.22, 1, 0.36, 1) — exit animations */
var(--motion-ease-spring) /* cubic-bezier(0.34, 1.56, 0.64, 1) — bouncy */
var(--motion-ease-standard) /* cubic-bezier(0.4, 0, 0.2, 1) — material */
```

### Usage

```vue
<Transition name="fade">
  <div v-if="visible">Appears</div>
</Transition>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity var(--motion-base) var(--motion-ease-standard);
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
```

### @vueuse/motion Integration

```vue
<script setup>
import { useMotion } from '@vueuse/motion'
import { ref } from 'vue'

const el = ref()

useMotion(el, {
  initial: { opacity: 0, y: 10 },
  enter: { opacity: 1, y: 0 },
  transition: {
    type: 'spring',
    duration: 'var(--motion-base)', // Respects reduced-motion
  },
})
</script>

<template>
  <div ref="el">Staggered item</div>
</template>
```

## Typography

Font stack and scale are defined in `design/typography.css`.

```css
--fs-xs       /* ~0.73rem */
--fs-sm       /* ~0.85rem */
--fs-base     /* ~0.98rem — body text */
--fs-lg       /* ~1.15rem */
--fs-xl       /* ~1.4rem */
--fs-2xl      /* ~1.9rem */
--fs-3xl      /* ~2.5rem */
--fs-display  /* ~3.2rem — hero headings */
```

Fonts:
- **UI text**: `Inter Variable` (sans, system fallback)
- **Display/headings**: `Fraunces Variable` (serif, optical sizing, wonky style)

### Usage

```vue
<h1 class="text-display font-bold">Hero</h1>
<h2 class="text-3xl font-semibold">Section</h2>
<p class="text-base">Body</p>
<small class="text-xs">Fine print</small>
```

## Examples: Deriving Custom Styles

### Button variant
```css
.btn-custom {
  background-color: var(--color-primary);
  color: var(--color-primary-content);
  border-radius: var(--radius-md);
  padding: 0.5rem 1rem;
  transition: all var(--motion-fast) var(--motion-ease-out);
  box-shadow: var(--shadow-sm);
}

.btn-custom:hover {
  box-shadow: var(--shadow-md);
  transform: translateY(-1px);
}

.btn-custom:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 3px;
}
```

### Stat card
```css
.stat-card {
  background: var(--color-base-200);
  border: 1px solid var(--color-base-300);
  border-radius: var(--radius-lg);
  padding: 1.5rem;
  box-shadow: var(--shadow-md);
}

.stat-value {
  font-size: var(--fs-3xl);
  font-family: "Fraunces Variable", serif;
  color: var(--color-primary);
}
```

