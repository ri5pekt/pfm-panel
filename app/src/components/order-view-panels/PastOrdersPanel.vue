<!-- PastOrdersPanel.vue -->
<template>
    <div class="panel past-orders-panel span-2-cols">
        <h3>Past Orders</h3>
        <div class="past-orders-scroll">
            <n-data-table :columns="columns" :data="pastOrders" :loading="loading" size="small" striped />

            <div class="load-more-wrapper" v-if="hasMore">
                <n-button @click="loadMore" :loading="loading" size="small"> Load More </n-button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, watch, h } from "vue";
import { formatOrderDate, formatCurrency } from "@/utils/utils";
import { request } from "@/utils/api";
import { NTag } from "naive-ui";

const props = defineProps({
    customerId: Number,
    excludeOrderId: Number,
});

const pastOrders = ref([]);
const page = ref(1);
const hasMore = ref(true);
const loading = ref(false);

const columns = [
    {
        title: "Order ID",
        key: "id",
        render(row) {
            const children = [
                h("a", { href: `#/orders/${row.id}` }, `#${row.id}`)
            ];
            if (row.is_archived) {
                children.push(
                    h(
                        NTag,
                        {
                            type: "warning",
                            size: "small",
                            style: "margin-left: 8px; vertical-align: middle"
                        },
                        { default: () => "Archived" }
                    )
                );
            }
            return h("span", children);
        },
    },
    {
        title: "Date",
        key: "date_created",
        render(row) {
            return formatOrderDate(row.date_created);
        },
    },
    {
        title: "Status",
        key: "status",
        render(row) {
            return row.status ? row.status.charAt(0).toUpperCase() + row.status.slice(1) : "";
        },
    },
    {
        title: "Total",
        key: "total",
        render(row) {
            return formatCurrency(row.total);
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
        if (val) fetchOrders();
    },
    { immediate: true }
);
</script>