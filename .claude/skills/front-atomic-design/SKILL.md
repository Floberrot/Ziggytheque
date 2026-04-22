# front-atomic-design

**name**: front-atomic-design

**description**: Atomic Design + Editorial Dark design system for the Ziggytheque Vue 3 frontend. MUST trigger on any change under `front/src/**`. Enforces page LOC budgets, composable-only data fetching, design-token usage, Headless UI primitives, and feature-folder structure.

---

## When to Load This Skill

Automatically load whenever:
- User edits any file under `front/src/**`
- User asks about "atomic design", "frontend refactor", "component structure"

---

## Atomic Design Principle

Pages are thin composition surfaces. UI patterns are reusable atoms/molecules. Data fetching is centralized in `composables/queries/**`. No page exceeds 200 LOC.

**Structure**:
```
front/src/
├── components/
│   ├── atoms/       # 20+ A* primitives (buttons, inputs, icons, badges, etc.)
│   ├── molecules/   # 15+ M* compositions (modals, cards, search, pagination)
│   └── organisms/   # O* cross-domain (sidebar, nav, settings, toast, batch bar)
├── features/
│   ├── collection/organisms/  # CollectionEntry / VolumeEntry domain organisms
│   ├── wishlist/organisms/
│   ├── add/organisms/
│   └── [feature]/organisms/
├── layouts/         # AppLayout (auth shell), AuthLayout (gate)
├── composables/
│   ├── queries/     # ONLY place useQuery/useMutation live
│   └── ui/          # useToast, useConfirm, useBreakpoint, etc.
├── stores/          # useAuthStore, useThemeStore, useUiStore
├── design/          # tokens.css, daisy.css, typography.css, motion.css
└── pages/           # Route-level pages (composition-only, ≤200 LOC)
```

---

## Hard Rules Checklist (Pre-Edit)

Before making changes to `front/src/**`, verify:

- [ ] **Is this a page?** Pages must be ≤200 LOC, composition-only (no raw HTML elements).
- [ ] **Does it import `useQuery`/`useMutation`?** Move to `composables/queries/**` if in a non-query file.
- [ ] **Are there inline `<button>`, `<input>`, `<select>`, `<svg>` elements?** Replace with atoms/molecules (AButton, AInput, AIcon, etc.).
- [ ] **Any DaisyUI classes (`btn-*`, `badge-*`, `alert-*`, `card-*`)?** Those belong in components, not templates. Use atoms instead.
- [ ] **Repeated UI pattern (≥2 occurrences)?** Extract to atom/molecule.
- [ ] **Using hex/rgb colors or raw shadow values?** Use design tokens instead (`var(--color-primary)`, `var(--shadow-md)`, `var(--radius-lg)`).
- [ ] **Adding motion/transitions?** Use `var(--motion-*)` tokens and `v-motion` library (respects `prefers-reduced-motion`).
- [ ] **Modal, dialog, or overlay?** Use `MModal`, `MConfirmDialog`, or `MBottomSheet` (Headless UI under the hood). No custom divs.
- [ ] **Form inputs?** Use `AInput`, `ATextarea`, `ASelect` with error/hint props. Always pass `aria-describedby`.
- [ ] **Any `focus:outline-none` without `focus-visible:ring-*`?** Add focus ring or use `.focus-ring-inset`.
- [ ] **New feature with ≥2 UI components?** Create a feature folder under `features/<domain>/organisms/`.
- [ ] **Importing `useQuery` outside `composables/queries/**`?** This is forbidden. Wrap it in a composable under `composables/queries/`.

---

## Atom/Molecule/Organism Inventory

See `references/atoms.md`, `references/molecules.md`, `references/composables.md` for full catalog with usage examples.

**Common atoms**: AButton, AInput, AIcon, ABadge, AChip, AEmptyState, ASpinner, AProgressBar, ASwitch, AHeartRating, ATooltip.

**Common molecules**: MModal, MConfirmDialog, MSearchInput, MSegmentedControl, MStatCard, MCoverImage, MPagination.

**Common organisms**: OAppSidebar, OBottomNav, OSettingsSheet, OToastHost, OBatchActionBar.

---

## Design Tokens

All spacing, colors, shadows, and motion derive from `design/tokens.css` and DaisyUI's `ziggy-light` / `ziggy-dark` themes.

- **Colors**: `var(--color-primary)`, `var(--color-secondary)`, `var(--color-success)`, `var(--color-error)`.
- **Shadows**: `var(--shadow-sm)`, `var(--shadow-md)`, `var(--shadow-lg)`, `var(--shadow-glow-primary)`.
- **Radii**: `var(--radius-xs)`, `var(--radius-sm)`, `var(--radius-md)`, `var(--radius-lg)`.
- **Motion**: `var(--motion-fast)`, `var(--motion-base)`, `var(--motion-slow)` with easing tokens.
- **Type scale**: `var(--fs-xs)`, `var(--fs-sm)`, `var(--fs-base)`, `var(--fs-lg)`, ..., `var(--fs-display)`.

---

## Pre-Edit Self-Check

1. **LOC**: Is this component >100 atoms, >150 molecules, >300 organisms, >200 pages? If so, split it first.
2. **Props/Emits**: Explicit with TypeScript, using `defineProps`/`defineEmits`.
3. **Accessibility**: Forms have `aria-describedby`, modals have `aria-modal`, focus rings visible, keyboard shortcuts documented.
4. **No prop drilling**: Deep nesting? Use `provide/inject` for context, keep props shallow.
5. **Tokens**: Any magic numbers? Derive from `--color-*`, `--radius-*`, `--shadow-*`, `--motion-*`.
6. **Data flow**: Props down, events up. Keep component responsibilities single.

---

## Common Patterns

### Button with loading state
```vue
<AButton :loading="isLoading" variant="primary">
  Submit
</AButton>
```

### Input with validation
```vue
<AInput
  v-model="email"
  type="email"
  label="Email"
  :error="errors.email"
  hint="We'll never share your email"
/>
```

### Modal with form
```vue
<MModal :open="open" title="Edit" @close="onClose">
  <form @submit.prevent="save">
    <AInput v-model="name" label="Name" />
    <div class="flex gap-2 mt-4">
      <AButton variant="ghost" @click="onClose">Cancel</AButton>
      <AButton variant="primary" type="submit">Save</AButton>
    </div>
  </form>
</MModal>
```

### List with batch actions
```vue
<div>
  <OBatchActionBar
    v-if="selectedCount > 0"
    :count="selectedCount"
    :actions="[{ id: 'delete', label: 'Delete', variant: 'danger' }]"
    @action="handleAction"
    @clear="clearSelection"
  />
  <!-- list items -->
</div>
```

---

## Accessibility Checklist

- [ ] Form inputs have labels and `aria-describedby` for errors/hints.
- [ ] Buttons have clear labels (no icon-only without `aria-label`).
- [ ] Modals have `role="dialog"`, `aria-modal="true"`, focus trap, Esc to close.
- [ ] Lists have `role="listbox"`, items have `role="option"`, selections have `aria-selected`.
- [ ] Focus ring visible on all interactive elements (`:focus-visible`).
- [ ] Motion respects `prefers-reduced-motion` (built into motion tokens + `@vueuse/motion`).
- [ ] Color not sole means of differentiation (use icons, text, patterns).

---

## References

- `references/tokens.md` — Design token values + CSS variable consumption.
- `references/atoms.md` — A* catalog with prop/emit contracts + examples.
- `references/molecules.md` — M* catalog with usage + composition patterns.
- `references/composables.md` — Query vs. UI composables, `useQuery` centralization, invalidation patterns.

