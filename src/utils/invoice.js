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
  const gstRate = parseFloat(item.gst_rate  || 0)

  const taxable = qty * price

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

export function calcInvoice(items, supplyType = 'intra', discountType = 'percent', discountValue = 0) {
  let grossSubtotal = 0, cgst = 0, sgst = 0, igst = 0

  for (const item of items) {
    const qty   = parseFloat(item.quantity || 0)
    const price = parseFloat(item.unit_price || 0)
    grossSubtotal += qty * price
  }

  // Invoice-level discount on gross subtotal
  const dv = parseFloat(discountValue || 0)
  let discount = 0
  if (discountType === 'percent') {
    discount = round2(grossSubtotal * (Math.min(dv, 100) / 100))
  } else {
    discount = round2(Math.min(dv, grossSubtotal))
  }

  const subtotal = round2(grossSubtotal - discount)

  // Calculate tax on discounted subtotal, proportionally per line
  for (const item of items) {
    const qty   = parseFloat(item.quantity || 0)
    const price = parseFloat(item.unit_price || 0)
    const gstRate = parseFloat(item.gst_rate || 0)
    const lineGross = qty * price
    // Proportion of this line in gross subtotal
    const ratio = grossSubtotal > 0 ? lineGross / grossSubtotal : 0
    const lineTaxable = subtotal * ratio

    if (supplyType === 'intra') {
      cgst += lineTaxable * (gstRate / 2 / 100)
      sgst += lineTaxable * (gstRate / 2 / 100)
    } else {
      igst += lineTaxable * (gstRate / 100)
    }
  }

  cgst = round2(cgst); sgst = round2(sgst); igst = round2(igst)
  const tax   = round2(cgst + sgst + igst)
  const raw   = subtotal + tax
  const total = Math.round(raw)
  const roundOff = round2(total - raw)

  return { grossSubtotal: round2(grossSubtotal), subtotal, cgst, sgst, igst, tax, discount, total, roundOff }
}

function round2(n) { return Math.round(n * 100) / 100 }
