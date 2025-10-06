<!-- SubscriptionPanel.vue -->
<template>
    <div class="panel subscription-panel" v-if="branch?.length">
        <h3>Subscription Details</h3>
        <n-data-table :columns="columns" :data="branch" :pagination="false" size="small" striped />
    </div>
</template>

<script setup>
import { h } from "vue";
import { NTag } from "naive-ui";

const props = defineProps({
    branch: Array,
});

const columns = [
    {
        title: "Order Number",
        key: "id",
        render(row) {
            // If this row is a subscription, link to subscriptions; otherwise, link to orders
            const isSubscription = row.relationship.includes("Subscription");

            const children = [
                h("a", { href: isSubscription ? `#/subscriptions/${row.id}` : `#/orders/${row.id}` }, `#${row.id}`),
            ];

            if (row.relationship.includes("This Order")) {
                children.push(
                    h(
                        NTag,
                        {
                            size: "small",
                            type: "info",
                            style: "margin-left: 8px",
                        },
                        { default: () => "This Order" }
                    )
                );
            }

            if (row.relationship.includes("This Subscription")) {
                children.push(
                    h(
                        NTag,
                        {
                            size: "small",
                            type: "info",
                            style: "margin-left: 8px",
                        },
                        { default: () => "This Subscription" }
                    )
                );
            }

            return h("div", { style: "display: flex; align-items: center; gap: 6px" }, children);
        },
    },
    {
        title: "Relationship",
        key: "relationship",
        render(row) {
            return row.relationship.replace(" (This Order)", "").replace(" (This Subscription)", "");
        },
    },
    {
        title: "Date",
        key: "date",
        render(row) {
            return new Date(row.date).toLocaleString();
        },
    },
    {
        title: "Status",
        key: "status",
        render(row) {
            return h(
                "span",
                { class: `order-status ${row.status}` },
                row.status.charAt(0).toUpperCase() + row.status.slice(1)
            );
        },
    },
    {
        title: "Total",
        key: "total",
        render(row) {
            return h("div", { innerHTML: row.total });
        },
    },
];
</script>
