<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Copy } from 'lucide-vue-next'
import { useUiStore } from '@/stores/useUiStore'
import type { ActivityLog } from '@/types'

const props = defineProps<{ log: ActivityLog }>()

const { t } = useI18n()
const ui = useUiStore()

const metadataEntries = computed(() =>
  Object.entries(props.log.metadata ?? {}).map(([key, value]) => ({
    key,
    value: typeof value === 'string' ? value : JSON.stringify(value),
  })),
)

async function copyJson(): Promise<void> {
  try {
    await navigator.clipboard.writeText(JSON.stringify(props.log, null, 2))
    ui.addToast(t('journal.copied'), 'success')
  } catch {
    ui.addToast(t('journal.copyFailed'), 'error')
  }
}
</script>

<template>
  <div class="space-y-3 text-xs">
    <div
      v-if="log.errorMessage"
      class="rounded-lg border border-error/40 bg-error/10 text-error px-3 py-2 font-mono whitespace-pre-wrap break-all"
    >
      {{ log.errorMessage }}
    </div>

    <div v-if="log.owner" class="text-base-content/60">
      {{ t('journal.user') }} :
      <span class="font-medium text-base-content">{{ log.owner.displayName }}</span>
      <span class="text-base-content/50"> — {{ log.owner.email }}</span>
    </div>

    <div v-if="metadataEntries.length" class="overflow-x-auto rounded-lg border border-base-300">
      <table class="table table-xs w-full">
        <tbody>
          <tr v-for="entry in metadataEntries" :key="entry.key">
            <td class="font-mono text-base-content/50 whitespace-nowrap align-top w-36">
              {{ entry.key }}
            </td>
            <td class="font-mono break-all">{{ entry.value }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-else-if="!log.errorMessage" class="text-base-content/40">
      {{ t('journal.noMetadata') }}
    </div>

    <button class="btn btn-xs btn-ghost gap-1" @click.stop="copyJson">
      <Copy class="w-3 h-3" />
      {{ t('journal.copyJson') }}
    </button>
  </div>
</template>
