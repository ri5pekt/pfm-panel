<!-- OrdersList.vue -->
<template>
    <div class="view-wrapper orders-list">
        <div class="page-title">All Orders</div>

        <OrderFiltersPanel v-model="filters" @search="handleSearch" />

        <n-space align="center" justify="flex-start" wrap style="margin-bottom: 8px">
            <BulkEditPanel
                v-if="$can('edit_orders_info')"
                :selected-orders="selectedOrders"
                :on-complete="onBulkComplete"
                @toggle-checkboxes="toggleCheckboxes"
            />
            <JumpToOrderPanel />
        </n-space>

        <OrderStatusCountsPanel ref="statusPanel" @select-status="onSelectStatus" />

        <n-space vertical size="large" style="width: 100%; margin-top: 18px">
            <n-spin :show="loading">
                <div class="orders-table-scroll">
                    <n-data-table
                        :row-key="(row) => row.id"
                        :row-props="rowProps"
                        :columns="columns"
                        :data="orders"
                        :pagination="false"
                        :bordered="true"
                    />
                </div>
            </n-spin>

            <n-pagination
                v-model:page="page"
                v-model:page-size="perPage"
                :page-count="totalPages"
                :page-sizes="ALLOWED_PAGE_SIZES"
                :show-size-picker="true"
                style="margin-top: 1rem"
                :size="isMobile ? 'small' : 'medium'"
                :page-slot="isMobile ? 5 : 7"
            >
            </n-pagination>
        </n-space>
    </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, watch, h, reactive, computed } from "vue";
import { NTag, useMessage, NCheckbox, c } from "naive-ui";
import { useRouter, useRoute } from "vue-router";
import { request } from "@/utils/api";

import { formatCurrency } from "@/utils/utils";
import { formatOrderDate } from "@/utils/utils";
import { getSpecialTags } from "@/utils/orderTags";
import OrderFiltersPanel from "@/components/ui-elements/OrderFiltersPanel.vue";
import BulkEditPanel from "@/components/ui-elements/BulkEditPanel.vue";
import OrderPreviewPopover from "@/components/ui-elements/OrderPreviewPopover.vue";
import JumpToOrderPanel from "@/components/ui-elements/JumpToOrderPanel.vue";
import OrderStatusCountsPanel from "@/components/ui-elements/OrderStatusCountsPanel.vue";

import { useIsMobile } from "@/composables/useIsMobile";

const { isMobile } = useIsMobile();

const orders = ref([]);

const totalPages = ref(1);

const PER_PAGE_LS_KEY = "pfm.orders.perPage";
const ALLOWED_PAGE_SIZES = [10, 20, 50, 100];
function coercePerPage(v) {
    const n = Number(v);
    return ALLOWED_PAGE_SIZES.includes(n) ? n : 10;
}
const perPage = ref(coercePerPage(localStorage.getItem(PER_PAGE_LS_KEY)));

watch(perPage, (val) => {
    // persist & refetch from page 1
    localStorage.setItem(PER_PAGE_LS_KEY, String(val));
    if (!ALLOWED_PAGE_SIZES.includes(val)) return;
    if (page.value !== 1) {
        router.replace({ path: "/orders", query: buildQuery(1) });
        page.value = 1;
    } else {
        fetchOrders(1);
    }
});

const loading = ref(false);

const router = useRouter();
const route = useRoute();
const message = useMessage();

const page = ref(1);

const latestOrderId = ref(null);

const refreshKey = ref(0);

if (route.query.page) {
    page.value = parseInt(route.query.page);
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
        router.replace({ path: "/orders", query: buildQuery(page.value) });
        fetchOrders(page.value);
    },
    { deep: true }
);

function buildQuery(targetPage = page.value) {
    const base = Object.fromEntries(
        Object.entries(filters).filter(
            ([k, v]) => !["search_type", "search_value"].includes(k) && v !== null && v !== ""
        )
    );
    return {
        ...base,
        page: targetPage,
        ...(filters.search_type && filters.search_value
            ? { search_type: filters.search_type, search_value: filters.search_value }
            : {}),
    };
}

