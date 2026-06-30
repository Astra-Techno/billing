import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from './router'
import App from './App.vue'
import './style.css'

const app = createApp(App)
app.use(createPinia())
app.use(router)
app.mount('#app')

// Android WebView LTR fix — only run in WebView (CSS handles desktop)
;(function enforceLtrInputs() {
  const isWebView = document.documentElement.classList.contains('wv-android')
  if (!isWebView) return

  function applyLtr(el, force) {
    if (!el || !el.tagName) return
    const tag = el.tagName
    if (tag !== 'INPUT' && tag !== 'TEXTAREA' && tag !== 'SELECT') return
    if (!force &&
        el.getAttribute('dir') === 'ltr' &&
        el.style.getPropertyValue('unicode-bidi') === 'plaintext') return
    el.setAttribute('dir', 'ltr')
    el.setAttribute('lang', 'en')
    if (tag === 'INPUT') {
      const t = (el.getAttribute('type') || 'text').toLowerCase()
      if (t === 'email' || t === 'tel' || t === 'number' || t === 'password' || t === 'text') {
        el.setAttribute('inputmode', t === 'tel' ? 'tel' : t === 'email' ? 'email' : t === 'number' ? 'numeric' : 'text')
      }
    }
    el.style.setProperty('direction', 'ltr', 'important')
    el.style.setProperty('unicode-bidi', 'plaintext', 'important')
    el.style.setProperty('text-align', 'left', 'important')
    el.style.setProperty('writing-mode', 'horizontal-tb', 'important')
  }

  document.documentElement.setAttribute('dir', 'ltr')
  document.documentElement.setAttribute('lang', 'en')

  document.addEventListener('focusin', e => applyLtr(e.target, true), true)
  document.addEventListener('touchstart', e => {
    const el = e.target
    if (el && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.tagName === 'SELECT')) {
      applyLtr(el, true)
    }
  }, { capture: true, passive: true })
})()
