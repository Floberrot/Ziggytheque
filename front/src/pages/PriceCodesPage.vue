<script setup lang="ts">
import { ref } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import { getPriceCodes, createPriceCode, updatePriceCode, deletePriceCode } from '@/api/priceCode'
import { useUiStore } from '@/stores/useUiStore'
import { useI18n } from 'vue-i18n'

const qc = useQueryClient()
const ui = useUiStore()
const { t } = useI18n()

const { data: codes, isPending } = useQuery({ queryKey: ['price-codes'], queryFn: getPriceCodes })

const showForm = ref(false)
const editCode = ref<string | null>(null)
const form = ref({ code: '', label: '', value: 0 })

function openCreate() {
  editCode.value = null
  form.value = { code: '', label: '', value: 0 }
  showForm.value = true
}

function openEdit(code: string, label: string, value: number) {
  editCode.value = code
  form.value = { code, label, value }
  showForm.value = true
}

const saveMutation = useMutation({
  mutationFn: () =>
    editCode.value
      ? updatePriceCode(editCode.value, { label: form.value.label, value: form.value.value })
      : createPriceCode(form.value),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['price-codes'] })
    qc.invalidateQueries({ queryKey: ['stats'] })
    showForm.value = false
    ui.addToast(t('priceCodes.saved'), 'success')
  },
})

const deleteMutation = useMutation({
  mutationFn: (code: string) => deletePriceCode(code),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['price-codes'] })
    qc.invalidateQueries({ queryKey: ['stats'] })
  },
})
</script>

<template>
  <div class="p-6 space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold">{{ t('priceCodes.title') }}</h1>
      <button class="btn btn-primary btn-sm" @click="openCreate">+ {{ t('priceCodes.add') }}</button>
    </div>

    <!-- Form modal -->
    <div v-if="showForm" class="card bg-base-100 shadow max-w-sm">
      <form class="card-body space-y-3" @submit.prevent="saveMutation.mutate()">
        <h2 class="card-title text-base">{{ editCode ? t('priceCodes.edit') : t('priceCodes.new') }}</h2>
        <div class="form-control">
          <label class="label"><span class="label-text">{{ t('priceCodes.code') }}</span></label>
          <input
            v-model="form.code"
            type="text"
            class="input input-bordered input-sm"
            :disabled="!!editCode"
            required
          />
        </div>
        <div class="form-control">
          <label class="label"><span class="label-text">{{ t('priceCodes.label') }}</span></label>
          <input v-model="form.label" type="text" class="input input-bordered input-sm" required />
        </div>
        <div class="form-control">
          <label class="label"><span class="label-text">{{ t('priceCodes.value') }} (€)</span></label>
          <input v-model.number="form.value" type="number" step="0.01" min="0" class="input input-bordered input-sm" required />
        </div>
        <div class="flex gap-2">
          <button type="submit" class="btn btn-primary btn-sm flex-1" :class="{ loading: saveMutation.isPending.value }">
            {{ t('common.save') }}
          </button>
          <button type="button" class="btn btn-ghost btn-sm" @click="showForm = false">
            {{ t('common.cancel') }}
          </button>
        </div>
      </form>
    </div>

    <!-- Table -->
    <div v-if="isPending" class="flex justify-center py-8">
      <span class="loading loading-spinner" />
    </div>

    <div v-else class="overflow-x-auto">
      <table class="table table-zebra w-full">
        <thead>
          <tr>
            <th>{{ t('priceCodes.code') }}</th>
            <th>{{ t('priceCodes.label') }}</th>
            <th>{{ t('priceCodes.value') }}</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr v-for="pc in codes" :key="pc.code">
            <td class="font-mono font-bold">{{ pc.code }}</td>
            <td>{{ pc.label }}</td>
            <td class="font-semibold text-success">{{ pc.value.toFixed(2) }}€</td>
            <td class="text-right space-x-2">
              <button class="btn btn-ghost btn-xs" @click="openEdit(pc.code, pc.label, pc.value)">
                {{ t('common.edit') }}
              </button>
              <button class="btn btn-error btn-xs" @click="deleteMutation.mutate(pc.code)">
                {{ t('common.delete') }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
