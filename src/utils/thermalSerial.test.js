import { afterEach, expect, test, vi } from 'vitest'
import { sendWebSerial, testReceiptBytes } from './thermalSerial'

afterEach(() => vi.unstubAllGlobals())

test('asks for the serial port before loading invoice bytes and closes it after printing', async () => {
  const events = []
  const writer = {
    write: vi.fn(async bytes => { events.push('write'); expect(bytes).toEqual(testReceiptBytes()) }),
    releaseLock: vi.fn(() => events.push('release')),
  }
  const port = {
    open: vi.fn(async () => events.push('open')),
    writable: { getWriter: () => writer },
    close: vi.fn(async () => events.push('close')),
  }
  vi.stubGlobal('navigator', { serial: { requestPort: vi.fn(async () => { events.push('select'); return port }) } })

  await sendWebSerial(async () => { events.push('load'); return testReceiptBytes() })

  expect(events).toEqual(['select', 'load', 'open', 'write', 'release', 'close'])
})
