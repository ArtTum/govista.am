<script setup>
import { Check, ChevronDown, UsersRound } from '@lucide/vue'

const props = defineProps({
  options: {
    type: Array,
    default: () => Array.from({ length: 10 }, (_, index) => index + 1),
  },
  placement: { type: String, default: 'bottom' },
})

const model = defineModel({ type: [Number, String], default: 2 })
const { locale, t } = useLocale()
const root = ref(null)
const panel = ref(null)
const isOpen = ref(false)
const isMobilePanel = ref(false)
const resolvedPlacement = ref(props.placement)
const panelStyle = ref({})

const copy = computed(() => ({
  hy: { title: 'Քանի՞ հյուր է մեկնելու', hint: 'Ընտրեք հյուրերի քանակը', guest: 'հյուր' },
  ru: { title: 'Сколько гостей едет?', hint: 'Выберите количество гостей', guest: 'гостей' },
  en: { title: 'How many guests?', hint: 'Choose the number of guests', guest: 'guests' },
}[locale.value] || {
  title: 'How many guests?',
  hint: 'Choose the number of guests',
  guest: 'guests',
}))

function valueOf(option) {
  return typeof option === 'object' && option !== null ? option.value : option
}

function labelOf(option) {
  if (typeof option === 'object' && option !== null && option.label) return option.label
  return String(valueOf(option))
}

function guestWord(value) {
  const count = Number.parseInt(String(value), 10)
  if (locale.value === 'en') return count === 1 ? 'guest' : 'guests'
  if (locale.value === 'ru') {
    if (count === 1) return 'гость'
    if (count >= 2 && count <= 4) return 'гостя'
    return 'гостей'
  }
  return copy.value.guest
}

function isSelected(option) {
  return String(valueOf(option)) === String(model.value)
}

function selectOption(option) {
  model.value = valueOf(option)
  isOpen.value = false
}

function positionPanel() {
  if (!isOpen.value || !root.value || !panel.value) return

  const triggerRect = root.value.getBoundingClientRect()
  const panelRect = panel.value.getBoundingClientRect()
  const viewportWidth = window.innerWidth
  const viewportHeight = window.innerHeight

  if (viewportWidth <= 620) {
    isMobilePanel.value = true
    panelStyle.value = {
      top: 'auto',
      right: '14px',
      bottom: '14px',
      left: '14px',
      width: 'auto',
    }
    return
  }

  isMobilePanel.value = false
  const panelWidth = 286
  const panelHeight = (panelRect.height || 367) + 18
  const gap = 12
  const edge = 14
  const topSpace = triggerRect.top - gap
  const bottomSpace = viewportHeight - triggerRect.bottom - gap

  let placement = props.placement
  if (placement === 'top' && topSpace < panelHeight && bottomSpace > topSpace) placement = 'bottom'
  if (placement === 'bottom' && bottomSpace < panelHeight && topSpace > bottomSpace) placement = 'top'

  let top = placement === 'top'
    ? triggerRect.top - panelHeight - gap
    : triggerRect.bottom + gap

  top = Math.max(edge, Math.min(top, viewportHeight - panelHeight - edge))
  const left = Math.max(edge, Math.min(triggerRect.right - panelWidth, viewportWidth - panelWidth - edge))

  resolvedPlacement.value = placement
  panelStyle.value = {
    top: `${Math.round(top)}px`,
    right: 'auto',
    bottom: 'auto',
    left: `${Math.round(left)}px`,
    width: `${panelWidth}px`,
  }
}

async function toggleSelect() {
  isOpen.value = !isOpen.value
  if (isOpen.value) {
    await nextTick()
    positionPanel()
  }
}

function onPointerDown(event) {
  if (
    isOpen.value
    && root.value
    && !root.value.contains(event.target)
    && (!panel.value || !panel.value.contains(event.target))
  ) isOpen.value = false
}

function onKeyDown(event) {
  if (event.key === 'Escape') isOpen.value = false
}

onMounted(() => {
  document.addEventListener('pointerdown', onPointerDown)
  document.addEventListener('keydown', onKeyDown)
  window.addEventListener('resize', positionPanel)
  window.addEventListener('scroll', positionPanel, true)
})

onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', onPointerDown)
  document.removeEventListener('keydown', onKeyDown)
  window.removeEventListener('resize', positionPanel)
  window.removeEventListener('scroll', positionPanel, true)
})
</script>

<template>
  <div ref="root" class="guest-select" :class="{ 'is-open': isOpen }">
    <button
      type="button"
      class="guest-select-trigger"
      :aria-label="`${t('forms.guests')}: ${model} ${guestWord(model)}`"
      :aria-expanded="isOpen"
      aria-haspopup="listbox"
      @click="toggleSelect"
    >
      <span><strong>{{ model }}</strong> {{ guestWord(model) }}</span>
      <ChevronDown :size="16" />
    </button>

    <Teleport to="body">
      <Transition name="select-popover">
        <div
          v-if="isOpen"
          ref="panel"
          class="guest-select-panel"
          :class="[`guest-select-panel--${resolvedPlacement}`, { 'is-mobile': isMobilePanel }]"
          :style="panelStyle"
        >
          <div class="guest-select-heading">
            <span><UsersRound :size="18" /></span>
            <div>
              <strong>{{ copy.title }}</strong>
              <small>{{ copy.hint }}</small>
            </div>
          </div>

          <div class="guest-select-options" role="listbox" :aria-label="t('forms.guests')">
            <button
              v-for="option in options"
              :key="String(valueOf(option))"
              type="button"
              role="option"
              :aria-selected="isSelected(option)"
              :class="{ 'is-selected': isSelected(option) }"
              @click="selectOption(option)"
            >
              <span class="guest-option-number">{{ labelOf(option) }}</span>
              <span class="guest-option-copy">{{ guestWord(valueOf(option)) }}</span>
              <Check v-if="isSelected(option)" :size="15" />
            </button>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
