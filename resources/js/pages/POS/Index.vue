<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AppLayout from '@/layouts/AppLayout.vue'
import { type BreadcrumbItem } from '@/types'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { MonitorSmartphone, Circle, LoaderCircle, TableProperties, ReceiptText } from 'lucide-vue-next'

interface PosTerminal {
    id: string
    name: string
    type: string
    ip_address: string | null
    port: number | null
    is_active: number | boolean
    terminal_session_id: string | null
    session_id: string | null
    terminal_session_opened_at: string | null
    terminal_session_closed_at: string | null
    session_closed_at: string | null
    open_orders_count: number | string
}

interface PosTable {
    id: string
    name: string
    status: string
    is_available: number | boolean
    is_locked: number | boolean
    table_group_id: string
    order_created_in: string | null
    open_orders_count: number | string
    is_occupied: number | boolean
}

interface PosOrder {
    id: string
    reference: string
    date_time_opened: string
    guest_count: number | string
    terminal_id: string
    total_amount: number | string
    paid_amount: number | string
    is_settled: number | boolean
    resetable_transaction_number: string | null
    table_names: string | null
}

const props = defineProps<{
    title: string
    description: string
    terminals: PosTerminal[]
    tables: PosTable[]
    currentSession: {
        id: string
        date_time_opened: string
        date_time_closed: string | null
    } | null
}>()

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'POS',
        href: route('pos.index'),
    },
]

const pageTitle = computed(() => props.title || 'POS')
const terminals = computed<PosTerminal[]>(() => (Array.isArray(props.terminals) ? props.terminals : []))
const currentSession = computed(() => props.currentSession ?? null)

const selectedTerminalId = ref<string | null>(terminals.value[0]?.id ?? null)
const tables = ref<PosTable[]>(Array.isArray(props.tables) ? props.tables : [])
const tablesLoading = ref(false)
const tablesError = ref<string | null>(null)

const showOrdersModal = ref(false)
const selectedTable = ref<PosTable | null>(null)
const selectedOrders = ref<PosOrder[]>([])
const ordersLoading = ref(false)
const ordersError = ref<string | null>(null)
const actionLoading = ref(false)

const addGuestCount = ref<number>(2)
const addReference = ref<string>('')

const totalTerminals = computed(() => terminals.value.length)
const activeTerminals = computed(() => terminals.value.filter((terminal) => Boolean(Number(terminal.is_active))).length)
const totalOpenOrders = computed(() =>
    terminals.value.reduce((total, terminal) => total + Number(terminal.open_orders_count || 0), 0)
)

const selectedTerminal = computed(() =>
    terminals.value.find((terminal) => String(terminal.id) === String(selectedTerminalId.value)) ?? null
)

const occupiedTables = computed(() => tables.value.filter((table) => Boolean(Number(table.is_occupied))).length)

const currentSessionStatus = computed(() => {
    if (!currentSession.value) {
        return 'No Session'
    }

    return currentSession.value.date_time_closed ? 'Closed' : 'Open'
})

const readJsonPayload = async (response: Response): Promise<any> => {
    const contentType = response.headers.get('content-type') || ''

    if (contentType.includes('application/json')) {
        return await response.json()
    }

    const text = await response.text()
    return {
        success: false,
        message: text?.slice(0, 200) || 'Unexpected non-JSON response from server.',
    }
}

const formatMoney = (value: number | string): string => {
    const numeric = Number(value || 0)
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2,
    }).format(Number.isFinite(numeric) ? numeric : 0)
}

const formatDateTime = (value: string | null | undefined): string => {
    if (!value) {
        return '—'
    }

    const date = new Date(value)
    if (Number.isNaN(date.getTime())) {
        return value
    }

    return date.toLocaleString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    })
}

const getCsrfToken = (): string => {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    return token || ''
}

const loadTablesForTerminal = async (terminalId: string) => {
    selectedTerminalId.value = terminalId
    tablesLoading.value = true
    tablesError.value = null

    try {
        const response = await fetch(route('pos.terminal.tables', { terminalId }), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
        })

        const payload = await readJsonPayload(response)

        if (!response.ok || !payload?.success) {
            throw new Error(payload?.message || 'Failed to load tables for selected terminal.')
        }

        tables.value = Array.isArray(payload.tables) ? payload.tables : []
    } catch (error: any) {
        tablesError.value = error?.message || 'Unable to load tables from Krypton.'
    } finally {
        tablesLoading.value = false
    }
}

