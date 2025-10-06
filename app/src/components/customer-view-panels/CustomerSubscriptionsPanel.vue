<!-- CustomerSubscriptionsPanel.vue -->
<template>
    <div class="panel subscription-panel">
        <h3>Subscriptions</h3>
        <div class="past-orders-scroll">
            <n-data-table
                :columns="columns"
                :data="subscriptions"
                :loading="loading"
                size="small"
                striped
            />
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from "vue";
import { request } from "@/utils/api";
import { h } from "vue";
import { formatOrderDate } from "@/utils/utils";

const props = defineProps({
    customerId: Number,
});

const subscriptions = ref([]);
const loading = ref(false);

const columns = [
    {
        title: "Subscription ID",
        key: "id",
        render(row) {
            return h("a", { href: `#/subscriptions/${row.id}` }, `#${row.id}`);
        },
    },
    {
        title: "Date",
        key: "date",
        render(row) {
            // Try to format using your existing utility, else fallback
            return row.date ? formatOrderDate(row.date) : "";
        },
    },
    {
        title: "Status",
        key: "status",
        render(row) {
            return h(
                "span",
                { class: `order-status ${row.status}` },
                row.status.charAt(0).toUpperCase() + row.status.slice(1)
            );
        },
    },
    {
        title: "Total",
        key: "total",
        render(row) {
            return h("div", { innerHTML: row.total });
        },
    },
];

async function fetchSubscriptions() {
    subscriptions.value = [];
    loading.value = true;
    if (!props.customerId) {
        loading.value = false;
        return;
    }
    try {
        subscriptions.value = await request({
            url: `/subscriptions/by-user/${props.customerId}`,
        });
    } catch (err) {
        console.error("Failed to load subscriptions:", err);
    } finally {
        loading.value = false;
    }
}

watch(
    () => props.customerId,
    (val) => {
        if (val) {
            fetchSubscriptions();
        }
    },
    { immediate: true }
);
</script>