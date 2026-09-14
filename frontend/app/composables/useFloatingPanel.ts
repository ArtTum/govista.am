import type { Ref } from 'vue'

export function useFloatingPanel(root: Ref<HTMLElement | null>, panel: Ref<HTMLElement | null>, open: Ref<boolean>, placement: () => string, width = 326) {
  const panelStyle = ref<Record<string, string>>({})
  const buttons = () => Array.from(panel.value?.querySelectorAll<HTMLButtonElement>('button:not(:disabled)') || [])
  const close = () => {
    open.value = false
    root.value?.querySelector<HTMLButtonElement>('button')?.focus({ preventScroll: true })
  }
  const position = () => {
    if (!open.value || !root.value || !panel.value) return
    const anchor = root.value.getBoundingClientRect()
    const height = Math.min(panel.value.offsetHeight, window.innerHeight - 28)
    const mobile = window.innerWidth <= 620
    const actualWidth = Math.min(width, window.innerWidth - 28)
    let top = placement() === 'top' ? anchor.top - height - 10 : anchor.bottom + 10
    if (top + height > window.innerHeight - 14) top = anchor.top - height - 10
    top = Math.max(14, Math.min(top, window.innerHeight - height - 14))
    panelStyle.value = {
      top: mobile ? 'auto' : `${Math.round(top)}px`, bottom: mobile ? '14px' : 'auto',
      left: mobile ? '14px' : `${Math.round(Math.max(14, Math.min(anchor.left, window.innerWidth - actualWidth - 14)))}px`,
      right: mobile ? '14px' : 'auto', width: mobile ? 'auto' : `${actualWidth}px`,
      maxHeight: 'calc(100dvh - 28px)', overflowY: 'auto',
    }
  }
  const keydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape') { event.stopPropagation(); event.preventDefault(); close(); return }
    const elements = buttons()
    const index = elements.indexOf(document.activeElement as HTMLButtonElement)
    if (event.key === 'Tab') {
      if (event.shiftKey && index <= 0) { event.preventDefault(); elements.at(-1)?.focus() }
      else if (!event.shiftKey && index === elements.length - 1) { event.preventDefault(); elements[0]?.focus() }
    }
    const day = (event.target as HTMLElement).closest('.date-picker-grid')
    const options = (event.target as HTMLElement).closest('[role="listbox"]')
    if (!day && !options) return
    const candidates = Array.from((day || options)!.querySelectorAll<HTMLButtonElement>('button:not(:disabled)'))
    const current = candidates.indexOf(document.activeElement as HTMLButtonElement)
    const offsets: Record<string, number> = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: day ? -7 : -1, ArrowDown: day ? 7 : 1 }
    const offset = offsets[event.key]
    if (offset !== undefined) {
      event.preventDefault()
      candidates[Math.max(0, Math.min(candidates.length - 1, current + offset))]?.focus()
    }
  }
  const outside = (event: PointerEvent) => {
    const target = event.target as Node
    if (open.value && !root.value?.contains(target) && !panel.value?.contains(target)) open.value = false
  }
  watch(open, async value => {
    if (!value) return
    await nextTick()
    if (!open.value) return
    position()
    const selected = panel.value?.querySelector<HTMLButtonElement>('.is-selected:not(:disabled), .is-today:not(:disabled)')
    ;(selected || buttons()[0])?.focus({ preventScroll: true })
  })
  onMounted(() => {
    document.addEventListener('pointerdown', outside)
    window.addEventListener('resize', position)
    window.addEventListener('scroll', position, true)
  })
  onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', outside)
    window.removeEventListener('resize', position)
    window.removeEventListener('scroll', position, true)
  })
  return { panelStyle, position, close, keydown }
}
