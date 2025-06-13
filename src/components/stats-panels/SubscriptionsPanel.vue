<template>
    <div class="panel subscriptions-panel">
        <h3>Subscriptions</h3>
        <n-skeleton v-if="loading" text :repeat="2" />
        <div v-else class="subscriptions-stats-row">
            <div class="subscriptions-stat">
                <div class="stat-value">{{ stats?.new_subs_count ?? 0 }}</div>
                <div class="stat-label">New</div>
            </div>
            
            <div class="subscriptions-stat">
                <div class="stat-value">{{ stats?.renew_subs_count ?? 0 }}</div>
                <div class="stat-label">Renewals</div>
            </div>
            <div class="subscriptions-stat">
                <div class="stat-value">{{ formattedNewSubsValue }}</div>
                <div class="stat-label">New Subs Value</div>
            </div>
            <div class="subscriptions-stat">
                <div class="stat-value">{{ formattedRenewSubsValue }}</div>
                <div class="stat-label">Renewals Value</div>
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
const formattedNewSubsValue = computed(() => formatCurrency(props.stats?.new_subs_value, currency.value));
const formattedRenewSubsValue = computed(() => formatCurrency(props.stats?.renew_subs_value, currency.value));
</script>
