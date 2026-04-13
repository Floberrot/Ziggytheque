<script setup lang="ts">
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import { getWishlist, removeFromWishlist, purchaseWishlistItem } from '@/api/wishlist'
import { useUiStore } from '@/stores/useUiStore'
import { useI18n } from 'vue-i18n'

const qc = useQueryClient()
const ui = useUiStore()
const { t } = useI18n()

const { data: items, isPending } = useQuery({ queryKey: ['wishlist'], queryFn: getWishlist })

const removeMutation = useMutation({
  mutationFn: (id: string) => removeFromWishlist(id),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['wishlist'] })
    qc.invalidateQueries({ queryKey: ['stats'] })
  },
})

const purchaseMutation = useMutation({
  mutationFn: (id: string) => purchaseWishlistItem(id),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['wishlist'] })
    qc.invalidateQueries({ queryKey: ['collection'] })
    qc.invalidateQueries({ queryKey: ['stats'] })
    ui.addToast(t('wishlist.purchased'), 'success')
  },
})
</script>

<template>
  <div class="p-6 space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold">{{ t('wishlist.title') }}</h1>
    </div>

    <div v-if="isPending" class="flex justify-center py-16">
      <span class="loading loading-spinner loading-lg" />
    </div>

    <div v-else-if="!items?.length" class="text-center py-16 text-base-content/50">
      {{ t('wishlist.empty') }}
    </div>

    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <div
        v-for="item in items.filter(i => !i.isPurchased)"
        :key="item.id"
        class="card bg-base-100 shadow hover:shadow-lg transition-shadow"
      >
        <div class="card-body flex-row gap-4 p-4">
          <img
            v-if="item.manga.coverUrl"
            :src="item.manga.coverUrl"
            :alt="item.manga.title"
            class="w-16 h-22 object-cover rounded"
          />
          <div class="flex-1 min-w-0 space-y-1">
            <p class="font-semibold truncate">{{ item.manga.title }}</p>
            <p class="text-sm text-base-content/60">{{ item.manga.edition }}</p>
            <div class="flex gap-2 pt-2">
              <button
                class="btn btn-success btn-xs"
                :class="{ loading: purchaseMutation.isPending.value }"
                @click="purchaseMutation.mutate(item.id)"
              >
                {{ t('wishlist.purchase') }}
              </button>
              <button
                class="btn btn-ghost btn-xs"
                @click="removeMutation.mutate(item.id)"
              >
                {{ t('common.remove') }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
