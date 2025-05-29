<template>
    <div class="orders-list">
        <div class="page-title">All Orders</div>
        <n-space class="filters" align="center" wrap>
            <div class="filter-field">
                <n-text depth="3">Order Status</n-text>
                <n-select v-model:value="selectedStatus" :options="statusOptions" placeholder="All" clearable />
            </div>

            <div class="filter-field">
                <n-text depth="3">Tags</n-text>
                <n-select v-model:value="selectedTag" :options="tagFilterOptions" placeholder="All" clearable />
            </div>

            <div class="filter-field">
                <n-text depth="3">Warehouse</n-text>
                <n-select v-model:value="selectedWarehouse" :options="warehouseOptions" placeholder="All" clearable />
            </div>

            <div class="filter-field">
                <n-text depth="3">Export Status</n-text>
                <n-select v-model:value="selectedExportStatus" :options="exportStatusOptions" placeholder="All" clearable />
            </div>

            <div class="filter-field">
                <n-text depth="3">Addr. Validation</n-text>
                <n-select v-model:value="selectedAddrStatus" :options="addrStatusOptions" placeholder="All" clearable />
            </div>

            <div class="filter-field">
                <n-text depth="3">Date</n-text>
                <n-dropdown trigger="click" :options="dateRangeOptions" @select="handleDateFilter">
                    <n-button style="justify-content: flex-start; width: 150px">
                        {{ selectedDateLabel }}
                    </n-button>
                </n-dropdown>
            </div>
        </n-space>

        <n-space vertical size="large">
            <n-spin :show="loading">
                <n-data-table :row-props="rowProps" :columns="columns" :data="orders" :pagination="false" :bordered="true" @row-click="handleRowClick" />
            </n-spin>

            <n-pagination v-model:page="page" :page-count="totalPages" :page-size="perPage" style="margin-top: 1rem" />
        </n-space>
    </div>
</template>

<script setup>
import { ref, watchEffect, h } from "vue";
import { NButton, NTag, useMessage } from "naive-ui";
import { useRouter, useRoute } from "vue-router";
import { apiBaseCustom, authHeader } from "@/utils/api";
import Datepicker from "@vuepic/vue-datepicker";
import "@vuepic/vue-datepicker/dist/main.css";
import { computed } from "vue";
import { formatOrderDate } from "@/utils/utils";
import { getSpecialTags } from "@/utils/orderTags";

const orders = ref([]);

const totalPages = ref(1);
const perPage = 10;
const loading = ref(false);

const router = useRouter();
const route = useRoute();
const message = useMessage();

const page = ref(1);

if (route.query.page) {
    page.value = parseInt(route.query.page);
}

const selectedStatus = ref(route.query.status ?? null);
const selectedWarehouse = ref(route.query.warehouse ?? null);
const selectedExportStatus = ref(route.query.export_status ?? null);
const selectedAddrStatus = ref(route.query.addr_status ?? null);
const selectedTag = ref(route.query.tag ?? null);

watchEffect(() => {
    router.replace({
        path: "/orders",
        query: {
            page: page.value,
            status: selectedStatus.value || undefined,
            warehouse: selectedWarehouse.value || undefined,
            export_status: selectedExportStatus.value || undefined,
            addr_status: selectedAddrStatus.value || undefined,
            date_from: route.query.date_from || undefined,
            date_to: route.query.date_to || undefined,
            tag: selectedTag.value || undefined,
        },
    });

    fetchOrders(page.value);
});

function getMeta(row, key) {
    const entry = (row.meta_data || []).find((m) => m.key === key);
    return entry ? entry.value : null;
}

