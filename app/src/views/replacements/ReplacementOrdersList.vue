<!-- ReplacementOrdersList.vue -->
<template>
    <div class="view-wrapper replacements-list orders-list">
        <n-space justify="space-between" style="margin-bottom: 1rem">
            <div class="page-title">Replacement Orders</div>
            <n-button v-if="$can('edit_replacement_orders')" type="primary" @click="showCreateConfirm = true"
                >Create New Replacement Order</n-button
            >
        </n-space>
        <OrderFiltersPanel
            v-model="filters"
            :showStatus="true"
            :showTags="false"
            :showWarehouse="true"
            :showExportStatus="true"
            :showAddrStatus="false"
            :showDate="true"
            :showSearch="true"
            @search="handleSearch"
        />
        <n-space vertical size="large" class="orders-table">
            <n-spin :show="loading">
                <n-data-table
                    :row-props="rowProps"
                    :columns="columns"
                    :data="orders"
                    :pagination="false"
                    :bordered="true"
                />
            </n-spin>

            <n-pagination v-model:page="page" :page-count="totalPages" :page-size="perPage" style="margin-top: 1rem" />
        </n-space>

        <n-modal
            v-model:show="showCreateConfirm"
            preset="dialog"
            title="Create New Replacement Order"
            @positive-click="handleCreateReplacement"
            @negative-click="showCreateConfirm = false"
            positive-text="Create"
            negative-text="Cancel"
            :positive-button-props="{ loading: creatingReplacement }"
        >
            Are you sure you want to create a new replacement order?
        </n-modal>
    </div>
</template>

<script setup>
import { ref, onMounted, watch, computed, h, reactive } from "vue";
import { NTag, useMessage } from "naive-ui";
import { useRouter, useRoute } from "vue-router";
import { request } from "@/utils/api";
import { formatOrderDate } from "@/utils/utils";
import OrderFiltersPanel from "@/components/ui-elements/OrderFiltersPanel.vue";

const router = useRouter();
const route = useRoute();
const message = useMessage();

const orders = ref([]);
const loading = ref(false);
const perPage = 10;

const page = ref(parseInt(route.query.page) || 1);
const totalPages = ref(1);

const showCreateConfirm = ref(false);
const creatingReplacement = ref(false);

async function handleCreateReplacement() {
    creatingReplacement.value = true;
    try {
        const res = await request({
            url: "/replacements",
            method: "POST",
        });

        const data = res;
        if (data?.id) {
            message.success("New replacement order created");
            router.push(`/replacements/${data.id}`);
        } else {
            throw new Error("Invalid response");
        }
    } catch (err) {
        console.error("❌ Failed to create replacement order", err);
        message.error("Failed to create replacement order");
    } finally {
        creatingReplacement.value = false;
        showCreateConfirm.value = false;
    }
}

const filters = reactive({
    status: route.query.status ?? null,
    warehouse: route.query.warehouse ?? null,
    export_status: route.query.export_status ?? null,
    addr_status: route.query.addr_status ?? null,
    tag: route.query.tag ?? null,
    date_from: route.query.date_from ?? null,
    date_to: route.query.date_to ?? null,
    search_type: route.query.search_type ?? null,
    search_value: route.query.search_value ?? null,
});

watch(
    () => ({
        status: filters.status,
        warehouse: filters.warehouse,
        export_status: filters.export_status,
        addr_status: filters.addr_status,
        tag: filters.tag,
        date_from: filters.date_from,
        date_to: filters.date_to,
        page: page.value,
    }),
    () => {
        router.replace({
            path: "/replacements",
            query: {
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
            },
        });

        fetchReplacements(page.value);
    },
    { deep: true }
);

function rowProps(row) {
    return {
        style: { cursor: "pointer" },
        onClick: (e) => openReplacement(row, e),
        onMousedown: (e) => {
            // Middle mouse = 1 → open new tab natively
            if (e.button === 1) {
                e.preventDefault();
                openReplacement(row, e);
            }
        },
        onContextmenu: (e) => {
            // right-click → open in new tab
            e.preventDefault(); // remove if you want the browser menu to show
            openReplacement(row, { ...e, metaKey: true }); // reuse logic → opens _blank
        },
        tabindex: 0,
        role: "link",
        onKeydown: (e) => {
            if (e.key === "Enter" || e.key === " ") openReplacement(row, e);
        },
    };
}

function openReplacement(row, e) {
    const loc = { name: "replacement-view", params: { id: row.id } };
    const href = router.resolve(loc).href; // e.g. "#/replacements/123" with hash history

    if (e?.metaKey || e?.ctrlKey || e?.button === 1) {
        window.open(href, "_blank", "noopener");
    } else {
        router.push(loc);
    }
}

async function fetchReplacements(currentPage = 1) {
    console.log("🔄 Fetching replacements for page:", currentPage);
    loading.value = true;

    const params = new URLSearchParams();
    params.append("per_page", perPage);
    params.append("page", currentPage);

    // Add all filters, including search_type and search_value, if present
    for (const [key, val] of Object.entries(filters)) {
        if (val !== null && val !== "") {
            params.append(key, val);
        }
    }

    console.log("🔍 Fetching with params:", params.toString());

    try {
        const res = await request({
            url: `/replacements?${params}`,
            raw: true, // to access headers like total pages
        });

        const data = await res.json();
        orders.value = data;
        totalPages.value = parseInt(res.headers.get("X-WP-TotalPages") || "1");

        console.log("📦 Replacement orders loaded:", data);
    } catch (err) {
        console.error("❌ Failed to load replacement orders", err);
        message.error("Failed to load replacement orders");
    } finally {
        loading.value = false;
    }
}

