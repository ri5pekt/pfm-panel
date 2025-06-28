
<!-- OrdersList.vue -->
<template>
    <div class="view-wrapper orders-list">
        <div class="page-title">All Orders</div>

        <!-- 🔁 Now connected properly with v-model -->
        <OrderFiltersPanel v-model="filters" @search="handleSearch" />

        <BulkEditPanel
            v-if="$can('edit_orders_info')"
            :selected-orders="selectedOrders"
            :on-complete="
                () => {
                    fetchOrders(page);
                    selectedOrders = [];
                }
            "
            @toggle-checkboxes="toggleCheckboxes"
        />

        <n-space vertical size="large">
            <n-spin :show="loading">
                <n-data-table
                    :row-props="rowProps"
                    :columns="columns"
                    :data="orders"
                    :pagination="false"
                    :bordered="true"
                    @row-click="handleRowClick"
                />
            </n-spin>

            <n-pagination v-model:page="page" :page-count="totalPages" :page-size="perPage" style="margin-top: 1rem" />
        </n-space>
    </div>
</template>

<script setup>
import { ref, onMounted, watch, h, reactive, computed } from "vue";
import { NTag, useMessage, NCheckbox } from "naive-ui";
import { useRouter, useRoute } from "vue-router";
import { request } from "@/utils/api";

import { formatOrderDate } from "@/utils/utils";
import { getSpecialTags } from "@/utils/orderTags";
import OrderFiltersPanel from "@/components/ui-elements/OrderFiltersPanel.vue";
import BulkEditPanel from "@/components/ui-elements/BulkEditPanel.vue";

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
            path: "/orders",
            query: {
                ...Object.fromEntries(
                    Object.entries(filters).filter(
                        ([k, v]) => !["search_type", "search_value"].includes(k) && v !== null && v !== ""
                    )
                ),
                page: page.value,
                // Only add search params if a search is in effect:
                ...(filters.search_type && filters.search_value
                    ? {
                          search_type: filters.search_type,
                          search_value: filters.search_value,
                      }
                    : {}),
            },
        });
        fetchOrders(page.value);
    },
    { deep: true }
);

function getMeta(row, key) {
    const entry = (row.meta_data || []).find((m) => m.key === key);
    return entry ? entry.value : null;
}

const showCheckboxes = ref(false);
const selectedAction = ref(null);
function toggleCheckboxes(val) {
    showCheckboxes.value = val;
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

                return h("div", { style: { display: "flex", gap: "6px", alignItems: "center" } }, [
                    h(NTag, { size: "small" }, { default: () => `#${row.id}` }),
                    h("span", null, name),
                ]);
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
        onClick: () => {
            if (showCheckboxes.value) {
                const id = row.id;
                const idx = selectedOrders.value.indexOf(id);
                if (idx === -1) {
                    selectedOrders.value.push(id);
                } else {
                    selectedOrders.value.splice(idx, 1);
                }
            } else {
                handleRowClick(row);
            }
        },
    };
}

function handleRowClick(row) {
    router.push(`/orders/${row.id}`);
}

async function fetchOrders(currentPage = 1) {
    console.log("🔄 Fetching orders for page:", currentPage);
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
            url: `/orders?${params}`,
            raw: true, // 👈 this is key to access headers
        });
        console.log(res);
        const data = await res.json();
        orders.value = data;
        totalPages.value = parseInt(res.headers.get("X-WP-TotalPages") || "1");
    } catch (err) {
        console.error(err);
        message.error("Failed to load orders");
    } finally {
        loading.value = false;
    }
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
    if (filters.search_type === "order_id" && filters.search_value) {
        // Clear all other filters except search fields
        Object.keys(filters).forEach((k) => {
            if (!["search_type", "search_value"].includes(k)) {
                filters[k] = null;
            }
        });
    }
    page.value = 1;

    // Build query for URL: add all non-empty filters except search_type/search_value unless they are set
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
        path: "/orders",
        query: queryObj,
    });

    fetchOrders(page.value);
}

onMounted(() => {
    console.log("🚀 Initial page load, fetching orders...");
    fetchOrders(page.value);
});
</script>
