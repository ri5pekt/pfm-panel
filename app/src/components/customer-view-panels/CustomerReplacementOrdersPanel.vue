<!-- CustomerReplacementOrdersPanel.vue -->
<template>
    <div class="panel replacement-orders-panel">
        <h3>Replacement Orders</h3>
        <div class="past-orders-scroll">
            <n-data-table
                :columns="columns"
                :data="replacements"
                :loading="loading"
                size="small"
                striped
                :row-key="(row) => row.id"
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

const loading = ref(false);
const replacements = ref([]);

const columns = [
    {
        title: "ID",
        key: "id",
        render(row) {
            return h("a", { href: `#/replacements/${row.id}` }, `#${row.id}`);
        },
    },
    {
        title: "Date",
        key: "created_at",
        render(row) {
            return row.created_at ? formatOrderDate(row.created_at) : "";
        },
    },
    {
        title: "Status",
        key: "status",
        render(row) {
            return h("span", { class: `order-status ${row.status}` }, row.status);
        },
    },
    {
        title: "Items",
        key: "items",
        render(row) {
            return row.items?.length ?? 0;
        },
    },
];

async function fetchReplacements() {
    if (!props.customerId) return;
    loading.value = true;
    try {
        const response = await request({
            url: "/replacements",
            method: "GET",
            params: {
                customer_id: props.customerId,
            },
        });
        replacements.value = response;
    } catch (e) {
        console.error("Failed to load replacement orders", e);
    } finally {
        loading.value = false;
    }
}

watch(
    () => props.customerId,
    () => {
        fetchReplacements();
    },
    { immediate: true }
);
</script>
