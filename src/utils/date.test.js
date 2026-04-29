import { describe, it, expect } from 'vitest'
import { fmtDate, fmtDateShort, today, addDays, isOverdue } from './date'

describe('fmtDate()', () => {
  it('formats ISO date to DD/MM/YYYY', () => {
    // en-IN locale: day/month/year with slashes
    const result = fmtDate('2025-03-31')
    expect(result).toContain('31')
    expect(result).toContain('03')
    expect(result).toContain('2025')
  })

  it('returns dash for null', () => {
    expect(fmtDate(null)).toBe('—')
  })

  it('returns dash for empty string', () => {
    expect(fmtDate('')).toBe('—')
  })

  it('returns dash for invalid date', () => {
    expect(fmtDate('not-a-date')).toBe('—')
  })
})

describe('fmtDateShort()', () => {
  it('formats ISO date with short month name', () => {
    const result = fmtDateShort('2025-01-15')
    expect(result).toContain('15')
    expect(result).toContain('2025')
    // Month should be abbreviated (Jan, Feb, etc.)
    expect(result).toMatch(/Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec/)
  })

  it('returns dash for null', () => {
    expect(fmtDateShort(null)).toBe('—')
  })

  it('returns dash for undefined', () => {
    expect(fmtDateShort(undefined)).toBe('—')
  })

  it('returns dash for invalid date string', () => {
    expect(fmtDateShort('garbage')).toBe('—')
  })
})

describe('today()', () => {
  it('returns a string in YYYY-MM-DD format', () => {
    const result = today()
    expect(result).toMatch(/^\d{4}-\d{2}-\d{2}$/)
  })

  it('matches current date', () => {
    const result = today()
    const now = new Date().toISOString().split('T')[0]
    expect(result).toBe(now)
  })
})

describe('addDays()', () => {
  it('adds days to a date', () => {
    expect(addDays('2025-01-01', 30)).toBe('2025-01-31')
  })

  it('crosses month boundary', () => {
    expect(addDays('2025-01-31', 1)).toBe('2025-02-01')
  })

  it('crosses year boundary', () => {
    expect(addDays('2024-12-31', 1)).toBe('2025-01-01')
  })

  it('adding 0 days returns same date', () => {
    expect(addDays('2025-06-15', 0)).toBe('2025-06-15')
  })

  it('returns YYYY-MM-DD format', () => {
    expect(addDays('2025-03-01', 15)).toMatch(/^\d{4}-\d{2}-\d{2}$/)
  })
})

describe('isOverdue()', () => {
  it('returns false for null', () => {
    expect(isOverdue(null)).toBe(false)
  })

  it('returns false for undefined', () => {
    expect(isOverdue(undefined)).toBe(false)
  })

  it('returns true for a past date', () => {
    expect(isOverdue('2020-01-01')).toBe(true)
  })

  it('returns false for a future date', () => {
    expect(isOverdue('2099-12-31')).toBe(false)
  })
})
