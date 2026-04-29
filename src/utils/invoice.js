// GST status badge
export function statusBadge(status) {
  const map = {
    draft:     'badge-gray',
    sent:      'badge-blue',
    partial:   'badge-yellow',
    paid:      'badge-green',
    overdue:   'badge-red',
    cancelled: 'badge-gray',
  }
  return map[status] || 'badge-gray'
}

export function statusLabel(status) {
  const map = {
    draft:     'Draft',
    sent:      'Sent',
    partial:   'Partial',
    paid:      'Paid',
    overdue:   'Overdue',
    cancelled: 'Cancelled',
  }
  return map[status] || status
}

// Calculate line total with GST
export function calcLine(item, supplyType = 'intra') {
  const qty     = parseFloat(item.quantity  || 0)
  const price   = parseFloat(item.unit_price || 0)
  const discPct = parseFloat(item.discount_pct || 0)
  const gstRate = parseFloat(item.gst_rate  || 0)

  const lineTotal = qty * price
  const discAmt   = lineTotal * (discPct / 100)
  const taxable   = lineTotal - discAmt

  let cgst = 0, sgst = 0, igst = 0
  if (supplyType === 'intra') {
    cgst = sgst = taxable * (gstRate / 2 / 100)
  } else {
    igst = taxable * (gstRate / 100)
  }

  return {
    taxable: round2(taxable),
    cgst: round2(cgst),
    sgst: round2(sgst),
    igst: round2(igst),
    total: round2(taxable + cgst + sgst + igst),
  }
}

export function calcInvoice(items, supplyType = 'intra') {
  let subtotal = 0, cgst = 0, sgst = 0, igst = 0, discount = 0

  for (const item of items) {
    const line = calcLine(item, supplyType)
    subtotal += line.taxable
    cgst     += line.cgst
    sgst     += line.sgst
    igst     += line.igst
    discount += (parseFloat(item.quantity || 0) * parseFloat(item.unit_price || 0)) * ((parseFloat(item.discount_pct || 0)) / 100)
  }

  const tax   = round2(cgst + sgst + igst)
  const raw   = subtotal + tax
  const total = Math.round(raw)
  const roundOff = round2(total - raw)

  return { subtotal: round2(subtotal), cgst: round2(cgst), sgst: round2(sgst), igst: round2(igst), tax, discount: round2(discount), total, roundOff }
}

function round2(n) { return Math.round(n * 100) / 100 }
