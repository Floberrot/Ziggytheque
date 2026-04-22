import { computed, ref } from 'vue'

export function useBatchSelection<T extends { id: string | number }>(items: T[]) {
  const selected = ref<Set<string | number>>(new Set())

  function toggle(id: string | number) {
    if (selected.value.has(id)) {
      selected.value.delete(id)
    } else {
      selected.value.add(id)
    }
  }

  function selectAll() {
    selected.value = new Set(items.map((item) => item.id))
  }

  function clear() {
    selected.value.clear()
  }

  function isSelected(id: string | number): boolean {
    return selected.value.has(id)
  }

  const selectedItems = computed(() =>
    items.filter((item) => selected.value.has(item.id))
  )

  const count = computed(() => selected.value.size)
  const allSelected = computed(() => selected.value.size === items.length && items.length > 0)

  return {
    selected: computed(() => Array.from(selected.value)),
    selectedItems,
    count,
    allSelected,
    toggle,
    selectAll,
    clear,
    isSelected,
  }
}
