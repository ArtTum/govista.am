<script setup>
import { Compass, Loader2, RefreshCw } from '@lucide/vue'
defineProps({ status: String, error: { default: null }, empty: Boolean })
defineEmits(['retry', 'reset'])
const { t } = useLocale()
</script>

<template>
  <div v-if="status === 'pending' || error || empty" class="collection-state" :role="error ? 'alert' : 'status'" aria-live="polite">
    <Loader2 v-if="status === 'pending'" class="spin" :size="28" />
    <RefreshCw v-else-if="error" :size="28" />
    <Compass v-else :size="32" />
    <h2>{{ status === 'pending' ? t('ui.loading') : error ? t('ui.loadError') : t('ui.empty') }}</h2>
    <p v-if="status !== 'pending'">{{ error ? t('ui.loadErrorHint') : t('ui.emptyHint') }}</p>
    <button v-if="error" class="primary-cta" @click="$emit('retry')">{{ t('ui.retry') }}</button>
    <slot v-else-if="empty" />
  </div>
</template>
