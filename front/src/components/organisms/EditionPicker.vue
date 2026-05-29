<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { discoverEditions } from '@/api/manga'
import type { DiscoveredEdition } from '@/api/manga'
import EditionCard from '@/components/molecules/EditionCard.vue'

const props = withDefaults(
  defineProps<{
    title: string
    initialCountry?: string
  }>(),
  { initialCountry: 'FR' },
)

const emit = defineEmits<{
  select: [edition: DiscoveredEdition]
  skip: []
}>()

const { t } = useI18n()

const COUNTRIES = [
  { code: 'FR', flag: '🇫🇷' },
  { code: 'US', flag: '🇺🇸' },
  { code: 'JP', flag: '🇯🇵' },
] as const

const country = ref(props.initialCountry)
const isLoading = ref(false)
const editions = ref<DiscoveredEdition[]>([])

async function load(): Promise<void> {
  isLoading.value = true
  try {
    editions.value = await discoverEditions(props.title, country.value)
  } catch {
    editions.value = []
  } finally {
    isLoading.value = false
  }
}

function selectCountry(code: string): void {
  if (code === country.value) return
  country.value = code
  load()
}

onMounted(load)
</script>

<template>
  <div class="space-y-4">
    <p class="text-sm text-base-content/60">
      {{ t('add.editionsFor') }} <strong>{{ title }}</strong>
    </p>

    <!-- Country chooser — drives which market's editions are discovered -->
    <div class="flex flex-wrap gap-2">
      <button
        v-for="c in COUNTRIES"
        :key="c.code"
        type="button"
        :data-test="`country-${c.code}`"
        class="btn btn-sm gap-1.5"
        :class="c.code === country ? 'btn-primary' : 'btn-outline'"
        @click="selectCountry(c.code)"
      >
        <span>{{ c.flag }}</span>
        <span>{{ t(`country.${c.code.toLowerCase()}`) }}</span>
      </button>
    </div>

    <div v-if="isLoading" class="flex justify-center py-8">
      <span class="loading loading-spinner loading-md" />
    </div>

    <template v-else>
      <div v-if="editions.length" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-3">
        <EditionCard
          v-for="(edition, index) in editions"
          :key="`${edition.publisher}-${index}`"
          :edition="edition"
          @select="emit('select', edition)"
        />
      </div>

      <p v-else class="text-sm text-center text-base-content/40 py-4">
        {{ t('add.noEditionsFound') }}
      </p>

      <div class="divider text-xs text-base-content/40">{{ t('common.or') }}</div>
      <button data-test="fill-manually" class="btn btn-outline btn-sm w-full" @click="emit('skip')">
        {{ t('add.fillManually') }}
      </button>
    </template>
  </div>
</template>
