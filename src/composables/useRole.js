import { useAuthStore } from '../stores/auth'

const PERMISSIONS = {
  // Actions
  delete:   ['owner', 'admin'],
  cancel:   ['owner', 'admin'],
  settings: ['owner', 'admin'],
  team:     ['owner', 'admin'],

  // Pages
  dashboard:        ['owner', 'admin', 'accountant', 'staff'],
  invoices:         ['owner', 'admin', 'accountant', 'staff'],
  quotes:           ['owner', 'admin', 'accountant'],
  expenses:         ['owner', 'admin', 'accountant'],
  clients:          ['owner', 'admin', 'accountant', 'staff'],
  products:         ['owner', 'admin', 'accountant', 'staff'],
  payments:         ['owner', 'admin', 'accountant'],
  reports:          ['owner', 'admin', 'accountant'],
  gst:              ['owner', 'admin', 'accountant'],
  credit_notes:     ['owner', 'admin', 'accountant'],
  purchase_orders:  ['owner', 'admin', 'accountant'],
  delivery_challans:['owner', 'admin', 'accountant', 'staff'],
  payroll:          ['owner', 'admin'],
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
