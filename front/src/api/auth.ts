import client from './client'

export interface User {
  id: string
  email: string
  displayName: string
  role: 'ROLE_USER' | 'ROLE_ADMIN'
  status: 'pending_email_verification' | 'pending_admin_approval' | 'active' | 'disabled'
  notificationChannel: 'email' | 'discord'
  notificationEmail: string | null
  discordWebhookUrl: string | null
}

export async function postLogin(email: string, password: string): Promise<{ token: string }> {
  const res = await client.post('/auth/login', { email, password })
  return res.data
}

export async function postRegister(
  email: string,
  password: string,
  displayName: string,
): Promise<void> {
  await client.post('/auth/register', { email, password, displayName })
}

export async function postVerifyEmail(token: string): Promise<void> {
  await client.post('/auth/verify-email', { token })
}

export async function postRequestPasswordReset(email: string): Promise<void> {
  await client.post('/auth/request-reset', { email })
}

export async function postResetPassword(token: string, newPassword: string): Promise<void> {
  await client.post('/auth/reset-password', { token, newPassword })
}

export async function postGate(password: string): Promise<{ token: string }> {
  const res = await client.post('/auth/gate', { password })
  return res.data
}

export async function getMe(): Promise<User> {
  const res = await client.get('/me')
  return res.data
}

export async function patchNotificationPreferences(
  channel: 'email' | 'discord',
  notificationEmail: string | null,
  discordWebhookUrl: string | null,
): Promise<void> {
  await client.patch('/me/notifications', { channel, notificationEmail, discordWebhookUrl })
}
