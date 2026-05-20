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
import { NTag, useMessage, NCheckbox, c, NTooltip } from "naive-ui";
import { useRouter, useRoute } from "vue-router";
import { request } from "@/utils/api";
import { useOrderWorkTabs } from "@/composables/useOrderWorkTabs";

import { formatCurrency } from "@/utils/utils";
import { formatOrderDate } from "@/utils/utils";
import { getSpecialTags } from "@/utils/orderTags";
import { getPaymentMethodLogo } from "@/utils/paymentMethod";
import OrderFiltersPanel from "@/components/ui-elements/OrderFiltersPanel.vue";
import BulkEditPanel from "@/components/ui-elements/BulkEditPanel.vue";
import OrderPreviewPopover from "@/components/ui-elements/OrderPreviewPopover.vue";
import JumpToOrderPanel from "@/components/ui-elements/JumpToOrderPanel.vue";
import OrderStatusCountsPanel from "@/components/ui-elements/OrderStatusCountsPanel.vue";

import mobileDeviceIcon from "@/assets/images/icons/device/mobile.svg";
import desktopDeviceIcon from "@/assets/images/icons/device/desktop.svg";

import { useIsMobile } from "@/composables/useIsMobile";

const { isMobile } = useIsMobile();

const orders = ref([]);

const totalPages = ref(1);

const flagImagesRaw = import.meta.glob("@/assets/images/icons/flags/*.png", {
    eager: true,
    import: "default",
});

