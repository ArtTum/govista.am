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
const panelId = useId()
const { panelStyle, close, keydown } = useFloatingPanel(root, panel, isOpen, () => props.placement, 286)

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
  close()
}

function toggleSelect() { isOpen.value = !isOpen.value }
</script>

<template>
  <div ref="root" class="guest-select" :class="{ 'is-open': isOpen }">
    <button
      type="button"
      class="guest-select-trigger"
      :aria-label="`${t('forms.guests')}: ${model} ${guestWord(model)}`"
      :aria-expanded="isOpen"
      :aria-controls="isOpen ? panelId : undefined"
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
          :id="panelId"
          data-overlay-popover
          @keydown="keydown"
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