function getMeta(row, key) {
    const entry = (row.meta_data || []).find((m) => m.key === key);
    return entry ? entry.value : null;
}

const columns = computed(() => [
    {
        title: "Order",
        key: "id",
        render(row) {
            const first = row.customer?.first_name || "";
            const last = row.customer?.last_name || "";
            const name = (first + " " + last).trim() || "Guest";

            return h("div", { style: { display: "flex", gap: "6px", alignItems: "center" } }, [
                h(NTag, { size: "small" }, { default: () => `#${row.id}` }),
                h("span", null, name),
            ]);
        },
    },
    {
        title: "Created By",
        key: "created_by",
        render(row) {
            return h("span", null, displayUser(row.created_by));
        },
    },
    {
        title: "Date",
        key: "date_created",
        render(row) {
            return formatOrderDate(row?.created_at);
        },
    },
    {
        title: "Status",
        key: "status",
        render(row) {
            const status = row.status.charAt(0).toUpperCase() + row.status.slice(1);
            return h("span", { class: `order-status ${row.status}` }, status);
        },
    },

    {
        title: "Items Count",
        key: "items",
        render: (row) => row.items?.length || 0,
    },
    {
        title: "Warehouse",
        key: "warehouse",
        render(row) {
            const warehouse = getMeta(row, "warehouse_to_export");
            const status = getMeta(row, "warehouse_export_status");
            const shipstation = getMeta(row, "_shipstation_exported");

            // ShipStation branch
            if (!warehouse || warehouse === "") {
                const classes = [
                    "warehouse-export-tag",
                    "warehouse-export-shipstation",
                    shipstation === "yes" ? "warehouse-export-exported" : "warehouse-export-pending",
                ];
                return h("span", { class: classes.join(" ") }, "Shipstation");
            }

            // Internal warehouses
            const classes = [
                "warehouse-export-tag",
                `warehouse-${warehouse.toLowerCase()}`,
                status === "delivered"
                    ? "warehouse-export-delivered"
                    : status === "exported" || status === "shipped"
                    ? "warehouse-export-exported"
                    : status === "failed"
                    ? "warehouse-export-failed"
                    : "warehouse-export-pending",
            ];
            return h("span", { class: classes.join(" ") }, warehouse);
        },
    },
    {
        title: "Export Status",
        key: "export_status",
        render(row) {
            const warehouse = getMeta(row, "warehouse_to_export");
            const status = getMeta(row, "warehouse_export_status");
            const shipstation = getMeta(row, "_shipstation_exported");

            // ShipStation orders
            if (!warehouse || warehouse === "") {
                const tagClass =
                    shipstation === "yes" ? "warehouse-export-status-exported" : "warehouse-export-status-pending";
                return h(
                    "span",
                    { class: ["warehouse-export-status-tag", tagClass].join(" ") },
                    shipstation === "yes" ? "Exported" : "Pending"
                );
            }

            // Internal system
            const mapping = {
                pending: "warehouse-export-status-pending",
                failed: "warehouse-export-status-failed",
                exported: "warehouse-export-status-exported",
                shipped: "warehouse-export-status-shipped",
                delivered: "warehouse-export-status-delivered",
            };
            const tagClass = mapping[status] || "warehouse-export-status-pending";
            const label =
                status === "delivered" ? "Delivered" : status ? status.charAt(0).toUpperCase() + status.slice(1) : "—";

            return h("span", { class: ["warehouse-export-status-tag", tagClass].join(" ") }, label);
        },
    },
]);
onMounted(() => fetchReplacements(page.value));

function handleSearch() {
    if (filters.search_type === null) {
        filters.search_value = null;
    }

    let filtersMutated = false;
    if (filters.search_type === "order_id" && filters.search_value) {
        Object.keys(filters).forEach((k) => {
            if (!["search_type", "search_value"].includes(k)) {
                if (filters[k] !== null) {
                    filters[k] = null;
                    filtersMutated = true;
                }
            }
        });
    }

    const switchingToPage1 = page.value !== 1;
    router.replace({
        path: "/replacements",
        query: {
            ...Object.fromEntries(
                Object.entries(filters).filter(
                    ([k, v]) => !["search_type", "search_value"].includes(k) && v !== null && v !== ""
                )
            ),
            page: 1,
            ...(filters.search_type && filters.search_value
                ? {
                      search_type: filters.search_type,
                      search_value: filters.search_value,
                  }
                : {}),
        },
    });
    if (switchingToPage1) {
        page.value = 1; // watcher will fetch once
    } else if (!filtersMutated) {
        fetchReplacements(1); // explicit fetch (watcher won't fire)
    }
}

function displayUser(u) {
    if (!u) return "—";
    const cap = (s) => (s ? s.charAt(0).toUpperCase() + s.slice(1).toLowerCase() : "");
    const first = cap(u.first_name || "");
    const last = cap(u.last_name || "");
    const full = `${first} ${last}`.trim();
    return full || u.email || `User #${u.id}` || "—";
}
</script>
