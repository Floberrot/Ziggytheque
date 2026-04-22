# Composables Reference

Composables are reusable stateful logic. They live in `composables/` and split into two categories:

1. **`composables/queries/**`** — Data fetching only (`useQuery`, `useMutation`, `useInfiniteQuery`)
2. **`composables/ui/**`** — UI state & interaction helpers (toast, confirm, breakpoint, etc.)

---

## Query Composables (`composables/queries/`)

**STRICT RULE**: `useQuery`, `useMutation`, `useInfiniteQuery` **may ONLY be used inside** `composables/queries/**/*.ts`.
Pages and organisms import these composables and call them; they never import Vue Query directly.

### useCollectionQueries

Collection (manga in library) operations.

```ts
// composables/queries/useCollectionQueries.ts
export function useCollectionList(params?: { page?: number; search?: string }) {
  const queryClient = useQueryClient()
  return useQuery({
    queryKey: ['collection', params],
    queryFn: () => collectionApi.list(params),
  })
}

export function useCollectionEntry(id: string) {
  return useQuery({
    queryKey: ['collection', id],
    queryFn: () => collectionApi.get(id),
  })
}

export function useAddToCollection() {
  const queryClient = useQueryClient()
  const toast = useToast()

  return useMutation({
    mutationFn: (data: AddCollectionDto) => collectionApi.add(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['collection'] })
      toast.success('Added to collection!')
    },
    onError: () => toast.error('Failed to add'),
  })
}

export function useToggleVolume(entryId: string, volumeId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (field: 'isOwned' | 'isRead') =>
      collectionApi.toggleVolume(entryId, volumeId, field),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['collection', entryId] })
      // Also invalidate list in case stats changed
      queryClient.invalidateQueries({ queryKey: ['stats'] })
    },
  })
}
```

**Usage in pages/organisms**:
```vue
<script setup>
import { useCollectionList } from '@/composables/queries/useCollectionQueries'

const { data: collection, isLoading } = useCollectionList()
</script>
```

### useMangaQueries

Manga (external search & import) operations.

```ts
export function useInfiniteExternalSearch(query: Ref<string>) {
  return useInfiniteQuery({
    queryKey: ['manga', 'external', query],
    queryFn: ({ pageParam = 0 }) =>
      mangaApi.external(query.value, pageParam),
    getNextPageParam: (lastPage) => lastPage.nextPage,
  })
}

export function useImportManga() {
  const queryClient = useQueryClient()
  const toast = useToast()

  return useMutation({
    mutationFn: (data: ImportMangaDto) => mangaApi.import(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['collection'] })
      toast.success('Manga imported!')
    },
  })
}
```

### useWishlistQueries

Wishlist operations.

```ts
export function useWishlistList() {
  return useQuery({
    queryKey: ['wishlist'],
    queryFn: () => wishlistApi.list(),
  })
}

export function usePurchaseVolume() {
  const queryClient = useQueryClient()
  const toast = useToast()

  return useMutation({
    mutationFn: (id: string) => wishlistApi.purchase(id),
    onSuccess: () => {
      // Move to collection
      queryClient.invalidateQueries({ queryKey: ['wishlist'] })
      queryClient.invalidateQueries({ queryKey: ['collection'] })
      toast.success('Moved to collection!')
    },
  })
}
```

### useStatsQueries

Dashboard statistics.

```ts
export function useStats() {
  return useQuery({
    queryKey: ['stats'],
    queryFn: () => statsApi.get(),
    staleTime: 5 * 60 * 1000, // 5 min
  })
}
```

### useNotificationQueries

Notifications with polling.

```ts
export function useNotifications() {
  return useQuery({
    queryKey: ['notifications'],
    queryFn: () => notificationApi.list(),
    refetchInterval: 60 * 1000, // Poll every minute
  })
}

export function useMarkNotificationRead() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => notificationApi.markRead(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['notifications'] })
    },
  })
}
```

### useAuthFlow

Authentication (gate) logic.

```ts
export function useGate() {
  const auth = useAuthStore()
  const toast = useToast()

  return useMutation({
    mutationFn: (password: string) => authClient.gate(password),
    onSuccess: (data) => {
      auth.setToken(data.token)
      toast.success('Welcome!')
    },
    onError: () => toast.error('Invalid password'),
  })
}
```

---

## UI Composables (`composables/ui/`)

### useToast

Imperative toast notifications.

