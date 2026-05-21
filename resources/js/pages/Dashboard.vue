<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { type BreadcrumbItem } from '@/types'
import { Head } from '@inertiajs/vue3'

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
]

const kpis = [
  { label: 'Active Devices', value: '7 / 8', helper: '⚠ 1 warning · 1 offline', tone: 'text-[#f5b96d]' },
  { label: 'Open Orders', value: '12', helper: '↑ +3 in last 30 min', tone: 'text-[#75a86f]' },
  { label: 'Queue Depth', value: '3', helper: 'Background jobs', tone: 'text-[#847b67]' },
  { label: 'Print Failures', value: '0', helper: 'No failures today', tone: 'text-[#847b67]' },
]

const revenueBars = [
  { hour: '10', height: 0 },
  { hour: '11', height: 12 },
  { hour: '12', height: 30 },
  { hour: '13', height: 46 },
  { hour: '14', height: 54 },
  { hour: '15', height: 34 },
  { hour: '16', height: 28 },
  { hour: '17', height: 68 },
  { hour: '18', height: 106 },
  { hour: '19', height: 110, active: true },
  { hour: '20', height: 0 },
  { hour: '21', height: 0 },
]

const queue = [
  { label: 'Incoming', value: 3, dot: 'bg-[#7a9bc4]' },
  { label: 'Grilling', value: 3, dot: 'bg-[#d6a24a]' },
  { label: 'Ready', value: 2, dot: 'bg-[#75a86f]' },
  { label: 'Served', value: 12, dot: 'bg-[#5a5345]' },
]

const sessions = [
  { id: 'SES-0342', table: 'T-04', guests: '4 pax', package: 'Noble Selection', time: '7:28 PM', total: '₱1,996', status: 'confirmed' },
  { id: 'SES-0341', table: 'T-06', guests: '2 pax', package: 'Classic Feast', time: '7:24 PM', total: '₱898', status: 'confirmed' },
  { id: 'SES-0340', table: 'T-01', guests: '6 pax', package: 'Royal Banquet', time: '7:15 PM', total: '₱3,294', status: 'confirmed' },
  { id: 'SES-0339', table: 'T-02', guests: '3 pax', package: 'Noble Selection', time: '6:58 PM', total: '₱1,497', status: 'confirmed' },
]
</script>

