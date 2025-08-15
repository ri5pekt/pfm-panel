<!-- StatisticsView.vue -->
<template>
    <div class="view-wrapper stats-wrapper">
        <div class="page-title">Statistics</div>
        <OrderFiltersPanel
            v-model="filters"
            :showStatus="false"
            :showTags="false"
            :showWarehouse="false"
            :showExportStatus="false"
            :showAddrStatus="false"
            :showSearch="false"
            :showDate="true"
        />
        <div class="stats-grid">
            <!-- Pass stats object as prop instead of orders -->
            <MainStatsPanel :stats="stats" :loading="loading" />
            <PostPurchaseUpsellsPanel :stats="stats" :loading="loading" />
            <SubscriptionsPanel :stats="stats" :loading="loading" />

            <OrdersCountChartPanel :series="ordersTimeSeries" :loading="chartLoading" />
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, watch, reactive } from "vue";
import MainStatsPanel from "@/components/stats-panels/MainStatsPanel.vue";
import PostPurchaseUpsellsPanel from "@/components/stats-panels/PostPurchaseUpsellsPanel.vue";
import SubscriptionsPanel from "@/components/stats-panels/SubscriptionsPanel.vue";
import OrdersCountChartPanel from "@/components/stats-panels/OrdersCountChartPanel.vue";
import OrderFiltersPanel from "@/components/ui-elements/OrderFiltersPanel.vue";
import { request } from "@/utils/api";

const stats = ref({});
const loading = ref(true);

const ordersTimeSeries = ref([]); // 1. Declare
const chartLoading = ref(true); // 1. Declare

function getToday() {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, "0");
    const day = String(d.getDate()).padStart(2, "0");
    return `${y}-${m}-${day}`;
}

//const today = "2025-01-01";
const today = getToday(); // 1. Use today as default

const filters = reactive({
    status: null,
    warehouse: null,
    export_status: null,
    addr_status: null,
    tag: null,
    date_from: today,
    date_to: today,
});

// 2. Update watcher to call both functions!
watch(
    () => ({ ...filters }),
    () => {
        fetchStats();
        fetchOrdersTimeSeries();
    },
    { deep: true }
);

async function fetchStats() {
    loading.value = true;
    const params = new URLSearchParams();
    if (filters.status) params.append("status", filters.status);
    if (filters.warehouse) params.append("warehouse", filters.warehouse);
    if (filters.export_status) params.append("export_status", filters.export_status);
    if (filters.addr_status) params.append("addr_status", filters.addr_status);
    if (filters.tag) params.append("tag", filters.tag);
    if (filters.date_from) params.append("date_from", filters.date_from);
    if (filters.date_to) params.append("date_to", filters.date_to);

    try {
        const data = await request({
            url: `/stats/orders?${params}`,
        });
        stats.value = data;
    } catch (err) {
        // handle error
    } finally {
        loading.value = false;
    }
}

async function fetchOrdersTimeSeries() {
    chartLoading.value = true;
    const params = new URLSearchParams();
    if (filters.date_from) params.append("date_from", filters.date_from);
    if (filters.date_to) params.append("date_to", filters.date_to);

    try {
        const data = await request({ url: `/stats/orders/timeseries?${params}` });
        ordersTimeSeries.value = data.orders_time_series ?? [];

        // 🔎 Debug: log what the backend thinks the window is
        console.log("[TS] bounds.local:", data?.bounds?.local);
        console.log("[TS] bounds.utc:", data?.bounds?.utc);

        // 🔎 Debug: log “now” and current local hour
        const now = new Date();
        console.log("[TS] now local:", now.toString(), "hour=", now.getHours());

        // 🔎 Debug: find last non-zero bucket today
        const series = ordersTimeSeries.value;
        const lastIdx = [...series].reverse().findIndex((p) => Number(p.count) > 0);
        const realLastIdx = lastIdx === -1 ? -1 : series.length - 1 - lastIdx;

        if (realLastIdx >= 0) {
            const last = series[realLastIdx]; // { label: "HH:00" | "YYYY-MM-DD" | "Week of ...", count }
            console.log("[TS] last non-zero bucket:", realLastIdx, last);

            // Only do precise hour math when we’re in hourly mode (24 labels like "00:00".."23:00")
            const hourlyLike = series.length === 24 && /^\d{2}:\d{2}$/.test(series[0]?.label || "");
            if (hourlyLike && filters.date_from === filters.date_to) {
                const [hh] = String(last.label)
                    .split(":")
                    .map((n) => parseInt(n, 10));
                const lastDate = new Date(`${filters.date_from}T00:00:00`); // local midnight
                lastDate.setHours(hh, 0, 0, 0);

                const gapHours = Math.floor((now - lastDate) / 36e5);
                console.log(
                    "[TS] last order hour:",
                    hh,
                    "last order time:",
                    lastDate.toString(),
                    "gapHours≈",
                    gapHours
                );
            }
        } else {
            console.log("[TS] No non-zero buckets for this range.");
        }
    } catch (err) {
        // handle error
    } finally {
        chartLoading.value = false;
    }
}

// 2. Call both on mount
onMounted(() => {
    fetchStats();
    fetchOrdersTimeSeries();
});
</script>
