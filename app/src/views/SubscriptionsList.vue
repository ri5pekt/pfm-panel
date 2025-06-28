<!-- SubscriptionsList.vue -->
<template>
    <div class="view-wrapper subscriptions-list">
        <div class="page-title">All Subscriptions</div>
        <!-- Filters Panel: Only date and search filters shown -->
        <OrderFiltersPanel
            v-model="filters"
            :showStatus="false"
            :showTags="false"
            :showWarehouse="false"
            :showExportStatus="false"
            :showAddrStatus="false"
            :showDate="true"
            :showSearch="true"
            @search="handleSearch"
        />

        <n-spin :show="loading">
            <n-data-table
                :columns="columns"
                :data="subscriptions"
                :pagination="false"
                :bordered="true"
                :row-props="rowProps"
            />
        </n-spin>
        <n-pagination v-model:page="page" :page-count="totalPages" style="margin-top: 1rem" />
    </div>
</template>

<script setup>
import OrderFiltersPanel from "@/components/ui-elements/OrderFiltersPanel.vue";

import { ref, reactive, onMounted, watch, h } from "vue";
import { useRouter, useRoute } from "vue-router";
import { NTag } from "naive-ui";
import { request } from "@/utils/api";

const subscriptions = ref([]);
const loading = ref(false);
const page = ref(1);
const perPage = 10;
const totalPages = ref(1);

const router = useRouter();
const route = useRoute();

const filters = reactive({
    date_from: route.query.date_from ?? null,
    date_to: route.query.date_to ?? null,
    search_type: route.query.search_type ?? null,
    search_value: route.query.search_value ?? null,
});

function handleSearch() {
    // If not searching, clear search_value
    if (filters.search_type === null) {
        filters.search_value = null;
    }
    if (filters.search_type === "order_id" && filters.search_value) {
        Object.keys(filters).forEach((k) => {
            if (!["search_type", "search_value"].includes(k)) {
                filters[k] = null;
            }
        });
    }
    page.value = 1;

    const queryObj = {
        ...Object.fromEntries(
            Object.entries(filters).filter(
                ([k, v]) => !["search_type", "search_value"].includes(k) && v !== null && v !== ""
            )
        ),
        page: page.value,
        ...(filters.search_type && filters.search_value
            ? {
                  search_type: filters.search_type,
                  search_value: filters.search_value,
              }
            : {}),
    };

    router.replace({
        path: "/subscriptions",
        query: queryObj,
    });

    fetchSubscriptions();
}

async function fetchSubscriptions() {
    loading.value = true;
    try {
        const params = new URLSearchParams();
        params.append("per_page", perPage);
        params.append("page", page.value);

        if (filters.date_from) params.append("date_from", filters.date_from);
        if (filters.date_to) params.append("date_to", filters.date_to);
        if (filters.search_type && filters.search_value) {
            params.append("search_type", filters.search_type);
            params.append("search_value", filters.search_value);
        }

        const res = await request({
            url: `/subscriptions?${params}`,
            raw: true,
        });
        const data = await res.json();
        subscriptions.value = data;
        totalPages.value = parseInt(res.headers.get("X-WP-TotalPages") || "1");
    } catch (err) {
        console.log("Error fetching subscriptions:", err);
    } finally {
        loading.value = false;
    }
}

const columns = [
    {
        title: "Subscription",
        key: "id",
        render(row) {
            const name = row.customer_name || "Guest";
            return h("div", { style: { display: "flex", gap: "6px", alignItems: "center" } }, [
                h(NTag, { size: "small" }, { default: () => `#${row.id}` }),
                h("span", null, name),
            ]);
        },
    },
    {
        title: "Status",
        key: "status",
        render(row) {
            const status = row.status.charAt(0).toUpperCase() + row.status.slice(1);
            return h("span", { class: `subscription-status ${row.status}` }, status);
        },
    },
    {
        title: "Items",
        key: "items",
        render(row) {
            if (row.items.length === 1) {
                return row.items[0].name;
            }
            return `${row.items.length} items`;
        },
    },
    {
        title: "Total",
        key: "recurring_total",
        render(row) {
            return h("span", { innerHTML: row.recurring_total });
        },
    },
    {
        title: "Start Date",
        key: "start_date",
        render(row) {
            return row.start_date || "—";
        },
    },
    {
        title: "Next Payment Date",
        key: "next_payment_date",
        render(row) {
            return row.next_payment_date || "—";
        },
    },
    {
        title: "Last Order Date",
        key: "last_order_date",
        render(row) {
            return row.last_order_date || "—";
        },
    },
];

function handleRowClick(row) {
    router.push(`/subscriptions/${row.id}`);
}

function rowProps(row) {
    return {
        style: { cursor: "pointer" },
        onClick: () => handleRowClick(row),
    };
}

// --- Sync route query to filters and page on mount and navigation ---
onMounted(() => {
    if (route.query.page) {
        page.value = parseInt(route.query.page);
    }
    fetchSubscriptions();
});

watch(
    () => route.query,
    (newQuery) => {
        filters.date_from = newQuery.date_from ?? null;
        filters.date_to = newQuery.date_to ?? null;
        filters.search_type = newQuery.search_type ?? null;
        filters.search_value = newQuery.search_value ?? null;
        if (newQuery.page && page.value !== parseInt(newQuery.page)) {
            page.value = parseInt(newQuery.page);
        }
    }
);

// --- React to page changes from pagination ---
watch(
    () => page.value,
    (newPage) => {
        router.replace({
            path: "/subscriptions",
            query: { ...route.query, page: newPage },
        });
    }
);

// --- Main watcher: watch for any change in non-search filters or page and fetch data + update route ---
watch(
    () => ({
        date_from: filters.date_from,
        date_to: filters.date_to,
        page: page.value,
    }),
    () => {
        const queryObj = {
            ...Object.fromEntries(
                Object.entries(filters).filter(
                    ([k, v]) => !["search_type", "search_value"].includes(k) && v !== null && v !== ""
                )
            ),
            page: page.value,
        };
        // Add search params only if both are set
        if (filters.search_type && filters.search_value) {
            queryObj.search_type = filters.search_type;
            queryObj.search_value = filters.search_value;
        }

        router.replace({
            path: "/subscriptions",
            query: queryObj,
        });
        fetchSubscriptions();
    },
    { deep: true }
);
</script>