```ts
// composables/ui/useToast.ts
export function useToast() {
  const ui = useUiStore()

  return {
    success: (msg: string) => ui.addToast({ message: msg, type: 'success' }),
    error: (msg: string) => ui.addToast({ message: msg, type: 'error' }),
    info: (msg: string) => ui.addToast({ message: msg, type: 'info' }),
    warning: (msg: string) => ui.addToast({ message: msg, type: 'warning' }),
  }
}
```

**Usage**:
```vue
const toast = useToast()
toast.success('Saved!')
toast.error('Something broke')
```

### useConfirm

Confirmation dialog.

```ts
export function useConfirm() {
  // Singleton dialog state
  const dialog = ref({ open: false, options: null })
  let resolver: ((val: boolean) => void) | null = null

  return {
    confirm: (options) => new Promise(resolve => {
      resolver = resolve
      dialog.value = { open: true, options }
    }),
    confirmAction: () => {
      resolver?.(true)
      dialog.value.open = false
    },
    cancelAction: () => {
      resolver?.(false)
      dialog.value.open = false
    },
    dialog,
  }
}
```

**Usage**:
```vue
const { confirm } = useConfirm()

const confirmed = await confirm({
  title: 'Delete?',
  description: 'Cannot be undone.',
  danger: true,
})

if (confirmed) {
  // delete
}
```

### useBreakpoint

Responsive queries via @vueuse/core.

```ts
export function useBreakpoint() {
  return {
    sm: useMediaQuery('(min-width: 640px)'),
    md: useMediaQuery('(min-width: 768px)'),
    lg: useMediaQuery('(min-width: 1024px)'),
    xl: useMediaQuery('(min-width: 1280px)'),
  }
}
```

**Usage**:
```vue
const breakpoint = useBreakpoint()
<div v-if="breakpoint.lg">Desktop only</div>
```

### useBatchSelection

Generic multi-select state.

```ts
export function useBatchSelection<T extends { id: string }>(items: Ref<T[]>) {
  const selected = ref(new Set<string>())

  return {
    isSelected: (item: T) => selected.value.has(item.id),
    toggle: (id: string) => {
      if (selected.value.has(id)) {
        selected.value.delete(id)
      } else {
        selected.value.add(id)
      }
    },
    selectAll: () => {
      selected.value = new Set(items.value.map(it => it.id))
    },
    clear: () => selected.value.clear(),
    count: computed(() => selected.value.size),
    items: computed(() => items.value.filter(it => isSelected(it))),
  }
}
```

**Usage**:
```vue
const items = ref([...])
const selection = useBatchSelection(items)

if (selection.count.value > 0) {
  <OBatchActionBar :count="selection.count" @action="handleAction" />
}
```

### useCoverUrl

Reactive cover image URL with fallback.

```ts
export function useCoverUrl(manga: Ref<{ coverUrl?: string; title: string }>) {
  return computed(() => manga.value.coverUrl || '/images/no-cover.png')
}
```

### useContextMenu

Floating UI context menu positioning.

```ts
export function useContextMenu() {
  const position = ref({ x: 0, y: 0 })
  const open = ref(false)

  return {
    show: (e: MouseEvent) => {
      position.value = { x: e.clientX, y: e.clientY }
      open.value = true
    },
    hide: () => (open.value = false),
    position,
    open,
  }
}
```

### useKeyboardShortcut

@vueuse/core magic keys wrapper.

```ts
export function useKeyboardShortcut() {
  const keys = useMagicKeys()

  return {
    cmdK: computed(() => keys.meta_k.value || keys.ctrl_k.value),
    esc: computed(() => keys.escape.value),
  }
}
```

---

## Invalidation Patterns

**Each mutation composable owns its invalidation set.**

Example: Adding an item invalidates the list and stats, but not notifications.

```ts
export function useAddToCollection() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (data) => collectionApi.add(data),
    onSuccess: () => {
      // Invalidate only what changed
      queryClient.invalidateQueries({ queryKey: ['collection'] })
      queryClient.invalidateQueries({ queryKey: ['stats'] })
      // Do NOT invalidate ['wishlist'] unless it moved there
    },
  })
}
```

---

## Naming Conventions

- Query composables: `use<Domain>Queries.ts` (e.g., `useCollectionQueries.ts`)
- Each export is a single query/mutation: `useCollectionList`, `useCollectionEntry`, `useAddToCollection`
- UI composables: `use<Behavior>.ts` (e.g., `useToast.ts`, `useBatchSelection.ts`)