const columns = [
    /* ── Order column (pretty tag) ───────────────────────────── */
    {
        title: "Order",
        key: "id",
        render(row) {
            const first = row.billing?.first_name || "";
            const last = row.billing?.last_name || "";
            const name = (first + " " + last).trim() || "Guest";

            return h("div", { style: { display: "flex", gap: "6px", alignItems: "center" } }, [h(NTag, { size: "small" }, { default: () => `#${row.id}` }), h("span", null, name)]);
        },
    },
    {
        title: "Tags",
        key: "special_tags",
        render(row) {
            return h(
                "div",
                {
                    style: {
                        display: "flex",
                        flexWrap: "wrap",
                        gap: "6px",
                    },
                },
                getSpecialTags(row)
            );
        },
    },

    {
        title: "Date",
        key: "date_created",
        render(row) {
            return formatOrderDate(row?.date_created?.date);
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
        title: "Total",
        key: "total",
        render(row) {
            return `$${row.total}`;
        },
    },
    // WAREHOUSE  🔵
    {
        title: "Warehouse",
        key: "warehouse",
        render(row) {
            const warehouse = getMeta(row, "warehouse_to_export");
            const status = getMeta(row, "warehouse_export_status");
            const shipstation = getMeta(row, "_shipstation_exported");

            // ShipStation branch
            if (!warehouse || warehouse === "") {
                const classes = ["warehouse-export-tag", "warehouse-export-shipstation", shipstation === "yes" ? "warehouse-export-exported" : "warehouse-export-pending"];
                return h("span", { class: classes.join(" ") }, "Shipstation");
            }

            // Internal warehouses
            const classes = ["warehouse-export-tag", `warehouse-${warehouse.toLowerCase()}`, status === "exported" || status === "shipped" ? "warehouse-export-exported" : status === "failed" ? "warehouse-export-failed" : "warehouse-export-pending"];
            return h("span", { class: classes.join(" ") }, warehouse);
        },
    },

    // EXPORT STATUS  🟢
    {
        title: "Export Status",
        key: "export_status",
        render(row) {
            const warehouse = getMeta(row, "warehouse_to_export");
            const status = getMeta(row, "warehouse_export_status");
            const shipstation = getMeta(row, "_shipstation_exported");

            // ShipStation orders
            if (!warehouse || warehouse === "") {
                const tagClass = shipstation === "yes" ? "warehouse-export-status-exported" : "warehouse-export-status-pending";
                return h("span", { class: ["warehouse-export-status-tag", tagClass].join(" ") }, shipstation === "yes" ? "Exported" : "Pending");
            }

            // Internal system
            const mapping = {
                pending: "warehouse-export-status-pending",
                failed: "warehouse-export-status-failed",
                exported: "warehouse-export-status-exported",
                shipped: "warehouse-export-status-shipped",
            };
            const tagClass = mapping[status] || "warehouse-export-status-pending";
            const label = status ? status.charAt(0).toUpperCase() + status.slice(1) : "—";

            return h("span", { class: ["warehouse-export-status-tag", tagClass].join(" ") }, label);
        },
    },

    // ADDRESS VALIDATION  📝
    {
        title: "Address Validation",
        key: "address_validation",
        render(row) {
            const statusRaw = getMeta(row, "validate_address_status") || "not-validated";

            // nice label ⇢ first letter caps + dashes → spaces
            const label =
                {
                    "pending-validation": "Pending",
                    invalid: "Invalid",
                    valid: "Valid",
                    unavailable: "Error",
                    "not-validated": "Not Validated",
                }[statusRaw] || statusRaw;

            const tagClass = `address-validation-status-${statusRaw.replace(/_/g, "-")}`;

            return h("span", { class: ["address-validation-tag", tagClass].join(" ") }, label);
        },
    },
];

function rowProps(row) {
    return {
        style: { cursor: "pointer" },
        onClick: () => handleRowClick(row),
    };
}

function handleRowClick(row) {
    router.push(`/orders/${row.id}`);
}

const statusOptions = [
    { label: "All", value: null }, // 👈 keep null here
    { label: "Pending", value: "pending" },
    { label: "Processing", value: "processing" },
    { label: "On Hold", value: "on-hold" },
    { label: "Completed", value: "completed" },
    { label: "Cancelled", value: "cancelled" },
    { label: "Refunded", value: "refunded" },
    { label: "Failed", value: "failed" },
];

/* dropdown data */
const warehouseOptions = [
    { label: "All", value: null },
    { label: "Shipstation", value: "shipstation" },
    { label: "ShipBob", value: "shipbob" },
    { label: "Fulfillrite", value: "fulfillrite" },
    { label: "KLB Global", value: "klbglobal" },
    { label: "Green", value: "green" },
];

const exportStatusOptions = [
    { label: "All", value: null },
    { label: "Pending", value: "pending" },
    { label: "Failed", value: "failed" },
    { label: "Exported", value: "exported" },
    { label: "Shipped", value: "shipped" },
];

const addrStatusOptions = [
    { label: "All", value: null },
    { label: "Pending", value: "pending-validation" },
    { label: "Invalid", value: "invalid" },
    { label: "Valid", value: "valid" },
    { label: "Error", value: "unavailable" },
    { label: "Not Validated", value: "not-validated" },
];

const tagFilterOptions = [
    { label: "All", value: null },
    { label: "PPU on-hold", value: "ppu-on-hold" },
    { label: "PPU Added", value: "ppu-added" },
    { label: "Facebook", value: "facebook" },
    { label: "Walmart", value: "walmart" },
    { label: "Subscription Renewal", value: "subscription-renewal" },
    { label: "Subscription Parent", value: "subscription-parent" },
    { label: "BAS Added", value: "bas-added" },
];

const selectedDateLabel = computed(() => {
    if (route.query.date_from && route.query.date_to) {
        const from = new Date(route.query.date_from);
        const to = new Date(route.query.date_to);
        const opts = { month: "short", day: "numeric" };

        const sameDay = route.query.date_from === route.query.date_to;
        return sameDay ? from.toLocaleDateString(undefined, opts) : `${from.toLocaleDateString(undefined, opts)} - ${to.toLocaleDateString(undefined, opts)}`;
    }

    return "Select a date";
});

async function fetchOrders(currentPage = 1) {
    loading.value = true;

    const params = new URLSearchParams();
    params.append("per_page", perPage);
    params.append("page", currentPage);

    if (selectedStatus.value) params.append("status", selectedStatus.value);
    if (selectedWarehouse.value) params.append("warehouse", selectedWarehouse.value);
    if (selectedExportStatus.value) params.append("export_status", selectedExportStatus.value);
    if (selectedAddrStatus.value) params.append("addr_status", selectedAddrStatus.value);
    if (route.query.date_from) params.append("date_from", route.query.date_from);
    if (route.query.date_to) params.append("date_to", route.query.date_to);
    if (selectedTag.value) params.append("tag", selectedTag.value);

    try {
        const res = await fetch(`${apiBaseCustom}/orders?${params}`, {
            headers: { Authorization: authHeader },
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        orders.value = await res.json();
        totalPages.value = parseInt(res.headers.get("X-WP-TotalPages") || 1);
    } catch (err) {
        console.error(err);
        message.error("Failed to load orders");
    } finally {
        loading.value = false;
    }
}

const showCustomRange = ref(false);
const customRange = ref([new Date(), new Date()]);

const dateRangeOptions = [
    { label: "All Time", key: "all_time" },
    { label: "Today", key: "today" },
    { label: "Yesterday", key: "yesterday" },
    { label: "Last 7 Days", key: "last_7" },
    { label: "Last 30 Days", key: "last_30" },
    { label: "This Month", key: "this_month" },
    {
        key: "custom",
        type: "render",
        render() {
            return h("div", { style: "padding: 8px 12px; width: 300px;" }, [
                h(Datepicker, {
                    modelValue: customRange.value,
                    "onUpdate:modelValue": applyCustomRange,
                    range: true,
                    format: "dd/MM/yy",
                    previewFormat: "dd/MM/yy",
                    enableTimePicker: false,
                    autoApply: true,
                    startDate: new Date(),
                    showNowButton: false,
                }),
            ]);
        },
    },
];

function handleDateFilter(key) {
    showCustomRange.value = false;

    const now = new Date();
    let from, to;

    switch (key) {
        case "today":
            from = to = now;
            break;
        case "yesterday":
            from = to = new Date(now.setDate(now.getDate() - 1));
            break;
        case "last_7":
            from = new Date(now.setDate(now.getDate() - 6));
            to = new Date();
            break;
        case "last_30":
            from = new Date(now.setDate(now.getDate() - 29));
            to = new Date();
            break;
        case "this_month":
            from = new Date(now.getFullYear(), now.getMonth(), 1);
            to = new Date();
            break;
        case "all_time":
            customRange.value = [new Date(), new Date()];
            page.value = 1;
            router.replace({
                path: "/orders",
                query: {
                    ...route.query,
                    page: 1,
                    date_from: undefined,
                    date_to: undefined,
                },
            });
            return;
        case "custom":
            return; // handled in render
    }

    customRange.value = [from, to];
    applyDateFilter(from, to);
}

function applyCustomRange(value) {
    if (!Array.isArray(value) || value.length !== 2) return;
    customRange.value = value;
    const [from, to] = value;
    if (from && to) {
        applyDateFilter(from, to);
    }
}

function applyDateFilter(from, to) {
    const format = (d) => {
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, "0");
        const day = String(d.getDate()).padStart(2, "0");
        return `${year}-${month}-${day}`;
    };
    router.replace({
        path: "/orders",
        query: {
            ...route.query,
            date_from: format(from),
            date_to: format(to),
        },
    });
}

function getMetaValue(row, key) {
    const entry = (row.meta_data || []).find((m) => m.key === key);
    return entry ? entry.value : null;
}
</script>
