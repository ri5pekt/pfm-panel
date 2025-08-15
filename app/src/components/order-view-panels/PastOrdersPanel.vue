<!-- PastOrdersPanel.vue -->
<template>
    <div class="panel past-orders-panel">
        <h3>Customer's Past Orders</h3>
        <div class="past-orders-scroll">
            <n-data-table :columns="columns" :data="pastOrders" :loading="loading" size="small" striped />

            <div class="load-more-wrapper" v-if="hasMore">
                <n-button @click="loadMore" :loading="loading" size="small"> Load More </n-button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, h } from "vue";
import { RouterLink } from "vue-router";
import { NTag } from "naive-ui";
import { formatOrderDate, formatCurrency } from "@/utils/utils";
import { request } from "@/utils/api";

const props = defineProps({
    customerId: Number,
    excludeOrderId: Number,
});

const pastOrders = ref([]);
const page = ref(1);
const hasMore = ref(true);
const loading = ref(false);

const tag = (label, type = "default") =>
    h(NTag, { type, size: "small", style: "margin-left: 8px; vertical-align: middle" }, { default: () => label });

const columns = [
    {
        title: "Order ID",
        key: "id",
        render(row) {
            // Route based on type
            const to = row.is_replacement
                ? { name: "replacement-view", params: { id: row.id } }
                : {
                      name: "order-view",
                      params: { id: row.id },
                      query: row.is_archived ? { is_archived: 1 } : undefined,
                  };

            const children = [h(RouterLink, { to }, { default: () => `#${row.id}` })];

            if (row.is_archived) children.push(tag("Archived", "warning"));
            if (row.is_replacement) children.push(tag("Replacement", "info"));
            if (row.has_chargeback || (row.disputed_amount && Number(row.disputed_amount) > 0)) {
                children.push(tag("Charged back", "error"));
            }
            return h("span", children);
        },
    },
    {
        title: "Date",
        key: "date_created",
        render(row) {
            return row.date_created ? formatOrderDate(row.date_created) : "";
        },
    },
    {
        title: "Status",
        key: "status",
        render(row) {
            if (row.is_replacement) {
                // replacement statuses are already clean; just title-case
                const s = (row.status || "").replace(/_/g, " ");
                return s.charAt(0).toUpperCase() + s.slice(1);
            }
            if (!row.status) return "";
            const clean = row.status.replace(/^wc-/, "");
            return clean.charAt(0).toUpperCase() + clean.slice(1);
        },
    },
    {
        title: "Total",
        key: "total",
        render(row) {
            return typeof row.total === "number" ? formatCurrency(row.total, row.currency) : "";
        },
    },
];

async function fetchOrders() {
    if (!props.customerId) return;
    loading.value = true;
    try {
        const data = await request({
            url: `/orders/by-user/${props.customerId}?page=${page.value}&per_page=10`,
        });
        const filtered = data.filter((o) => o.id !== props.excludeOrderId);
        if (filtered.length < 10) hasMore.value = false;
        pastOrders.value.push(...filtered);
    } catch (err) {
        console.error("Failed to load past orders:", err);
    } finally {
        loading.value = false;
    }
}

function loadMore() {
    page.value += 1;
    fetchOrders();
}

watch(
    () => props.customerId,
    (val) => {
        if (!val) return;
        // reset when customer changes
        pastOrders.value = [];
        page.value = 1;
        hasMore.value = true;
        fetchOrders();
    },
    { immediate: true }
);
</script>
