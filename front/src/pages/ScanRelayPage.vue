<script setup lang="ts">
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { postScannedIsbn } from '@/api/manga'
import IsbnScanner from '@/components/molecules/IsbnScanner.vue'

const route = useRoute()
const { t } = useI18n()

const sessionId = route.params.sessionId as string
const lastSentIsbn = ref<string | null>(null)
const errorMessage = ref<string | null>(null)

async function handleDetected(isbn: string) {
  errorMessage.value = null
  try {
    await postScannedIsbn(sessionId, isbn)
    lastSentIsbn.value = isbn
  } catch {
    errorMessage.value = 'Failed to send ISBN'
  }
}
</script>

<template>
  <div class="min-h-screen bg-base-200 flex flex-col items-center justify-center gap-6 p-4">
    <h1 class="text-2xl font-bold">{{ t('scan.title') }}</h1>

    <IsbnScanner @detected="handleDetected" />

    <div v-if="lastSentIsbn" class="alert alert-success text-sm">
      {{ t('scan.sent') }}: {{ lastSentIsbn }}
    </div>

    <div v-if="errorMessage" class="alert alert-error text-sm">
      {{ errorMessage }}
    </div>
  </div>
</template>