<template>
  <Head title="Dashboard" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="space-y-3">
      <header class="flex items-center gap-3">
        <h1 class="text-[20px] font-black tracking-[-0.03em] text-[#f5f1e6]">Dashboard</h1>
        <span class="text-[#6b3622]">·</span>
        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#8d7b60]">Operations Overview</p>
      </header>

      <section class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
        <article
          v-for="kpi in kpis"
          :key="kpi.label"
          class="rounded-lg border border-[#25221c] bg-[#1a1814] p-5 shadow-[0_1px_0_rgba(255,255,255,0.03)_inset,0_1px_2px_rgba(0,0,0,0.4)]"
        >
          <p class="text-[10.5px] font-semibold uppercase tracking-[0.16em] text-[#847b67]">{{ kpi.label }}</p>
          <p class="mt-2 text-[32px] font-black leading-none tracking-[-0.04em] text-[#f5f1e6]">{{ kpi.value }}</p>
          <p class="mt-3 text-[12px] font-semibold" :class="kpi.tone">{{ kpi.helper }}</p>
        </article>
      </section>

      <section class="grid grid-cols-1 gap-3 xl:grid-cols-[minmax(0,1fr)_300px]">
        <article class="min-h-[360px] rounded-lg border border-[#25221c] bg-[#1a1814] p-5 shadow-[0_1px_0_rgba(255,255,255,0.03)_inset,0_1px_2px_rgba(0,0,0,0.4)]">
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-[10.5px] font-semibold uppercase tracking-[0.16em] text-[#847b67]">Hourly Revenue</p>
              <div class="mt-5 flex items-baseline gap-2">
                <p class="text-[24px] font-black tracking-[-0.05em] text-[#f5f1e6]">₱34,200</p>
                <span class="text-[12px] font-semibold text-[#847b67]">today so far</span>
              </div>
            </div>

            <div class="flex overflow-hidden rounded-md border border-[#43392c] bg-[#0d0c0a] text-[11px] font-bold text-[#847b67]">
              <button class="bg-[#25221b] px-4 py-2 text-[#f5f1e6]">Today</button>
              <button class="px-4 py-2">Week</button>
              <button class="px-4 py-2">Month</button>
            </div>
          </div>

          <div class="mt-16 flex h-[132px] items-end gap-1 px-7">
            <div
              v-for="bar in revenueBars"
              :key="bar.hour"
              class="flex flex-1 flex-col items-center justify-end gap-2"
            >
              <div
                class="w-full rounded-t-sm"
                :class="bar.active ? 'bg-[#ffbd70]' : 'bg-[#2a261f]'"
                :style="{ height: `${bar.height}px` }"
              />
              <span class="text-[10px] font-semibold text-[#43392c]">{{ bar.hour }}</span>
            </div>
          </div>
        </article>

        <aside class="rounded-lg border border-[#25221c] bg-[#1a1814] p-4 shadow-[0_1px_0_rgba(255,255,255,0.03)_inset,0_1px_2px_rgba(0,0,0,0.4)]">
          <div class="flex items-center justify-between border-b border-[#25221c] pb-3">
            <p class="text-[10.5px] font-semibold uppercase tracking-[0.16em] text-[#847b67]">Live Queue</p>
            <span class="rounded-full border border-[#6b3622] bg-[#2b1a0b] px-3 py-1 text-[11px] font-bold text-[#ffbd70]">• 7 active</span>
          </div>

          <div class="mt-3 space-y-2">
            <div
              v-for="item in queue"
              :key="item.label"
              class="flex items-center justify-between rounded-md bg-[#25221b] px-3 py-3"
            >
              <div class="flex items-center gap-2">
                <span class="h-2 w-2 rounded-full" :class="item.dot" />
                <span class="text-[13px] font-bold text-[#c5bda9]">{{ item.label }}</span>
              </div>
              <span class="text-[18px] font-black text-[#f5f1e6]">{{ item.value }}</span>
            </div>
          </div>

          <div class="mt-4 border-t border-[#25221c] pt-4">
            <p class="text-[10.5px] font-semibold uppercase tracking-[0.16em] text-[#847b67]">System Health</p>
            <div class="mt-2 flex flex-wrap gap-2">
              <span v-for="item in ['MySQL', 'Redis', 'POS DB', 'Queue']" :key="item" class="rounded-md border border-[#244b24] bg-[#122612] px-2.5 py-1 text-[11px] font-bold text-[#75a86f]">• {{ item }}</span>
            </div>
          </div>
        </aside>
      </section>

      <section class="overflow-hidden rounded-lg border border-[#25221c] bg-[#1a1814] shadow-[0_1px_0_rgba(255,255,255,0.03)_inset,0_1px_2px_rgba(0,0,0,0.4)]">
        <header class="flex items-center justify-between border-b border-[#25221c] px-4 py-4">
          <p class="text-[10.5px] font-semibold uppercase tracking-[0.16em] text-[#847b67]">Recent Sessions</p>
          <button class="text-[12px] font-bold text-[#f5f1e6] hover:text-[#ffbd70]">View all</button>
        </header>

        <div class="overflow-x-auto">
          <table class="w-full min-w-[760px] text-left">
            <thead>
              <tr class="border-b border-[#25221c] text-[10px] font-bold uppercase tracking-[0.14em] text-[#5a5345]">
                <th class="px-4 py-2">Session</th>
                <th class="px-4 py-2">Table</th>
                <th class="px-4 py-2">Guests</th>
                <th class="px-4 py-2">Package</th>
                <th class="px-4 py-2">Time</th>
                <th class="px-4 py-2">Total</th>
                <th class="px-4 py-2">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="session in sessions" :key="session.id" class="border-b border-[#25221c] last:border-0">
                <td class="px-4 py-3 text-[13px] font-bold text-[#ffbd70]">{{ session.id }}</td>
                <td class="px-4 py-3 text-[15px] font-black text-[#f5f1e6]">{{ session.table }}</td>
                <td class="px-4 py-3 text-[13px] font-bold text-[#c5bda9]">{{ session.guests }}</td>
                <td class="px-4 py-3 text-[13px] font-bold text-[#c5bda9]">{{ session.package }}</td>
                <td class="px-4 py-3 text-[13px] font-semibold text-[#847b67]">{{ session.time }}</td>
                <td class="px-4 py-3 text-[15px] font-black text-[#f5f1e6]">{{ session.total }}</td>
                <td class="px-4 py-3">
                  <span class="rounded-full border border-[#6b3622] bg-[#2b1a0b] px-3 py-1 text-[11px] font-bold text-[#ffbd70]">• {{ session.status }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </AppLayout>
</template>
