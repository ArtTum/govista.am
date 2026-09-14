import type { Ref } from 'vue'

export function useModalFocus(open: Ref<boolean>, panel: Ref<HTMLElement | null>, close: () => void, bodyClass = 'drawer-open', inertSelectors = '#__nuxt') {
  let previousFocus: HTMLElement | null = null
  let backgrounds: { element: HTMLElement, inert: boolean }[] = []
  const focusables = () => Array.from(panel.value?.querySelectorAll<HTMLElement>('a[href], button:not(:disabled), input:not(:disabled), textarea:not(:disabled), select:not(:disabled), [tabindex="0"]') || []).filter(element => element.getClientRects().length)
  const release = () => {
    document.body.classList.remove(bodyClass)
    backgrounds.forEach(({ element, inert }) => { element.inert = inert })
    backgrounds = []
    if (previousFocus?.isConnected) previousFocus.focus({ preventScroll: true })
    previousFocus = null
  }
  const keydown = (event: KeyboardEvent) => {
    if (!open.value || event.defaultPrevented) return
    // Teleported date/guest controls manage their own keyboard navigation.
    if ((event.target as HTMLElement)?.closest('[data-overlay-popover]')) return
    if (event.key === 'Escape') { event.preventDefault(); close(); return }
    if (event.key !== 'Tab') return
    const elements = focusables()
    const first = elements[0]
    const last = elements.at(-1)
    if (!first) { event.preventDefault(); panel.value?.focus(); return }
    if (event.shiftKey && (document.activeElement === first || !panel.value?.contains(document.activeElement))) {
      event.preventDefault(); last?.focus()
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault(); first.focus()
    }
  }
  watch(open, async value => {
    if (!import.meta.client) return
    if (!value) { release(); return }
    previousFocus = document.activeElement as HTMLElement
    document.body.classList.add(bodyClass)
    backgrounds = Array.from(document.querySelectorAll<HTMLElement>(inertSelectors)).map(element => ({ element, inert: element.inert }))
    backgrounds.forEach(({ element }) => { element.inert = true })
    await nextTick()
    if (open.value) (focusables()[0] || panel.value)?.focus({ preventScroll: true })
  }, { flush: 'post' })
  onMounted(() => document.addEventListener('keydown', keydown))
  onBeforeUnmount(() => { document.removeEventListener('keydown', keydown); if (open.value) release() })
}
