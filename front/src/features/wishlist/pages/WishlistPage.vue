<script setup lang="ts">
import { computed } from 'vue'
import { useWishlistList, useBatchPurchase } from '@/composables/queries/useWishlistQueries'
import { useBatchSelection } from '@/composables/ui/useBatchSelection'

const { data: wishlist, isLoading } = useWishlistList()
const { mutate: batchPurchase, isPending } = useBatchPurchase()
const { selected, toggle, count, clear } = useBatchSelection((wishlist?.value ?? []) as any[])

const batchActions = computed(() => ({
  selected: selected.value || [],
  toggle,
  count: count.value || 0,
  clear,
}))

function handlePurchase() {
  if (batchActions.value.count === 0) return
  const purchases = batchActions.value.selected.map((id: any) => ({
    collectionEntryId: id,
    volumeEntryId: '',
  }))
  batchPurchase(purchases)
  batchActions.value.clear()
}
</script>

<template>
  <div class="p-4 lg:p-6">
    <h1 class="heading-xl mb-6">Wishlist</h1>

    <div v-if="isLoading" class="space-y-2">
      <ASkeleton v-for="i in 3" :key="i" height="80px" />
    </div>

    <div v-else-if="wishlist?.length" class="space-y-2">
      <div
        v-for="entry in wishlist"
        :key="entry.id"
        class="card bg-base-200 p-4 cursor-pointer hover:bg-base-300 transition-colors"
        :class="{ 'ring-2 ring-primary': batchActions.selected.includes(entry.id) }"
        @click="batchActions.toggle(entry.id)"
      >
        <div class="flex items-center justify-between">
          <div class="flex-1">
            <h3 class="font-semibold">{{ entry.manga.title }}</h3>
            <p class="text-sm text-base-content/70">{{ entry.wishedCount }} volumes wished</p>
          </div>
          <ASwitch :model-value="batchActions.selected.includes(entry.id)" />
        </div>
      </div>
    </div>

    <AEmptyState v-else icon="lucide:heart" title="Wishlist empty" description="Add items to your wishlist" />

    <OBatchActionBar v-if="batchActions.count > 0" :count="batchActions.count" :loading="isPending" @delete="handlePurchase" @clear="batchActions.clear" />
  </div>
</template>
