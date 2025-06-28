<template>
    <div class="panel main-stats-panel">
        <h3>Main Stats</h3>
        <n-skeleton v-if="loading" text :repeat="2" />
        <div v-else class="main-stats-row">
            <div class="main-stat">
                <div class="stat-value">{{ formattedGrossSales }}</div>
                <div class="stat-label">Gross Sales</div>
            </div>
            <div class="main-stat">
                <div class="stat-value">{{ formattedNetSales }}</div>
                <div class="stat-label">Net Sales</div>
            </div>
            <div class="main-stat">
                <div class="stat-value">{{ stats?.order_count ?? 0 }}</div>
                <div class="stat-label">Orders</div>
            </div>
            <div class="main-stat">
                <div class="stat-value">{{ formattedRefunded }}</div>
                <div class="stat-label">Refunded</div>
            </div>
            <div class="main-stat">
                <div class="stat-value">{{ stats?.refund_count ?? 0 }}</div>
                <div class="stat-label">Refunds</div>
            </div>
            <div class="main-stat">
                <div class="stat-value">{{ formattedTaxes }}</div>
                <div class="stat-label">Taxes</div>
            </div>
            <div class="main-stat">
                <div class="stat-value">{{ stats?.items_sold ?? 0 }}</div>
                <div class="stat-label">Items Sold</div>
            </div>
            <div class="main-stat">
                <div class="stat-value">{{ stats?.new_customers ?? 0 }}</div>
                <div class="stat-label">New Customers</div>
            </div>
            <div class="main-stat">
                <div class="stat-value">{{ stats?.returning_customers ?? 0 }}</div>
                <div class="stat-label">Returning</div>
            </div>
        </div>
    </div>
</template>
<script setup>
import { computed } from "vue";

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    loading: Boolean,
});

// Reusable formatting function
function formatCurrency(amount, currency = "USD") {
    return new Intl.NumberFormat("en-US", {
        style: "currency",
        currency,
        maximumFractionDigits: 0,
    }).format(amount ?? 0);
}

// Computed currency code (fallback to USD)
const currency = computed(() => props.stats?.currency || "USD");

// Composables for each value
const formattedNetSales = computed(() => formatCurrency(props.stats?.net_sales, currency.value));
const formattedGrossSales = computed(() => formatCurrency(props.stats?.gross_sales, currency.value));
const formattedRefunded = computed(() => formatCurrency(props.stats?.refunded, currency.value));
const formattedTaxes = computed(() => formatCurrency(props.stats?.total_taxes, currency.value));
</script>