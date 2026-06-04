<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { TopAuthor } from '@/types'

const props = defineProps<{ authors: TopAuthor[] }>()

const { t } = useI18n()

const max = computed(() => Math.max(1, ...props.authors.map((author) => author.count)))
</script>

<template>
  <ul v-if="authors.length" class="space-y-3">
    <li v-for="(author, index) in authors" :key="author.author" class="flex items-center gap-3">
      <span
        class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg text-xs font-bold"
        :class="index === 0 ? 'bg-primary/15 text-primary' : 'bg-base-200 text-base-content/50'"
      >
        {{ index + 1 }}
      </span>
      <div class="min-w-0 flex-1">
        <div class="flex items-baseline justify-between gap-2">
          <span class="truncate text-sm font-medium">{{ author.author }}</span>
          <span class="shrink-0 text-xs text-base-content/45 tabular-nums">{{ author.count }}</span>
        </div>
        <div class="mt-1 h-1 w-full overflow-hidden rounded-full bg-base-200">
          <div
            class="h-full rounded-full bg-primary/70 transition-all duration-500"
            :style="{ width: `${(author.count / max) * 100}%` }"
          />
        </div>
      </div>
    </li>
  </ul>
  <p v-else class="text-sm text-base-content/40 italic py-6 text-center">{{ t('common.noData') }}</p>
</template>
