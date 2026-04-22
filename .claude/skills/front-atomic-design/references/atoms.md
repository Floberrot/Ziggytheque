# Atoms Catalog (A*)

All atoms are presentational (props in → events out). They live in `components/atoms/` and import nothing but other atoms and icon library. Max 100 LOC each.

## Form Inputs

### AInput
Text/email/password/number/url/search with label, error, hint.

```vue
<AInput
  v-model="email"
  type="email"
  label="Email"
  placeholder="you@example.com"
  :error="errors.email"
  hint="We'll never share this"
/>
```

**Props**: `modelValue`, `type`, `label`, `placeholder`, `error`, `hint`, `disabled`, `readonly`
**Emits**: `update:modelValue`, `blur`, `focus`

### ATextarea
Auto-growing text area with error support.

```vue
<ATextarea
  v-model="bio"
  label="Bio"
  rows="4"
  :error="errors.bio"
/>
```

**Props**: `modelValue`, `label`, `placeholder`, `error`, `rows`, `disabled`
**Emits**: `update:modelValue`

### ASelect
Native select with options.

```vue
<ASelect
  v-model="status"
  :options="[
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
  ]"
  label="Status"
  :error="errors.status"
/>
```

**Props**: `modelValue`, `options`, `label`, `placeholder`, `error`, `disabled`
**Emits**: `update:modelValue`

### ASwitch
Headless UI toggle.

```vue
<ASwitch v-model="darkMode" label="Dark mode" />
```

**Props**: `modelValue`, `label`, `disabled`
**Emits**: `update:modelValue`

## Buttons

### AButton
All buttons. Variants: primary, ghost, danger, success, warning, outline. Sizes: sm, md, lg.

```vue
<AButton variant="primary" size="md" :loading="isSaving">
  Save
</AButton>
```

**Props**: `variant`, `size`, `loading`, `disabled`, `type`
**Slots**: default

### AIconButton
Icon-only button with required `aria-label`.

```vue
<AIconButton
  icon="lucide:trash"
  aria-label="Delete item"
  variant="danger"
  size="sm"
  @click="delete()"
/>
```

**Props**: `icon`, `size`, `variant`, `disabled`, `ariaLabel`

## Icons & Images

### AIcon
@iconify/vue wrapper. Sizes: xs, sm, md, lg, xl.

```vue
<AIcon name="lucide:check" size="md" class="text-success" />
```

**Props**: `name`, `size`, `class`

### MCoverImage
Image with fallback, aspect ratio, loading state.

```vue
<MCoverImage
  :src="manga.coverUrl"
  :alt="manga.title"
  aspect="2/3"
  class="rounded-lg"
/>
```

**Props**: `src`, `alt`, `loading`, `aspect`, `class`

## Display

### ABadge
Small label with color variant.

```vue
<ABadge variant="success" subtle>
  Owned
</ABadge>
```

**Props**: `variant`, `subtle`
**Slots**: default

### AChip
Pill with optional icon/indicator.

```vue
<AChip icon="lucide:star" indicator="success" :count="5">
  Rating
</AChip>
```

**Props**: `icon`, `indicator`, `count`
**Slots**: default

### ATag
Removable label.

```vue
<ATag removable @remove="remove()">
  #action
</ATag>
```

**Props**: `removable`
**Emits**: `remove`

### ADivider
Horizontal or vertical separator.

```vue
<ADivider label="Or continue with" />
```

**Props**: `orientation`, `label`

### AEmptyState
Icon + title + description + CTA.

```vue
<AEmptyState
  icon="lucide:inbox"
  title="No items"
  description="Try searching or adding a new item."
>
  <template #actions>
    <AButton variant="primary" @click="create()">Create</AButton>
  </template>
</AEmptyState>
```

**Props**: `icon`, `title`, `description`
**Slots**: `default`, `actions`

## Progress & Status

### AProgressBar
Single-value progress bar with optional label.

```vue
<AProgressBar :value="75" :max="100" color="success" show-label />
```

**Props**: `value`, `max`, `color`, `showLabel`

### AProgressRing
Circular progress indicator.

```vue
<AProgressRing :value="60" :max="100" size="120" color="oklch(0.72 0.18 12)">
  60%
</AProgressRing>
```

**Props**: `value`, `max`, `size`, `strokeWidth`, `color`
**Slots**: default (inner label)

### ASpinner
Loading spinner.

```vue
<ASpinner size="md" />
```

**Props**: `size` (sm, md, lg)

### ASkeleton
Placeholder shimmer.

```vue
<ASkeleton aspect="2/3" radius="lg" width="100%" />
```

**Props**: `aspect`, `radius`, `width`, `height`

## Data Display

### AAvatar
Initials or image avatar.

```vue
<AAvatar initials="JD" src="/avatar.jpg" size="md" />
```

**Props**: `src`, `initials`, `size`

### AHeartRating
Heart-based rating (1–5).

```vue
<AHeartRating v-model="rating" :max="5" readonly />
```

**Props**: `modelValue`, `max`, `readonly`
**Emits**: `update:modelValue`

## Utilities

### ATooltip
Floating UI tooltip on hover.

```vue
<ATooltip text="Delete this item" side="top">
  <AIconButton icon="lucide:trash" aria-label="Delete" />
</ATooltip>
```

**Props**: `text`, `side`
**Slots**: default

### AFade
Transition component for fade in/out.

```vue
<AFade>
  <div v-if="visible">Fades in/out</div>
</AFade>
```

**Props**: `name` (for custom class)
**Slots**: default

### ASlideUp
Transition component for slide-up entry.

```vue
<ASlideUp>
  <div v-if="show">Slides up from bottom</div>
</ASlideUp>
```

**Props**: `name`
**Slots**: default

---

## Naming & Patterns

- All atoms are prefixed `A*`.
- No `useQuery`, `useMutation`, or store imports (except direct store state reads if absolutely necessary).
- Props are typed and documented with TypeScript.
- Events are explicit: `defineEmits<{ 'update:modelValue': [value: string] }>()`.
- Slots are named (`#default`, `#icon`, `#actions`) and documented.
- Tailwind classes: use only utility classes, never component classes like `@apply btn`. (DaisyUI handles that in molecules/organisms.)

