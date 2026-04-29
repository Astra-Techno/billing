// Format date to DD/MM/YYYY (Indian standard)
export function fmtDate(date) {
  if (!date) return '—'
  const d = new Date(date)
  if (isNaN(d)) return '—'
  return d.toLocaleDateString('en-IN', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

export function fmtDateShort(date) {
  if (!date) return '—'
  const d = new Date(date)
  if (isNaN(d)) return '—'
  return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
}

export function today() {
  return new Date().toISOString().split('T')[0]
}

export function addDays(date, days) {
  const d = new Date(date)
  d.setDate(d.getDate() + days)
  return d.toISOString().split('T')[0]
}

export function isOverdue(dueDate) {
  if (!dueDate) return false
  return new Date(dueDate) < new Date()
}