const openTableOrders = async (table: PosTable) => {
    if (!selectedTerminalId.value) {
        return
    }

    selectedTable.value = table
    selectedOrders.value = []
    ordersError.value = null
    ordersLoading.value = true
    showOrdersModal.value = true

    try {
        const response = await fetch(route('pos.table.orders', { terminalId: selectedTerminalId.value, tableId: table.id }), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
        })

        const payload = await readJsonPayload(response)

        if (!response.ok || !payload?.success) {
            throw new Error(payload?.message || 'Failed to load Krypton orders for this table.')
        }

        selectedOrders.value = Array.isArray(payload.orders) ? payload.orders : []
    } catch (error: any) {
        ordersError.value = error?.message || 'Unable to fetch table orders from Krypton.'
    } finally {
        ordersLoading.value = false
    }
}

const refreshCurrentTableOrders = async () => {
    if (selectedTable.value) {
        await openTableOrders(selectedTable.value)
    }
}

const addOrderForTable = async () => {
    if (!selectedTerminalId.value || !selectedTable.value) {
        return
    }

    actionLoading.value = true
    try {
        const response = await fetch(route('pos.table.orders.add', { terminalId: selectedTerminalId.value, tableId: selectedTable.value.id }), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            body: JSON.stringify({
                guest_count: Number(addGuestCount.value || 1),
                reference: addReference.value || null,
            }),
        })

        const payload = await readJsonPayload(response)
        if (!response.ok || !payload?.success) {
            throw new Error(payload?.message || 'Failed to add order.')
        }

        await refreshCurrentTableOrders()
        await loadTablesForTerminal(selectedTerminalId.value)
        addReference.value = ''
    } catch (error: any) {
        ordersError.value = error?.message || 'Unable to add order from POS.'
    } finally {
        actionLoading.value = false
    }
}

const editOrder = async (order: PosOrder) => {
    const nextGuestCount = window.prompt('Edit guest count:', String(order.guest_count ?? 1))
    if (!nextGuestCount) {
        return
    }

    const nextReference = window.prompt('Edit reference (optional):', order.reference || '')

    actionLoading.value = true
    try {
        const response = await fetch(route('pos.orders.edit', { orderId: order.id }), {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            body: JSON.stringify({
                guest_count: Number(nextGuestCount),
                reference: nextReference || null,
            }),
        })

        const payload = await readJsonPayload(response)
        if (!response.ok || !payload?.success) {
            throw new Error(payload?.message || 'Failed to edit order.')
        }

        await refreshCurrentTableOrders()
    } catch (error: any) {
        ordersError.value = error?.message || 'Unable to edit order in Krypton.'
    } finally {
        actionLoading.value = false
    }
}

const voidOrder = async (order: PosOrder) => {
    const confirmed = window.confirm(`Void order #${order.id}?`)
    if (!confirmed) {
        return
    }

    actionLoading.value = true
    try {
        const response = await fetch(route('pos.orders.void', { orderId: order.id }), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
        })

        const payload = await readJsonPayload(response)
        if (!response.ok || !payload?.success) {
            throw new Error(payload?.message || 'Failed to void order.')
        }

        await refreshCurrentTableOrders()
        if (selectedTerminalId.value) {
            await loadTablesForTerminal(selectedTerminalId.value)
        }
    } catch (error: any) {
        ordersError.value = error?.message || 'Unable to void order in Krypton.'
    } finally {
        actionLoading.value = false
    }
}

