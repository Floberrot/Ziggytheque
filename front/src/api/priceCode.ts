import client from './client'
import type { PriceCode } from '@/types'

export async function getPriceCodes(): Promise<PriceCode[]> {
  const res = await client.get('/price-codes')
  return res.data
}

export async function createPriceCode(payload: {
  code: string
  label: string
  value: number
}): Promise<void> {
  await client.post('/price-codes', payload)
}

export async function updatePriceCode(
  code: string,
  payload: { label: string; value: number },
): Promise<void> {
  await client.patch(`/price-codes/${code}`, payload)
}

export async function deletePriceCode(code: string): Promise<void> {
  await client.delete(`/price-codes/${code}`)
}