const flagImages = Object.fromEntries(
    Object.entries(flagImagesRaw)
        .map(([path, src]) => {
            const match = path.match(/\/([A-Z]{2})\.png$/i);
            return match ? [match[1].toUpperCase(), src] : null;
        })
        .filter(Boolean)
);
const DEVICE_ICONS = {
    mobile: mobileDeviceIcon,
    desktop: desktopDeviceIcon,
};
function getDeviceIcon(row) {
    const typeRaw = getMeta(row, "_wc_order_attribution_device_type");
    if (!typeRaw) return null;
    const key = typeRaw.toLowerCase();
    return DEVICE_ICONS[key] || null;
}

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
const { openOrder: openOrderTab } = useOrderWorkTabs();
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
        /* ── Customer Type column ───────────────────────────── */
        {
            title: "New/Returning",
            key: "customer_type",
            render(row) {
                const customerType = getMeta(row, "new_or_returning");
                // Default to "new" if meta is not found
                const isNew = !customerType || customerType === "new";
                const type = isNew ? "New" : "Returning";

                return h(
                    NTag,
                    {
                        size: "small",
                        type: isNew ? "success" : "info",
                        round: true,
                        style: {
                            backgroundColor: isNew ? "#f0fdf4" : "#eff6ff",
                            color: isNew ? "#18a058" : "#2080f0",
                            border: isNew ? "1px solid #18a058" : "1px solid #2080f0",
                        },
                    },
                    { default: () => type }
                );
            },
        },
        {
            title: "Location",
            key: "location",
            render(row) {
                const shipping = row.shipping || {};
                const countryCode = (shipping.country || "").toUpperCase();
                const isUS = countryCode === "US";
                const primaryLabel = isUS ? (shipping.state || "").toUpperCase() : shipping.city || "";
                const deviceTypeRaw = getMeta(row, "_wc_order_attribution_device_type");
                const deviceIcon = getDeviceIcon(row);

                const nodes = [];

                const flagSrc = flagImages[countryCode];
                if (flagSrc) {
                    nodes.push(
                        h("img", {
                            src: flagSrc,
                            alt: countryCode,
                        })
                    );
                }

                if (primaryLabel) {
                    nodes.push(
                        h(
                            NTag,
                            {
                                size: "small",
                                style: {
                                    backgroundColor: "#fff",
                                    color: "#374151",
                                    border: "none",
                                },
                            },
                            { default: () => primaryLabel }
                        )
                    );
                }

                if (deviceIcon) {
                    nodes.push(
                        h("img", {
                            src: deviceIcon,
                            alt: deviceTypeRaw || "Device",
                            style: {
                                width: "14px",
                                height: "14px",
                            },
                            title: deviceTypeRaw || undefined,
                        })
                    );
                }

                if (nodes.length === 0) {
                    nodes.push(h("span", { style: { color: "#9ca3af", fontSize: "0.85rem" } }, "—"));
                }

                return h(
                    "div",
                    {
                        style: {
                            display: "flex",
                            alignItems: "center",
                            gap: "6px",
                        },
                    },
                    nodes
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
            title: "Signifyd",
            key: "signifyd_score",
            width: 90,
            render(row) {
                const sigData = getMeta(row, "_pfm_signifyd");
                const raw = sigData?.score;
                if (raw == null || raw === "") {
                    return h("span", { style: { color: "#d1d5db", fontSize: "12px" } }, "—");
                }
                const score = Math.max(0, Math.min(1000, Math.round(Number(raw))));
                const hue = (score / 1000) * 120;
                const bg     = `hsl(${hue}, 75%, 93%)`;
                const color  = `hsl(${hue}, 70%, 32%)`;
                const border = `1px solid hsl(${hue}, 65%, 78%)`;
                const decision = (sigData?.decision || "").toUpperCase();
                const isBlocked = decision === "REJECT";

                const blockIcon = h("svg", {
                    xmlns: "http://www.w3.org/2000/svg",
                    viewBox: "0 0 24 24",
                    width: "12",
                    height: "12",
                    fill: "#dc2626",
                    style: { flexShrink: 0 },
                    title: `Signifyd: ${decision}`,
                }, [
                    h("path", {
                        d: "M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 5h2v6h-2V7zm0 8h2v2h-2v-2z",
                    }),
                ]);

                // Use a "no entry" circle-slash icon for blocked
                const blockedIcon = h("svg", {
                    xmlns: "http://www.w3.org/2000/svg",
                    viewBox: "0 0 24 24",
                    width: "13",
                    height: "13",
                    fill: "#dc2626",
                    style: { flexShrink: 0 },
                }, [
                    h("path", {
                        d: "M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zM4 12c0-4.42 3.58-8 8-8 1.85 0 3.55.63 4.9 1.68L5.68 16.9C4.63 15.55 4 13.85 4 12zm8 8c-1.85 0-3.55-.63-4.9-1.68l11.22-11.22C19.37 8.45 20 10.15 20 12c0 4.42-3.58 8-8 8z",
                    }),
                ]);

                const badge = h("span", {
                    style: {
                        display: "inline-flex",
                        alignItems: "center",
                        gap: "4px",
                        padding: "1px 7px",
                        borderRadius: "10px",
                        fontSize: "11px",
                        fontWeight: "bold",
                        backgroundColor: bg,
                        color,
                        border,
                        whiteSpace: "nowrap",
                    },
                }, [
                    ...(isBlocked ? [blockedIcon] : []),
                    String(score),
                ]);

                const tooltipText = `Signifyd Score: ${score} / 1000${decision ? ` · ${decision}` : ""}`;

                return h(NTooltip,
                    { trigger: "hover", placement: "top" },
                    {
                        trigger: () => badge,
                        default: () => tooltipText,
                    }
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
                const statusLabel = row.status.charAt(0).toUpperCase() + row.status.slice(1);
                const statusNode = h("span", { class: `order-status ${row.status}` }, statusLabel);

                if (row.status === "failed" && row.failed_reason) {
                    return h(
                        NTooltip,
                        {
                            trigger: "hover",
                            placement: "top",
                            delay: 100,
                        },
                        {
                            trigger: () => statusNode,
                            default: () => row.failed_reason,
                        }
                    );
                }

                return statusNode;
            },
        },
        {
            title: "Total",
            key: "total",
            render(row) {
                return formatCurrency(row.total, row.currency);
            },
        },
        /* ── Payment Method column ───────────────────────────── */
        {
            title: "Payment",
            key: "payment_method",
            width: 80,
            render(row) {
                const { logoUrl, altText } = getPaymentMethodLogo(row);
                const cardDetails = getMeta(row, '_braintree_card_details') || {};
                const last4 = cardDetails.last4 || '';
                const expMonth = cardDetails.expirationMonth || '';
                const expYear = cardDetails.expirationYear || '';

                const imgNode = h("img", {
                    src: logoUrl,
                    alt: altText,
                    style: {
                        height: "20px",
                        width: "auto",
                        maxWidth: "40px",
                        objectFit: "contain",
                    },
                });

                // If we have card details, wrap in tooltip
                if (last4 || expMonth) {
                    const tooltipText = [
                        altText,
                        last4 ? `••••${last4}` : null,
                        (expMonth && expYear) ? `Exp: ${expMonth}/${expYear}` : null
                    ].filter(Boolean).join('\n');

                    return h(
                        NTooltip,
                        {
                            trigger: "hover",
                            placement: "top",
                        },
                        {
                            trigger: () => imgNode,
                            default: () => h("div", { style: { whiteSpace: "pre-line" } }, tooltipText),
                        }
                    );
                }

                return imgNode;
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
                    return h("span", { class: classes.join(" ") }, "Not set");
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
                    delivered: "warehouse-export-status-delivered",
                    shipment_exception: "warehouse-export-status-exception",
                };

                const labelMapping = {
                    shipment_exception: "Exception",
                    delivered: "Delivered",
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
            // Right-click should open a real browser tab (not an internal tab)
            e.preventDefault();
            if (!showCheckboxes.value) {
                const loc = { name: "order-view", params: { id: row.id } };
                const href = router.resolve(loc).href;
                window.open(href, "_blank", "noopener");
            }
        },
    };
}

function openOrder(row, e) {
    // Use a named route or a plain path LOCATION (not href)
    const loc = { name: "order-view", params: { id: row.id } };
    const href = router.resolve(loc).href; // for window.open / <a> (e.g. "#/orders/123")

    // Orders should behave like browser tabs:
    // - Ctrl/Cmd/middle-click: open a *background* internal tab (stay on list)
    // - Plain click: open and navigate to the order (activate tab)
    // Escape hatch: Shift-click opens a real browser tab.
    if (e?.shiftKey) {
        window.open(href, "_blank", "noopener");
        return;
    }

    if (e?.metaKey || e?.ctrlKey || e?.button === 1) {
        openOrderTab(row.id, { activate: false });
        return;
    }

    openOrderTab(row.id, { activate: true });
    router.push(loc); // ✅ push the location, not the href string
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