const payOrder = async (order: PosOrder) => {
    const total = Number(order.total_amount || 0)
    const paid = Number(order.paid_amount || 0)
    const remaining = Math.max(total - paid, 0)

    const amountPrompt = window.prompt('Payment amount:', String(remaining || total || 0))
    if (!amountPrompt) {
        return
    }

    const paymentTypePrompt = window.prompt('Payment type id (Cash=1, Credit=2, Debit=3, GCASH=4, PAYMAYA=5):', '1')
    if (!paymentTypePrompt) {
        return
    }

    actionLoading.value = true
    try {
        const response = await fetch(route('pos.orders.pay', { orderId: order.id }), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            body: JSON.stringify({
                amount: Number(amountPrompt),
                payment_type_id: Number(paymentTypePrompt),
            }),
        })

        const payload = await readJsonPayload(response)
        if (!response.ok || !payload?.success) {
            throw new Error(payload?.message || 'Failed to pay order.')
        }

        await refreshCurrentTableOrders()
        if (selectedTerminalId.value) {
            await loadTablesForTerminal(selectedTerminalId.value)
        }
    } catch (error: any) {
        ordersError.value = error?.message || 'Unable to pay order in Krypton.'
    } finally {
        actionLoading.value = false
    }
}
</script>

<template>
    <Head :title="pageTitle" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-[1600px] flex-col gap-8 px-4 pb-8 pt-6 sm:px-6 lg:px-8 lg:pt-8">
            <section class="rounded-[28px] border border-border/60 bg-card/95 p-5 shadow-sm shadow-black/5 backdrop-blur-sm dark:bg-card/80 sm:p-6 lg:p-8">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-3xl space-y-2">
                        <h2 class="text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
                            POS
                        </h2>
                        <p class="text-sm leading-6 text-muted-foreground sm:text-base">
                            Dedicated Krypton POS surface. Terminals, tables, orders, and session state in this page are loaded from
                            <span class="font-semibold text-foreground">krypton_woosoo only</span>.
                            Restaurant tables shown here are Krypton's real live tables.
                        </p>
                    </div>
                    <div class="rounded-2xl border border-emerald-400/30 bg-emerald-500/10 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-emerald-300">
                        Data Source: Krypton Only
                    </div>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-4">
                <div class="rounded-2xl border border-border/60 bg-card/95 px-5 py-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Terminals</p>
                    <p class="mt-1 text-2xl font-semibold">{{ totalTerminals }}</p>
                </div>
                <div class="rounded-2xl border border-border/60 bg-card/95 px-5 py-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Active Terminals</p>
                    <p class="mt-1 text-2xl font-semibold">{{ activeTerminals }}</p>
                </div>
                <div class="rounded-2xl border border-border/60 bg-card/95 px-5 py-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Open Orders</p>
                    <p class="mt-1 text-2xl font-semibold">{{ totalOpenOrders }}</p>
                </div>
                <div class="rounded-2xl border border-border/60 bg-card/95 px-5 py-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Current Session</p>
                    <p class="mt-1 text-lg font-semibold">#{{ currentSession?.id || '—' }} • {{ currentSessionStatus }}</p>
                    <p class="text-xs text-muted-foreground">Opened: {{ formatDateTime(currentSession?.date_time_opened) }}</p>
                </div>
            </section>

            <section class="rounded-[28px] border border-border/60 bg-card/95 p-4 shadow-sm shadow-black/5 backdrop-blur-sm dark:bg-card/80 sm:p-6 lg:p-8">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">POS &gt; Terminal</h3>
                    <p class="text-xs text-muted-foreground">Pick a terminal, then click a table to manage orders.</p>
                </div>
                <div class="p-4 sm:p-6 lg:p-8">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <button
                            v-for="terminal in terminals"
                            :key="terminal.id"
                            type="button"
                            class="group rounded-3xl border border-border/60 bg-gradient-to-b from-slate-900/95 to-slate-950 p-4 text-left shadow-lg transition-all hover:-translate-y-0.5 hover:border-primary/70 hover:shadow-primary/20"
                            :class="String(selectedTerminalId) === String(terminal.id) ? 'ring-2 ring-primary/70' : ''"
                            @click="loadTablesForTerminal(String(terminal.id))"
                        >
                            <div class="mb-3 flex items-center justify-between">
                                <span class="inline-flex items-center gap-1 rounded-full border border-white/15 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-white/80">
                                    <Circle :size="8" :class="Number(terminal.is_active) ? 'fill-emerald-400 text-emerald-400' : 'fill-rose-400 text-rose-400'" />
                                    {{ Number(terminal.is_active) ? 'Active' : 'Inactive' }}
                                </span>
                                <span class="text-[10px] font-semibold uppercase tracking-wider text-white/60">ID {{ terminal.id }}</span>
                            </div>

                            <div class="mx-auto mb-4 flex h-44 w-full max-w-[220px] items-center justify-center rounded-[1.7rem] border-[10px] border-slate-700 bg-slate-800 shadow-inner">
                                <div class="flex h-full w-full flex-col items-center justify-center rounded-[1.2rem] bg-slate-900 text-center text-white/90">
                                    <MonitorSmartphone class="mb-2 h-10 w-10 text-primary" />
                                    <p class="px-2 text-sm font-semibold leading-tight">{{ terminal.name }}</p>
                                    <p class="mt-1 text-[11px] text-white/50">{{ terminal.type }}</p>
                                </div>
                            </div>

                            <div class="space-y-1 text-xs text-white/70">
                                <p><span class="text-white/45">IP:</span> {{ terminal.ip_address || '—' }}</p>
                                <p><span class="text-white/45">Port:</span> {{ terminal.port ?? '—' }}</p>
                                <p><span class="text-white/45">Session:</span> #{{ terminal.session_id || '—' }} • {{ terminal.session_closed_at ? 'Closed' : 'Open' }}</p>
                                <p><span class="text-white/45">Open Orders:</span> <span class="font-semibold text-primary">{{ Number(terminal.open_orders_count) }}</span></p>
                            </div>
                        </button>
                    </div>
                </div>
            </section>

            <section class="rounded-[28px] border border-border/60 bg-card/95 shadow-sm shadow-black/5 backdrop-blur-sm dark:bg-card/80">
                <div class="border-b px-4 py-4 sm:px-6">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h3 class="text-base font-semibold">POS &gt; Terminal &gt; Tables</h3>
                            <p class="text-xs text-muted-foreground">
                                Selected terminal: <span class="font-semibold text-foreground">{{ selectedTerminal?.name || '—' }}</span>
                            </p>
                        </div>
                        <div class="text-right text-xs text-muted-foreground">
                            <p>Registered Tables: <span class="font-semibold text-foreground">{{ tables.length }}</span></p>
                            <p>Occupied Tables: <span class="font-semibold text-rose-500">{{ occupiedTables }}</span></p>
                        </div>
                    </div>
                </div>

                <div class="p-4 sm:p-6">
                    <div v-if="tablesLoading" class="flex items-center justify-center gap-2 py-14 text-sm text-muted-foreground">
                        <LoaderCircle class="h-4 w-4 animate-spin" />
                        Loading tables from Krypton...
                    </div>

                    <div v-else-if="tablesError" class="rounded-xl border border-rose-300/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-700 dark:text-rose-300">
                        {{ tablesError }}
                    </div>

                    <div v-else class="grid grid-cols-2 gap-4 md:grid-cols-4 xl:grid-cols-6">
                        <button
                            v-for="table in tables"
                            :key="table.id"
                            type="button"
                            class="rounded-2xl border p-4 text-left transition hover:border-primary"
                            :class="Number(table.is_occupied) ? 'border-rose-400/70 bg-rose-500/10' : 'border-emerald-400/40 bg-emerald-500/10'"
                            @click="openTableOrders(table)"
                        >
                            <div class="mb-2 flex items-center justify-between">
                                <TableProperties class="h-4 w-4 text-foreground/70" />
                                <span class="text-[10px] font-semibold uppercase tracking-wide" :class="Number(table.is_occupied) ? 'text-rose-500' : 'text-emerald-600'">
                                    {{ Number(table.is_occupied) ? 'Occupied' : 'Available' }}
                                </span>
                            </div>
                            <p class="text-base font-semibold">{{ table.name }}</p>
                            <p class="text-[11px] text-muted-foreground">ID {{ table.id }}</p>
                            <p class="mt-2 text-xs text-muted-foreground">
                                Orders: <span class="font-semibold text-foreground">{{ Number(table.open_orders_count) }}</span>
                            </p>
                        </button>
                    </div>
                </div>
            </section>
        </div>

        <Dialog v-model:open="showOrdersModal">
            <DialogContent class="max-h-[90vh] max-w-6xl overflow-hidden p-0">
                <DialogHeader class="border-b px-6 py-4">
                    <DialogTitle>
                        {{ selectedTable?.name || 'Table' }} — Table Orders
                    </DialogTitle>
                    <DialogDescription>
                        Source: <span class="font-semibold">krypton_woosoo</span> • terminal {{ selectedTerminalId || '—' }} • table {{ selectedTable?.id || '—' }}
                    </DialogDescription>
                </DialogHeader>

                <div class="max-h-[62vh] overflow-auto px-6 py-4">
                    <div class="mb-4 rounded-xl border border-border/60 bg-muted/30 p-4">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Add Order by Table</p>
                        <div class="flex flex-wrap items-end gap-3">
                            <div>
                                <label class="mb-1 block text-xs text-muted-foreground">Guest Count</label>
                                <input
                                    v-model.number="addGuestCount"
                                    type="number"
                                    min="1"
                                    class="w-28 rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                            </div>
                            <div>
                                <label class="mb-1 block text-xs text-muted-foreground">Reference</label>
                                <input
                                    v-model="addReference"
                                    type="text"
                                    class="w-56 rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    placeholder="Optional"
                                >
                            </div>
                            <Button :disabled="actionLoading" @click="addOrderForTable">
                                Add Order
                            </Button>
                        </div>
                    </div>

                    <div v-if="ordersLoading" class="flex items-center justify-center gap-2 py-16 text-sm text-muted-foreground">
                        <LoaderCircle class="h-4 w-4 animate-spin" />
                        Loading current orders from Krypton...
                    </div>

                    <div v-else-if="ordersError" class="rounded-xl border border-rose-300/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-700 dark:text-rose-300">
                        {{ ordersError }}
                    </div>

                    <div v-else-if="selectedOrders.length === 0" class="rounded-xl border border-border/60 bg-muted/30 px-4 py-8 text-center text-sm text-muted-foreground">
                        No open orders found for this device in Krypton.
                    </div>

                    <table v-else class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-left text-xs uppercase tracking-wide text-muted-foreground">
                                <th class="px-2 py-3">Order</th>
                                <th class="px-2 py-3">Opened</th>
                                <th class="px-2 py-3">Resto Table(s)</th>
                                <th class="px-2 py-3 text-right">Guests</th>
                                <th class="px-2 py-3 text-right">Total</th>
                                <th class="px-2 py-3 text-right">Paid</th>
                                <th class="px-2 py-3 text-right">Resetable Txn#</th>
                                <th class="px-2 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="order in selectedOrders" :key="order.id" class="border-b border-border/40 align-top">
                                <td class="px-2 py-3 font-medium">#{{ order.id }}<br><span class="text-xs text-muted-foreground">{{ order.reference || '—' }}</span></td>
                                <td class="px-2 py-3">{{ order.date_time_opened || '—' }}</td>
                                <td class="px-2 py-3">{{ order.table_names || 'Unassigned' }}</td>
                                <td class="px-2 py-3 text-right">{{ Number(order.guest_count || 0) }}</td>
                                <td class="px-2 py-3 text-right">{{ formatMoney(order.total_amount) }}</td>
                                <td class="px-2 py-3 text-right">{{ formatMoney(order.paid_amount) }}</td>
                                <td class="px-2 py-3 text-right">{{ order.resetable_transaction_number || '—' }}</td>
                                <td class="px-2 py-3">
                                    <div class="flex justify-end gap-2">
                                        <Button size="sm" variant="outline" :disabled="actionLoading" @click="editOrder(order)">Edit</Button>
                                        <Button size="sm" variant="secondary" :disabled="actionLoading" @click="payOrder(order)">
                                            <ReceiptText class="mr-1 h-3.5 w-3.5" /> Pay
                                        </Button>
                                        <Button size="sm" variant="destructive" :disabled="actionLoading" @click="voidOrder(order)">Void</Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <DialogFooter class="border-t px-6 py-4">
                    <Button type="button" variant="outline" @click="showOrdersModal = false">
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