function getMeta(row, key) {
    const entry = (row.meta_data || []).find((m) => m.key === key);
    return entry ? entry.value : null;
}

const showCheckboxes = ref(false);
const selectedAction = ref(null);
function toggleCheckboxes(val) {
    showCheckboxes.value = val;
}

function onBulkComplete() {
    fetchOrders(page.value);
    selectedOrders.value = [];
}

const columns = computed(() => {
    const base = [
        /* ── Order column (pretty tag) ───────────────────────────── */
        {
            title: "Order",
            key: "id",
            render(row) {
                const first = row.billing?.first_name || "";
                const last = row.billing?.last_name || "";
                const name = (first + " " + last).trim() || "Guest";

                return h(
                    OrderPreviewPopover,
                    { orderId: row.id, refreshKey: refreshKey.value },
                    {
                        default: () =>
                            h("div", { style: { display: "flex", gap: "6px", alignItems: "center" } }, [
                                h(NTag, { size: "small" }, { default: () => `#${row.id}` }),
                                h("span", null, name),
                            ]),
                    }
                );
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
                            alignItems: "center",
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
                const dt = row?.date_created?.date || row?.date_created;
                return formatOrderDate(dt);
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
                return formatCurrency(row.total, row.currency);
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
                    status === "exported" || status === "shipped"
                        ? "warehouse-export-exported"
                        : status === "failed"
                        ? "warehouse-export-failed"
                        : "warehouse-export-pending",
                ];
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
                    const tagClass =
                        shipstation === "yes" ? "warehouse-export-status-exported" : "warehouse-export-status-pending";
                    return h(
                        "span",
                        { class: ["warehouse-export-status-tag", tagClass].join(" ") },
                        shipstation === "yes" ? "Exported" : "Pending"
                    );
                }

                const mapping = {
                    pending: "warehouse-export-status-pending",
                    failed: "warehouse-export-status-failed",
                    exported: "warehouse-export-status-exported",
                    shipped: "warehouse-export-status-shipped",
                    shipment_exception: "warehouse-export-status-exception",
                };

                const labelMapping = {
                    shipment_exception: "Exception",
                };

                const tagClass = mapping[status] || "warehouse-export-status-pending";
                const label = labelMapping[status] || (status ? status.charAt(0).toUpperCase() + status.slice(1) : "—");

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
    ]; // All normal order/status/etc. columns

    /* ── Bulk actions checkboxes ───────────────────────────── */
    if (showCheckboxes.value) {
        base.unshift({
            title: () =>
                h(NCheckbox, {
                    checked: allSelected.value,
                    "onUpdate:checked": (val) => {
                        allSelected.value = val;
                    },
                }),
            key: "select",
            width: 40,
            render(row) {
                return h(NCheckbox, {
                    checked: selectedOrders.value.includes(row.id),
                    "onUpdate:checked": (val) => {
                        if (val) {
                            if (!selectedOrders.value.includes(row.id)) {
                                selectedOrders.value.push(row.id);
                            }
                        } else {
                            const idx = selectedOrders.value.indexOf(row.id);
                            if (idx !== -1) {
                                selectedOrders.value.splice(idx, 1);
                            }
                        }
                    },
                    onClick: (e) => e.stopPropagation(),
                });
            },
        });
    }

    return base;
});

function rowProps(row) {
    return {
        style: { cursor: "pointer" },
        onClick: (e) => {
            if (showCheckboxes.value) {
                const id = row.id;
                const idx = selectedOrders.value.indexOf(id);
                if (idx === -1) selectedOrders.value.push(id);
                else selectedOrders.value.splice(idx, 1);
            } else {
                openOrder(row, e);
            }
        },
        onMousedown: (e) => {
            // Middle button down
            if (!showCheckboxes.value && e.button === 1) {
                e.preventDefault(); // stop browser trying to drag-scroll
                openOrder(row, e);
            }
        },
        onContextmenu: (e) => {
            e.preventDefault(); // prevent browser menu
            if (!showCheckboxes.value) {
                openOrder(row, { ...e, metaKey: true });
            }
        },
    };
}

