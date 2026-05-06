import { useAuthStore } from '../stores/auth'

const PERMISSIONS = {
  delete:   ['owner', 'admin'],
  cancel:   ['owner', 'admin'],
  settings: ['owner', 'admin'],
  reports:  ['owner', 'admin', 'accountant'],
  payments: ['owner', 'admin', 'accountant'],
  team:     ['owner', 'admin'],
}

export function useRole() {
  const auth = useAuthStore()

  function can(action) {
    const allowed = PERMISSIONS[action]
    if (!allowed) return true
    return allowed.includes(auth.role)
  }

  return { role: auth.role, can }
}
