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
// Android WebView positions the IME cursor based on the dir= DOM attribute,
// not CSS direction. We must set it on every input/textarea/select explicitly.
// This runs after Vue mounts and watches for any new elements Vue adds later.
;(function enforceLtrInputs() {
  function applyLtr(root) {
    ;(root.querySelectorAll ? root : document).querySelectorAll('input,textarea,select').forEach(el => {
      el.setAttribute('dir', 'ltr')
    })
  }
  applyLtr(document)
  new MutationObserver(muts => {
    muts.forEach(m => m.addedNodes.forEach(n => {
      if (n.nodeType !== 1) return
      const tag = n.nodeName
      if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') {
        n.setAttribute('dir', 'ltr')
      } else {
        applyLtr(n)
      }
    }))
  }).observe(document.body, { childList: true, subtree: true })
})()
