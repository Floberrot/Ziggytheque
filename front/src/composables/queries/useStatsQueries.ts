import { useQuery } from '@tanstack/vue-query'
import * as api from '@/api/stats'

export function useStats() {
  return useQuery({
    queryKey: ['stats'],
    queryFn: () => api.getStats(),
  })
}
