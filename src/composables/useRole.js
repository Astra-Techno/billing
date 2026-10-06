import { useAuthStore } from '../stores/auth'

// All assignable page permissions (shown as checkboxes when creating staff)
export const PAGE_PERMISSIONS = [
  { key: 'dashboard',        label: 'Dashboard' },
  { key: 'invoices',         label: 'Invoices' },
  { key: 'quotes',           label: 'Quotes' },
  { key: 'expenses',         label: 'Expenses' },
  { key: 'clients',          label: 'Clients' },
  { key: 'products',         label: 'Products' },
  { key: 'inventory',        label: 'Stock' },
  { key: 'payments',         label: 'Payments' },
  { key: 'reports',          label: 'Reports' },
  { key: 'gst',              label: 'GST Returns' },
  { key: 'credit_notes',     label: 'Credit Notes' },
  { key: 'purchases',        label: 'Purchases' },
  { key: 'purchase_orders',  label: 'Purchase Orders' },
  { key: 'delivery_challans',label: 'Delivery Challans' },
  { key: 'timesheets',       label: 'Timesheets' },
  { key: 'payroll',          label: 'Payroll' },
]

// Modules that support create/edit/delete actions (others are view-only)
export const ACTION_MODULES = [
  'invoices', 'quotes', 'expenses', 'clients', 'products',
  'credit_notes', 'purchases', 'purchase_orders', 'delivery_challans',
  'inventory', 'timesheets', 'payroll',
]

// Actions that can be granted per module
export const ACTIONS = [
  { key: 'create', label: 'Create' },
  { key: 'edit',   label: 'Edit' },
  { key: 'delete', label: 'Delete' },
]

// Actions that only owner/admin can perform regardless of custom permissions
const ADMIN_ONLY = ['settings', 'team']

export function useRole() {
  const auth = useAuthStore()

  /**
   * Check if current user can perform an action.
   *
   * Usage:
   *   can('invoices')        → view access to invoices page
   *   can('invoices.create') → can create new invoices
   *   can('invoices.edit')   → can edit existing invoices
   *   can('invoices.delete') → can delete invoices
   *   can('settings')        → admin-only
   */
  function can(action) {
    const role = auth.role

    // Owner & admin always have full access
    if (role === 'owner' || role === 'admin') return true

    // Admin-only actions
    if (ADMIN_ONLY.includes(action)) return false

    // If no custom permissions set, default to dashboard view only
    const perms = auth.permissions
    if (!perms) return action === 'dashboard'

    // Dotted permission (e.g., 'invoices.edit') — check exact match
    if (action.includes('.')) return perms.includes(action)

    // Simple module key = view access (page-level)
    return perms.includes(action)
  }

  return { role: auth.role, can }
}
