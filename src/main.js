import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from './router'
import App from './App.vue'
import './style.css'

const app = createApp(App)
app.use(createPinia())
app.use(router)
app.mount('#app')

// ── Android WebView LTR fix ──────────────────────────────────────────────────
// Android creates the IME InputConnection at the moment an input is focused.
// We must force LTR at that exact moment — not just at mount time.
// 1. focusin fires just before the keyboard opens → re-applies dir+style on tap.
// 2. MutationObserver catches any inputs Vue adds after initial mount.
;(function enforceLtrInputs() {
  function applyLtr(el) {
    if (el.getAttribute('dir') === 'ltr' &&
        el.style.direction === 'ltr') return  // already set, skip
    el.setAttribute('dir', 'ltr')
    el.style.setProperty('direction', 'ltr', 'important')
    el.style.setProperty('unicode-bidi', 'embed', 'important')
    el.style.setProperty('text-align', 'left', 'important')
  }
  function applyLtrAll(root) {
    ;(root.querySelectorAll ? root : document)
      .querySelectorAll('input,textarea,select')
      .forEach(applyLtr)
  }

  // Apply to all existing inputs after mount
  applyLtrAll(document)

  // Re-apply at the exact moment the user taps an input (before IME opens)
  document.addEventListener('focusin', e => {
    const el = e.target
    if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.tagName === 'SELECT') {
      applyLtr(el)
    }
  }, true)

  // Watch for inputs Vue adds later (route changes, dynamic components)
  new MutationObserver(muts => {
    muts.forEach(m => m.addedNodes.forEach(n => {
      if (n.nodeType !== 1) return
      const tag = n.nodeName
      if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') {
        applyLtr(n)
      } else if (n.querySelectorAll) {
        applyLtrAll(n)
      }
    }))
  }).observe(document.body, { childList: true, subtree: true })
})()
