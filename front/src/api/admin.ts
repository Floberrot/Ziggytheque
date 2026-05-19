import client from './client'
import type { User } from './auth'

export interface UserListParams {
  search?: string
  status?: string
  page?: number
  limit?: number
}

export interface UserListResult {
  items: User[]
  total: number
  page: number
  limit: number
}

export async function getUsers(params: UserListParams = {}): Promise<UserListResult> {
  const res = await client.get('/admin/users', { params })
  return res.data
}

export async function getUser(id: string): Promise<User> {
  const res = await client.get(`/admin/users/${id}`)
  return res.data
}

export interface UpdateUserPayload {
  displayName?: string | null
  status?: User['status'] | null
  notificationChannel?: User['notificationChannel'] | null
  notificationEmail?: string | null
  discordWebhookUrl?: string | null
}

export async function patchUser(id: string, payload: UpdateUserPayload): Promise<User> {
  const res = await client.patch(`/admin/users/${id}`, payload)
  return res.data
}

export async function approveUser(id: string): Promise<void> {
  await client.post(`/admin/users/${id}/approve`)
}

export async function deleteUser(id: string): Promise<void> {
  await client.delete(`/admin/users/${id}`)
}

export async function generateResetLink(id: string): Promise<{ resetLink: string }> {
  const res = await client.post(`/admin/users/${id}/reset-link`)
  return res.data
}
