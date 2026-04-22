# Molecules Catalog (M*)

Molecules are compositions of atoms with enhanced interactions. They live in `components/molecules/` and can import from `components/atoms/` only (no queries). Max 150 LOC each.

## Forms & Input

### MSearchInput
Input + search icon + clear button + loading state.

```vue
<MSearchInput
  v-model="query"
  placeholder="Search manga..."
  :loading="isSearching"
  @search="handleSearch"
/>
```

**Props**: `modelValue`, `placeholder`, `loading`
**Emits**: `update:modelValue`, `search`

### MAutocomplete
Headless UI Combobox with filtered list.

```vue
<MAutocomplete
  v-model="selectedManga"
  :options="mangaList"
  label="Manga"
  placeholder="Type to search..."
/>
```

**Props**: `modelValue`, `options`, `label`, `placeholder`
**Emits**: `update:modelValue`

## Dialogs & Modals

### MModal
Headless UI Dialog shell with header/body/footer.

```vue
<MModal :open="showModal" title="Edit Profile" size="md" @close="closeModal">
  <form @submit.prevent="save">
    <AInput v-model="name" label="Name" />
    <div class="flex gap-2 mt-4">
      <AButton variant="ghost" @click="closeModal">Cancel</AButton>
      <AButton variant="primary" type="submit">Save</AButton>
    </div>
  </form>
</MModal>
```

**Props**: `open`, `title`, `size` (sm, md, lg, xl)
**Emits**: `close`
**Slots**: default (body)

### MConfirmDialog
Confirmation dialog with OK/Cancel.

```vue
<MConfirmDialog
  :open="showConfirm"
  title="Delete?"
  description="This cannot be undone."
  :danger="true"
  @confirm="delete()"
  @cancel="showConfirm = false"
/>
```

**Props**: `open`, `title`, `description`, `confirmText`, `cancelText`, `danger`
**Emits**: `confirm`, `cancel`

### MBottomSheet
Mobile-optimized sheet that slides up from bottom.

```vue
<MBottomSheet :open="showSheet" title="Filters" @close="hideSheet">
  <!-- filter options -->
</MBottomSheet>
```

**Props**: `open`, `title`
**Emits**: `close`
**Slots**: default

## Inputs & Controls

### MSegmentedControl
Headless UI RadioGroup as pill buttons (theme, status, filters).

```vue
<MSegmentedControl
  v-model="theme"
  :options="[
    { value: 'light', label: '☀️ Light' },
    { value: 'dark', label: '🌙 Dark' },
    { value: 'system', label: '💻 System' },
  ]"
/>
```

**Props**: `modelValue`, `options`
**Emits**: `update:modelValue`

### MContextMenu
Headless UI Menu + Floating UI positioning.

```vue
<MContextMenu
  :options="[
    { id: 'edit', label: 'Edit' },
    { id: 'delete', label: 'Delete', danger: true },
  ]"
  @select="handleAction"
/>
```

**Props**: `options`, `icon`
**Emits**: `select`

## Data Display

### MStatCard
Title + large value + optional trend/icon.

```vue
<MStatCard
  title="Total Owned"
  :value="250"
  icon="lucide:books"
  trend="up"
  :trend-value="12"
/>
```

**Props**: `title`, `value`, `icon`, `trend`, `trendValue`

### MStatPill
Colored dot + label + value (dashboard KPI).

```vue
<MStatPill color="success" label="Completed" value="85%" />
```

**Props**: `color`, `label`, `value`

### MCoverImage
Image with fallback + aspect ratio.

```vue
<MCoverImage :src="manga.coverUrl" :alt="manga.title" aspect="2/3" />
```

**Props**: `src`, `alt`, `loading`, `aspect`, `class`

### MCardTile
Cover image tile with selection checkmark, hover ring, badge slot.

```vue
<MCardTile
  :src="cover"
  :selected="isSelected"
  @toggle="toggle()"
>
  <template #badge>
    <ABadge variant="success">Owned</ABadge>
  </template>
</MCardTile>
```

**Props**: `src`, `selected`
**Emits**: `toggle`
**Slots**: `badge`

## Lists & Navigation

### MPagination
Number pager with prev/next buttons.

```vue
<MPagination
  :current-page="page"
  :total-pages="10"
  @update:current-page="page = $event"
/>
```

**Props**: `currentPage`, `totalPages`
**Emits**: `update:currentPage`

## Status & Feedback

### MToast
Status message with type (success, error, info, warning).

```vue
<MToast type="success" message="Saved successfully!" />
```

**Props**: `type`, `message`, `icon`
**Role**: `status` (polite) or `alert` (assertive for errors)

---

## Patterns

### Modal with form validation
```vue
<script setup>
import { ref } from 'vue'
import MModal from '@/components/molecules/MModal.vue'
import AInput from '@/components/atoms/AInput.vue'
import AButton from '@/components/atoms/AButton.vue'

const open = ref(false)
const form = ref({ name: '', email: '' })
const errors = ref({})

async function save() {
  errors.value = {}
  if (!form.value.name) errors.value.name = 'Name required'
  if (!form.value.email) errors.value.email = 'Email required'

  if (Object.keys(errors.value).length > 0) return

  // submit
  open.value = false
}
</script>

<template>
  <MModal :open="open" title="New User" @close="open = false">
    <AInput
      v-model="form.name"
      label="Name"
      :error="errors.name"
    />
    <AInput
      v-model="form.email"
      type="email"
      label="Email"
      :error="errors.email"
      class="mt-3"
    />
    <div class="flex gap-2 mt-6">
      <AButton variant="ghost" @click="open = false">Cancel</AButton>
      <AButton variant="primary" @click="save">Create</AButton>
    </div>
  </MModal>
</template>
```

### Search + autocomplete
```vue
<script setup>
import { ref, computed } from 'vue'
import MSearchInput from '@/components/molecules/MSearchInput.vue'
import MAutocomplete from '@/components/molecules/MAutocomplete.vue'

const query = ref('')
const results = computed(() => 
  // filter by query
)
</script>

<template>
  <MSearchInput v-model="query" @search="doSearch" />
  <MAutocomplete
    :model-value="selected"
    :options="results"
    @update:model-value="selected = $event"
  />
</template>
```

---

## Naming & Patterns

- All molecules are prefixed `M*`.
- Compose atoms only; no queries or complex logic.
- Props typed with TypeScript.
- Events explicit and minimal.
- Use Headless UI components for accessibility (`Dialog`, `Menu`, `RadioGroup`, `Combobox`, `Switch`).
- Floating UI for positioning (tooltips, dropdowns, context menus).

