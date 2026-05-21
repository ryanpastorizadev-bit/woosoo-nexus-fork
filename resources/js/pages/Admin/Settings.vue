<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'
import { reactive, ref } from 'vue'
import { toast } from 'vue-sonner'
import { type BreadcrumbItem } from '@/types'

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Settings', href: '#' },
]

const defaults = {
  posSystem: 'Krypton POS',
  apiBaseUrl: 'https://nexus.woosoo.ph/api',
  websocketUrl: 'wss://nexus.woosoo.ph:8080',
}

const settings = reactive({ ...defaults })
const saving = ref(false)

function resetDefaults() {
  Object.assign(settings, defaults)
  toast.info('Defaults restored locally')
}

function saveSettings() {
  saving.value = true

  window.setTimeout(() => {
    saving.value = false
    toast.success('Settings saved locally')
  }, 250)
}
</script>

<template>
  <Head title="Settings" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="max-w-[640px] space-y-5">
      <section class="overflow-hidden rounded-lg border border-[#25221c] bg-[#1a1814] shadow-[0_1px_0_rgba(255,255,255,0.03)_inset,0_1px_2px_rgba(0,0,0,0.4)]">
        <header class="border-b border-[#25221c] px-5 py-4">
          <h1 class="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#a68f68]">
            Integrations
          </h1>
        </header>

        <div class="space-y-4 px-5 py-3.5">
          <div class="space-y-2">
            <label for="posSystem" class="block text-[10.5px] font-semibold uppercase tracking-[0.14em] text-[#8d7b60]">
              POS System Name
            </label>
            <input
              id="posSystem"
              v-model="settings.posSystem"
              type="text"
              class="h-8 w-full rounded-md border border-[#43392c] bg-[#0b0a08] px-2.5 text-[13px] font-semibold text-[#f5f1e6] outline-none transition focus:border-[#f6b56d] focus:ring-2 focus:ring-[#f6b56d]/20"
            />
          </div>

          <div class="space-y-2">
            <label for="apiBaseUrl" class="block text-[10.5px] font-semibold uppercase tracking-[0.14em] text-[#8d7b60]">
              API Base URL
            </label>
            <input
              id="apiBaseUrl"
              v-model="settings.apiBaseUrl"
              type="url"
              class="h-8 w-full rounded-md border border-[#43392c] bg-[#0b0a08] px-2.5 text-[13px] font-semibold text-[#f5f1e6] outline-none transition focus:border-[#f6b56d] focus:ring-2 focus:ring-[#f6b56d]/20"
            />
          </div>

          <div class="space-y-2">
            <label for="websocketUrl" class="block text-[10.5px] font-semibold uppercase tracking-[0.14em] text-[#8d7b60]">
              WebSocket URL
            </label>
            <input
              id="websocketUrl"
              v-model="settings.websocketUrl"
              type="url"
              class="h-8 w-full rounded-md border border-[#43392c] bg-[#0b0a08] px-2.5 text-[13px] font-semibold text-[#f5f1e6] outline-none transition focus:border-[#f6b56d] focus:ring-2 focus:ring-[#f6b56d]/20"
            />
          </div>
        </div>
      </section>

      <div class="flex items-center justify-end gap-6">
        <button
          type="button"
          class="text-[12px] font-semibold text-[#f5f1e6] transition hover:text-[#f6b56d]"
          @click="resetDefaults"
        >
          Reset to Defaults
        </button>

        <button
          type="button"
          class="inline-flex h-8 items-center gap-2 rounded-md bg-[#ffbd70] px-4 text-[13px] font-semibold text-[#24150b] shadow-[0_1px_0_rgba(255,255,255,0.22)_inset] transition hover:bg-[#ffc984] disabled:cursor-not-allowed disabled:opacity-70"
          :disabled="saving"
          @click="saveSettings"
        >
          <span aria-hidden="true">✓</span>
          {{ saving ? 'Saving…' : 'Save Settings' }}
        </button>
      </div>
    </div>
  </AppLayout>
</template>
