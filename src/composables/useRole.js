import { useAuthStore } from '../stores/auth'

// All assignable page permissions (shown as checkboxes when creating staff)
export const PAGE_PERMISSIONS = [
  { key: 'dashboard',        label: 'Dashboard' },
  { key: 'invoices',         label: 'Invoices' },
  { key: 'quotes',           label: 'Quotes' },
  { key: 'expenses',         label: 'Expenses' },
  { key: 'clients',          label: 'Clients' },
  { key: 'products',         label: 'Products' },
  { key: 'payments',         label: 'Payments' },
  { key: 'reports',          label: 'Reports' },
  { key: 'gst',              label: 'GST Returns' },
  { key: 'credit_notes',     label: 'Credit Notes' },
  { key: 'purchase_orders',  label: 'Purchase Orders' },
  { key: 'delivery_challans',label: 'Delivery Challans' },
  { key: 'timesheets',       label: 'Timesheets' },
  { key: 'payroll',          label: 'Payroll' },
]

// Actions that only owner/admin can perform regardless of custom permissions
const ADMIN_ONLY = ['delete', 'cancel', 'settings', 'team']

export function useRole() {
  const auth = useAuthStore()

  function can(action) {
    const role = auth.role

    // Owner & admin always have full access
    if (role === 'owner' || role === 'admin') return true

    // Admin-only actions
    if (ADMIN_ONLY.includes(action)) return false

    // If custom permissions are set, check them
    const perms = auth.permissions
    if (perms) return perms.includes(action)

    // Fallback: no custom permissions = dashboard + invoices only
    return ['dashboard', 'invoices'].includes(action)
  }

  return { role: auth.role, can }
}
