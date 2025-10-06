<!-- PastOrdersPanel.vue -->
<template>
    <div class="panel past-orders-panel">
        <h3>Customer's Past Orders</h3>
        <div class="past-orders-scroll">
            <n-data-table
                :columns="columns"
                :data="pastOrders"
                :loading="loading"
                size="small"
                striped
            />
            <div class="load-more-wrapper" v-if="hasMore">
                <n-button @click="loadMore" :loading="loading" size="small"> Load More </n-button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, h } from "vue";
import { RouterLink } from "vue-router";
import { NTag, NTooltip } from "naive-ui";
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

/** Build "Face Cream x2, Eye Serum" */
function itemsLabel(row) {
    const items = Array.isArray(row.items) ? row.items : null;
    if (!items || items.length === 0) return "";
    const parts = items
        .map((it) => {
            const name = String(it?.name ?? "").trim();
            if (!name) return null;
            const qty = Number(it?.qty ?? 0);
            return qty > 1 ? `${name} x${qty}` : name;
        })
        .filter(Boolean);
    return parts.join(", ");
}

/** Wrap any vnode with Naive's tooltip for this row */
function withTooltip(vnode, row) {
    const label = itemsLabel(row);
    if (!label) return vnode; // no tooltip if no items
    return h(
        NTooltip,
        { trigger: "hover", placement: "top-start" },
        {
            default: () => label,
            trigger: () => vnode,
        }
    );
}



const columns = [
    {
        title: "Order ID",
        key: "id",
        //width: 150,
        render(row) {
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
            if (row.has_subscription_parent) children.push(tag("Sub Parent"));
            if (row.has_subscription_renewal) children.push(tag("Sub Renewal"));

            return withTooltip(h("span", children), row);
        },
    },
    {
        title: "Date",
        key: "date_created",
        width: 130,
        render(row) {
            const node = h("span", row.date_created ? formatOrderDate(row.date_created) : "");
            return withTooltip(node, row);
        },
    },
    {
        title: "Status",
        key: "status",
        width: 120,
        render(row) {
            let text = "";
            if (row.is_replacement) {
                const s = (row.status || "").replace(/_/g, " ");
                text = s.charAt(0).toUpperCase() + s.slice(1);
            } else if (row.status) {
                const clean = row.status.replace(/^wc-/, "");
                text = clean.charAt(0).toUpperCase() + clean.slice(1);
            }
            return withTooltip(h("span", text), row);
        },
    },
    {
        title: "Total",
        key: "total",
        width: 110,
        render(row) {
            const text = typeof row.total === "number" ? formatCurrency(row.total, row.currency) : "";
            return withTooltip(h("span", text), row);
        },
    },
];

async function fetchOrders() {
    if (!props.customerId) return;
    loading.value = true;
    const perPage = 10;
    try {
        const data = await request({
            url: `/orders/by-user/${props.customerId}?page=${page.value}&per_page=${perPage}`,
        });
        // tiny sanity log to ensure items are present
        // console.debug("past-orders page", page.value, data.map(d => ({ id: d.id, items: d.items?.length })));
        const list = props.excludeOrderId ? data.filter((o) => o.id !== props.excludeOrderId) : data;
        hasMore.value = data.length === perPage;
        pastOrders.value.push(...list);
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
        pastOrders.value = [];
        page.value = 1;
        hasMore.value = true;
        fetchOrders();
    },
    { immediate: true }
);
</script>

<style scoped>
.past-orders-scroll {
    max-height: 420px;
    overflow: auto;
}
.load-more-wrapper {
    display: flex;
    justify-content: center;
    margin-top: 8px;
}
</style>
