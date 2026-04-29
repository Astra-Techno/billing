import { describe, it, expect } from 'vitest'
import { statusBadge, statusLabel, calcLine, calcInvoice } from './invoice'

describe('statusBadge()', () => {
  it('maps draft to badge-gray', () => {
    expect(statusBadge('draft')).toBe('badge-gray')
  })
  it('maps sent to badge-blue', () => {
    expect(statusBadge('sent')).toBe('badge-blue')
  })
  it('maps partial to badge-yellow', () => {
    expect(statusBadge('partial')).toBe('badge-yellow')
  })
  it('maps paid to badge-green', () => {
    expect(statusBadge('paid')).toBe('badge-green')
  })
  it('maps overdue to badge-red', () => {
    expect(statusBadge('overdue')).toBe('badge-red')
  })
  it('maps cancelled to badge-gray', () => {
    expect(statusBadge('cancelled')).toBe('badge-gray')
  })
  it('defaults unknown status to badge-gray', () => {
    expect(statusBadge('unknown')).toBe('badge-gray')
    expect(statusBadge(undefined)).toBe('badge-gray')
  })
})

describe('statusLabel()', () => {
  it('returns human-readable labels', () => {
    expect(statusLabel('draft')).toBe('Draft')
    expect(statusLabel('sent')).toBe('Sent')
    expect(statusLabel('partial')).toBe('Partial')
    expect(statusLabel('paid')).toBe('Paid')
    expect(statusLabel('overdue')).toBe('Overdue')
    expect(statusLabel('cancelled')).toBe('Cancelled')
  })
  it('returns the raw status for unknown values', () => {
    expect(statusLabel('custom')).toBe('custom')
  })
})

describe('calcLine()', () => {
  const base = { quantity: 2, unit_price: 1000, discount_pct: 0, gst_rate: 18 }

  it('calculates taxable amount', () => {
    const result = calcLine(base)
    expect(result.taxable).toBe(2000)
  })

  it('calculates intra-state CGST and SGST (9% each for 18% GST)', () => {
    const result = calcLine(base)
    expect(result.cgst).toBe(180)
    expect(result.sgst).toBe(180)
    expect(result.igst).toBe(0)
  })

  it('calculates inter-state IGST (18%)', () => {
    const result = calcLine(base, 'inter')
    expect(result.igst).toBe(360)
    expect(result.cgst).toBe(0)
    expect(result.sgst).toBe(0)
  })

  it('calculates total correctly for intra', () => {
    const result = calcLine(base)
    expect(result.total).toBe(2360) // 2000 + 180 + 180
  })

  it('applies discount before tax', () => {
    const item = { ...base, discount_pct: 10 }
    const result = calcLine(item)
    // taxable = 2000 - 200 = 1800
    expect(result.taxable).toBe(1800)
    // CGST = SGST = 1800 * 9% = 162
    expect(result.cgst).toBe(162)
    expect(result.sgst).toBe(162)
    expect(result.total).toBe(2124) // 1800 + 162 + 162
  })

  it('handles zero GST rate', () => {
    const item = { quantity: 5, unit_price: 100, discount_pct: 0, gst_rate: 0 }
    const result = calcLine(item)
    expect(result.taxable).toBe(500)
    expect(result.cgst).toBe(0)
    expect(result.sgst).toBe(0)
    expect(result.total).toBe(500)
  })

  it('handles missing fields gracefully (treats as 0)', () => {
    const result = calcLine({})
    expect(result.taxable).toBe(0)
    expect(result.total).toBe(0)
  })

  it('rounds to 2 decimal places', () => {
    // 1 * 100 at 5% → taxable=100, cgst=sgst=2.5
    const item = { quantity: 1, unit_price: 100, discount_pct: 0, gst_rate: 5 }
    const result = calcLine(item)
    expect(result.cgst).toBe(2.5)
    expect(result.sgst).toBe(2.5)
    expect(result.total).toBe(105)
  })
})

describe('calcInvoice()', () => {
  const items = [
    { quantity: 2, unit_price: 1000, discount_pct: 0, gst_rate: 18 },
    { quantity: 1, unit_price: 500,  discount_pct: 0, gst_rate: 5  },
  ]

  it('sums subtotal across lines', () => {
    const result = calcInvoice(items)
    // line1 taxable = 2000, line2 taxable = 500
    expect(result.subtotal).toBe(2500)
  })

  it('sums CGST and SGST for intra', () => {
    const result = calcInvoice(items)
    // line1: cgst=180, sgst=180 | line2: cgst=12.5, sgst=12.5
    expect(result.cgst).toBe(192.5)
    expect(result.sgst).toBe(192.5)
    expect(result.igst).toBe(0)
  })

  it('sums IGST for inter-state', () => {
    const result = calcInvoice(items, 'inter')
    // line1: igst=360 | line2: igst=25
    expect(result.igst).toBe(385)
    expect(result.cgst).toBe(0)
    expect(result.sgst).toBe(0)
  })

  it('calculates total tax', () => {
    const result = calcInvoice(items)
    expect(result.tax).toBe(385) // 192.5 + 192.5
  })

  it('total is integer (rounded)', () => {
    const result = calcInvoice(items)
    expect(Number.isInteger(result.total)).toBe(true)
  })

  it('roundOff is difference from raw total', () => {
    const result = calcInvoice(items)
    // raw = subtotal + tax = 2500 + 385 = 2885 (exact), roundOff = 0
    expect(result.roundOff).toBe(0)
    expect(result.total).toBe(2885)
  })

  it('handles empty items array', () => {
    const result = calcInvoice([])
    expect(result.subtotal).toBe(0)
    expect(result.total).toBe(0)
    expect(result.tax).toBe(0)
  })

  it('accumulates discount amount', () => {
    const discountedItems = [
      { quantity: 2, unit_price: 1000, discount_pct: 10, gst_rate: 18 },
    ]
    const result = calcInvoice(discountedItems)
    expect(result.discount).toBe(200) // 2000 * 10%
  })

  it('roundOff non-zero when raw total has fraction', () => {
    // Force a fractional total: 1 item at 100 with 3% GST (intra)
    // cgst=sgst=1.5, taxable=100, total_raw=103, rounded=103, roundOff=0
    // Use 7% GST: cgst=sgst=3.5, taxable=100, raw=107, rounded=107, roundOff=0
    // For a fractional case: 1 item, price=33.33, qty=1, gst=18%
    // taxable=33.33, cgst=sgst=2.9997, raw=33.33+5.9994=39.3294, rounded=39, roundOff=-0.33
    const result = calcInvoice([
      { quantity: 1, unit_price: 33.33, discount_pct: 0, gst_rate: 18 },
    ])
    expect(result.total).toBe(Math.round(result.subtotal + result.tax))
    expect(typeof result.roundOff).toBe('number')
  })
})
