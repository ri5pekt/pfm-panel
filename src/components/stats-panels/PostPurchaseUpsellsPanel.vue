<template>
    <div class="panel ppu-panel">
        <h3>Post-Purchase Upsells</h3>
        <n-skeleton v-if="loading" text :repeat="2" />
        <div v-else class="ppu-stats-row">
            <div class="ppu-stat">
                <div class="stat-value">{{ stats?.ppu_orders_count ?? 0 }}</div>
                <div class="stat-label">Orders</div>
            </div>
            <div class="ppu-stat">
                <div class="stat-value">{{ stats?.ppu_products_count ?? 0 }}</div>
                <div class="stat-label">Products</div>
            </div>
            <div class="ppu-stat">
                <div class="stat-value">{{ formattedPPUValue }}</div>
                <div class="stat-label">Value</div>
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

function formatCurrency(amount, currency = "USD") {
    return new Intl.NumberFormat("en-US", {
        style: "currency",
        currency,
        maximumFractionDigits: 0,
    }).format(amount ?? 0);
}

const currency = computed(() => props.stats?.currency || "USD");
const formattedPPUValue = computed(() => formatCurrency(props.stats?.ppu_products_value, currency.value));
</script>
