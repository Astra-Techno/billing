export function canUseWebSerial() {
  return window.isSecureContext && 'serial' in navigator
}

export function decodeReceiptBytes(base64) {
  const raw = atob(base64)
  return Uint8Array.from(raw, char => char.charCodeAt(0))
}

export function testReceiptBytes() {
  return new TextEncoder().encode('\x1b\x40\x1b\x61\x01AI Billing\nSC588 printer ready\n\n\n')
}

export async function sendWebSerial(bytesOrLoad) {
  // Reuse a previously authorized port to skip the picker dialog.
  // Falls back to requestPort() (shows picker) on first use or if no ports are saved.
  const ports = await navigator.serial.getPorts()
  const port = ports.length > 0 ? ports[0] : await navigator.serial.requestPort()
  let opened = false
  try {
    const bytes = typeof bytesOrLoad === 'function' ? await bytesOrLoad() : bytesOrLoad
    await port.open({ baudRate: 9600 })
    opened = true
    const writer = port.writable.getWriter()
    try { await writer.write(bytes) } finally { writer.releaseLock() }
  } finally {
    if (opened) await port.close()
  }
}
