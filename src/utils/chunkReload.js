const RELOAD_KEY = 'app_chunk_reload'

const CHUNK_ERROR_RE =
  /Failed to fetch dynamically imported module|Importing a module script failed|Loading chunk [\d]+ failed|error loading dynamically imported module|Unable to preload CSS/i

export function isChunkLoadError(err) {
  const msg = err?.message || String(err ?? '')
  return CHUNK_ERROR_RE.test(msg)
}

/** Reload once (twice max per session) to pick up new assets after deployment. */
export function reloadForStaleChunk() {
  const count = parseInt(sessionStorage.getItem(RELOAD_KEY) || '0', 10)
  if (count >= 2) return false

  sessionStorage.setItem(RELOAD_KEY, String(count + 1))

  const url = new URL(window.location.href)
  url.searchParams.set('_v', String(Date.now()))
  window.location.replace(url.toString())
  return true
}

export function clearChunkReloadCounter() {
  sessionStorage.removeItem(RELOAD_KEY)
}

export function installChunkReloadHandlers() {
  window.addEventListener('vite:preloadError', (e) => {
    e.preventDefault()
    reloadForStaleChunk()
  })

  window.addEventListener('unhandledrejection', (e) => {
    if (isChunkLoadError(e.reason)) {
      e.preventDefault()
      reloadForStaleChunk()
    }
  })

  window.addEventListener('load', clearChunkReloadCounter)
}
