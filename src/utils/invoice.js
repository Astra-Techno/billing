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
  let grossSubtotal = 0, itemDiscountTotal = 0, cgst = 0, sgst = 0, igst = 0

  // First pass: compute gross subtotal (before any discounts) and per-item discount totals
  for (const item of items) {
    const qty   = parseFloat(item.quantity || 0)
    const price = parseFloat(item.unit_price || 0)
    const discPct = parseFloat(item.discount_pct || 0)
    const lineGross = qty * price
    const lineDisc = round2(lineGross * (Math.min(discPct, 100) / 100))
    grossSubtotal += lineGross
    itemDiscountTotal += lineDisc
  }

  const afterItemDiscount = round2(grossSubtotal - itemDiscountTotal)

  // Invoice-level discount on amount after item discounts
  const dv = parseFloat(discountValue || 0)
  let invoiceDiscount = 0
  if (discountType === 'percent') {
    invoiceDiscount = round2(afterItemDiscount * (Math.min(dv, 100) / 100))
  } else {
    invoiceDiscount = round2(Math.min(dv, afterItemDiscount))
  }

  const discount = round2(itemDiscountTotal + invoiceDiscount)
  const subtotal = round2(afterItemDiscount - invoiceDiscount)

  // Allocate cents and round each line identically to the persisted API calculation.
  let allocated = 0
  for (const [index, item] of items.entries()) {
    const qty   = parseFloat(item.quantity || 0)
    const price = parseFloat(item.unit_price || 0)
    const discPct = parseFloat(item.discount_pct || 0)
    const gstRate = parseFloat(item.gst_rate || 0)
    const lineGross = qty * price
    const lineAfterItemDisc = lineGross - round2(lineGross * (Math.min(discPct, 100) / 100))
    // Proportion of this line in after-item-discount total
    const ratio = afterItemDiscount > 0 ? lineAfterItemDisc / afterItemDiscount : 0
    const lineTaxable = index === items.length - 1 ? round2(subtotal - allocated) : round2(subtotal * ratio)
    allocated += lineTaxable

    if (supplyType === 'intra') {
      cgst += round2(lineTaxable * (gstRate / 2 / 100))
      sgst += round2(lineTaxable * (gstRate / 2 / 100))
    } else {
      igst += round2(lineTaxable * (gstRate / 100))
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