function openOrder(row, e) {
    // Use a named route or a plain path LOCATION (not href)
    const loc = { name: "order-view", params: { id: row.id } };
    const href = router.resolve(loc).href; // for window.open / <a> (e.g. "#/orders/123")

    if (e?.metaKey || e?.ctrlKey || e?.button === 1) {
        window.open(href, "_blank", "noopener");
    } else {
        router.push(loc); // ✅ push the location, not the href string
    }
}

async function fetchOrders(currentPage = 1) {
    console.log("🔄 Fetching orders for page:", currentPage);
    loading.value = true;

    const params = new URLSearchParams();
    params.append("per_page", perPage.value);
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
            url: `/orders?${params}`,
            raw: true, // 👈 this is key to access headers
        });

        const data = await res.json();
        orders.value = data;
        totalPages.value = parseInt(res.headers.get("X-WP-TotalPages") || "1");
        refreshKey.value++;
        console.log(data);

        if (data.length > 0 && page.value === 1 && isFilterEmpty()) {
            latestOrderId.value = data[0].id;
        }
    } catch (err) {
        console.error(err);
        message.error("Failed to load orders");
    } finally {
        loading.value = false;
    }
}

function isFilterEmpty() {
    return (
        !filters.status &&
        !filters.warehouse &&
        !filters.export_status &&
        !filters.addr_status &&
        !filters.tag &&
        !filters.date_from &&
        !filters.date_to &&
        !filters.search_type &&
        !filters.search_value
    );
}

const selectedOrders = ref([]);
const allSelected = computed({
    get: () => selectedOrders.value.length === orders.value.length,
    set: (val) => {
        selectedOrders.value = val ? orders.value.map((o) => o.id) : [];
    },
});

function handleSearch() {
    // If we are not searching (user selected "Search by"), clear all filters except search fields
    if (filters.search_type === null) {
        filters.search_value = null;
    }
    let filtersMutated = false;
    if (filters.search_type === "order_id" && filters.search_value) {
        // Clear all other filters except search fields
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
    router.replace({ path: "/orders", query: buildQuery(1) });
    if (switchingToPage1) {
        // Let the watcher (on page) do the single fetch
        page.value = 1;
    } else if (!filtersMutated) {
        // Only search fields changed → watcher won’t fire
        fetchOrders(1);
    }
}

const pollId = ref(null);

function startPolling() {
    if (pollId.value) return;
    pollId.value = setInterval(checkLatest, 30000);
}
function stopPolling() {
    if (!pollId.value) return;
    clearInterval(pollId.value);
    pollId.value = null;
}

async function checkLatest() {
    if (page.value !== 1 || loading.value || !isFilterEmpty()) return;
    try {
        const res = await request({ url: "/orders/latest-id" });
        const { latest_id } = await res;
        if (latest_id !== latestOrderId.value) fetchOrders(1);
        else refreshOrderTimestamps();
    } catch (err) {
        console.warn("⚠️ Error checking latest order ID", err);
    }
}

onMounted(() => {
    fetchOrders(page.value);
    startPolling();
});

onBeforeUnmount(() => stopPolling());

// stop polling whenever filters/page make it irrelevant
watch([page, () => JSON.stringify(filters)], () => {
    if (page.value !== 1 || !isFilterEmpty()) stopPolling();
    else startPolling();
});

function refreshOrderTimestamps() {
    orders.value = orders.value.map((order) => {
        // Trick Vue into re-rendering rows by shallow-cloning
        return { ...order };
    });
}

function onSelectStatus(status) {
    // "All" clears the filter
    filters.status = status === "all" ? null : status;
    // go to page 1 so URL looks like ...?status=processing&page=1
    if (page.value !== 1) page.value = 1;
    else fetchOrders(1); // optional: immediate fetch if you're already on page 1
}
</script>
