const attached = new WeakSet()

export function attachAutoHideScrollbar(root = document) {
  const nodes = root.querySelectorAll?.('.scroll-auto-hide, .split-pane-right, .app-main-scroll') ?? []
  nodes.forEach((el) => {
    if (attached.has(el)) return
    attached.add(el)
    let timer
    el.addEventListener('scroll', () => {
      el.classList.add('is-scrolling')
      clearTimeout(timer)
      timer = setTimeout(() => el.classList.remove('is-scrolling'), 700)
    }, { passive: true })
  })
}
