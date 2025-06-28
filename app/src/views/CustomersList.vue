<!-- CustomerFiltersPanel.vue -->
<template>
    <div class="view-wrapper customers-list">
        <div class="page-title">All Customers</div>
        <CustomerFiltersPanel v-model="filters" @search="handleSearch" />
        <n-spin :show="loading">
            <n-data-table
                :columns="columns"
                :data="customers"
                :pagination="false"
                :bordered="true"
                :row-props="rowProps"
                @row-click="handleRowClick"
            />
        </n-spin>
        <n-pagination
            v-model:page="page"
            :page-count="totalPages"
            :page-size="perPage"
            style="margin-top: 1rem"
            @update:page="fetchCustomers"
        />
    </div>
</template>

<script setup>
import { ref, onMounted, watch, computed } from "vue";
import { useRouter, useRoute } from "vue-router";
import { request } from "@/utils/api";
import CustomerFiltersPanel from "@/components/ui-elements/CustomerFiltersPanel.vue";

const customers = ref([]);
const totalPages = ref(1);
const perPage = 10;
const loading = ref(false);

const router = useRouter();
const route = useRoute();

const page = ref(1);

if (route.query.page) {
    page.value = parseInt(route.query.page);
}

// --- Filters: only one filter active at a time (enforced in panel) ---
const filters = ref({
    search_type: route.query.search_type ?? null,
    search_value: route.query.search_value ?? null,
    registered_from: route.query.registered_from ?? null,
    registered_to: route.query.registered_to ?? null,
});

watch(
    () => ({
        search_type: filters.value.search_type,
        search_value: filters.value.search_value,
        registered_from: filters.value.registered_from,
        registered_to: filters.value.registered_to,
        page: page.value,
    }),
    (cur, prev) => {
        if (
            cur.search_type &&
            cur.search_value &&
            (cur.search_type !== prev.search_type || cur.search_value !== prev.search_value)
        ) {
            return;
        }
        if (
            cur.registered_from !== prev.registered_from ||
            cur.registered_to !== prev.registered_to ||
            cur.page !== prev.page
        ) {
            fetchCustomers(page.value);
        }
    },
    { deep: true }
);

const columns = computed(() => [
    { title: "Name", key: "name" },
    { title: "Email", key: "email" },
    { title: "Orders", key: "orders_count" },
    { title: "Last Order Date", key: "last_order_date" },
    { title: "Registered", key: "registered" },
]);

function rowProps(row) {
    return {
        style: { cursor: "pointer" },
        onClick: () => handleRowClick(row),
    };
}

function handleRowClick(row) {
    console.log("Row clicked:", row);
    router.push(`/customers/${row.id}`);
}

function buildPayload() {
    return {
        ...filters.value,
        page: page.value,
        per_page: perPage,
    };
}

async function fetchCustomers(currentPage = 1) {
    loading.value = true;
    try {
        const payload = buildPayload();
        // Remove empty/null
        const cleanPayload = Object.fromEntries(Object.entries(payload).filter(([, v]) => v !== null && v !== ""));
        console.log("🔍 Fetching customers with:", cleanPayload);

        const params = new URLSearchParams(cleanPayload).toString();
        const res = await request({
            url: `/customers?${params}`,
            raw: true,
        });
        customers.value = await res.json();
        totalPages.value = parseInt(res.headers.get("X-WP-TotalPages") || "1");
    } catch (e) {
        // error handling
    } finally {
        loading.value = false;
    }
}

function handleSearch() {
    page.value = 1;
    fetchCustomers(page.value);
}

onMounted(() => {
    fetchCustomers(page.value);
});
</script>
