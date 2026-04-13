import client from './client'

export async function postGate(password: string): Promise<{ token: string }> {
  const res = await client.post('/auth/gate', { password })
  return res.data
}
