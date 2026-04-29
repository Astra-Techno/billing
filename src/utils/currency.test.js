import { describe, it, expect } from 'vitest'
import { inr, inrCompact } from './currency'

describe('inr()', () => {
  it('formats a whole number with 2 decimals', () => {
    expect(inr(1000)).toBe('₹1,000.00')
  })

  it('formats with Indian lakh/crore grouping', () => {
    // 150000 → ₹1,50,000.00 in en-IN locale
    const result = inr(150000)
    expect(result.startsWith('₹')).toBe(true)
    expect(result).toContain('50,000')
  })

  it('returns ₹0 for null', () => {
    expect(inr(null)).toBe('₹0')
  })

  it('returns ₹0 for undefined', () => {
    expect(inr(undefined)).toBe('₹0')
  })

  it('returns ₹0 for empty string', () => {
    expect(inr('')).toBe('₹0')
  })

  it('returns ₹0 for NaN string', () => {
    expect(inr('abc')).toBe('₹0')
  })

  it('parses numeric strings', () => {
    expect(inr('500')).toBe('₹500.00')
  })

  it('respects custom decimals param', () => {
    expect(inr(100, 0)).toBe('₹100')
  })

  it('handles zero', () => {
    expect(inr(0)).toBe('₹0.00')
  })

  it('handles negative numbers', () => {
    const result = inr(-500)
    expect(result.startsWith('-₹') || result.startsWith('₹-')).toBe(true)
  })
})

describe('inrCompact()', () => {
  it('formats crores (≥ 1 Cr)', () => {
    expect(inrCompact(10000000)).toBe('₹1.00 Cr')
  })

  it('formats above 1 Cr correctly', () => {
    expect(inrCompact(25000000)).toBe('₹2.50 Cr')
  })

  it('formats lakhs (≥ 1L, < 1Cr)', () => {
    expect(inrCompact(100000)).toBe('₹1.00 L')
  })

  it('formats lakhs with decimals', () => {
    expect(inrCompact(150000)).toBe('₹1.50 L')
  })

  it('formats thousands (≥ 1K, < 1L)', () => {
    expect(inrCompact(5000)).toBe('₹5.0K')
  })

  it('formats sub-thousand as plain inr', () => {
    const result = inrCompact(999)
    expect(result).toBe('₹999')
  })

  it('handles zero', () => {
    expect(inrCompact(0)).toBe('₹0')
  })

  it('handles null/undefined as 0', () => {
    expect(inrCompact(null)).toBe('₹0')
    expect(inrCompact(undefined)).toBe('₹0')
  })
})
