<template>
    <div class="view-wrapper stats-wrapper">
        <div class="page-title">Statistics</div>
        <OrderFiltersPanel v-model="filters" />
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

const today = "2025-01-01";

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
        const data = await request({
            url: `/stats/orders/timeseries?${params}`,
        });
        ordersTimeSeries.value = data.orders_time_series ?? [];
        console.log("Orders Time Series:", ordersTimeSeries.value);
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
